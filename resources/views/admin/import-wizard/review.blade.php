@extends('voyager::master')

@section('page_title', 'Revisión de Importación #' . $batch->id)

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-list"></i> Revisión de Importación #{{ $batch->id }} — {{ $batch->file_name }}
        </h1>
        <a href="{{ route('voyager.import-wizard.index') }}" class="btn btn-default btn-add-new">
            <i class="voyager-list"></i> Volver al historial
        </a>
    </div>
@stop

@section('content')
<div class="page-content browse container-fluid">
    @include('voyager::alerts')

    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-bordered"><div class="panel-body text-center">
                <h2>{{ $batch->total_rows }}</h2><small>Filas totales</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-bordered" style="border-color:#5cb85c"><div class="panel-body text-center">
                <h2 style="color:#5cb85c">{{ $batch->ok_rows }}</h2><small>OK</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-bordered" style="border-color:#f0ad4e"><div class="panel-body text-center">
                <h2 style="color:#f0ad4e">{{ $batch->warning_rows }}</h2><small>Advertencias</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-bordered" style="border-color:#d9534f"><div class="panel-body text-center">
                <h2 style="color:#d9534f">{{ $batch->pending_rows }} / {{ $batch->error_rows }}</h2><small>Pendientes / Errores</small>
            </div></div>
        </div>
    </div>

    @if(count($pendingGroups) > 0)
    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Referencias sin resolver ({{ count($pendingGroups) }} grupos)</h3></div>
        <div class="panel-body">
            <p class="help-block">Cada tarjeta agrupa filas que comparten el mismo valor sin resolver. Elegí el registro real y aplicá — resuelve todas las filas del grupo de una vez.</p>
            @foreach($pendingGroups as $group)
            <div class="well" style="margin-bottom:15px;">
                <strong>{{ $group['entity_label'] }} → {{ $group['field_label'] }}</strong>
                <span class="label label-warning">{{ $group['count'] }} filas</span>
                <br>
                <small>Valor en el archivo: <code>{{ $group['raw_value'] }}</code></small><br>
                <small>
                    Ejemplos: {{ implode(', ', $group['samples']) }}
                    @if($group['count'] > count($group['samples']))
                        y {{ $group['count'] - count($group['samples']) }} más
                    @endif
                </small>

                <form action="{{ route('voyager.import-wizard.resolve-group', $batch->id) }}" method="POST" class="form-inline" style="margin-top:10px;">
                    @csrf
                    <input type="hidden" name="field" value="{{ $group['field'] }}">
                    @foreach($group['row_ids'] as $rid)
                        <input type="hidden" name="row_ids[]" value="{{ $rid }}">
                    @endforeach

                    <select name="local_id" class="form-control select2" style="min-width:300px" required>
                        <option value="">-- Elegir {{ $group['field_label'] }} --</option>
                        @if(isset($group['text_match_model']))
                            @php $textMatchModel = $group['text_match_model']; @endphp
                            @foreach($textMatchModel::orderBy($group['text_match_field'])->get() as $opt)
                                <option value="{{ $opt->id }}">{{ $opt->{$group['text_match_field']} }}</option>
                            @endforeach
                        @else
                            @foreach($parentLists[$group['parent_entity']] as $opt)
                                <option value="{{ $opt->id }}">{{ $opt->name }} ({{ $opt->identification }})</option>
                            @endforeach
                        @endif
                    </select>
                    <button type="submit" class="btn btn-primary">Aplicar a las {{ $group['count'] }} filas</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(count($duplicates) > 0)
    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Identificaciones duplicadas dentro del archivo</h3></div>
        <div class="panel-body">
            <p class="help-block">
                Si continuás sin corregir, la fila con número mayor sobrescribirá a la anterior (no se descarta ninguna, pero solo queda un registro con esa identificación).
                Podés descargar el detalle, corregirlo en Excel y volver a subir el archivo corregido (o solo esas filas en un archivo aparte, si esta importación todavía no se ejecutó).
            </p>
            @foreach($duplicates as $slug => $info)
                <p>
                    <strong>{{ $info['label'] }}</strong>: {{ $info['count'] }} filas en conflicto —
                    <a href="{{ route('voyager.import-wizard.duplicates', [$batch->id, $slug]) }}" class="btn btn-xs btn-warning">
                        <i class="voyager-download"></i> Descargar reporte de duplicados
                    </a>
                </p>
            @endforeach
        </div>
    </div>
    @endif

    @if(count($errorSamples) > 0)
    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Errores bloqueantes (muestra)</h3></div>
        <div class="panel-body">
            @foreach($errorSamples as $slug => $info)
                <strong>{{ $info['label'] }}</strong>
                <ul>
                @foreach($info['rows'] as $row)
                    <li>Fila {{ $row->row_number }}: {{ implode(' | ', $row->notes ?? []) }}</li>
                @endforeach
                </ul>
            @endforeach
        </div>
    </div>
    @endif

    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Ejecutar</h3></div>
        <div class="panel-body">
            @if(in_array($batch->status, ['completed', 'completed_with_errors', 'rolled_back']))
                <p>Este batch ya fue procesado (estado: <strong>{{ $batch->status }}</strong>).</p>
                @if(in_array($batch->status, ['completed', 'completed_with_errors']))
                    <form action="{{ route('voyager.import-wizard.rollback', $batch->id) }}" method="POST" onsubmit="return confirm('Esto elimina los registros CREADOS por este batch (no revierte actualizaciones). ¿Continuar?');">
                        @csrf
                        <button class="btn btn-danger">Revertir importación (para volver a probar)</button>
                    </form>
                @endif
            @else
                <form action="{{ route('voyager.import-wizard.execute', $batch->id) }}" method="POST" onsubmit="return confirm('Esto va a escribir en la base de datos real. ¿Continuar?');">
                    @csrf
                    @if($batch->pending_rows > 0)
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="allow_unresolved" value="1">
                                Ejecutar de todas formas dejando SIN ASIGNAR los {{ $batch->pending_rows }} pendientes (quedan con la referencia vacía).
                            </label>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-success btn-lg" {{ $batch->pending_rows > 0 ? '' : '' }}>
                        <i class="voyager-check"></i> Ejecutar importación ({{ $batch->ok_rows + $batch->warning_rows }} filas listas)
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@stop

@section('javascript')
<script>
    $(document).ready(function () {
        $('.select2').select2();
    });
</script>
@stop
