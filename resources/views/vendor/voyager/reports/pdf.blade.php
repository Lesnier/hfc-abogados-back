<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte de Gestión - HFC Abogados</title>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #162e47; padding-bottom: 10px; }
        .logo { width: 150px; }
        h1 { margin: 5px 0; font-size: 24px; color: #162e47; }
        .meta { font-size: 12px; color: #777; }
        
        .company-section { margin-top: 20px; }
        .company-title { background-color: #162e47; color: #fff; padding: 5px 10px; font-size: 16px; font-weight: bold; page-break-after: avoid; }
        
        .supplier-section { margin-top: 15px; margin-left: 15px; border-left: 3px solid #e3c06d; padding-left: 10px; }
        .supplier-title { font-size: 14px; font-weight: bold; color: #162e47; margin-bottom: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 5px; font-size: 10px; text-align: left; }
        th { background-color: #f2f2f2; color: #333; }
        
        .kpi-container { display: flex; gap: 10px; margin-bottom: 5px; font-size: 11px; }
        .kpi-item { background: #f9f9f9; padding: 2px 5px; border: 1px solid #eee; }
        
        .text-center { text-align: center; }
        .status-revisión { color: #e3c06d; }
        .status-aprobado { color: #2c784f; }
        .status-rechazado { color: #a53d3d; }
        .status-baja { color: #555; }
    </style>
</head>
<body>

    @if(!isset($show_main_header) || $show_main_header)
    <div class="header">
        <h1>Reporte de Gestión</h1>
        <div class="meta">
            <strong>Generado por:</strong> {{ $generated_by ?? (auth()->user() ? auth()->user()->name : 'Sistema') }} <br>
            <strong>Fecha:</strong> {{ date('d/m/Y H:i') }} <br>
            <strong>Periodo:</strong> {{ $start_date ?? 'Inicio' }} - {{ $end_date ?? 'Actualidad' }} <br>
            @if(isset($filters['approval_status']) && $filters['approval_status'])
                <strong>Estado:</strong> {{ $filters['approval_status'] }} <br>
            @endif
            @if(isset($filters['enabled']))
                <strong>Habilitado:</strong> {{ $filters['enabled'] == '1' ? 'Si' : 'No' }} <br>
            @endif
            @if(isset($filters['cost_center']) && $filters['cost_center'])
                <strong>Centro Costo:</strong> {{ $filters['cost_center'] }} <br>
            @endif
            @if(isset($filters['responsible']) && $filters['responsible'])
                <strong>Responsable:</strong> {{ $filters['responsible'] }} <br>
            @endif
        </div>
    </div>
    @endif

    @if($companies->isEmpty())
        <div class="text-center" style="margin-top: 50px;">
            <p>No se encontraron registros para los filtros seleccionados.</p>
        </div>
    @endif

    @foreach($companies as $company)
        @php
            // Calculate Company Level KPIs (based on filtered relations)
            // Use global stats passed from Job if available, otherwise fall back to chunk count (should not happen in new logic)
            $totalSuppliers = $company->global_supplier_count ?? $company->suppliers->count();
            $totalEmployees = $company->global_employee_count ?? $company->suppliers->sum(function($s) { return $s->employees->count(); });
            $totalApproved = $company->global_approved_count ?? $company->suppliers->sum(function($s) { return $s->employees->where('approval_status', 'Aprobado')->count(); });
            
            $showHeader = $company->show_header ?? true;
        @endphp

        <!-- Only show company if it has relevant data (suppliers/employees) -->
        @if($totalSuppliers > 0 || $totalEmployees > 0)
            <div class="company-section">
                @if($showHeader)
                <div class="company-title">
                    {{ $company->name }} <span style="font-size: 12px; font-weight: normal;">(ID: {{ $company->id }})</span>
                </div>
                <!-- Only show KPIs if header is shown? Or typically KPIs go with header. Yes. -->
                <div class="kpi-container" style="margin-top: 5px; padding: 5px;">
                    <strong>Métricas:</strong>
                    Proveedores: {{ $totalSuppliers }} | 
                    Empleados: {{ $totalEmployees }} |
                    Aprobados: {{ $totalApproved }}
                </div>
                @endif

                @foreach($company->suppliers as $supplier)
                    @if($supplier->employees->isNotEmpty())
                        @php
                             $supplierShowHeader = $supplier->show_header ?? true;
                             $supplierTotalEmployees = $supplier->global_employee_count ?? $supplier->employees->count();
                             $supplierTotalApproved = $supplier->global_approved_count ?? $supplier->employees->where('approval_status', 'Aprobado')->count();
                        @endphp

                        <div class="supplier-section">
                            @if($supplierShowHeader)
                            <div class="supplier-title">
                                Prov: {{ $supplier->name }} <span style="font-weight: normal; font-size: 12px;"> (ID: {{ $supplier->id }})</span> 
                            </div>
                            <div class="kpi-container">
                                Empleados: {{ $supplierTotalEmployees }} | 
                                Aprobados: {{ $supplierTotalApproved }}
                            </div>
                            @endif

                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>DNI</th>
                                        <th>Estado</th>
                                        <th>Condición</th>
                                        <th>Alta</th>
                                        <th>Vencimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($supplier->employees as $employee)
                                        @if(isset($enable_map_script) && $enable_map_script)
                                            <script type="text/php">
                                                $GLOBALS['pdf_map'][$pdf->get_page_number()][] = {{ $employee->id }};
                                            </script>
                                        @endif
                                        <tr>
                                            <td>{{ $employee->name }}</td>
                                            <td>{{ $employee->identification }}</td>
                                            <td class="status-{{ Str::slug($employee->approval_status) }}">
                                                {{ $employee->approval_status }}
                                            </td>
                                            <td>{{ $employee->condition }}</td>
                                            <td>{{ $employee->validity_from ? $employee->validity_from->format('d/m/Y') : '-' }}

                                            </td>
                                            <td>{{ $employee->validity_to ? $employee->validity_to->format('d/m/Y') : '-' }}

                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    @endforeach

</body>
</html>
