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
            margin-top: 5px;
        }
        .custom-file-input-wrapper input[type="file"] {
            display: none !important;
        }
        .btn-file-select {
            background-color: #e74c3c; /* Red color */
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }
        .btn-file-select:hover {
            background-color: #c0392b;
        }
        .file-preview-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
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
        /* CBU Validation Styles */
        .cbu-error {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: none;
            font-weight: bold;
        }
        .has-error .cbu-error {
            display: block;
        }
        .has-success .control-label:after {
            content: " \f00c";
            font-family: Voyager;
            color: #2ecc71;
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
                                    <label class="control-label" for="{{ $row->field }}">{{ $row->getTranslatedAttribute('display_name') }}</label>
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
                                    
                                    <!-- CBU Error Container -->
                                    @if($row->field == 'cbu_checking_account' || $row->field == 'sbu_checking_account')
                                        <div class="cbu-error" id="cbu-error-msg">El CBU ingresado no es válido para Argentina.</div>
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

        // CBU Validation Algorithm
        function validateCBU(cbu) {
            cbu = cbu.replace(/\D/g, ''); // Remove non-numeric
            if (cbu.length !== 22) return false;

            var block1 = cbu.substring(0, 8);
            var block2 = cbu.substring(8, 22);

            var w1 = [7, 1, 3, 9, 7, 1, 3];
            var w2 = [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3];

            // Verify Block 1
            var sum1 = 0;
            for (var i = 0; i < 7; i++) {
                sum1 += parseInt(block1[i]) * w1[i];
            }
            var mod1 = sum1 % 10;
            var diff1 = 10 - mod1;
            var v1 = diff1 === 10 ? 0 : diff1;

            if (v1 != parseInt(block1[7])) return false;

            // Verify Block 2
            var sum2 = 0;
            for (var i = 0; i < 13; i++) {
                sum2 += parseInt(block2[i]) * w2[i];
            }
            var mod2 = sum2 % 10;
            var diff2 = 10 - mod2;
            var v2 = diff2 === 10 ? 0 : diff2;

            if (v2 != parseInt(block2[13])) return false;

            return true;
        }

        $(document).ready(function () {
            console.log("Suppliers Edit-Add JS Loaded with CBU Validation");

            // Approval Status Logic
            @if(!$canEditStatus)
                var $statusSelect = $('[name="approval_status"]');
                var isAdd = {{ $add ? 'true' : 'false' }};
                $statusSelect.prop('disabled', true);
                var $hiddenStatus = $('<input>').attr({type: 'hidden', name: 'approval_status', value: $statusSelect.val()});
                $statusSelect.after($hiddenStatus);
                if (isAdd) { $statusSelect.val('Revisión').trigger('change'); $hiddenStatus.val('Revisión'); }
            @endif

            // CBU Field Validation
            var $cbuInput = $('input[name="cbu_checking_account"], input[name="sbu_checking_account"]');
            var $submitBtn = $('.btn.save');
            var $cbuError = $('#cbu-error-msg');
            var $formGroup = $cbuInput.closest('.form-group');

            function checkCbu() {
                var val = $cbuInput.val();
                
                // Sync to CBU if using SBU (for DB saving)
                if ($cbuInput.attr('name') === 'sbu_checking_account') {
                    var $hiddenCbu = $('input[name="cbu_checking_account"][type="hidden"]');
                    if ($hiddenCbu.length === 0) {
                        $hiddenCbu = $('<input>').attr({
                            type: 'hidden',
                            name: 'cbu_checking_account',
                            value: val
                        });
                        $('form').append($hiddenCbu);
                    } else {
                        $hiddenCbu.val(val);
                    }
                }

                if (!val) {
                     // If empty, not invalid (unless required, which HTML handled or backend)
                     $formGroup.removeClass('has-error has-success');
                     $cbuError.hide();
                     $submitBtn.prop('disabled', false);
                     return;
                }
                
                if (validateCBU(val)) {
                    $formGroup.removeClass('has-error').addClass('has-success');
                    $cbuError.hide();
                    $submitBtn.prop('disabled', false);
                } else {
                    $formGroup.removeClass('has-success').addClass('has-error');
                    $cbuError.show();
                    $submitBtn.prop('disabled', true);
                }
            }

            if ($cbuInput.length > 0) {
                // Check on load (if editing)
                checkCbu();
                
                // Check on input
                $cbuInput.on('input change keyup', function() {
                    checkCbu();
                });
            }

            $('.toggleswitch').bootstrapToggle();
            
            // Standard Datepicker Init
            $('.form-group input[type=date]').each(function (idx, elt) {
                if (elt.hasAttribute('data-datepicker')) {
                    elt.type = 'text';
                    $(elt).datetimepicker($(elt).data('datepicker'));
                } else if (elt.type != 'date') {
                    elt.type = 'text';
                    $(elt).datetimepicker({ format: 'L', extraFormats: [ 'YYYY-MM-DD' ] }).datetimepicker($(elt).data('datepicker'));
                }
            });

            @if ($isModelTranslatable)
                $('.side-body').multilingual({"editing": true});
            @endif

            $('.side-body input[data-slug-origin]').each(function(i, el) { $(el).slugify(); });
            $('.form-group').on('click', '.remove-multi-image', deleteHandler('img', true));
            $('.form-group').on('click', '.remove-single-image', deleteHandler('img', false));
            $('.form-group').on('click', '.remove-multi-file', deleteHandler('a', true));
            $('.form-group').on('click', '.remove-single-file', deleteHandler('a', false));

            $('#confirm_delete').on('click', function(){
                $.post('{{ route('voyager.'.$dataType->slug.'.media.remove') }}', params, function (response) {
                    if ( response && response.data && response.data.status && response.data.status == 200 ) {
                        toastr.success(response.data.message);
                        $file.parent().fadeOut(300, function() { $(this).remove(); })
                    } else {
                        toastr.error("Error removing file.");
                    }
                });
                $('#confirm_delete_modal').modal('hide');
            });
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
@stop
