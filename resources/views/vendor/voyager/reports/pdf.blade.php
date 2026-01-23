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

    @if($companies->isEmpty())
        <div class="text-center" style="margin-top: 50px;">
            <p>No se encontraron registros para los filtros seleccionados.</p>
        </div>
    @endif

    @foreach($companies as $company)
        @php
            // Calculate Company Level KPIs (based on filtered relations)
            $totalSuppliers = $company->suppliers->count();
            // Count total employees across all these suppliers
            $totalEmployees = $company->suppliers->sum(function($s) { return $s->employees->count(); });
        @endphp

        <!-- Only show company if it has relevant data (suppliers/employees) -->
        @if($totalSuppliers > 0 || $totalEmployees > 0)
            <div class="company-section">
                <div class="company-title">
                    {{ $company->name }} <span style="font-size: 12px; font-weight: normal;">(ID: {{ $company->id }})</span>
                </div>
                <div class="kpi-container" style="margin-top: 5px; padding: 5px;">
                    <strong>Métricas:</strong>
                    Proveedores: {{ $totalSuppliers }} | 
                    Empleados: {{ $totalEmployees }}
                </div>

                @foreach($company->suppliers as $supplier)
                    @if($supplier->employees->isNotEmpty())
                        <div class="supplier-section">
                            <div class="supplier-title">
                                Prov: {{ $supplier->name }} <span style="font-weight: normal; font-size: 12px;"> (ID: {{ $supplier->id }})</span> 
                            </div>
                            <div class="kpi-container">
                                Empleados: {{ $supplier->employees->count() }} | 
                                Aprobados: {{ $supplier->employees->where('approval_status', 'Aprobado')->count() }}
                            </div>

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
