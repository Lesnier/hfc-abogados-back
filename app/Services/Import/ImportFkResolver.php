<?php

namespace App\Services\Import;

use App\Models\ImportMapping;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve una referencia a entidad padre (external_id / identification) a un local_id real.
 *
 * Orden de resolución:
 *  1. import_mappings (external_id ya registrado, de esta corrida o de una anterior).
 *  2. Clave de negocio natural en la tabla real del padre (ej. suppliers.identification).
 *  3. Filas de staging del padre, en el MISMO batch, que todavía no se ejecutaron
 *     (permite que un archivo con varias hojas resuelva relaciones entre sí sin
 *     tener que ejecutarlas en pasadas separadas).
 */
class ImportFkResolver
{
    /** Cache en memoria: "{batchId}:{entity}" => ['external' => [id=>true], 'identification' => [id=>true]] */
    private array $inBatchIndex = [];

    /** Cache en memoria de mappings ya resueltos dentro de esta corrida (evita re-consultar la misma fila) */
    private array $mappingCache = [];

    /**
     * @return array{status: 'resolved'|'resolved_in_batch'|'unresolved', local_id: ?int}
     */
    public function resolve(string $parentEntitySlug, ?string $externalId, ?string $identification, ?int $batchId = null): array
    {
        $externalId = $externalId !== '' ? $externalId : null;
        $identification = $identification !== '' ? $identification : null;

        if ($externalId === null && $identification === null) {
            return ['status' => 'unresolved', 'local_id' => null];
        }

        if ($externalId !== null) {
            $mapCacheKey = "{$parentEntitySlug}:{$externalId}";
            if (!array_key_exists($mapCacheKey, $this->mappingCache)) {
                $mapping = ImportMapping::where('entity_slug', $parentEntitySlug)
                    ->where('external_id', $externalId)
                    ->first();
                $this->mappingCache[$mapCacheKey] = $mapping ? $mapping->local_id : null;
            }
            if ($this->mappingCache[$mapCacheKey] !== null) {
                return ['status' => 'resolved', 'local_id' => $this->mappingCache[$mapCacheKey]];
            }
        }

        $schema = config("import_schemas.{$parentEntitySlug}");
        if ($schema && $identification !== null) {
            $model = $schema['model'];
            $uniqueKey = $schema['unique_key'];
            $found = $model::where($uniqueKey, $identification)->first();
            if ($found) {
                return ['status' => 'resolved', 'local_id' => $found->getKey()];
            }
        }

        // No existe todavía: ¿se va a crear en este mismo batch? (índice cacheado, una sola query por entidad)
        if ($batchId) {
            $index = $this->getInBatchIndex($batchId, $parentEntitySlug);
            if ($externalId !== null && isset($index['external'][$externalId])) {
                return ['status' => 'resolved_in_batch', 'local_id' => null];
            }
            if ($identification !== null && isset($index['identification'][$identification])) {
                return ['status' => 'resolved_in_batch', 'local_id' => null];
            }
        }

        return ['status' => 'unresolved', 'local_id' => null];
    }

    private function getInBatchIndex(int $batchId, string $entitySlug): array
    {
        $cacheKey = "{$batchId}:{$entitySlug}";
        if (isset($this->inBatchIndex[$cacheKey])) {
            return $this->inBatchIndex[$cacheKey];
        }

        // Cualquier fila que SÍ se va a crear/actualizar cuenta, aunque esté pendiente
        // de resolver OTRO campo suyo (ej. un proveedor pendiente de compañía igual
        // se va a crear, y otras filas pueden enlazarse a él).
        $index = ['external' => [], 'identification' => []];
        DB::table('import_staging_rows')
            ->where('import_batch_id', $batchId)
            ->where('entity_slug', $entitySlug)
            ->where('status', '!=', 'error')
            ->select('raw_data')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$index) {
                foreach ($rows as $row) {
                    $raw = json_decode($row->raw_data, true);
                    if (!empty($raw['external_id'])) $index['external'][$raw['external_id']] = true;
                    if (!empty($raw['identification'])) $index['identification'][$raw['identification']] = true;
                }
            });

        $this->inBatchIndex[$cacheKey] = $index;
        return $index;
    }

    public function registerMapping(string $entitySlug, ?string $externalId, ?string $identification, int $localId, string $action, ?int $batchId): void
    {
        if ($externalId === null || $externalId === '') {
            return; // sin external_id no hay nada que mapear para futuras corridas
        }

        ImportMapping::updateOrCreate(
            ['entity_slug' => $entitySlug, 'external_id' => $externalId],
            [
                'identification' => $identification !== '' ? $identification : null,
                'local_id' => $localId,
                'action' => $action,
                'import_batch_id' => $batchId,
            ]
        );
    }
}
