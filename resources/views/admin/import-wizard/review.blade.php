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
        <div class="col-md-2">
            <div class="panel panel-bordered"><div class="panel-body text-center">
                <h2>{{ $batch->total_rows }}</h2><small>Filas totales</small>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-bordered" style="border-color:#5cb85c"><div class="panel-body text-center">
                <h2 style="color:#5cb85c">{{ $liveCounts['ok'] }}</h2><small>OK</small>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-bordered" style="border-color:#f0ad4e"><div class="panel-body text-center">
                <h2 style="color:#f0ad4e">{{ $liveCounts['warning'] }}</h2><small>Advertencias</small>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-bordered" style="border-color:#d9534f"><div class="panel-body text-center">
                <h2 style="color:#d9534f">{{ $liveCounts['needs_resolution'] }} / {{ $liveCounts['error'] }}</h2><small>Pendientes / Errores</small>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-bordered" style="border-color:#5bc0de"><div class="panel-body text-center">
                <h2 style="color:#5bc0de">{{ $liveCounts['imported'] }}</h2><small>Ya importadas</small>
            </div></div>
        </div>
    </div>

    @if($liveCounts['imported'] > 0 && $liveCounts['executable'] + $liveCounts['needs_resolution'] > 0)
    <div class="alert alert-info">
        <i class="voyager-info-circled"></i> Esta importación ya tiene <strong>{{ $liveCounts['imported'] }}</strong> fila(s) cargadas en el sistema. Las {{ $liveCounts['executable'] + $liveCounts['needs_resolution'] }} restantes están pendientes — podés resolverlas y ejecutar ahora, o dejarlas para otra sesión: este mismo batch va a seguir mostrando "Ejecutar" hasta que no quede nada por procesar.
    </div>
    @endif

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

    @if($liveCounts['executable'] > 0 || $liveCounts['needs_resolution'] > 0)
    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Ejecutar</h3></div>
        <div class="panel-body">
            <form action="{{ route('voyager.import-wizard.execute', $batch->id) }}" method="POST" onsubmit="return confirm('Esto va a escribir en la base de datos real. ¿Continuar?');">
                @csrf
                @if($liveCounts['needs_resolution'] > 0)
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="allow_unresolved" value="1">
                            Ejecutar de todas formas dejando SIN ASIGNAR los {{ $liveCounts['needs_resolution'] }} pendientes (quedan con la referencia vacía). Si no marcás esto, esas filas simplemente NO se importan ahora y quedan disponibles para resolver y ejecutar más adelante en este mismo batch.
                        </label>
                    </div>
                @endif
                <button type="submit" class="btn btn-success btn-lg" {{ $liveCounts['executable'] == 0 ? 'disabled' : '' }}>
                    <i class="voyager-check"></i> Ejecutar importación ({{ $liveCounts['executable'] }} filas listas)
                </button>
            </form>
        </div>
    </div>
    @endif

    @if($liveCounts['imported'] > 0)
    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Revertir</h3></div>
        <div class="panel-body">
            <p class="help-block">Elimina los <strong>{{ $liveCounts['imported'] }}</strong> registros que este batch ya CREÓ (no revierte actualizaciones sobre registros que ya existían).</p>
            <form action="{{ route('voyager.import-wizard.rollback', $batch->id) }}" method="POST" onsubmit="return confirm('Esto elimina los registros CREADOS por este batch (no revierte actualizaciones). ¿Continuar?');">
                @csrf
                <button class="btn btn-danger">Revertir lo ya importado</button>
            </form>
        </div>
    </div>
    @endif

    @if($liveCounts['executable'] == 0 && $liveCounts['needs_resolution'] == 0 && $liveCounts['imported'] == 0)
    <div class="panel panel-bordered">
        <div class="panel-body">
            <p>No queda nada por ejecutar en este batch (todo quedó en error, o ya se revirtió por completo).</p>
        </div>
    </div>
    @endif
</div>
@stop

@section('javascript')
<script>
    $(document).ready(function () {
        $('.select2').select2();
    });
</script>
@stop
