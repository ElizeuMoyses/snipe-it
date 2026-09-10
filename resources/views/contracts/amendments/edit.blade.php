@extends('layouts/default')

{{-- Page title --}}
@section('title')
    @if ($item->id)
        {{ trans('admin/contracts/general.update_amendment') }}
    @else
        {{ trans('admin/contracts/general.create_amendment') }}
    @endif
    @parent
@stop

{{-- Page content --}}
@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <form method="POST"
              action="{{ $item->id
                  ? route('contracts.amendments.update', [$contract->id, $item->id])
                  : route('contracts.amendments.store', $contract->id) }}"
              id="amendment-form">
            @csrf
            @if (! $item->id)
                <input type="hidden" name="preview_token" id="preview_token" value="">
            @endif
            @if ($item->id)
                @method('PUT')
            @endif

            <div class="box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title">
                        {{ $item->id
                            ? trans('admin/contracts/general.update_amendment')
                            : trans('admin/contracts/general.create_amendment') }}
                        &mdash; {{ $contract->name }}
                    </h2>
                </div>

                <div class="box-body">

                    <p class="alert alert-info">{{ trans('admin/contracts/general.immediate_effects_help') }}</p>
                    <div class="form-group">
                        <label for="rectifies_amendment_id" class="col-md-3 control-label">{{ trans('admin/contracts/general.rectification_of') }}</label>
                        <div class="col-md-7">
                            <select name="rectifies_amendment_id" id="rectifies_amendment_id" class="form-control" {{ $item->id ? 'disabled' : '' }}>
                                <option value="">{{ trans('admin/contracts/general.rectification_none') }}</option>
                                @foreach($contract->amendments()->orderByDesc('id')->get() as $previousAmendment)
                                    @if($previousAmendment->id !== $item->id)
                                        <option value="{{ $previousAmendment->id }}" {{ (string) old('rectifies_amendment_id', $item->rectifies_amendment_id) === (string) $previousAmendment->id ? 'selected' : '' }}>#{{ $previousAmendment->id }} — {{ \Illuminate\Support\Str::limit($previousAmendment->description, 70) }}</option>
                                    @endif
                                @endforeach
                            </select>
                            {!! $errors->first('rectifies_amendment_id', '<span class="alert-msg">:message</span>') !!}
                            <span id="preview-error-rectifies_amendment_id" class="alert-msg preview-field-error" role="alert"></span>
                        </div>
                    </div>

                    {{-- Amendment Type --}}
                    <div class="form-group {{ $errors->has('amendment_type') ? 'error' : '' }}">
                        <label for="amendment_type" class="col-md-3 control-label">
                            {{ trans('admin/contracts/general.amendment_type') }} <span class="text-danger">*</span>
                        </label>
                        <div class="col-md-7">
                            <select name="amendment_type" id="amendment_type" class="form-control"
                                    {{ $item->id ? 'disabled' : '' }}>
                                <option value="">{{ trans('general.select') }}</option>
                                @foreach (['readjustment', 'scope_change', 'renewal', 'termination'] as $type)
                                    <option value="{{ $type }}"
                                        {{ old('amendment_type', $item->amendment_type) === $type ? 'selected' : '' }}>
                                        {{ trans('admin/contracts/general.amendment_type_' . $type) }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($item->id)
                                <input type="hidden" name="amendment_type" value="{{ $item->amendment_type }}">
                            @endif
                            {!! $errors->first('amendment_type', '<span class="alert-msg">:message</span>') !!}
                            <span id="preview-error-amendment_type" class="alert-msg preview-field-error" role="alert"></span>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="form-group {{ $errors->has('description') ? 'error' : '' }}">
                        <label for="description" class="col-md-3 control-label">
                            {{ trans('admin/contracts/general.description') }} <span class="text-danger">*</span>
                        </label>
                        <div class="col-md-7">
                            <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea>
                            {!! $errors->first('description', '<span class="alert-msg">:message</span>') !!}
                            <span id="preview-error-description" class="alert-msg preview-field-error" role="alert"></span>
                        </div>
                    </div>

                    {{-- Effective Date --}}
                    <div class="form-group {{ $errors->has('effective_date') ? 'error' : '' }}">
                        <label for="effective_date" class="col-md-3 control-label">
                            {{ trans('admin/contracts/general.effective_date') }} <span class="text-danger">*</span>
                        </label>
                        <div class="col-md-7">
                            <input type="date" name="effective_date" id="effective_date" class="form-control"
                                   value="{{ old('effective_date', $item->effective_date?->format('Y-m-d')) }}"
                                   {{ $item->id ? 'readonly' : '' }}>
                            {!! $errors->first('effective_date', '<span class="alert-msg">:message</span>') !!}
                            <span id="preview-error-effective_date" class="alert-msg preview-field-error" role="alert"></span>
                        </div>
                    </div>

                    {{-- Readjustment fields --}}
                    <div id="readjustment-fields" style="display:none;">
                        <div class="form-group {{ $errors->has('old_value') ? 'error' : '' }}">
                            <label for="old_value" class="col-md-3 control-label">
                                {{ trans('admin/contracts/general.old_value') }}
                            </label>
                            <div class="col-md-7">
                                 <input type="number" name="old_value" id="old_value" class="form-control" step="0.01"
                                        value="{{ old('old_value', $item->old_value ?? $contract->installment_value) }}"
                                        readonly>
                                 <span class="help-block">{{ trans('admin/contracts/message.amendment.preview_details.server_snapshot_help') }}</span>
                                 {!! $errors->first('old_value', '<span class="alert-msg">:message</span>') !!}
                                 <span id="preview-error-old_value" class="alert-msg preview-field-error" role="alert"></span>
                             </div>
                        </div>
                        <div class="form-group {{ $errors->has('new_value') ? 'error' : '' }}">
                            <label for="new_value" class="col-md-3 control-label">
                                {{ trans('admin/contracts/general.new_value') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-7">
                                <input type="number" name="new_value" id="new_value" class="form-control" step="0.01"
                                       value="{{ old('new_value', $item->new_value) }}"
                                       {{ $item->id ? 'readonly' : '' }}>
                                {!! $errors->first('new_value', '<span class="alert-msg">:message</span>') !!}
                                <span id="preview-error-new_value" class="alert-msg preview-field-error" role="alert"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Renewal fields --}}
                    <div id="renewal-fields" style="display:none;">
                        <div class="form-group {{ $errors->has('old_end_date') ? 'error' : '' }}">
                            <label for="old_end_date" class="col-md-3 control-label">
                                {{ trans('admin/contracts/general.old_end_date') }}
                            </label>
                            <div class="col-md-7">
                                <input type="date" name="old_end_date" id="old_end_date" class="form-control"
                                       value="{{ old('old_end_date', $item->old_end_date?->format('Y-m-d') ?? $contract->end_date?->format('Y-m-d')) }}"
                                       readonly>
                                {!! $errors->first('old_end_date', '<span class="alert-msg">:message</span>') !!}
                                <span id="preview-error-old_end_date" class="alert-msg preview-field-error" role="alert"></span>
                            </div>
                        </div>
                        <div class="form-group {{ $errors->has('new_end_date') ? 'error' : '' }}">
                            <label for="new_end_date" class="col-md-3 control-label">
                                {{ trans('admin/contracts/general.new_end_date') }}
                            </label>
                            <div class="col-md-7">
                                <input type="date" name="new_end_date" id="new_end_date" class="form-control"
                                       value="{{ old('new_end_date', $item->new_end_date?->format('Y-m-d')) }}"
                                       {{ $item->id ? 'readonly' : '' }}>
                                {!! $errors->first('new_end_date', '<span class="alert-msg">:message</span>') !!}
                                <span id="preview-error-new_end_date" class="alert-msg preview-field-error" role="alert"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Ticket Reference --}}
                    <div class="form-group {{ $errors->has('ticket_reference') ? 'error' : '' }}">
                        <label for="ticket_reference" class="col-md-3 control-label">
                            {{ trans('admin/contracts/general.ticket_reference') }}
                        </label>
                        <div class="col-md-7">
                            <input type="text" name="ticket_reference" id="ticket_reference" class="form-control" maxlength="100"
                                   value="{{ old('ticket_reference', $item->ticket_reference) }}">
                            {!! $errors->first('ticket_reference', '<span class="alert-msg">:message</span>') !!}
                            <span id="preview-error-ticket_reference" class="alert-msg preview-field-error" role="alert"></span>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="form-group {{ $errors->has('notes') ? 'error' : '' }}">
                        <label for="notes" class="col-md-3 control-label">
                            {{ trans('general.notes') }}
                        </label>
                        <div class="col-md-7">
                            <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $item->notes) }}</textarea>
                            {!! $errors->first('notes', '<span class="alert-msg">:message</span>') !!}
                            <span id="preview-error-notes" class="alert-msg preview-field-error" role="alert"></span>
                        </div>
                    </div>

                </div>{{-- /.box-body --}}

                <div class="box-footer text-right">
                    <a class="btn btn-link" href="{{ route('contracts.show', $contract->id) }}#amendments">
                        {{ trans('button.cancel') }}
                    </a>
                    @if (! $item->id)
                        {{-- Creation: trigger preview first --}}
                        <button type="button" id="btn-preview" class="btn btn-primary">
                            <i class="fas fa-check icon-white" aria-hidden="true"></i>
                            {{ trans('general.save') }}
                        </button>
                    @else
                        {{-- Edit: submit directly (no side-effects) --}}
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check icon-white" aria-hidden="true"></i>
                            {{ trans('general.save') }}
                        </button>
                    @endif
                </div>
            </div>{{-- /.box --}}
        </form>
    </div>
</div>

{{-- Confirmation modal for side-effect preview --}}
@if (! $item->id)
<div class="modal fade" id="confirmAmendmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ trans('admin/contracts/general.confirm_amendment') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="preview-loading" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i> {{ trans('general.loading') }}...
                </div>
                <div id="preview-error-summary" class="alert alert-danger" role="alert" aria-live="assertive" style="display:none;"></div>
                <div id="preview-content" style="display:none;">
                    <div id="preview-message-box" class="alert alert-warning" role="status" aria-live="polite">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="preview-message"></span>
                    </div>
                    <div id="preview-details"></div>
                </div>
                <div id="preview-no-effects" style="display:none;">
                    <p>{{ trans('admin/contracts/message.amendment.no_side_effects') }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    {{ trans('button.cancel') }}
                </button>
                <button type="button" id="btn-confirm-submit" class="btn btn-primary" disabled>
                    <i class="fas fa-check"></i>
                    {{ trans('admin/contracts/general.confirm_and_save') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('moar_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var typeSelect = document.getElementById('amendment_type');
        var readjustmentFields = document.getElementById('readjustment-fields');
        var renewalFields = document.getElementById('renewal-fields');

        function toggleFields() {
            var type = typeSelect.value;
            readjustmentFields.style.display = (type === 'readjustment') ? 'block' : 'none';
            renewalFields.style.display = (type === 'renewal') ? 'block' : 'none';

            document.getElementById('new_value').required = (type === 'readjustment');
            document.getElementById('old_end_date').required = (type === 'renewal');
            document.getElementById('new_end_date').required = (type === 'renewal');
        }

        typeSelect.addEventListener('change', toggleFields);
        toggleFields();

        @if (! $item->id)
        var btnPreview = document.getElementById('btn-preview');
        var btnConfirm = document.getElementById('btn-confirm-submit');
        var modal = $('#confirmAmendmentModal');
        var form = document.getElementById('amendment-form');
        var previewToken = document.getElementById('preview_token');
        var previewDetails = document.getElementById('preview-details');
        var previewErrorSummary = document.getElementById('preview-error-summary');

        function clearPreviewErrors() {
            form.querySelectorAll('.preview-field-error').forEach(function(node) {
                node.textContent = '';
            });
            form.querySelectorAll('[aria-invalid="true"]').forEach(function(field) {
                field.removeAttribute('aria-invalid');
            });
            previewErrorSummary.textContent = '';
            previewErrorSummary.style.display = 'none';
        }

        function invalidatePreview() {
            previewToken.value = '';
            btnConfirm.disabled = true;
            document.getElementById('preview-content').style.display = 'none';
            document.getElementById('preview-no-effects').style.display = 'none';
            clearPreviewErrors();
        }

        function showPreviewErrors(messages) {
            var firstMessage = null;
            Object.keys(messages || {}).forEach(function(field) {
                var fieldMessages = Array.isArray(messages[field]) ? messages[field] : [messages[field]];
                var message = fieldMessages.filter(Boolean).join(' ');
                var errorNode = document.getElementById('preview-error-' + field);
                var fieldNode = document.getElementById(field);

                if (errorNode) {
                    errorNode.textContent = message;
                }
                if (fieldNode) {
                    fieldNode.setAttribute('aria-invalid', 'true');
                }
                if (!firstMessage && message) {
                    firstMessage = message;
                }
            });

            previewErrorSummary.textContent = firstMessage || @json(trans('admin/contracts/message.amendment.validation.preview_invalid'));
            previewErrorSummary.style.display = 'block';
        }

        function renderPreviewDetails(details) {
            previewDetails.textContent = '';
            if (!Array.isArray(details) || details.length === 0) {
                return;
            }

            var list = document.createElement('dl');
            list.className = 'dl-horizontal';
            details.forEach(function(detail) {
                var label = document.createElement('dt');
                var value = document.createElement('dd');
                label.textContent = detail.label || '';
                value.textContent = detail.value || '';
                list.appendChild(label);
                list.appendChild(value);
            });
            previewDetails.appendChild(list);
        }

        form.querySelectorAll('input, select, textarea').forEach(function(field) {
            if (field.id !== 'preview_token') {
                field.addEventListener('input', invalidatePreview);
                field.addEventListener('change', invalidatePreview);
            }
        });

        btnPreview.addEventListener('click', function() {
            var formData = new FormData(form);

            clearPreviewErrors();
            previewToken.value = '';
            btnConfirm.disabled = true;
            document.getElementById('preview-loading').style.display = 'block';
            document.getElementById('preview-content').style.display = 'none';
            document.getElementById('preview-no-effects').style.display = 'none';
            modal.modal('show');

            fetch('{{ route("contracts.amendments.preview", $contract->id) }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(function(response) {
                return response.json().then(function(data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function(result) {
                document.getElementById('preview-loading').style.display = 'none';

                if (!result.ok || result.data.status === 'error' || result.data.preview_valid !== true) {
                    showPreviewErrors(result.data.messages || result.data.errors || {});
                    return;
                }

                previewToken.value = result.data.preview_token || '';
                document.getElementById('preview-message').textContent = result.data.message || '';
                document.getElementById('preview-message-box').className = result.data.has_side_effects
                    ? 'alert alert-warning'
                    : 'alert alert-info';
                renderPreviewDetails(result.data.details || []);
                document.getElementById('preview-content').style.display = 'block';
                document.getElementById('preview-no-effects').style.display = result.data.has_side_effects
                    ? 'none'
                    : 'block';
                btnConfirm.disabled = !previewToken.value;
            })
            .catch(function() {
                document.getElementById('preview-loading').style.display = 'none';
                showPreviewErrors({
                    preview_token: [@json(trans('admin/contracts/message.amendment.validation.preview_invalid'))]
                });
            });
        });

        btnConfirm.addEventListener('click', function() {
            if (!previewToken.value) {
                showPreviewErrors({
                    preview_token: [@json(trans('admin/contracts/message.amendment.validation.preview_required'))]
                });
                return;
            }
            modal.modal('hide');
            form.submit();
        });
        @endif
    });
</script>
@endsection
