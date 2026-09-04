<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportStagingRow;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Prelectura (dry-run): parsea el archivo estandarizado completo, valida cada fila
 * y resuelve lo que se pueda resolver — SIN escribir nada en las tablas de negocio.
 * El resultado queda en import_staging_rows para revisión antes de ejecutar.
 */
class ImportAnalyzer
{
    private ImportFkResolver $resolver;

    public function __construct()
    {
        $this->resolver = new ImportFkResolver();
    }

    public function analyze(string $filePath, string $fileName, ?int $userId = null): ImportBatch
    {
        // Todo el dry-run corre en UNA transacción: en MySQL local, confirmar cada
        // INSERT por separado implica un fsync por fila (~50-60ms) — con miles de
        // filas eso son minutos. Una sola transacción reduce esto a fracciones de
        // segundo. No afecta las tablas de negocio (esto solo escribe en staging).
        return DB::transaction(function () use ($filePath, $fileName, $userId) {
            return $this->analyzeInTransaction($filePath, $fileName, $userId);
        });
    }

    private function analyzeInTransaction(string $filePath, string $fileName, ?int $userId): ImportBatch
    {
        $batch = ImportBatch::create([
            'file_name' => $fileName,
            'status' => 'analyzing',
            'imported_by' => $userId,
        ]);

        $spreadsheet = $this->loadSpreadsheetSafely($filePath);
        $schemas = config('import_schemas');
        uasort($schemas, fn ($a, $b) => $a['order'] <=> $b['order']);

        $entitiesIncluded = [];
        $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';

        foreach ($schemas as $entitySlug => $schema) {
            $sheet = $spreadsheet->getSheetByName($schema['sheet']);
            if (!$sheet) {
                continue;
            }

            $headerMap = $this->readHeaderMap($sheet);
            $maxRow = $sheet->getHighestRow();

            $columnsToRead = array_keys($schema['columns'] ?? []);
            foreach (($schema['fk'] ?? []) as $fk) {
                $columnsToRead[] = $fk['external_col'];
                $columnsToRead[] = $fk['identification_col'];
            }
            foreach (($schema['text_match'] ?? []) as $col => $tm) {
                $columnsToRead[] = $col;
            }
            if (isset($schema['user_link'])) {
                $columnsToRead[] = $schema['user_link']['email_col'];
            }
            if ($schema['mode'] === 'relation') {
                $columnsToRead[] = $schema['match_external_col'];
                $columnsToRead[] = $schema['match_identification_col'];
            }

            $seenUniqueKeys = [];
            $rowsInEntity = 0;

            for ($r = 2; $r <= $maxRow; $r++) {
                $raw = [];
                $hasData = false;
                foreach ($columnsToRead as $colName) {
                    if (!isset($headerMap[$colName])) {
                        $raw[$colName] = null;
                        continue;
                    }
                    $val = trim((string) $sheet->getCell($headerMap[$colName] . $r)->getFormattedValue());
                    $raw[$colName] = $val;
                    if ($val !== '') $hasData = true;
                }
                if (!$hasData) continue;

                $rowsInEntity++;
                $notes = [];
                $pendingFields = [];
                $status = 'ok';

                try {
                    if ($schema['mode'] === 'relation') {
                        $this->analyzeRelationRow($entitySlug, $schema, $raw, $r, $batch->id, $notes, $pendingFields, $status);
                    } else {
                        $this->analyzeEntityRow($entitySlug, $schema, $raw, $r, $batch->id, $seenUniqueKeys, $dateRegex, $notes, $pendingFields, $status);
                    }
                } catch (\Throwable $e) {
                    $status = 'error';
                    $notes[] = 'Error inesperado analizando esta fila: ' . $e->getMessage();
                }

                $action = $raw['__action'] ?? null;
                $matchedLocalId = $raw['__matched_local_id'] ?? null;
                unset($raw['__action'], $raw['__matched_local_id']);

                ImportStagingRow::create([
                    'import_batch_id' => $batch->id,
                    'entity_slug' => $entitySlug,
                    'row_number' => $r,
                    'raw_data' => $raw,
                    'status' => $status,
                    'action' => $action,
                    'matched_local_id' => $matchedLocalId,
                    'notes' => $notes,
                    'pending_fields' => $pendingFields,
                ]);
            }

            if ($rowsInEntity > 0) {
                $entitiesIncluded[] = $entitySlug;
            }
        }

        $this->recalculateTotals($batch);
        $batch->refresh();
        $batch->entities_included = $entitiesIncluded;
        $batch->status = $batch->pending_rows > 0 || $batch->error_rows > 0 ? 'needs_review' : 'ready';
        $batch->save();

        return $batch;
    }

    private function analyzeEntityRow(
        string $entitySlug,
        array $schema,
        array &$raw,
        int $rowNumber,
        int $batchId,
        array &$seenUniqueKeys,
        string $dateRegex,
        array &$notes,
        array &$pendingFields,
        string &$status
    ): void {
        $hasBlockingError = false;

        foreach ($schema['columns'] as $colName => $colDef) {
            $val = $raw[$colName] ?? '';

            if (!empty($colDef['required']) && $val === '') {
                $notes[] = "Falta el campo obligatorio '{$colName}'.";
                $hasBlockingError = true;
            }

            if ($colDef['type'] === 'select' && $val !== '' && !in_array($val, $colDef['options'], true)) {
                $notes[] = "El valor '{$val}' en '{$colName}' no está en la lista permitida (" . implode(' / ', $colDef['options']) . ").";
                $hasBlockingError = true;
            }

            if ($colDef['type'] === 'date' && $val !== '' && !preg_match($dateRegex, $val)) {
                $notes[] = "El valor '{$val}' en '{$colName}' no tiene formato AAAA-MM-DD.";
                $hasBlockingError = true;
            }
        }

        // Duplicado de clave única DENTRO del mismo archivo
        $uniqueKey = $schema['unique_key'];
        $uniqueVal = $raw[$uniqueKey] ?? '';
        if ($uniqueVal !== '') {
            if (isset($seenUniqueKeys[$uniqueVal])) {
                $notes[] = "'{$uniqueKey}' = '{$uniqueVal}' está repetido en este archivo (también en la fila {$seenUniqueKeys[$uniqueVal]}). Si continúa, la fila con número mayor sobrescribirá a la anterior.";
                if ($status !== 'error') $status = 'warning';
            }
            $seenUniqueKeys[$uniqueVal] = $rowNumber;
        }

        // Acción create/update contra la tabla real
        if ($uniqueVal !== '') {
            $model = $schema['model'];
            $existing = $model::where($uniqueKey, $uniqueVal)->first();
            if ($existing) {
                $raw['__action'] = 'update';
                $raw['__matched_local_id'] = $existing->getKey();
                $notes[] = "Ya existe un registro con {$uniqueKey}='{$uniqueVal}': esta fila lo ACTUALIZARÁ.";
            } else {
                $raw['__action'] = 'create';
            }
        }

        // Resolución de FKs
        foreach (($schema['fk'] ?? []) as $localField => $fk) {
            $extVal = $raw[$fk['external_col']] ?? '';
            $identVal = $raw[$fk['identification_col']] ?? '';
            $result = $this->resolver->resolve($fk['parent_entity'], $extVal, $identVal, $batchId);

            if ($result['status'] === 'unresolved') {
                if ($extVal === '' && $identVal === '') {
                    $notes[] = "Sin referencia a {$fk['label']} (ambas columnas vacías) — pendiente de asignar manualmente.";
                } else {
                    $notes[] = "No se pudo resolver {$fk['label']} con external_id='{$extVal}' / identification='{$identVal}'.";
                }
                $pendingFields[] = $localField;
            } elseif ($result['status'] === 'resolved_in_batch') {
                $notes[] = "{$fk['label']} se resolverá al ejecutar (se crea en este mismo archivo).";
            }
        }

        // Coincidencia de texto exacto (ej. law_firm_name)
        foreach (($schema['text_match'] ?? []) as $colName => $tm) {
            $val = $raw[$colName] ?? '';
            if ($val === '') continue;
            $match = $tm['model']::where($tm['match_field'], $val)->first();
            if (!$match) {
                $notes[] = "'{$val}' en '{$colName}' no coincide con ningún(a) {$tm['label']} existente.";
                $pendingFields[] = $tm['local_field'];
            }
        }

        // Vínculo de usuario (Representante/Auditor): informativo, no bloquea ni
        // queda pendiente — se resuelve/crea recién al ejecutar (ver
        // ImportExecutor::resolveOrCreateUser). Acá solo se anticipa qué va a pasar.
        if (isset($schema['user_link'])) {
            $email = $raw[$schema['user_link']['email_col']] ?? '';
            if ($email !== '') {
                $existingUser = \App\Models\User::where('email', $email)->first();
                if ($existingUser) {
                    if (!$existingUser->hasRole($schema['user_link']['role'])) {
                        $notes[] = "El usuario '{$email}' existe pero no tiene el rol '{$schema['user_link']['role']}' — se vinculará como {$schema['user_link']['label']} de todas formas; revisar manualmente.";
                        if ($status !== 'error') $status = 'warning';
                    } else {
                        $notes[] = "Se vinculará como {$schema['user_link']['label']} al usuario existente '{$email}'.";
                    }
                } else {
                    $notes[] = "Se creará una cuenta de usuario nueva con email '{$email}' como {$schema['user_link']['label']}.";
                }
            } else {
                $notes[] = "No se indicó email de {$schema['user_link']['label']} — se creará una cuenta de usuario placeholder para poder dejarlo vinculado.";
            }
        }

        if ($hasBlockingError) {
            $status = 'error';
        } elseif (!empty($pendingFields)) {
            $status = 'needs_resolution';
        }
    }

    private function analyzeRelationRow(
        string $entitySlug,
        array $schema,
        array &$raw,
        int $rowNumber,
        int $batchId,
        array &$notes,
        array &$pendingFields,
        string &$status
    ): void {
        $extVal = $raw[$schema['match_external_col']] ?? '';
        $identVal = $raw[$schema['match_identification_col']] ?? '';
        $employeeResult = $this->resolver->resolve($schema['match_entity'], $extVal, $identVal, $batchId);

        if ($employeeResult['status'] === 'unresolved') {
            $notes[] = "No se pudo identificar al empleado con external_id='{$extVal}' / identification='{$identVal}'.";
            $pendingFields[] = 'employee';
        } else {
            $raw['__action'] = 'update';
            $raw['__matched_local_id'] = $employeeResult['local_id'];
        }

        foreach (($schema['fk'] ?? []) as $localField => $fk) {
            $fkExt = $raw[$fk['external_col']] ?? '';
            $fkIdent = $raw[$fk['identification_col']] ?? '';
            $result = $this->resolver->resolve($fk['parent_entity'], $fkExt, $fkIdent, $batchId);
            if ($result['status'] === 'unresolved') {
                $notes[] = "No se pudo resolver {$fk['label']} con external_id='{$fkExt}' / identification='{$fkIdent}'.";
                $pendingFields[] = $localField;
            }
        }

        if (!empty($pendingFields)) {
            $status = 'needs_resolution';
        }
    }

    /**
     * PhpSpreadsheet (Shared\File::realpath) llama file_exists() sobre nombres de
     * entrada del ZIP (ej. "xl/styles.xml") como si fueran rutas reales del
     * filesystem. En hosts con open_basedir restringido eso genera un WARNING de
     * PHP que el manejador de errores de Laravel convierte en ErrorException
     * fatal, aunque PhpSpreadsheet igual sabe resolverlo internamente después.
     * Acá silenciamos puntualmente ESE warning (nada más) mientras se carga el
     * archivo, sin tocar vendor/ (así sobrevive a un composer update).
     */
    private function loadSpreadsheetSafely(string $filePath)
    {
        $previousHandler = set_error_handler(function ($errno, $errstr, $errfile = '', $errline = 0) use (&$previousHandler) {
            if ($errno === E_WARNING && str_contains($errstr, 'open_basedir restriction')) {
                return true; // silenciado: no propagar como excepción
            }

            return $previousHandler ? $previousHandler($errno, $errstr, $errfile, $errline) : false;
        });

        try {
            return IOFactory::load($filePath);
        } finally {
            restore_error_handler();
        }
    }

    private function readHeaderMap($sheet): array
    {
        $map = [];
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($c = 1; $c <= $highestCol; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $name = trim((string) $sheet->getCell($colLetter . '1')->getValue());
            if ($name !== '') {
                $map[$name] = $colLetter;
            }
        }
        return $map;
    }

    private function recalculateTotals(ImportBatch $batch): void
    {
        $counts = ImportStagingRow::where('import_batch_id', $batch->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $batch->update([
            'total_rows' => $counts->sum(),
            'ok_rows' => $counts->get('ok', 0),
            'warning_rows' => $counts->get('warning', 0),
            'error_rows' => $counts->get('error', 0),
            'pending_rows' => $counts->get('needs_resolution', 0),
        ]);
    }
}
