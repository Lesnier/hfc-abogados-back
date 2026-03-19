@extends('voyager::master')

@section('page_title', 'Generar Reporte')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-documentation"></i> Reportes
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <form action="{{ route('voyager.reports.generate') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="start_date">Fecha Inicio</label>
                                    <input type="date" class="form-control" name="start_date">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="end_date">Fecha Fin</label>
                                    <input type="date" class="form-control" name="end_date">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="company_id">Empresa</label>
                                    <select class="form-control select2" name="company_id">
                                        <option value="">Todas</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="supplier_id">Proveedor</label>
                                    <select class="form-control select2" name="supplier_id">
                                        <option value="">Todos</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->identification }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="employee_id">Empleado</label>
                                    <select class="form-control select2" name="employee_id">
                                        <option value="">Todos</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->identification }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="approval_status">Estado</label>
                                    <select class="form-control" name="approval_status">
                                        <option value="">Todos</option>
                                        <option value="Revisión">Revisión</option>
                                        <option value="Aprobado">Aprobado</option>
                                        <option value="Rechazado">Rechazado</option>
                                        <option value="Baja">Baja</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="enabled">Habilitado (Vigente)</label>
                                    <select class="form-control" name="enabled">
                                        <option value="">Todos</option>
                                        <option value="1">Si</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="cost_center">Centro de Costo</label>
                                    <input type="text" class="form-control" name="cost_center" placeholder="Buscar...">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="responsible">Responsable</label>
                                    <input type="text" class="form-control" name="responsible" placeholder="Buscar...">
                                </div>
                            </div>

                            <div class="form-group text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="voyager-download"></i> Generar PDF
                                </button>
                            </div>
                        </form>

                        @if(!empty($historicalReports))
                            <hr>
                            <h4><i class="voyager-list"></i> Últimos Reportes Generados</h4>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>PDF</th>
                                            <th>Excel</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($historicalReports as $report)
                                            <tr>
                                                <td>Generado {{ $report['timeForHumans'] }}</td>
                                                <td>
                                                    @if($report['pdf_url'])
                                                        <a href="{{ $report['pdf_url'] }}" class="btn btn-sm" target="_blank" style="margin: 0; background-color: white; color: #333; border: 1px solid #ccc;">
                                                            <i class="voyager-documentation" style="color: #e74c3c;"></i> Ver PDF
                                                        </a>
                                                    @else
                                                        <span class="text-muted">No disponible</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($report['excel_url'])
                                                        <a href="{{ $report['excel_url'] }}" class="btn btn-sm" target="_blank" style="margin: 0; background-color: white; color: #333; border: 1px solid #ccc;">
                                                            <i class="voyager-documentation" style="color: #19914bff;"></i> Ver Excel
                                                        </a>
                                                    @else
                                                        <span class="text-muted">No disponible</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <form action="{{ route('voyager.reports.delete-history', $report['timestamp']) }}" method="POST" class="delete-history-form" style="display:inline-block;">
                                                        {{ method_field('DELETE') }}
                                                        {{ csrf_field() }}
                                                        <button type="submit" class="btn btn-sm btn-danger pull-right delete-confirm" style="margin: 0;" title="Borrar del Historial">
                                                            <i class="voyager-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <hr>
                            <div class="alert alert-info" style="margin-bottom: 0;">
                                <i class="voyager-info-circled"></i> No hay reportes generados recientemente.
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function(){
            $('.select2').select2({
                width: '100%'
            });

            // AJAX URL
            const filtersUrl = "{{ route('voyager.reports.filters') }}";
            
            // Elements
            const companySelect = $('select[name="company_id"]');
            const supplierSelect = $('select[name="supplier_id"]');
            const employeeSelect = $('select[name="employee_id"]');

            // Helper to populate select
            function populateSelect(selectElement, data, placeholder = 'Todos') {
                selectElement.empty();
                selectElement.append('<option value="">' + placeholder + '</option>');
                $.each(data, function(key, item) {
                     // Handle name + identification format if present
                     let text = item.name;
                     if(item.identification) {
                         text += ' (' + item.identification + ')';
                     }
                     selectElement.append('<option value="' + item.id + '">' + text + '</option>');
                });
                selectElement.trigger('change.select2'); // Notify Select2 of update (without triggering another change event if possible, or handle recursion)
                // Note: trigger('change') might cause recursion if not careful. Select2 usually needs trigger('change') or specifically updating data.
                // Better: selectElement.trigger('change.select2'); 
            }

            // On Company Change -> Load Suppliers (and filtered Employees)
            companySelect.on('change', function() {
                const companyId = $(this).val();
                
                // Clear dependent fields if company cleared? Or just fetch filtered.
                // Fetch new data
                $.ajax({
                    url: filtersUrl,
                    data: { 
                        company_id: companyId 
                    },
                    success: function(response) {
                        populateSelect(supplierSelect, response.suppliers, 'Todos');
                        populateSelect(employeeSelect, response.employees, 'Todos');
                    }
                });
            });

             // On Supplier Change -> Load Employees
            supplierSelect.on('change', function() {
                const supplierId = $(this).val();
                const companyId = companySelect.val(); // Keep company context if needed, though backend handles it separately or cumulatively
                
                $.ajax({
                    url: filtersUrl,
                    data: { 
                        company_id: companyId,
                        supplier_id: supplierId 
                    },
                    success: function(response) {
                        // Only update employees, keep suppliers as is (unless we want to narrow down suppliers too? No, usually just child)
                        // Actually getFilters returns suppliers too. If we update supplierSelect here we might reset valid selection? 
                        // Let's only update employeeSelect when supplier changes. 
                        populateSelect(employeeSelect, response.employees, 'Todos');
                    }
                });
            });
            
            // Initial state for Supplier Role (Disable readonly fields)
            @if(auth()->user()->hasRole('supplier'))
                companySelect.prop('disabled', true);
                supplierSelect.prop('disabled', true);
                // Note: Disabled fields are not sent in POST. We might need hidden inputs or enable on submit.
                // Better: Create hidden inputs for them and keep selects disabled visually.
                $('<input>').attr({type: 'hidden', name: 'company_id', value: companySelect.val()}).appendTo('form');
                $('<input>').attr({type: 'hidden', name: 'supplier_id', value: supplierSelect.val()}).appendTo('form');
            @endif
        });

        // Handle Form Submit to show Loading
        $('form').on('submit', function() {
            // Disable button
            // Disable button and visual change
            const btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true);
            btn.css('background-color', '#95a5a6'); // Gray
            btn.css('border-color', '#95a5a6');
            btn.html('<i class="voyager-paper-plane"></i> Procesando...');

            // Show Loading SweetAlert
            swal({
                title: "Generando Solicitud",
                text: "Por favor espere mientras procesamos su petición...",
                icon: "info",
                buttons: false,
                closeOnClickOutside: false,
                closeOnEsc: false
            });
        });

        // Show simplified SweetAlert if "message" session exists (overriding default toastr if preferred, or distinct)
        @if(session('message'))
            swal({
                title: "Información",
                text: "{{ session('message') }}",
                icon: "{{ session('alert-type') == 'info' ? 'success' : (session('alert-type') == 'error' ? 'error' : 'success') }}",
                button: "Entendido",
            });
        @endif

        // Delete confirmation
        $('.delete-history-form').on('submit', function(e) {
            e.preventDefault();
            var form = this;
            swal({
                title: '¿Estás seguro?',
                text: "Esto eliminará permanentemente la copia guardada del reporte en ambas versiones (PDF y Excel).",
                icon: 'warning',
                buttons: {
                    cancel: {
                        text: "Cancelar",
                        value: null,
                        visible: true,
                        className: "",
                        closeModal: true,
                    },
                    confirm: {
                        text: "Sí, ¡eliminar!",
                        value: true,
                        visible: true,
                        className: "bg-danger",
                        closeModal: true
                    }
                },
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    form.submit();
                }
            });
        });
    </script>
@stop
