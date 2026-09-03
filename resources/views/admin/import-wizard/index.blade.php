@extends('voyager::master')

@section('page_title', 'Importador Estandarizado')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-upload"></i> Importador Estandarizado
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-md-5">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3 class="panel-title">Nueva importación</h3></div>
                    <div class="panel-body">
                        <p class="help-block">
                            Subí el archivo en el formato estandarizado (plantilla con hojas
                            <code>companias</code>, <code>proveedores</code>, <code>empleados</code>,
                            <code>relacion_empleado_proveedor</code>). El sistema analiza todo primero
                            sin escribir nada en la base de datos — recién después de revisar y confirmar
                            se ejecuta.
                        </p>
                        <form action="{{ route('voyager.import-wizard.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label>Archivo (.xlsx)</label>
                                <input type="file" name="archivo" class="form-control" accept=".xlsx" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="voyager-upload"></i> Analizar archivo
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3 class="panel-title">Historial de importaciones</h3></div>
                    <div class="panel-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Archivo</th>
                                    <th>Estado</th>
                                    <th>Filas</th>
                                    <th>Pendientes</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($batches as $batch)
                                <tr>
                                    <td>{{ $batch->id }}</td>
                                    <td>{{ $batch->file_name }}</td>
                                    <td>
                                        <span class="label label-{{
                                            $batch->status === 'completed' ? 'success' :
                                            ($batch->status === 'completed_with_errors' ? 'warning' :
                                            ($batch->status === 'rolled_back' ? 'default' :
                                            ($batch->status === 'needs_review' ? 'info' : 'primary')))
                                        }}">{{ $batch->status }}</span>
                                    </td>
                                    <td>{{ $batch->total_rows }}</td>
                                    <td>{{ $batch->pending_rows }}</td>
                                    <td>{{ $batch->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('voyager.import-wizard.review', $batch->id) }}" class="btn btn-sm btn-info">Ver</a>
                                        @if(in_array($batch->status, ['completed', 'completed_with_errors']))
                                            <form action="{{ route('voyager.import-wizard.rollback', $batch->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Esto va a ELIMINAR los registros que este batch creó (no las actualizaciones). ¿Continuar?');">
                                                @csrf
                                                <button class="btn btn-sm btn-danger">Revertir</button>
                                            </form>
                                        @elseif(!in_array($batch->status, ['rolled_back']))
                                            <form action="{{ route('voyager.import-wizard.destroy', $batch->id) }}" method="POST" style="display:inline" onsubmit="return confirm('¿Descartar esta importación sin ejecutar?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-default">Descartar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7">Todavía no hay importaciones.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        {{ $batches->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
