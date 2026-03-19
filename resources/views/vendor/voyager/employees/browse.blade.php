@extends('voyager::master')

@section('page_title', __('voyager::generic.viewing').' '.$dataType->getTranslatedAttribute('display_name_plural'))

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="{{ $dataType->icon }}"></i> {{ $dataType->getTranslatedAttribute('display_name_plural') }}
        </h1>
        @can('add', app($dataType->model_name))
            <a href="{{ route('voyager.'.$dataType->slug.'.create') }}" class="btn btn-success btn-add-new">
                <i class="voyager-plus"></i> <span>{{ __('voyager::generic.add_new') }}</span>
            </a>
            {{-- CSV Buttons for Admin, Tech Admin, Lawyer, Supplier --}}
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('tech_admin') || Auth::user()->hasRole('lawyer') || Auth::user()->hasRole('supplier'))
            <a href="{{ route('voyager.csv-import.template', $dataType->slug) }}" class="btn btn-info" style="margin-left: 10px;">
                <i class="voyager-download"></i> Descargar Plantilla CSV
            </a>
            <button type="button" class="btn btn-dark" data-toggle="modal" data-target="#csv_import_modal" style="margin-left: 5px;">
                <i class="voyager-upload"></i> Importar CSV
            </button>
            @endif
        @endcan
        @can('delete', app($dataType->model_name))
            @include('voyager::partials.bulk-delete')
            <button type="button" class="btn btn-primary" id="bulk_edit_btn">
                <i class="voyager-edit"></i> <span>Bulk Edit</span>
            </button>
        @endcan
        @can('edit', app($dataType->model_name))
            @if(!empty($dataType->order_column) && !empty($dataType->order_display_column))
                <a href="{{ route('voyager.'.$dataType->slug.'.order') }}" class="btn btn-primary btn-add-new">
                    <i class="voyager-list"></i> <span>{{ __('voyager::bread.order') }}</span>
                </a>
            @endif
        @endcan
        @can('delete', app($dataType->model_name))
            @if($usesSoftDeletes)
                <input type="checkbox" @if ($showSoftDeleted) checked @endif id="show_soft_deletes" data-toggle="toggle" data-on="{{ __('voyager::bread.soft_deletes_off') }}" data-off="{{ __('voyager::bread.soft_deletes_on') }}">
            @endif
        @endcan
        @foreach($actions as $action)
            @if (method_exists($action, 'massAction'))
                @include('voyager::bread.partials.actions', ['action' => $action, 'data' => null])
            @endif
        @endforeach
        @include('voyager::multilingual.language-selector')
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        @if ($isServerSide)
                            <form method="get" class="form-search">
                                <div id="search-input">
                                    <div class="col-2">
                                        <select id="search_key" name="key">
                                            @foreach($searchNames as $key => $name)
                                                <option value="{{ $key }}" @if($search->key == $key || (empty($search->key) && $key == $defaultSearchKey)) selected @endif>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-2">
                                        <select id="filter" name="filter">
                                            <option value="contains" @if($search->filter == "contains") selected @endif>{{ __('voyager::generic.contains') }}</option>
                                            <option value="equals" @if($search->filter == "equals") selected @endif>=</option>
                                        </select>
                                    </div>
                                    <div class="input-group col-md-12">
                                        <input type="text" class="form-control" placeholder="{{ __('voyager::generic.search') }}" name="s" value="{{ $search->value }}">
                                        <span class="input-group-btn">
                                            <button class="btn btn-info btn-lg" type="submit">
                                                <i class="voyager-search"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                                @if (Request::has('sort_order') && Request::has('order_by'))
                                    <input type="hidden" name="sort_order" value="{{ Request::get('sort_order') }}">
                                    <input type="hidden" name="order_by" value="{{ Request::get('order_by') }}">
                                @endif
                            </form>
                        @endif
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        @if($showCheckboxColumn)
                                            <th class="dt-not-orderable">
                                                <input type="checkbox" class="select_all">
                                            </th>
                                        @endif
                                        @foreach($dataType->browseRows as $row)
                                        <th>
                                            @if ($isServerSide && in_array($row->field, $sortableColumns))
                                                <a href="{{ $row->sortByUrl($orderBy, $sortOrder) }}">
                                            @endif
                                            {{ $row->getTranslatedAttribute('display_name') }}
                                            @if ($isServerSide)
                                                @if ($row->isCurrentSortField($orderBy))
                                                    @if ($sortOrder == 'asc')
                                                        <i class="voyager-angle-up pull-right"></i>
                                                    @else
                                                        <i class="voyager-angle-down pull-right"></i>
                                                    @endif
                                                @endif
                                                </a>
                                            @endif
                                        </th>
                                        @endforeach
                                        <th class="actions text-right dt-not-orderable">{{ __('voyager::generic.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dataTypeContent as $data)
                                    <tr>
                                        @if($showCheckboxColumn)
                                            <td>
                                                <input type="checkbox" name="row_id" id="checkbox_{{ $data->getKey() }}" value="{{ $data->getKey() }}">
                                            </td>
                                        @endif
                                        @foreach($dataType->browseRows as $row)
                                            @php
                                            if ($data->{$row->field.'_browse'}) {
                                                $data->{$row->field} = $data->{$row->field.'_browse'};
                                            }
                                            @endphp
                                            <td>
                                                @if (isset($row->details->view_browse))
                                                    @include($row->details->view_browse, ['row' => $row, 'dataType' => $dataType, 'dataTypeContent' => $dataTypeContent, 'content' => $data->{$row->field}, 'view' => 'browse', 'options' => $row->details])
                                                @elseif (isset($row->details->view))
                                                    @include($row->details->view, ['row' => $row, 'dataType' => $dataType, 'dataTypeContent' => $dataTypeContent, 'content' => $data->{$row->field}, 'action' => 'browse', 'view' => 'browse', 'options' => $row->details])
                                                @elseif($row->type == 'image')
                                                    <img src="@if( !filter_var($data->{$row->field}, FILTER_VALIDATE_URL)){{ Voyager::image( $data->{$row->field} ) }}@else{{ $data->{$row->field} }}@endif" style="width:100px">
                                                @elseif($row->type == 'relationship')
                                                    @include('voyager::formfields.relationship', ['view' => 'browse','options' => $row->details])
                                                @elseif($row->type == 'select_multiple')
                                                    @if(property_exists($row->details, 'relationship'))

                                                        @foreach($data->{$row->field} as $item)
                                                            {{ $item->{$row->field} }}
                                                        @endforeach

                                                    @elseif(property_exists($row->details, 'options'))
                                                        @if (!empty(json_decode($data->{$row->field})))
                                                            @foreach(json_decode($data->{$row->field}) as $item)
                                                                @if (@$row->details->options->{$item})
                                                                    {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            {{ __('voyager::generic.none') }}
                                                        @endif
                                                    @endif

                                                    @elseif($row->type == 'multiple_checkbox' && property_exists($row->details, 'options'))
                                                        @if (@count(json_decode($data->{$row->field}, true)) > 0)
                                                            @foreach(json_decode($data->{$row->field}) as $item)
                                                                @if (@$row->details->options->{$item})
                                                                    {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            {{ __('voyager::generic.none') }}
                                                        @endif

                                                @elseif(($row->type == 'select_dropdown' || $row->type == 'radio_btn') && property_exists($row->details, 'options'))

                                                    {!! $row->details->options->{$data->{$row->field}} ?? '' !!}

                                                @elseif($row->type == 'date' || $row->type == 'timestamp')
                                                    @if ( property_exists($row->details, 'format') && !is_null($data->{$row->field}) )
                                                        {{ \Carbon\Carbon::parse($data->{$row->field})->formatLocalized($row->details->format) }}
                                                    @else
                                                        {{ $data->{$row->field} }}
                                                    @endif
                                                @elseif($row->type == 'checkbox')
                                                    @if(property_exists($row->details, 'on') && property_exists($row->details, 'off'))
                                                        @if($data->{$row->field})
                                                            <span class="label label-info">{{ $row->details->on }}</span>
                                                        @else
                                                            <span class="label label-primary">{{ $row->details->off }}</span>
                                                        @endif
                                                    @else
                                                    {{ $data->{$row->field} }}
                                                    @endif
                                                @elseif($row->type == 'color')
                                                    <span class="badge badge-lg" style="background-color: {{ $data->{$row->field} }}">{{ $data->{$row->field} }}</span>
                                                @elseif($row->type == 'text')
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <div>{{ mb_strlen( $data->{$row->field} ) > 200 ? mb_substr($data->{$row->field}, 0, 200) . ' ...' : $data->{$row->field} }}</div>
                                                @elseif($row->type == 'text_area')
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <div>{{ mb_strlen( $data->{$row->field} ) > 200 ? mb_substr($data->{$row->field}, 0, 200) . ' ...' : $data->{$row->field} }}</div>
                                                @elseif($row->type == 'file' && !empty($data->{$row->field}) )
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    @if(json_decode($data->{$row->field}) !== null)
                                                        @foreach(json_decode($data->{$row->field}) as $file)
                                                            <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($file->download_link) ?: '' }}" target="_blank">
                                                                {{ $file->original_name ?: '' }}
                                                            </a>
                                                            <br/>
                                                        @endforeach
                                                    @else
                                                        <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($data->{$row->field}) }}" target="_blank">
                                                            {{ __('voyager::generic.download') }}
                                                        </a>
                                                    @endif
                                                @elseif($row->type == 'rich_text_box')
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <div>{{ mb_strlen( strip_tags($data->{$row->field}, '<b><i><u>') ) > 200 ? mb_substr(strip_tags($data->{$row->field}, '<b><i><u>'), 0, 200) . ' ...' : strip_tags($data->{$row->field}, '<b><i><u>') }}</div>
                                                @elseif($row->type == 'coordinates')
                                                    @include('voyager::partials.coordinates-static-image')
                                                @elseif($row->type == 'multiple_images')
                                                    @php $images = json_decode($data->{$row->field}); @endphp
                                                    @if($images)
                                                        @php $images = array_slice($images, 0, 3); @endphp
                                                        @foreach($images as $image)
                                                            <img src="@if( !filter_var($image, FILTER_VALIDATE_URL)){{ Voyager::image( $image ) }}@else{{ $image }}@endif" style="width:50px">
                                                        @endforeach
                                                    @endif
                                                @elseif($row->type == 'media_picker')
                                                    @php
                                                        if (is_array($data->{$row->field})) {
                                                            $files = $data->{$row->field};
                                                        } else {
                                                            $files = json_decode($data->{$row->field});
                                                        }
                                                    @endphp
                                                    @if ($files)
                                                        @if (property_exists($row->details, 'show_as_images') && $row->details->show_as_images)
                                                            @foreach (array_slice($files, 0, 3) as $file)
                                                            <img src="@if( !filter_var($file, FILTER_VALIDATE_URL)){{ Voyager::image( $file ) }}@else{{ $file }}@endif" style="width:50px">
                                                            @endforeach
                                                        @else
                                                            <ul>
                                                            @foreach (array_slice($files, 0, 3) as $file)
                                                                <li>{{ $file }}</li>
                                                            @endforeach
                                                            </ul>
                                                        @endif
                                                        @if (count($files) > 3)
                                                            {{ __('voyager::media.files_more', ['count' => (count($files) - 3)]) }}
                                                        @endif
                                                    @elseif (is_array($files) && count($files) == 0)
                                                        {{ trans_choice('voyager::media.files', 0) }}
                                                    @elseif ($data->{$row->field} != '')
                                                        @if (property_exists($row->details, 'show_as_images') && $row->details->show_as_images)
                                                            <img src="@if( !filter_var($data->{$row->field}, FILTER_VALIDATE_URL)){{ Voyager::image( $data->{$row->field} ) }}@else{{ $data->{$row->field} }}@endif" style="width:50px">
                                                        @else
                                                            {{ $data->{$row->field} }}
                                                        @endif
                                                    @else
                                                        {{ trans_choice('voyager::media.files', 0) }}
                                                    @endif
                                                @else
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <span>{{ $data->{$row->field} }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="no-sort no-click bread-actions">
                                            @foreach($actions as $action)
                                                @if (!method_exists($action, 'massAction'))
                                                    @include('voyager::bread.partials.actions', ['action' => $action])
                                                @endif
                                            @endforeach
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($isServerSide)
                            <div class="pull-left">
                                <div role="status" class="show-res" aria-live="polite">{{ trans_choice(
                                    'voyager::generic.showing_entries', $dataTypeContent->total(), [
                                        'from' => $dataTypeContent->firstItem(),
                                        'to' => $dataTypeContent->lastItem(),
                                        'all' => $dataTypeContent->total()
                                    ]) }}</div>
                            </div>
                            <div class="pull-right">
                                {{ $dataTypeContent->appends([
                                    's' => $search->value,
                                    'filter' => $search->filter,
                                    'key' => $search->key,
                                    'order_by' => $orderBy,
                                    'sort_order' => $sortOrder,
                                    'showSoftDeleted' => $showSoftDeleted,
                                ])->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CSV Import Modal --}}
    <div class="modal fade" tabindex="-1" id="csv_import_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-upload"></i> Importar CSV - {{ $dataType->getTranslatedAttribute('display_name_plural') }}</h4>
                </div>
                <form action="{{ route('voyager.csv-import.import', $dataType->slug) }}" method="POST" enctype="multipart/form-data" id="csv_import_form">
                    {{ csrf_field() }}
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="csv_file">Seleccionar Archivo CSV</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv, .txt" required>
                            <p class="help-block">Asegúrese de usar el formato de la plantilla. Se usará la columna <strong>identification</strong> para evitar duplicados.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- CSV Errors Modal --}}
    <div class="modal modal-danger fade" tabindex="-1" id="csv_errors_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-warning"></i> Errores de Validación</h4>
                </div>
                <div class="modal-body">
                    <p>Se encontraron los siguientes errores en el archivo CSV:</p>
                    <ul id="csv_error_list" style="max-height: 300px; overflow-y: auto;"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Single delete modal --}}
    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }} {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="#" id="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm" value="{{ __('voyager::generic.delete_confirm') }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    {{-- Bulk Edit Modal --}}
    <div class="modal fade" tabindex="-1" id="bulk_edit_modal" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-edit"></i> Edición Masiva - {{ $dataType->getTranslatedAttribute('display_name_plural') }}</h4>
                </div>
                <form action="{{ route('voyager.employees.bulk-update') }}" method="POST" enctype="multipart/form-data" id="bulk_edit_form">
                    {{ csrf_field() }}
                    <input type="hidden" name="ids" id="bulk_edit_ids">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="voyager-info-circled"></i> Los campos que se dejen vacíos no serán modificados en los registros seleccionados.
                        </div>
                        <div class="row">
                            {{-- Proveedor --}}
                            <div class="form-group col-md-6">
                                <label for="supplier_id">Proveedor</label>
                                <select name="supplier_id" class="form-control select2">
                                    <option value="">-- No Cambiar --</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Condicion --}}
                            <div class="form-group col-md-6">
                                <label for="condition">Condición</label>
                                <input type="text" name="condition" class="form-control" placeholder="Ej: Pasante, Efectivo...">
                            </div>
                            {{-- Valido Desde --}}
                            <div class="form-group col-md-6">
                                <label for="validity_from">Valido Desde</label>
                                <input type="date" name="validity_from" class="form-control">
                            </div>
                            {{-- Valido Hasta --}}
                            <div class="form-group col-md-6">
                                <label for="validity_to">Valido Hasta</label>
                                <input type="date" name="validity_to" class="form-control">
                            </div>
                            {{-- Ingresos --}}
                            <div class="form-group col-md-6">
                                <label for="suitable_income">Ingresos</label>
                                <input type="text" name="suitable_income" class="form-control">
                            </div>
                            {{-- Responsable --}}
                            <div class="form-group col-md-6">
                                <label for="responsible">Responsable</label>
                                <input type="text" name="responsible" class="form-control">
                            </div>
                            {{-- Centro de Costo --}}
                            <div class="form-group col-md-6">
                                <label for="cost_center">Centro de Costo</label>
                                <input type="text" name="cost_center" class="form-control">
                            </div>
                            {{-- Estatus de Aprobacion --}}
                            <div class="form-group col-md-6">
                                <label for="approval_status">Estatus de Aprobación</label>
                                <select name="approval_status" class="form-control">
                                    <option value="">-- No Cambiar --</option>
                                    <option value="Revisión">Revisión</option>
                                    <option value="Aprobado">Aprobado</option>
                                    <option value="Baja">Baja</option>
                                </select>
                            </div>
                            {{-- Recibo de Salario (File) --}}
                            <div class="form-group col-md-12">
                                <label for="salary_receipt">Recibo de Salario</label>
                                <div class="custom-file-input-wrapper">
                                    <input type="file" name="salary_receipt" id="bulk_salary_receipt">
                                    <button type="button" class="btn-file-select" id="bulk_file_btn">
                                        <i class="voyager-upload"></i> Seleccionar Archivo
                                    </button>
                                    <div class="file-preview-info" id="bulk_file_preview" style="display:none;">
                                        <i class="voyager-file-text" style="font-size: 16px; color: #e74c3c;margin-top: 4px;"></i>
                                        <span class="file-name" style="width: 100%;"></span>
                                        <i class="voyager-x remove-file-btn" id="bulk_remove_file" title="Quitar"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios Masivos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('css')
@if(!$dataType->server_side && config('dashboard.data_tables.responsive'))
    <link rel="stylesheet" href="{{ voyager_asset('lib/css/responsive.dataTables.min.css') }}">
@endif
<style>
    .custom-file-input-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 1px;
    }
    .custom-file-input-wrapper input[type="file"] {
        display: none !important;
    }
    .btn-file-select {
        background-color: #e74c3c; /* Red color */
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background 0.3s;
        width: 100%;
    }
    .btn-file-select:hover {
        background-color: #c0392b;
    }
    .file-preview-info {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f8f9fa;
        padding: 1px 10px;
        border-radius: 4px;
        border: 1px solid #ddd;
        width: 100%;
        height: 34px;
    }
    .remove-file-btn {
        color: #999;
        cursor: pointer;
        font-size: 16px;
        line-height: 1;
    }
    .remove-file-btn:hover {
        color: #d9534f;
    }
</style>
@stop

@section('javascript')
    <!-- DataTables -->
    @if(!$dataType->server_side && config('dashboard.data_tables.responsive'))
        <script src="{{ voyager_asset('lib/js/dataTables.responsive.min.js') }}"></script>
    @endif
    <script>
        $(document).ready(function () {
            @if (!$dataType->server_side)
                var table = $('#dataTable').DataTable({!! json_encode(
                    array_merge([
                        "order" => $orderColumn,
                        "language" => __('voyager::datatable'),
                        "columnDefs" => [
                            ['targets' => 'dt-not-orderable', 'searchable' =>  false, 'orderable' => false],
                        ],
                    ],
                    config('voyager.dashboard.data_tables', []))
                , true) !!});
            @else
                $('#search-input select').select2({
                    minimumResultsForSearch: Infinity
                });
            @endif

            @if ($isModelTranslatable)
                $('.side-body').multilingual();
                //Reinitialise the multilingual features when they change tab
                $('#dataTable').on('draw.dt', function(){
                    $('.side-body').data('multilingual').init();
                })
            @endif
            $('.select_all').on('click', function(e) {
                $('input[name="row_id"]').prop('checked', $(this).prop('checked')).trigger('change');
            });

            // CSV Import AJAX Logic
            $('#csv_import_form').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        $('#csv_import_modal').modal('hide');
                        toastr.success(response.message);
                        setTimeout(function(){
                            location.reload(); 
                        }, 1000);
                    },
                    error: function(xhr) {
                        $('#csv_import_modal').modal('hide');
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            var list = '';
                            // Errors can be object or array
                            $.each(errors, function(index, value) {
                                list += '<li>' + value + '</li>';
                            });
                            $('#csv_error_list').html(list);
                            $('#csv_errors_modal').modal('show');
                        } else {
                            toastr.error('Ocurrió un error inesperado al importar.');
                        }
                    }
                });
            });
        });


        var deleteFormAction;
        $('td').on('click', '.delete', function (e) {
            $('#delete_form')[0].action = '{{ route('voyager.'.$dataType->slug.'.destroy', '__id') }}'.replace('__id', $(this).data('id'));
            $('#delete_modal').modal('show');
        });

        @if($usesSoftDeletes)
            @php
                $params = [
                    's' => $search->value,
                    'filter' => $search->filter,
                    'key' => $search->key,
                    'order_by' => $orderBy,
                    'sort_order' => $sortOrder,
                ];
            @endphp
            $(function() {
                $('#show_soft_deletes').change(function() {
                    if ($(this).prop('checked')) {
                        $('#dataTable').before('<a id="redir" href="{{ (route('voyager.'.$dataType->slug.'.index', array_merge($params, ['showSoftDeleted' => 1]), true)) }}"></a>');
                    }else{
                        $('#dataTable').before('<a id="redir" href="{{ (route('voyager.'.$dataType->slug.'.index', array_merge($params, ['showSoftDeleted' => 0]), true)) }}"></a>');
                    }

                    $('#redir')[0].click();
                })
            })
        @endif
        $('input[name="row_id"]').on('change', function () {
            var ids = [];
            $('input[name="row_id"]:checked').each(function() {
                ids.push($(this).val());
            });
            $('.selected_ids').val(ids);
        });

        // Bulk Edit Logic
        $('#bulk_edit_btn').on('click', function() {
            var ids = [];
            $('input[name="row_id"]:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length === 0) {
                toastr.warning('Por favor, selecciona al menos un empleado.');
                return;
            }

            $('#bulk_edit_ids').val(ids.join(','));
            $('#bulk_edit_modal').modal('show');
        });

        // Custom File Input for Bulk Modal
        $('#bulk_file_btn').on('click', function() {
            $('#bulk_salary_receipt').trigger('click');
        });

        $('#bulk_salary_receipt').on('change', function() {
            var file = this.files[0];
            if (file) {
                var fileName = file.name;
                if (fileName.length > 30) fileName = fileName.substring(0, 30) + '...';
                $('#bulk_file_preview .file-name').text(fileName).attr('title', file.name);
                $('#bulk_file_preview').css('display', 'flex');
                $('#bulk_file_btn').hide();
            }
        });

        $('#bulk_remove_file').on('click', function() {
            $('#bulk_salary_receipt').val('');
            $('#bulk_file_preview').hide();
            $('#bulk_file_btn').show();
        });

        // Confirmation Prompt
        $('#bulk_edit_form').on('submit', function(e) {
            var idsCount = $('#bulk_edit_ids').val().split(',').length;
            if (!confirm('¿Estás seguro de que deseas aplicar estos cambios masivamente a ' + idsCount + ' empleados? Esta acción no se puede deshacer fácilmente.')) {
                e.preventDefault();
            }
        });

        // Initialize select2 inside modal
        $('#bulk_edit_modal').on('shown.bs.modal', function () {
            $(this).find('.select2').select2({
                dropdownParent: $('#bulk_edit_modal')
            });
        });
    </script>
@stop
