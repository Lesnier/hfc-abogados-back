@php
    $edit = !is_null($dataTypeContent->getKey());
    $add  = is_null($dataTypeContent->getKey());
    $user = auth()->user();
    $canEditStatus = $user->hasRole('admin') || $user->hasRole('tech_admin') || $user->hasRole('lawyer');
@endphp

@extends('voyager::master')

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        .pdf-icon-img {
            width: 20px;
            height: 20px;
            object-fit: contain;
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
        /* CUIL Validation Styles */
        .cuil-error {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: none;
            font-weight: normal;
        }
        .has-error .cuil-error {
            display: block;
        }
        .has-success .control-label:after {
         /*   content: " \f00c";*/
          /*  font-family: Voyager;*/
          /*  color: #2ecc71;*/
        }
    </style>
@stop

@section('page_title', __('voyager::generic.'.($edit ? 'edit' : 'add')).' '.$dataType->getTranslatedAttribute('display_name_singular'))

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ __('voyager::generic.'.($edit ? 'edit' : 'add')).' '.$dataType->getTranslatedAttribute('display_name_singular') }}
    </h1>
    @include('voyager::multilingual.language-selector')
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <!-- form start -->
                    <form role="form"
                            class="form-edit-add"
                            action="{{ $edit ? route('voyager.'.$dataType->slug.'.update', $dataTypeContent->getKey()) : route('voyager.'.$dataType->slug.'.store') }}"
                            method="POST" enctype="multipart/form-data">
                        <!-- PUT Method if we are editing -->
                        @if($edit)
                            {{ method_field("PUT") }}
                        @endif

                        <!-- CSRF TOKEN -->
                        {{ csrf_field() }}

                        <div class="panel-body">
                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <!-- Adding / Editing -->
                            @php
                                $dataTypeRows = $dataType->{($edit ? 'editRows' : 'addRows' )};
                            @endphp

                            @foreach($dataTypeRows as $row)
                                <!-- GET THE DISPLAY OPTIONS -->
                                @php
                                    $display_options = $row->details->display ?? NULL;
                                    if ($dataTypeContent->{$row->field.'_'.($edit ? 'edit' : 'add')}) {
                                        $dataTypeContent->{$row->field} = $dataTypeContent->{$row->field.'_'.($edit ? 'edit' : 'add')};
                                    }
                                @endphp
                                @if (isset($row->details->legend) && isset($row->details->legend->text))
                                    <legend class="text-{{ $row->details->legend->align ?? 'center' }}" style="background-color: {{ $row->details->legend->bgcolor ?? '#f0f0f0' }};padding: 5px;">{{ $row->details->legend->text }}</legend>
                                @endif

                                <div class="form-group @if($row->type == 'hidden') hidden @endif col-md-{{ $display_options->width ?? 12 }} {{ $errors->has($row->field) ? 'has-error' : '' }}" @if(isset($display_options->id)){{ "id=$display_options->id" }}@endif>
                                    {{ $row->slugify }}
                                    <label class="control-label" for="{{ $row->field }}">{{ $row->getTranslatedAttribute('display_name') }}
                                    </label>
                                    <!-- CUIL Error Container -->
                                    @if($row->field == 'cuil')
                                        <span style="display: inline" class="cuil-error" id="cuil-error-msg">Inválido.</span>
                                    @endif
                                    @include('voyager::multilingual.input-hidden-bread-edit-add')
                                    @if ($add && isset($row->details->view_add))
                                        @include($row->details->view_add, ['row' => $row, 'dataType' => $dataType, 'dataTypeContent' => $dataTypeContent, 'content' => $dataTypeContent->{$row->field}, 'view' => 'add', 'options' => $row->details])
                                    @elseif ($edit && isset($row->details->view_edit))
                                        @include($row->details->view_edit, ['row' => $row, 'dataType' => $dataType, 'dataTypeContent' => $dataTypeContent, 'content' => $dataTypeContent->{$row->field}, 'view' => 'edit', 'options' => $row->details])
                                    @elseif (isset($row->details->view))
                                        @include($row->details->view, ['row' => $row, 'dataType' => $dataType, 'dataTypeContent' => $dataTypeContent, 'content' => $dataTypeContent->{$row->field}, 'action' => ($edit ? 'edit' : 'add'), 'view' => ($edit ? 'edit' : 'add'), 'options' => $row->details])
                                    @elseif ($row->type == 'relationship')
                                        @include('voyager::formfields.relationship', ['options' => $row->details])
                                    @else
                                        {!! app('voyager')->formField($row, $dataType, $dataTypeContent) !!}
                                    @endif

                                    @foreach (app('voyager')->afterFormFields($row, $dataType, $dataTypeContent) as $after)
                                        {!! $after->handle($row, $dataType, $dataTypeContent) !!}
                                    @endforeach
                                    @if ($errors->has($row->field))
                                        @foreach ($errors->get($row->field) as $error)
                                            <span class="help-block">{{ $error }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach

                        </div><!-- panel-body -->
                        
                        {{-- Document Versioning Matrix Section --}}
                        @if($edit && $dataTypeContent->getKey() && (auth()->user()->hasRole('lawyer') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('tech_admin')))
                            <div class="panel-body" style="background: #f9f9f9; border-top: 1px solid #eee; padding-top: 25px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 class="panel-title" style="margin: 0;"><i class="voyager-documentation"></i> Historial de Documentos</h3>
                                    <button type="button" class="btn btn-success btn-new-version">
                                        <i class="voyager-plus"></i> Nueva Versión (Mes Actual)
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="doc-versions-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 100px;">Versión</th>
                                                <th style="width: 120px;">Fecha</th>
                                                <th>Form. 931</th>
                                                <th>Póliza</th>
                                                <th>Seg. Vida</th>
                                                <th>Recibo</th>
                                                <th>Repetición</th>
                                                <th>Indemnidad</th>
                                                <th>Anexo</th>
                                                <th>Baja ARCA</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $docTypes = [
                                                    'form_931', 'policy', 'life_insurance', 'salary_receipt', 
                                                    'repetition', 'indemnity', 'proof_discharge', 'arca_termination_form'
                                                ];
                                            @endphp
                                            @foreach($dataTypeContent->docVersions as $version)
                                                <tr data-version-id="{{ $version->id }}">
                                                    <td class="text-center"><strong>V{{ $version->version_number }}</strong></td>
                                                    <td>{{ $version->effective_date->format('d/m/Y') }}</td>
                                                    @foreach($docTypes as $type)
                                                        @php
                                                            $file = $version->files->where('doc_type', $type)->first();
                                                            $filePath = null;
                                                            if ($file && $file->file_path && $file->file_path != '[]') {
                                                                $json = json_decode($file->file_path, true);
                                                                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                                                                    if (count($json) > 0) {
                                                                        // Support both object structure and simple string array
                                                                        if (isset($json[0]['download_link'])) {
                                                                             $filePath = $json[0]['download_link'];
                                                                        } else {
                                                                             $filePath = $json[0];
                                                                        }
                                                                    }
                                                                } else {
                                                                    $filePath = $file->file_path; // Legacy string
                                                                }
                                                            }
                                                            
                                                            // Ensure file object exists for note ID if path is null
                                                            $fileId = $file ? $file->id : null;
                                                            $fileNote = $file ? $file->note : null;
                                                        @endphp
                                                        <td class="text-center" style="vertical-align: middle; padding: 5px;">
                                                            <div class="doc-slot" data-type="{{ $type }}" style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                                                                @if($filePath)
                                                                    <a href="{{ Storage::url($filePath) }}" target="_blank" class="btn btn-xs btn-info" style="margin: 0; padding: 2px 5px;" title="Ver Documento">
                                                                        <i class="voyager-eye"></i>
                                                                    </a>
                                                                    <input type="checkbox" class="approval-toggle" data-file-id="{{ $fileId }}" {{ $file->is_approved ? 'checked' : '' }} title="Aprobado?" style="margin: 0; transform: scale(1.2);">
                                                                @endif
                                                                
                                                                {{-- Note Button always visible if file record exists (created by version logic), or handling empty slot logic? --}}
                                                                {{-- Version logic creates records for all slots? No, createVersion loops existing files. Empty slots might not have rows in DocFile? --}}
                                                                {{-- If no DocFile row, we can't save note to it. BUT createVersion logic only creates if existing. --}}
                                                                {{-- Actually, createVersion clones. If a type didn't exist, no row. --}}
                                                                {{-- If we want to add note to empty, we need a DocFile record. --}}
                                                                {{-- For now, show btn only if $file exists (record exists, even if path empty? No, path empty usually implies no record unless cleared?) --}}
                                                                {{-- If user deletes file, record stays with null path? Let's check Observer. --}}
                                                                {{-- Observer uses updateOrCreate. So record exists. --}}
                                                                
                                                                @if($fileId)
                                                                    <button type="button" class="btn btn-xs {{ $fileNote ? 'btn-warning' : 'btn-default' }} btn-note" 
                                                                            data-file-id="{{ $fileId }}" 
                                                                            data-note="{{ $fileNote }}" 
                                                                            style="margin: 0; padding: 2px 5px;" title="Nota/Observación">
                                                                        <i class="voyager-edit"></i>
                                                                    </button>
                                                                @endif
                                                                
                                                                <button type="button" class="btn btn-xs btn-default btn-upload-doc" style="margin: 0; padding: 2px 5px;" title="Subir/Actualizar">
                                                                    <i class="voyager-upload"></i>
                                                                </button>
                                                                <input type="file" class="hidden-doc-input" style="display:none;" data-version-id="{{ $version->id }}" data-type="{{ $type }}">
                                                            </div>
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    // New Version
                                    $('.btn-new-version').click(function() {
                                        var btn = $(this);
                                        btn.prop('disabled', true).html('<i class="voyager-refresh"></i> Creando...');
                                        
                                        $.post('{{ route('voyager.doc-versioning.create-version', $dataTypeContent->id) }}', {
                                            _token: '{{ csrf_token() }}'
                                        })
                                        .done(function(res) {
                                            if(res.success) {
                                                toastr.success(res.message);
                                                location.reload(); // Reload to show new row
                                            } else {
                                                toastr.error('Error creando versión');
                                                btn.prop('disabled', false).html('<i class="voyager-plus"></i> Nueva Versión');
                                            }
                                        })
                                        .fail(function() {
                                            toastr.error('Error de servidor');
                                            btn.prop('disabled', false).html('<i class="voyager-plus"></i> Nueva Versión');
                                        });
                                    });

                                    // Trigger Upload
                                    $(document).on('click', '.btn-upload-doc', function() {
                                        $(this).siblings('input[type="file"]').click();
                                    });

                                    // Handle File Selection
                                    $(document).on('change', '.hidden-doc-input', function() {
                                        var input = this;
                                        var versionId = $(this).data('version-id');
                                        var docType = $(this).data('type');
                                        
                                        if (input.files && input.files[0]) {
                                            var formData = new FormData();
                                            formData.append('file', input.files[0]);
                                            formData.append('doc_type', docType);
                                            formData.append('_token', '{{ csrf_token() }}');

                                            var url = '{{ route('voyager.doc-versioning.upload-file', ['version' => '__ver__']) }}'.replace('__ver__', versionId);

                                            toastr.info('Subiendo archivo...');

                                            $.ajax({
                                                url: url,
                                                type: 'POST',
                                                data: formData,
                                                processData: false,
                                                contentType: false,
                                                success: function(res) {
                                                    if(res.success) {
                                                        toastr.success('Archivo subido correctamente');
                                                        location.reload(); // Reload to update UI state (simplest way)
                                                    } else {
                                                        toastr.error('Error subiendo archivo');
                                                    }
                                                },
                                                error: function() {
                                                    toastr.error('Error al subir archivo');
                                                }
                                            });
                                        }
                                    });

                                    // Toggle Approval
                                    $(document).on('change', '.approval-toggle', function() {
                                        var fileId = $(this).data('file-id');
                                        var url = '{{ route('voyager.doc-versioning.toggle-approval', ['file' => '__file__']) }}'.replace('__file__', fileId);
                                        
                                        $.post(url, { _token: '{{ csrf_token() }}' })
                                        .done(function(res) {
                                            if(res.success) {
                                                toastr.success('Estado actualizado');
                                            }
                                        });
                                    });
                                });
                            </script>
                        @endif

                        <div class="panel-footer">
                            @section('submit-buttons')
                                <button type="submit" class="btn btn-primary save">{{ __('voyager::generic.save') }}</button>
                            @stop
                            @yield('submit-buttons')
                            
                            <!-- Checkbox Notificar -->
                            @if(auth()->user()->hasRole('lawyer') || auth()->user()->hasRole('admin'))
                                <div style="display: inline-block; margin-left: 20px; vertical-align: middle;">
                                    <label style="cursor: pointer; font-weight: bold; margin: 0;">
                                        <input type="checkbox" name="notify_supplier" value="1" style="transform: scale(1.2); margin-right: 5px;">
                                        Notificar por correo
                                    </label>
                                </div>
                            @endif
                        </div>
                    </form>

                    <div style="display:none">
                        <input type="hidden" id="upload_url" value="{{ route('voyager.upload') }}">
                        <input type="hidden" id="upload_type_slug" value="{{ $dataType->slug }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Note Modal -->
    <div class="modal fade" id="note_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Nota / Observación</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="note_file_id">
                    <div class="form-group">
                        <label for="note_text">Escriba su observación (opcional):</label>
                        <textarea class="form-control" id="note_text" rows="5" placeholder="Ej: Documento borroso, falta firma..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning pull-left" id="btn_clear_note">Limpiar</button>
                    <button type="button" class="btn btn-primary" id="btn_save_note">Guardar Nota</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Replace included delete modal with inline to enable JS logic and avoid view errors -->
    <div class="modal fade modal-danger" id="confirm_delete_modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"
                            aria-hidden="true">&times;</button>
                    <h4 class="modal-title"><i class="voyager-warning"></i> {{ __('voyager::generic.are_you_sure') }}</h4>
                </div>
                <div class="modal-body">
                    <h4>{{ __('voyager::generic.are_you_sure_delete') }} '<span class="confirm_delete_name"></span>'</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="confirm_delete">{{ __('voyager::generic.delete_confirm') }}</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        var params = {};
        var $file;

        function deleteHandler(tag, isMulti) {
          return function() {
            $file = $(this).siblings(tag);

            params = {
                slug:   '{{ $dataType->slug }}',
                filename:  $file.data('file-name'),
                id:     $file.data('id'),
                field:  $file.parent().data('field-name'),
                multi: isMulti,
                _token: '{{ csrf_token() }}'
            }

            $('.confirm_delete_name').text(params.filename);
            $('#confirm_delete_modal').modal('show');
          };
        }
        
        // CUIL Validation Algorithm
        function validateCUIL(cuil) {
            cuil = cuil.replace(/\D/g, ''); // Remove non-numeric
            if (cuil.length !== 11) return false;

            var weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            var sum = 0;

            for (var i = 0; i < 10; i++) {
                sum += parseInt(cuil[i]) * weights[i];
            }

            var mod = sum % 11;
            var verifier = 11 - mod;

            if (verifier === 11) verifier = 0;
            if (verifier === 10) verifier = 9;

            return verifier === parseInt(cuil[10]);
        }

        $(document).ready(function () {
            console.log("Employees Edit-Add JS Loaded with CUIL Validation");

             // JS Logic for Note Modal
             $(document).on('click', '.btn-note', function() {
                 var fileId = $(this).data('file-id');
                 var currentNote = $(this).attr('data-note') || '';
                 
                 $('#note_file_id').val(fileId);
                 $('#note_text').val(currentNote);
                 $('#note_modal').modal('show');
             });

             $('#btn_save_note').click(function() {
                 var fileId = $('#note_file_id').val();
                 var note = $('#note_text').val();
                 var url = '{{ route('voyager.doc-versioning.save-note', ['file' => '__file__']) }}'.replace('__file__', fileId);

                 // Disable button
                 var $btnSave = $(this);
                 $btnSave.prop('disabled', true);

                 $.post(url, { _token: '{{ csrf_token() }}', note: note })
                  .done(function(res) {
                      if(res.success) {
                          toastr.success('Nota guardada');
                          // Update button state (e.g., color)
                          var $btn = $('.btn-note[data-file-id="' + fileId + '"]');
                          $btn.attr('data-note', note);
                          if (note && note.trim().length > 0) {
                              $btn.removeClass('btn-default').addClass('btn-warning');
                          } else {
                              $btn.removeClass('btn-warning').addClass('btn-default');
                          }
                          $('#note_modal').modal('hide');
                      }
                  })
                  .always(function(){
                      $btnSave.prop('disabled', false);
                  });
             });

             $('#btn_clear_note').click(function() {
                 $('#note_text').val('');
             });

            // Approval Status Restriction Logic
            @if(!$canEditStatus)
                var $statusSelect = $('[name="approval_status"]');
                var isAdd = {{ $add ? 'true' : 'false' }};
                
                // Disable the field
                $statusSelect.prop('disabled', true);
                // Also create a hidden input to submit the value, since disabled fields aren't submitted
                var $hiddenStatus = $('<input>').attr({
                    type: 'hidden',
                    name: 'approval_status',
                    value: $statusSelect.val()
                });
                $statusSelect.after($hiddenStatus);

                if (isAdd) {
                    // Default to 'Revisión'
                    $statusSelect.val('Revisión').trigger('change');
                    $hiddenStatus.val('Revisión');
                }
            @endif

            // CUIL Field Validation
            var $cuilInput = $('input[name="cuil"]');
            var $submitBtn = $('.btn.save');
            var $cuilError = $('#cuil-error-msg');
            var $formGroup = $cuilInput.closest('.form-group');

            function checkCuil() {
                var val = $cuilInput.val();
                if (!val) {
                     // If empty, not invalid logic (unless required)
                     $formGroup.removeClass('has-error has-success');
                     $cuilError.hide();
                     $submitBtn.prop('disabled', false);
                     return;
                }
                
                if (validateCUIL(val)) {
                    $formGroup.removeClass('has-error').addClass('has-success');
                    $cuilError.hide();
                    $submitBtn.prop('disabled', false);
                } else {
                    $formGroup.removeClass('has-success').addClass('has-error');
                    $cuilError.show();
                    $submitBtn.prop('disabled', true);
                }
            }

            if ($cuilInput.length > 0) {
                 checkCuil();
                 $cuilInput.on('input change keyup', function() {
                    checkCuil();
                 });
            }

            // Conditional Logic: Show ARCA Termination Form only if Status is 'Baja'
            // Conditional Logic: Show ARCA Termination Form only if Status is 'Baja'
            function toggleTerminationForm() {
                var $status = $('[name="approval_status"]');
                // Use contains selector or exact match for array syntax
                var $tInput = $('input[name="arca_termination_form[]"]'); 
                
                // Fallback attempt if exact match fails
                if ($tInput.length === 0) {
                     $tInput = $('input[name^="arca_termination_form"]');
                }

                var $tGroup = $tInput.closest('.form-group');

                if ($tInput.length === 0) {
                     console.warn('ARCA Termination Form input not found');
                     return;
                }

                // If $status is a collection, val() returns value of first element.
                // For select, this is usually correct.
                if ($status.val() === 'Baja') {
                    $tGroup.show();
                } else {
                    $tGroup.hide();
                }
            }

            // Initial check
            toggleTerminationForm();

            // Bind change (standard and select2)
            $(document).on('change select2:select', '[name="approval_status"]', function() {
                toggleTerminationForm();
            });

            $('.toggleswitch').bootstrapToggle();
            
            //Init datepicker for date fields if data-datepicker attribute defined
            //or if browser does not handle date inputs
            $('.form-group input[type=date]').each(function (idx, elt) {
                if (elt.hasAttribute('data-datepicker')) {
                    elt.type = 'text';
                    $(elt).datetimepicker($(elt).data('datepicker'));
                } else if (elt.type != 'date') {
                    elt.type = 'text';
                    $(elt).datetimepicker({
                        format: 'L',
                        extraFormats: [ 'YYYY-MM-DD' ]
                    }).datetimepicker($(elt).data('datepicker'));
                }
            });

            @if ($isModelTranslatable)
                $('.side-body').multilingual({"editing": true});
            @endif

            $('.side-body input[data-slug-origin]').each(function(i, el) {
                $(el).slugify();
            });

            $('.form-group').on('click', '.remove-multi-image', deleteHandler('img', true));
            $('.form-group').on('click', '.remove-single-image', deleteHandler('img', false));
            $('.form-group').on('click', '.remove-multi-file', deleteHandler('a', true));
            $('.form-group').on('click', '.remove-single-file', deleteHandler('a', false));

            $('#confirm_delete').on('click', function(){
                $.post('{{ route('voyager.'.$dataType->slug.'.media.remove') }}', params, function (response) {
                    if ( response
                        && response.data
                        && response.data.status
                        && response.data.status == 200 ) {

                        toastr.success(response.data.message);
                        // Reset Custom UI if exists
                        var $customWrapper = $file.parent().closest('.form-group').find('.custom-file-input-wrapper');
                        if ($customWrapper.length > 0) {
                             $customWrapper.find('.file-preview-info').hide();
                             $customWrapper.find('.btn-file-select').show();
                             $customWrapper.find('input[type="file"]').val('');
                        }
                        $file.parent().fadeOut(300, function() { $(this).remove(); })
                        
                        // Update Document Matrix UI (Latest Version Row)
                        console.log("Syncing delete to matrix for field: " + params.field);
                        var $matrixSlot = $('#doc-versions-table tbody tr:first .doc-slot[data-type="' + params.field + '"]');
                        if ($matrixSlot.length > 0) {
                            $matrixSlot.find('a.btn-info').remove();          // Remove View Button
                            $matrixSlot.find('input.approval-toggle').remove(); // Remove Checkbox
                            $matrixSlot.find('.btn-note').remove();             // Remove Note Button
                            // Keep upload button and hidden input
                        }
                    } else {
                        toastr.error("Error removing file.");
                    }
                });

                $('#confirm_delete_modal').modal('hide');
            });
            $('[data-toggle="tooltip"]').tooltip();

            // Custom File Input Logic
            console.log("Searching for file inputs...");
            var $fileInputs = $('input[type="file"]').not('.hidden-doc-input');
            console.log("Found " + $fileInputs.length + " file inputs.");
            
            $fileInputs.each(function() {
                var $input = $(this);
                // Check if already processed
                if ($input.closest('.custom-file-input-wrapper').length > 0) return;

                // Voyager structure: The input is usually inside a form-group. 
                // Existing files are usually in a div or directly as siblings. 
                // User says: <div data-field-name="..."><a ...></a><a class="voyager-x ..."></a></div>
                // The input might be a sibling of that div, or inside the form group.
                
                var $formGroup = $input.closest('.form-group');
                var $existingFileContainer = $formGroup.find('[data-field-name]');
                var $existingLink = $existingFileContainer.find('a').not('.voyager-x'); // The file link
                var $existingRemoveBtn = $existingFileContainer.find('.voyager-x');    // The remove X link
                
                var existingFileName = '';
                
                // If we found an existing file link
                if ($existingLink.length > 0) {
                    existingFileName = $existingLink.data('file-name') || $existingLink.text();
                    // Hide original Voyager elements so our UI takes over
                    $existingFileContainer.hide(); 
                }

                // Create UI elements
                var $wrapper = $('<div class="custom-file-input-wrapper"></div>');
                var $btn = $('<button type="button" class="btn-file-select"><i class="voyager-upload"></i> Seleccionar Archivo</button>');
                var $preview = $('<div class="file-preview-info" style="display:none;">' +
                                    '<i class="voyager-file-text" style="font-size: 16px; color: #e74c3c;margin-top: 4px;"></i>' +
                                    '<span class="file-name" style="width: 100%;"></span>' +
                                    '<i class="voyager-x remove-file-btn" title="Quitar"></i>' +
                                 '</div>');
                
                // Insert wrapper before input, then move input inside
                $input.before($wrapper);
                $wrapper.append($input);
                $wrapper.append($btn);
                $wrapper.append($preview);

                function truncateFileName(name) {
                    if (name.length > 20) {
                        return name.substring(0, 20) + '...';
                    }
                    return name;
                }

                // Initial State: If existing file found
                if (existingFileName) {
                    $wrapper.find('.file-name').text(truncateFileName(existingFileName)).attr('title', existingFileName);
                    $preview.css('display', 'flex');
                    $btn.hide();
                }

                // Events
                $btn.on('click', function() {
                    $input.trigger('click');
                });

                $input.on('change', function() {
                    var file = this.files[0];
                    if (file) {
                        $wrapper.find('.file-name').text(truncateFileName(file.name)).attr('title', file.name);
                        $preview.css('display', 'flex');
                        $btn.hide(); 
                    }
                });

                $wrapper.on('click', '.remove-file-btn', function() {
                    if ($input.val()) {
                        // Case A: User selected a NEW file, and wants to clear it.
                        // Just clear input and return to start state
                        $input.val(''); 
                        // If there was an existing file before, we might want to show it again?
                        // For simplicity, let's just show "Select File".
                        // Logic: If we clear new file, and there is an existing file hidden...
                        // If we show "Select File", and they submit, the existing file remains in DB (Voyager logic).
                        // So showing "Select File" is fine, effectively "Undo new selection".
                    } 
                    
                    // Case B: There is an existing file (and no new file selected, or we just cleared it)
                    // and the user wants to remove the EXISTING file.
                    // Wait, if we are in state "New File Selected", clicking X should just clear new file.
                    // If we are in state "Existing File Shown", clicking X should TRIGGER REMOVE of existing file.
                    
                    if (existingFileName && !$input.val()) {
                         // We are showing the existing file. Pass the click to the real remove button.
                         if ($existingRemoveBtn.length > 0) {
                             $existingRemoveBtn[0].click(); // Trigger native DOM click to be safe or jQuery click
                         } else {
                             console.log("Original remove button not found");
                         }
                    } else {
                        // Just clearing the UI
                        $preview.hide();
                        $btn.show();
                        // If there was an existing file, we hidden it. 
                        // Should we show it? No, because "Select File" implies replacing it?
                        // Actually if we clear the NEW selection, we probably should revert to showing the OLD selection if it wasn't deleted?
                        // But verifying that "Undo" logic is complex.
                        // Let's assume standard behavior: clear UI = ready for new input.
                        // But if there WAS an existing file, and we reset, the user might see "Select File" and think "Empty". 
                        // If they submit, Voyager usually keeps the old file if input is empty.
                        // To allow DELETING the old file, they need to see the "Existing File" UI.
                        
                        if (existingFileName) {
                            // If we just cleared a new input, let's restore the view of the existing file
                            // so they have the option to delete it if they want.
                             $wrapper.find('.file-name').text(existingFileName);
                             $preview.css('display', 'flex');
                             $btn.hide();
                        }
                    }
                });
            });
        });
    </script>
@stop
