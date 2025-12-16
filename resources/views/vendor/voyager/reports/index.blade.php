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
                        <form action="{{ route('voyager.reports.generate') }}" method="POST" target="_blank">
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

                            <div class="form-group text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="voyager-download"></i> Generar PDF
                                </button>
                            </div>
                        </form>
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
    </script>
@stop
