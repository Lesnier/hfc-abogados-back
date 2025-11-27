@extends('voyager::master')

@section('page_title', 'Verificación de Acceso')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-key"></i>
        {{ 'Verificación de Acceso' }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        {{-- Incluimos las alertas de Voyager (ej. "ID Verificado") --}}
        @include('voyager::alerts')

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title">Verificar Identificación</h3>
                    </div>
                    <div class="panel-body">

                        {{-- El formulario apunta a nuestra ruta POST --}}
                        <form action="{{ route('voyager.security.check') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="id_number">Número de Identificación</label>
                                <input type="text" class="form-control" name="id_number" id="id_number"
                                       placeholder="Ingrese el ID o documento..." required>
                            </div>
                            <button type="submit" class="btn btn-primary">Verificar</button>
                        </form>

                        @if(isset($employee))

                            {{-- Mostramos los detalles del empleado que vino de la sesión en estado aprovado--}}
                            @if($employee->approval_status === 'Aprobado')
                                <hr>
                                <h4>Resultados de la Verificación: <strong> {{$employee->approval_status}} <i class="voyager-check-circle"></i></strong></h4>
                                <div class="alert alert-success" style="background-color: #dff0d8; border-color: #d6e9c6; color: #3c763d;">
                                    <p><strong><i class="voyager-person"></i> Nombre:</strong> {{ $employee->name }}</p>
                                    <p><strong><i class="voyager-credit-card"></i> Identificación:</strong> {{ $employee->identification }}</p>
                                    <p><strong><i class="voyager-news"></i> CUIL:</strong> {{ $employee->cuil }}</p>
                                    <p><strong><i class="voyager-forward"></i> Condición:</strong> {{ $employee->condition }}</p>
                                    <p><strong><i class="voyager-calendar"></i> Vencimieto:</strong> {{ $employee->validity_to }}</p>
                                    <p><strong><i class="voyager-check-circle"></i> Estatus:</strong> {{ $employee->approval_status }}</p>
                                </div>
                            @endif
                            {{-- Mostramos los detalles del empleado que vino de la sesión en estado revisión--}}
                            @if($employee->approval_status === 'Revisión')
                                <hr>
                                <h4>Resultados de la Verificación: <strong> {{$employee->approval_status}} <i class="voyager-warning "></i></strong></h4>
                                <div class="alert alert-warning" style="background-color: #fff3cd; border-color: #ffe69c; color: #664d03;">
                                    <p><strong><i class="voyager-person"></i> Nombre:</strong> {{ $employee->name }}</p>
                                    <p><strong><i class="voyager-credit-card"></i> Identificación:</strong> {{ $employee->identification }}</p>
                                    <p><strong><i class="voyager-news"></i> CUIL:</strong> {{ $employee->cuil }}</p>
                                    <p><strong><i class="voyager-forward"></i> Condición:</strong> {{ $employee->condition }}</p>
                                    <p><strong><i class="voyager-calendar"></i> Vencimieto:</strong> {{ $employee->validity_to }}</p>
                                    <p><strong><i class="voyager-warning"></i> Estatus:</strong> {{ $employee->approval_status }}</p>
                                </div>
                            @endif
                            {{-- Mostramos los detalles del empleado que vino de la sesión en estado rechazado--}}
                            @if($employee->approval_status === 'Rechazado')
                                <hr>
                                <h4>Resultados de la Verificación: <strong> {{$employee->approval_status}} <i class="voyager-x"></i></strong></h4>
                                <div class="alert alert-error" style="background-color: #f8d7da; border-color: #f1aeb5; color: #58151c;">
                                    <p><strong><i class="voyager-person"></i> Nombre:</strong> {{ $employee->name }}</p>
                                    <p><strong><i class="voyager-credit-card"></i> Identificación:</strong> {{ $employee->identification }}</p>
                                    <p><strong><i class="voyager-news"></i> CUIL:</strong> {{ $employee->cuil }}</p>
                                    <p><strong><i class="voyager-forward"></i> Condición:</strong> {{ $employee->condition }}</p>
                                    <p><strong><i class="voyager-calendar"></i> Vencimieto:</strong> {{ $employee->validity_to }}</p>
                                    <p><strong><i class="voyager-x"></i> Estatus:</strong> {{ $employee->approval_status }}</p>
                                </div>
                            @endif

                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

