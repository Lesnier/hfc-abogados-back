<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Models\ImportStagingRow;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $counts = ImportStagingRow::where('import_batch_id', $batch->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $stillPending = $counts->get('needs_resolution', 0);

        // Si todavía quedan filas sin resolver (ejecución parcial: se cargó solo
        // una parte a propósito), el batch sigue "needs_review" en vez de darse
        // por cerrado — así la pantalla de revisión sigue ofreciendo "Ejecutar"
        // para cuando se resuelva el resto más adelante, en la MISMA importación.
        $status = $stillPending > 0
            ? 'needs_review'
            : ($anyError ? 'completed_with_errors' : 'completed');

        $batch->update([
            'status' => $status,
            'ok_rows' => $counts->get('ok', 0),
            'warning_rows' => $counts->get('warning', 0),
            'error_rows' => $counts->get('error', 0),
            'pending_rows' => $stillPending,
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

        if (isset($schema['user_link'])) {
            $emailProvided = trim($raw[$schema['user_link']['email_col']] ?? '') !== '';
            // Si la fila es una ACTUALIZACIÓN de un registro que ya existía y el
            // archivo no trajo email, no tocamos user_id: podría ya tener un
            // representante/auditor real vinculado y no queremos pisarlo con un
            // placeholder solo porque esta fila no trajo el dato.
            if ($row->action === 'create' || $emailProvided) {
                $data[$schema['user_link']['local_field']] = $this->resolveOrCreateUser($schema['user_link'], $raw, $batch->id);
            }
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

    /**
     * Resuelve el usuario "Representante"/"Auditor" de una compañía o proveedor:
     * si vino un email, matchea (o crea) contra ese email; si no vino ninguno,
     * genera un email placeholder determinístico a partir de la identification
     * para poder crear igual la cuenta y dejarlo vinculado (nunca inventa un
     * email real: el dominio del placeholder deja explícito que es sintético).
     */
    private function resolveOrCreateUser(array $userLink, array $raw, int $batchId): ?int
    {
        $email = trim($raw[$userLink['email_col']] ?? '');

        if ($email === '') {
            $identification = preg_replace('/[^a-zA-Z0-9]/', '', $raw['identification'] ?? '');
            if ($identification === '') {
                return null; // sin identification no hay forma determinística de generar el placeholder
            }
            $email = strtolower($userLink['placeholder_prefix'] . '-' . $identification . '@sin-email.importado.local');
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            return $existing->id;
        }

        $roleId = Role::where('name', $userLink['role'])->value('id');

        $user = User::create([
            'name' => $raw['name'] ?? $email,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role_id' => $roleId,
        ]);

        // Registrado para que "Revertir" pueda limpiar también este usuario si
        // fue creado por este batch (nunca si se vinculó a uno ya existente).
        ImportMapping::create([
            'import_batch_id' => $batchId,
            'entity_slug' => 'users',
            'external_id' => null,
            'identification' => $email,
            'local_id' => $user->id,
            'action' => 'create',
        ]);

        return $user->id;
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

            // Las filas que se acaban de revertir vuelven a un estado ejecutable
            // (limpiando el ID que ya no existe), para poder ejecutarlas de nuevo
            // más adelante sin tener que reanalizar el archivo desde cero.
            ImportStagingRow::where('import_batch_id', $batch->id)
                ->where('entity_slug', $entitySlug)
                ->where('action', 'create')
                ->where('status', 'imported')
                ->get()
                ->each(function (ImportStagingRow $row) {
                    $hasDuplicateNote = collect($row->notes ?? [])->contains(fn ($n) => str_contains($n, 'repetido'));
                    $row->update([
                        'status' => $hasDuplicateNote ? 'warning' : 'ok',
                        // Se acaba de borrar el registro que esta fila había creado, así
                        // que la PRÓXIMA ejecución va a ser un alta nueva otra vez — dejar
                        // 'action' en null acá rompía silenciosamente el vínculo de usuario
                        // (executeEntityRow solo crea/vincula el Representante/Auditor
                        // cuando action==='create'; con null nunca se cumplía esa condición).
                        'action' => 'create',
                        'created_local_id' => null,
                        'resolved_data' => null,
                    ]);
                });
        }

        // Limpiar también los usuarios placeholder/nuevos que este batch haya
        // creado como Representante/Auditor (nunca los que solo vinculó a un
        // usuario ya existente — esos no tienen mapping con action='create').
        // Se hace al final, después de borrar proveedores/compañías, para no
        // dejar un user_id apuntando a un usuario ya eliminado mientras tanto.
        $userIds = ImportMapping::where('import_batch_id', $batch->id)
            ->where('entity_slug', 'users')
            ->where('action', 'create')
            ->pluck('local_id');
        if ($userIds->isNotEmpty()) {
            \App\Models\User::whereIn('id', $userIds)->delete();
            ImportMapping::where('import_batch_id', $batch->id)
                ->where('entity_slug', 'users')
                ->where('action', 'create')
                ->delete();
        }
        $deleted['usuarios'] = $userIds->count();

        $notUpdatedRolledBack = ImportStagingRow::where('import_batch_id', $batch->id)
            ->where('status', 'imported')
            ->where('action', 'update')
            ->count();

        // Estado del batch tras revertir: si queda algo por ejecutar (lo recién
        // revertido, u otras filas que seguían pendientes de resolver), vuelve a
        // quedar "accionable" en vez de cerrado.
        $counts = ImportStagingRow::where('import_batch_id', $batch->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $pending = $counts->get('needs_resolution', 0);
        $executable = $counts->get('ok', 0) + $counts->get('warning', 0);

        $status = $pending > 0 ? 'needs_review' : ($executable > 0 ? 'ready' : 'rolled_back');

        $batch->update([
            'status' => $status,
            'ok_rows' => $counts->get('ok', 0),
            'warning_rows' => $counts->get('warning', 0),
            'error_rows' => $counts->get('error', 0),
            'pending_rows' => $pending,
            'rolled_back_at' => now(),
        ]);

        return ['deleted' => $deleted, 'updates_not_reverted' => $notUpdatedRolledBack];
    }
}
