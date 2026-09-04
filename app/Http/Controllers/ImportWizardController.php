<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportStagingRow;
use App\Services\Import\ImportAnalyzer;
use App\Services\Import\ImportExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportWizardController extends Controller
{
    public function index()
    {
        $batches = ImportBatch::orderByDesc('id')->paginate(15);
        return view('admin.import-wizard.index', compact('batches'));
    }

    /**
     * Estas acciones (ejecutar, revertir, resolver grupo) solo aceptan POST.
     * Si llega un GET por navegación del browser (botón "atrás", pestaña
     * restaurada, un enlace guardado, etc.) no se ejecuta nada — se redirige
     * de vuelta a la revisión con un aviso, en vez de mostrar un error crudo.
     */
    public function redirectAccidentalGet(ImportBatch $batch)
    {
        return redirect()->route('voyager.import-wizard.review', $batch->id)->with([
            'message' => 'Esa acción necesita que uses el botón correspondiente en la página — no se hizo ningún cambio.',
            'alert-type' => 'warning',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx',
        ]);

        $file = $request->file('archivo');
        $storedPath = $file->store('import-wizard');
        $fullPath = Storage::path($storedPath);

        $analyzer = new ImportAnalyzer();
        $batch = $analyzer->analyze($fullPath, $file->getClientOriginalName(), auth()->id());

        return redirect()->route('voyager.import-wizard.review', $batch->id)
            ->with([
                'message' => "Archivo analizado: {$batch->total_rows} filas leídas.",
                'alert-type' => 'success',
            ]);
    }

    public function review(ImportBatch $batch)
    {
        $schemas = config('import_schemas');
        uasort($schemas, fn ($a, $b) => $a['order'] <=> $b['order']);

        $pendingGroups = [];
        $duplicates = [];
        $errorSamples = [];

        foreach ($schemas as $entitySlug => $schema) {
            // --- Grupos pendientes de resolución (por campo FK) ---
            $pendingRows = ImportStagingRow::where('import_batch_id', $batch->id)
                ->where('entity_slug', $entitySlug)
                ->where('status', 'needs_resolution')
                ->get();

            if ($pendingRows->isNotEmpty()) {
                foreach (($schema['fk'] ?? []) as $localField => $fk) {
                    $rowsForField = $pendingRows->filter(fn ($r) => in_array($localField, $r->pending_fields ?? [], true));
                    if ($rowsForField->isEmpty()) continue;

                    $groups = $rowsForField->groupBy(function ($r) use ($fk) {
                        $raw = $r->raw_data;
                        $ext = $raw[$fk['external_col']] ?? '';
                        $ident = $raw[$fk['identification_col']] ?? '';
                        if ($ext !== '') return 'ext:' . $ext;
                        if ($ident !== '') return 'ident:' . $ident;
                        return 'vacio';
                    });

                    foreach ($groups as $groupKey => $rows) {
                        $samples = $rows->take(3)->map(fn ($r) => $r->raw_data['name'] ?? ('fila ' . $r->row_number))->all();
                        $pendingGroups[] = [
                            'entity_slug' => $entitySlug,
                            'entity_label' => $schema['label'],
                            'field' => $localField,
                            'field_label' => $fk['label'],
                            'parent_entity' => $fk['parent_entity'],
                            'group_key' => $groupKey,
                            'raw_value' => str_starts_with($groupKey, 'ext:') ? substr($groupKey, 4) : (str_starts_with($groupKey, 'ident:') ? substr($groupKey, 6) : '(vacío)'),
                            'count' => $rows->count(),
                            'samples' => $samples,
                            'row_ids' => $rows->pluck('id')->all(),
                        ];
                    }
                }

                // Coincidencia de texto (companias.law_firm_name)
                foreach (($schema['text_match'] ?? []) as $colName => $tm) {
                    $rowsForField = $pendingRows->filter(fn ($r) => in_array($tm['local_field'], $r->pending_fields ?? [], true));
                    if ($rowsForField->isEmpty()) continue;
                    $groups = $rowsForField->groupBy(fn ($r) => $r->raw_data[$colName] ?? '(vacío)');
                    foreach ($groups as $groupKey => $rows) {
                        $samples = $rows->take(3)->map(fn ($r) => $r->raw_data['name'] ?? ('fila ' . $r->row_number))->all();
                        $pendingGroups[] = [
                            'entity_slug' => $entitySlug,
                            'entity_label' => $schema['label'],
                            'field' => $tm['local_field'],
                            'field_label' => $tm['label'],
                            'parent_entity' => null,
                            'text_match_model' => $tm['model'],
                            'text_match_field' => $tm['match_field'],
                            'group_key' => 'text:' . $groupKey,
                            'raw_value' => $groupKey,
                            'count' => $rows->count(),
                            'samples' => $samples,
                            'row_ids' => $rows->pluck('id')->all(),
                        ];
                    }
                }
            }

            // --- Duplicados dentro del archivo (status warning + nota de "repetido") ---
            $warningRows = ImportStagingRow::where('import_batch_id', $batch->id)
                ->where('entity_slug', $entitySlug)
                ->whereIn('status', ['warning', 'needs_resolution'])
                ->get()
                ->filter(fn ($r) => collect($r->notes ?? [])->contains(fn ($n) => str_contains($n, 'repetido')));

            if ($warningRows->isNotEmpty()) {
                $duplicates[$entitySlug] = [
                    'label' => $schema['label'],
                    'count' => $warningRows->count(),
                    'rows' => $warningRows->take(10),
                ];
            }

            // --- Muestra de errores bloqueantes ---
            $errRows = ImportStagingRow::where('import_batch_id', $batch->id)
                ->where('entity_slug', $entitySlug)
                ->where('status', 'error')
                ->limit(10)
                ->get();
            if ($errRows->isNotEmpty()) {
                $errorSamples[$entitySlug] = ['label' => $schema['label'], 'rows' => $errRows];
            }
        }

        // Listas para los selects de resolución
        $companiesList = \App\Models\Company::orderBy('name')->get(['id', 'name', 'identification']);
        $suppliersList = \App\Models\Supplier::orderBy('name')->get(['id', 'name', 'identification']);
        $parentLists = ['companias' => $companiesList, 'proveedores' => $suppliersList];

        // Contadores en vivo (no los que quedaron guardados del último análisis/
        // ejecución): esto permite ejecuciones parciales — importar solo lo que
        // ya está resuelto y dejar el resto pendiente para otra sesión sobre el
        // mismo batch.
        $liveCounts = ImportStagingRow::where('import_batch_id', $batch->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $liveCounts = [
            'ok' => $liveCounts->get('ok', 0),
            'warning' => $liveCounts->get('warning', 0),
            'error' => $liveCounts->get('error', 0),
            'needs_resolution' => $liveCounts->get('needs_resolution', 0),
            'imported' => $liveCounts->get('imported', 0),
        ];
        $liveCounts['executable'] = $liveCounts['ok'] + $liveCounts['warning'];

        return view('admin.import-wizard.review', compact('batch', 'pendingGroups', 'duplicates', 'errorSamples', 'parentLists', 'liveCounts'));
    }

    public function resolveGroup(Request $request, ImportBatch $batch)
    {
        $request->validate([
            'row_ids' => 'required|array',
            'field' => 'required|string',
            'local_id' => 'required|integer',
        ]);

        $rows = ImportStagingRow::where('import_batch_id', $batch->id)
            ->whereIn('id', $request->row_ids)
            ->get();

        $field = $request->field;
        foreach ($rows as $row) {
            $raw = $row->raw_data;
            $raw["__resolved_{$field}"] = (int) $request->local_id;
            $pending = array_values(array_diff($row->pending_fields ?? [], [$field]));

            $status = 'ok';
            if (!empty($pending)) {
                $status = 'needs_resolution';
            } elseif (collect($row->notes ?? [])->contains(fn ($n) => str_contains($n, 'repetido'))) {
                $status = 'warning';
            }

            $row->update(['raw_data' => $raw, 'pending_fields' => $pending, 'status' => $status]);
        }

        $this->recalculateTotals($batch);

        return back()->with(['message' => count($rows) . ' filas resueltas.', 'alert-type' => 'success']);
    }

    public function downloadDuplicates(ImportBatch $batch, string $entitySlug)
    {
        $schema = config("import_schemas.{$entitySlug}");
        $rows = ImportStagingRow::where('import_batch_id', $batch->id)
            ->where('entity_slug', $entitySlug)
            ->whereIn('status', ['warning', 'needs_resolution'])
            ->get()
            ->filter(fn ($r) => collect($r->notes ?? [])->contains(fn ($n) => str_contains($n, 'repetido')));

        $headers = array_keys($schema['columns'] ?? []);
        foreach (($schema['fk'] ?? []) as $fk) {
            $headers[] = $fk['external_col'];
            $headers[] = $fk['identification_col'];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($entitySlug);
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $sheet->setCellValueByColumnAndRow(count($headers) + 1, 1, 'fila_origen');
        $sheet->setCellValueByColumnAndRow(count($headers) + 2, 1, 'nota');

        $r = 2;
        foreach ($rows as $row) {
            $raw = $row->raw_data;
            foreach ($headers as $i => $h) {
                $sheet->setCellValueExplicit($sheet->getCellByColumnAndRow($i + 1, $r)->getCoordinate(), $raw[$h] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->setCellValue($sheet->getCellByColumnAndRow(count($headers) + 1, $r)->getCoordinate(), $row->row_number);
            $sheet->setCellValue($sheet->getCellByColumnAndRow(count($headers) + 2, $r)->getCoordinate(), implode(' | ', $row->notes ?? []));
            $r++;
        }

        $fileName = "duplicados_{$entitySlug}_batch{$batch->id}.xlsx";
        $tmpPath = tempnam(sys_get_temp_dir(), 'dup') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $fileName)->deleteFileAfterSend(true);
    }

    public function execute(Request $request, ImportBatch $batch)
    {
        $allowUnresolved = (bool) $request->input('allow_unresolved', false);
        $executor = new ImportExecutor();
        $executor->execute($batch, $allowUnresolved);

        return redirect()->route('voyager.import-wizard.review', $batch->id)
            ->with(['message' => 'Importación ejecutada.', 'alert-type' => 'success']);
    }

    public function rollback(ImportBatch $batch)
    {
        $executor = new ImportExecutor();
        $result = $executor->rollback($batch);

        $msg = 'Se revirtió el batch. Registros eliminados: ' . json_encode($result['deleted']);
        if ($result['updates_not_reverted'] > 0) {
            $msg .= " — ATENCIÓN: {$result['updates_not_reverted']} filas que ACTUALIZARON registros existentes no se revirtieron (no se guarda el valor anterior).";
        }

        return redirect()->route('voyager.import-wizard.index')->with(['message' => $msg, 'alert-type' => 'warning']);
    }

    public function destroy(ImportBatch $batch)
    {
        if (in_array($batch->status, ['completed', 'completed_with_errors'], true)) {
            return back()->with(['message' => 'Este batch ya se ejecutó — usá "Revertir" en vez de eliminar.', 'alert-type' => 'error']);
        }
        $batch->delete();
        return redirect()->route('voyager.import-wizard.index')->with(['message' => 'Importación descartada.', 'alert-type' => 'success']);
    }

    private function recalculateTotals(ImportBatch $batch): void
    {
        $counts = ImportStagingRow::where('import_batch_id', $batch->id)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $batch->update([
            'ok_rows' => $counts->get('ok', 0),
            'warning_rows' => $counts->get('warning', 0),
            'error_rows' => $counts->get('error', 0),
            'pending_rows' => $counts->get('needs_resolution', 0),
            'status' => ($counts->get('needs_resolution', 0) > 0) ? 'needs_review' : 'ready',
        ]);
    }
}
