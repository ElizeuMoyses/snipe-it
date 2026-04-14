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
                                       {{ $item->id ? 'readonly' : '' }}>
                                {!! $errors->first('old_value', '<span class="alert-msg">:message</span>') !!}
                            </div>
                        </div>
                        <div class="form-group {{ $errors->has('new_value') ? 'error' : '' }}">
                            <label for="new_value" class="col-md-3 control-label">
                                {{ trans('admin/contracts/general.new_value') }}
                            </label>
                            <div class="col-md-7">
                                <input type="number" name="new_value" id="new_value" class="form-control" step="0.01"
                                       value="{{ old('new_value', $item->new_value) }}"
                                       {{ $item->id ? 'readonly' : '' }}>
                                {!! $errors->first('new_value', '<span class="alert-msg">:message</span>') !!}
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
                                       {{ $item->id ? 'readonly' : '' }}>
                                {!! $errors->first('old_end_date', '<span class="alert-msg">:message</span>') !!}
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
                <div id="preview-content" style="display:none;">
                    <div class="alert alert-warning">
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
                <button type="button" id="btn-confirm-submit" class="btn btn-primary">
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
        }

        typeSelect.addEventListener('change', toggleFields);
        toggleFields();

        @if (! $item->id)
        var btnPreview = document.getElementById('btn-preview');
        var btnConfirm = document.getElementById('btn-confirm-submit');
        var modal = $('#confirmAmendmentModal');
        var form = document.getElementById('amendment-form');

        btnPreview.addEventListener('click', function() {
            var formData = new FormData(form);

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
            .then(function(response) { return response.json(); })
            .then(function(data) {
                document.getElementById('preview-loading').style.display = 'none';

                if (data.has_side_effects) {
                    document.getElementById('preview-message').textContent = data.message;
                    document.getElementById('preview-content').style.display = 'block';
                } else {
                    document.getElementById('preview-no-effects').style.display = 'block';
                }
            })
            .catch(function() {
                document.getElementById('preview-loading').style.display = 'none';
                form.submit();
            });
        });

        btnConfirm.addEventListener('click', function() {
            modal.modal('hide');
            form.submit();
        });
        @endif
    });
</script>
@endsection
