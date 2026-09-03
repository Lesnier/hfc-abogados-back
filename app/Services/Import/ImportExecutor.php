<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Models\ImportStagingRow;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta un batch ya revisado: escribe en las tablas de negocio reales,
 * registra los mapeos external_id -> local_id, y deja cada fila de staging
 * marcada como 'imported' o 'error' (si algo falló puntualmente al escribir).
 */
class ImportExecutor
{
    private ImportFkResolver $resolver;

    public function __construct()
    {
        $this->resolver = new ImportFkResolver();
    }

    public function execute(ImportBatch $batch, bool $allowUnresolved = false): ImportBatch
    {
        $batch->update(['status' => 'processing']);

        $schemas = config('import_schemas');
        uasort($schemas, fn ($a, $b) => $a['order'] <=> $b['order']);

        $processable = $allowUnresolved ? ['ok', 'warning', 'needs_resolution'] : ['ok', 'warning'];
        $anyError = false;

        // Transacción externa: agrupa todos los commits para evitar el costo de
        // fsync por fila en MySQL local. Cada fila corre además en su propio
        // SAVEPOINT (transacción anidada) para que si UNA fila falla, solo se
        // revierte esa fila y el resto del batch sigue procesándose.
        DB::transaction(function () use ($schemas, $batch, $processable, &$anyError) {
            foreach ($schemas as $entitySlug => $schema) {
                $rows = ImportStagingRow::where('import_batch_id', $batch->id)
                    ->where('entity_slug', $entitySlug)
                    ->whereIn('status', $processable)
                    ->orderBy('row_number')
                    ->get();

                foreach ($rows as $row) {
                    try {
                        DB::transaction(function () use ($batch, $entitySlug, $schema, $row) {
                            if ($schema['mode'] === 'relation') {
                                $this->executeRelationRow($batch, $entitySlug, $schema, $row);
                            } else {
                                $this->executeEntityRow($batch, $entitySlug, $schema, $row);
                            }
                        });
                    } catch (\Throwable $e) {
                        $anyError = true;
                        $notes = $row->notes ?? [];
                        $notes[] = 'Error al ejecutar: ' . $e->getMessage();
                        $row->update(['status' => 'error', 'notes' => $notes]);
                    }
                }
            }
        });

        $batch->update([
            'status' => $anyError ? 'completed_with_errors' : 'completed',
            'executed_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function executeEntityRow(ImportBatch $batch, string $entitySlug, array $schema, ImportStagingRow $row): void
    {
        $raw = $row->raw_data;
        $model = $schema['model'];
        $uniqueKey = $schema['unique_key'];

        $data = [];
        foreach ($schema['columns'] as $colName => $colDef) {
            if ($colName === 'external_id') continue;
            $val = $raw[$colName] ?? null;
            $data[$colName] = $val === '' ? null : $val;
        }

        foreach (($schema['fk'] ?? []) as $localField => $fk) {
            if (isset($raw["__resolved_{$localField}"])) {
                $data[$localField] = $raw["__resolved_{$localField}"];
                continue;
            }
            $result = $this->resolver->resolve(
                $fk['parent_entity'],
                $raw[$fk['external_col']] ?? null,
                $raw[$fk['identification_col']] ?? null,
                $batch->id
            );
            $data[$localField] = $result['local_id'];
        }

        foreach (($schema['text_match'] ?? []) as $colName => $tm) {
            $val = $raw[$colName] ?? '';
            if ($val === '') continue;
            $match = $tm['model']::where($tm['match_field'], $val)->first();
            $data[$tm['local_field']] = $match?->getKey();
        }

        $instance = $model::updateOrCreate([$uniqueKey => $raw[$uniqueKey]], $data);
        $action = $instance->wasRecentlyCreated ? 'create' : 'update';

        $this->resolver->registerMapping(
            $entitySlug,
            $raw['external_id'] ?? null,
            $raw[$uniqueKey] ?? null,
            $instance->getKey(),
            $action,
            $batch->id
        );

        $row->update([
            'status' => 'imported',
            'action' => $action,
            'resolved_data' => $data,
            'created_local_id' => $instance->getKey(),
        ]);
    }

    private function executeRelationRow(ImportBatch $batch, string $entitySlug, array $schema, ImportStagingRow $row): void
    {
        $raw = $row->raw_data;

        $employeeResult = $this->resolver->resolve(
            $schema['match_entity'],
            $raw[$schema['match_external_col']] ?? null,
            $raw[$schema['match_identification_col']] ?? null,
            $batch->id
        );

        if (!$employeeResult['local_id']) {
            $notes = $row->notes ?? [];
            $notes[] = 'No se pudo resolver el empleado al momento de ejecutar.';
            $row->update(['status' => 'error', 'notes' => $notes]);
            return;
        }

        $data = [];
        foreach (($schema['fk'] ?? []) as $localField => $fk) {
            if (isset($raw["__resolved_{$localField}"])) {
                $data[$localField] = $raw["__resolved_{$localField}"];
                continue;
            }
            $result = $this->resolver->resolve(
                $fk['parent_entity'],
                $raw[$fk['external_col']] ?? null,
                $raw[$fk['identification_col']] ?? null,
                $batch->id
            );
            $data[$localField] = $result['local_id'];
        }

        $model = $schema['model'];
        $instance = $model::find($employeeResult['local_id']);
        if (!$instance) {
            $notes = $row->notes ?? [];
            $notes[] = 'El empleado resuelto ya no existe.';
            $row->update(['status' => 'error', 'notes' => $notes]);
            return;
        }
        $instance->update($data);

        $row->update([
            'status' => 'imported',
            'action' => 'update',
            'resolved_data' => $data,
            'created_local_id' => $instance->getKey(),
        ]);
    }

    /**
     * Deshace un batch YA EJECUTADO: elimina los registros que este batch CREÓ
     * (nunca los que solo actualizó, porque no tenemos el valor anterior guardado).
     * Pensado para poder probar la importación varias veces en un ambiente de pruebas.
     */
    public function rollback(ImportBatch $batch): array
    {
        $schemas = config('import_schemas');
        uasort($schemas, fn ($a, $b) => $b['order'] <=> $a['order']); // orden inverso: hijos primero

        $deleted = [];
        foreach ($schemas as $entitySlug => $schema) {
            if ($schema['mode'] === 'relation') continue; // las relaciones solo actualizan, no se revierten acá

            // Fuente de verdad: la propia fila de staging (created_local_id + action),
            // NO import_mappings — un registro sin external_id (ej. empleados en esta
            // migración) nunca generó mapping, pero igual fue CREADO por este batch.
            $ids = ImportStagingRow::where('import_batch_id', $batch->id)
                ->where('entity_slug', $entitySlug)
                ->where('action', 'create')
                ->whereNotNull('created_local_id')
                ->pluck('created_local_id')
                ->all();

            $model = $schema['model'];
            if (!empty($ids)) {
                $keyName = (new $model())->getKeyName();
                $model::whereIn($keyName, $ids)->delete();
            }
            $deleted[$entitySlug] = count($ids);

            // Borrar TODO mapeo que apunte a un local_id recién eliminado (no solo los
            // marcados 'create'): un duplicado dentro del archivo puede haber generado
            // una segunda entrada 'update' sobre el mismo registro ya creado en esta
            // misma corrida — si no se limpia, queda un mapeo colgado apuntando a un
            // ID que ya no existe.
            if (!empty($ids)) {
                ImportMapping::where('entity_slug', $entitySlug)
                    ->whereIn('local_id', $ids)
                    ->delete();
            }
        }

        $notUpdatedRolledBack = ImportStagingRow::where('import_batch_id', $batch->id)
            ->where('status', 'imported')
            ->where('action', 'update')
            ->count();

        $batch->update(['status' => 'rolled_back', 'rolled_back_at' => now()]);

        return ['deleted' => $deleted, 'updates_not_reverted' => $notUpdatedRolledBack];
    }
}
