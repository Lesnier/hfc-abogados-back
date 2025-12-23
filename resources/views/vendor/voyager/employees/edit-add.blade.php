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

                        <div class="panel-footer">
                            @section('submit-buttons')
                                <button type="submit" class="btn btn-primary save">{{ __('voyager::generic.save') }}</button>
                            @stop
                            @yield('submit-buttons')
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
                    } else {
                        toastr.error("Error removing file.");
                    }
                });

                $('#confirm_delete_modal').modal('hide');
            });
            $('[data-toggle="tooltip"]').tooltip();

            // Custom File Input Logic
            console.log("Searching for file inputs...");
            var $fileInputs = $('input[type="file"]');
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
