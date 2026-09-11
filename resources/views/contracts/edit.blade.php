@extends('layouts/edit-form', [
    'createText' => trans('admin/contracts/general.create'),
    'updateText' => trans('admin/contracts/general.update'),
    'helpTitle' => trans('admin/contracts/general.about_contracts_title'),
    'helpText' => trans('admin/contracts/general.about_contracts_text'),
    'formAction' => (isset($item->id)) ? route('contracts.update', ['contract' => $item->id]) : route('contracts.store'),
])

{{-- Page content --}}
@section('inputFields')

@php
    $legacyContract = isset($item->id) && (
        $item->supplier_id === null
        || $item->company_id === null
        || $item->end_date === null
        || $item->billing_day === null
        || $item->description === null
        || $item->contract_type_id === null
    );
    $strictContractFields = ! $legacyContract;
    $totalMode = old('total_value_mode', $item->total_value_mode ?: 'automatic');
@endphp

@include ('partials.forms.edit.name', ['translated_name' => trans('admin/contracts/general.name')])

<!-- Contract Number -->
<div class="form-group {{ $errors->has('contract_number') ? ' has-error' : '' }}">
    <label for="contract_number" class="col-md-3 control-label">{{ trans('admin/contracts/general.contract_number') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="contract_number" type="text" id="contract_number" value="{{ old('contract_number', $item->contract_number) }}">
        {!! $errors->first('contract_number', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Contract Classification -->
<div class="form-group {{ $errors->has('contract_type_id') ? ' has-error' : '' }}">
    <label for="contract_type_id" class="col-md-3 control-label">{{ trans('admin/contracts/general.contract_classification') }}</label>
    <div class="col-md-7">
        <select class="form-control" name="contract_type_id" id="contract_type_id" aria-describedby="contract-type-help" @if ($strictContractFields) required aria-required="true" @endif>
            <option value="">{{ trans('general.select') }}</option>
            @foreach ($contractTypes as $contractType)
                <option value="{{ $contractType->id }}" @selected(old('contract_type_id', $item->contract_type_id) == $contractType->id)>{{ $contractType->name }}{{ $contractType->is_active ? '' : ' ('.trans('general.inactive').')' }}</option>
            @endforeach
        </select>
        <p id="contract-type-help" class="help-block">{{ trans('admin/contracts/general.classification_help') }}</p>
        @if ($legacyContract)
            <p class="help-block"><i class="fas fa-history" aria-hidden="true"></i> {{ trans('admin/contracts/general.legacy_fields_help') }}</p>
        @endif
        {!! $errors->first('contract_type_id', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Billing Modality -->
<div class="form-group {{ $errors->has('contract_type') ? ' has-error' : '' }}">
    <label for="contract_type" class="col-md-3 control-label">{{ trans('admin/contracts/general.billing_mode') }}</label>
    <div class="col-md-7">
        <select class="form-control" name="contract_type" id="contract_type" aria-describedby="billing-mode-help" @if ($strictContractFields) required aria-required="true" @endif>
            <option value="">{{ trans('general.select') }}</option>
            <option value="recurring" {{ old('contract_type', $item->contract_type) == 'recurring' ? 'selected' : '' }}>{{ trans('admin/contracts/general.type_recurring') }}</option>
            <option value="one_time" {{ old('contract_type', $item->contract_type) == 'one_time' ? 'selected' : '' }}>{{ trans('admin/contracts/general.type_one_time') }}</option>
        </select>
        <p id="billing-mode-help" class="help-block">{{ trans('admin/contracts/general.billing_mode_help') }}</p>
        {!! $errors->first('contract_type', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.supplier-select', ['translated_name' => trans('general.supplier'), 'fieldname' => 'supplier_id', 'required' => $strictContractFields])
@include ('partials.forms.edit.company-select', ['translated_name' => trans('general.company'), 'fieldname' => 'company_id', 'required' => $strictContractFields])

<!-- Status Label -->
<div class="form-group {{ $errors->has('status_label_id') ? ' has-error' : '' }}">
    <label for="status_label_id" class="col-md-3 control-label">{{ trans('admin/contracts/general.status_label') }}</label>
    <div class="col-md-7">
        <select class="select2" data-placeholder="{{ trans('admin/contracts/general.select_status') }}" name="status_label_id" style="width: 100%" id="status_label_id" aria-label="status_label_id" @if ($strictContractFields) required aria-required="true" @endif>
            <option value="">{{ trans('admin/contracts/general.select_status') }}</option>
            @foreach (\App\Models\ContractStatusLabel::where('scope', 'contract')->whereNull('deleted_at')->orderBy('name')->get() as $contractStatus)
                <option value="{{ $contractStatus->id }}" @selected(old('status_label_id', $item->status_label_id) == $contractStatus->id)>{{ $contractStatus->name }}</option>
            @endforeach
        </select>
        {!! $errors->first('status_label_id', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Start Date -->
<div class="form-group {{ $errors->has('start_date') ? ' has-error' : '' }}">
    <label for="start_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.start_date') }}</label>
    <div class="col-md-7">
        <div id="start_date_picker">
            <input type="date" class="form-control" name="start_date" id="start_date" value="{{ old('start_date', $item->start_date?->format('Y-m-d')) }}" aria-describedby="start-date-help" @if ($strictContractFields) required aria-required="true" @endif>
        </div>
        {!! $errors->first('start_date', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- End Date -->
<div class="form-group {{ $errors->has('end_date') ? ' has-error' : '' }}">
    <label for="end_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.end_date') }}</label>
    <div class="col-md-7">
        <div id="end_date_picker">
            <input type="date" class="form-control" name="end_date" id="end_date" value="{{ old('end_date', $item->end_date?->format('Y-m-d')) }}" aria-describedby="end-date-help" @if ($strictContractFields) required aria-required="true" @endif>
        </div>
        <p id="end-date-help" class="help-block">{{ trans('admin/contracts/general.end_date_help') }}</p>
        {!! $errors->first('end_date', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Billing Cycle -->
<div class="form-group {{ $errors->has('billing_cycle') ? ' has-error' : '' }}">
    <label for="billing_cycle" class="col-md-3 control-label">{{ trans('admin/contracts/general.billing_cycle') }}</label>
    <div class="col-md-7">
        <select class="form-control" name="billing_cycle" id="billing_cycle" @if ($strictContractFields) required aria-required="true" @endif>
            <option value="">{{ trans('general.select') }}</option>
            <option value="monthly" {{ old('billing_cycle', $item->billing_cycle) == 'monthly' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_monthly') }}</option>
            <option value="quarterly" {{ old('billing_cycle', $item->billing_cycle) == 'quarterly' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_quarterly') }}</option>
            <option value="semiannual" {{ old('billing_cycle', $item->billing_cycle) == 'semiannual' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_semiannual') }}</option>
            <option value="annual" {{ old('billing_cycle', $item->billing_cycle) == 'annual' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_annual') }}</option>
            <option value="one_time" {{ old('billing_cycle', $item->billing_cycle) == 'one_time' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_one_time') }}</option>
        </select>
        <p class="help-block">{{ trans('admin/contracts/general.billing_cycle_help') }}</p>
        {!! $errors->first('billing_cycle', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Billing Day -->
<div class="form-group {{ $errors->has('billing_day') ? ' has-error' : '' }}">
    <label for="billing_day" class="col-md-3 control-label">{{ trans('admin/contracts/general.billing_day') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="billing_day" type="number" min="1" max="31" id="billing_day" value="{{ old('billing_day', $item->billing_day) }}" placeholder="" aria-describedby="billing-day-help" @if ($strictContractFields) required aria-required="true" @endif>
        <p id="billing-day-help" class="help-block">{{ trans('admin/contracts/general.billing_day_help') }}</p>
        {!! $errors->first('billing_day', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Installment Value -->
<div class="form-group {{ $errors->has('installment_value') ? ' has-error' : '' }}">
    <label for="installment_value" class="col-md-3 control-label">{{ trans('admin/contracts/general.installment_value') }}</label>
    <div class="col-md-7">
        <div>
            <input class="form-control js-contract-money" name="installment_value" type="text" id="installment_value" inputmode="decimal" aria-describedby="installment-value-help" @if ($strictContractFields) required aria-required="true" @endif value="{{ old('installment_value', $item->installment_value) }}">
        </div>
        <p id="installment-value-help" class="help-block">{{ trans('admin/contracts/general.zero_value_help') }}</p>
        {!! $errors->first('installment_value', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Total Mode -->
<div class="form-group {{ $errors->has('total_value_mode') ? ' has-error' : '' }}">
    <label for="total_value_mode" class="col-md-3 control-label">{{ trans('admin/contracts/general.total_value_mode') }}</label>
    <div class="col-md-7">
        <select class="form-control" name="total_value_mode" id="total_value_mode" aria-describedby="total-mode-help">
            <option value="automatic" @selected($totalMode === 'automatic')>{{ trans('admin/contracts/general.total_mode_automatic') }}</option>
            <option value="manual" @selected($totalMode === 'manual')>{{ trans('admin/contracts/general.total_mode_manual') }}</option>
        </select>
        <p id="total-mode-help" class="help-block">{{ trans('admin/contracts/general.total_mode_help') }}</p>
        {!! $errors->first('total_value_mode', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Total Value -->
<div class="form-group {{ $errors->has('total_value') ? ' has-error' : '' }}">
    <label for="total_value" class="col-md-3 control-label">{{ trans('admin/contracts/general.total_value') }}</label>
    <div class="col-md-7">
        <div>
            <input class="form-control js-contract-money" name="total_value" type="text" id="total_value" inputmode="decimal" aria-describedby="total-value-help" @if ($strictContractFields && $totalMode === 'manual') required aria-required="true" @endif value="{{ old('total_value', $item->total_value) }}" @if ($totalMode === 'automatic') readonly @endif>
        </div>
        <p id="total-value-help" class="help-block">{{ trans('admin/contracts/general.preview_planned_total') }}: <span id="preview-planned-total">—</span></p>
        {!! $errors->first('total_value', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Total Installments -->
<div class="form-group {{ $errors->has('total_installments') ? ' has-error' : '' }}">
    <label for="total_installments" class="col-md-3 control-label">{{ trans('admin/contracts/general.total_installments') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="total_installments" type="number" min="1" id="total_installments" value="{{ old('total_installments', $item->total_installments) }}" @if ($strictContractFields && old('contract_type', $item->contract_type) === 'one_time') required aria-required="true" @endif>
        {!! $errors->first('total_installments', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Readjustment Index -->
<div class="form-group {{ $errors->has('readjustment_index') ? ' has-error' : '' }}">
    <label for="readjustment_index" class="col-md-3 control-label">{{ trans('admin/contracts/general.readjustment_index') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="readjustment_index" type="text" id="readjustment_index" value="{{ old('readjustment_index', $item->readjustment_index) }}">
        {!! $errors->first('readjustment_index', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Readjustment Month -->
<div class="form-group {{ $errors->has('readjustment_month') ? ' has-error' : '' }}">
    <label for="readjustment_month" class="col-md-3 control-label">{{ trans('admin/contracts/general.readjustment_month') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="readjustment_month" type="number" min="1" max="12" id="readjustment_month" value="{{ old('readjustment_month', $item->readjustment_month) }}">
        {!! $errors->first('readjustment_month', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Description -->
<div class="form-group {{ $errors->has('description') ? ' has-error' : '' }}">
    <label for="description" class="col-md-3 control-label">{{ trans('admin/contracts/general.description') }}</label>
    <div class="col-md-7">
        <textarea class="form-control" name="description" id="description" rows="3" @if ($strictContractFields) required aria-required="true" @endif>{{ old('description', $item->description) }}</textarea>
        {!! $errors->first('description', '<span class="alert-msg" role="alert"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.notes')

<!-- Installment preview -->
<div class="form-group" id="installment-preview" data-preview-url="{{ route('contracts.preview') }}" aria-live="polite">
    <div class="col-md-9 col-md-offset-3">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>{{ trans('admin/contracts/general.preview_title') }}</strong></div>
            <div class="panel-body">
                <dl class="dl-horizontal" style="margin-bottom: 0;">
                    <dt>{{ trans('admin/contracts/general.preview_count') }}</dt><dd id="preview-count">—</dd>
                    <dt>{{ trans('admin/contracts/general.preview_first') }}</dt><dd id="preview-first">—</dd>
                    <dt>{{ trans('admin/contracts/general.preview_last') }}</dt><dd id="preview-last">—</dd>
                    <dt>{{ trans('admin/contracts/general.preview_planned_total') }}</dt><dd id="preview-planned-total-detail">—</dd>
                    <dt>{{ trans('admin/contracts/general.preview_negotiated_total') }}</dt><dd id="preview-negotiated-total">—</dd>
                    <dt>{{ trans('admin/contracts/general.preview_difference') }}</dt><dd id="preview-difference">—</dd>
                </dl>
                <p class="help-block" id="preview-message">{{ trans('admin/contracts/general.preview_none') }}</p>
            </div>
        </div>
    </div>
</div>

@if (!isset($item->id))
<!-- Auto Generate Installments (create only) -->
<div class="form-group">
    <div class="col-md-9 col-md-offset-3">
        <label class="form-control">
            <input type="checkbox" value="1" name="auto_generate_installments"
                   id="auto_generate_installments" checked="checked">
            {{ trans('admin/contracts/general.auto_generate_installments') }}
        </label>
    </div>
</div>
@endif

@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('create-form');
        const preview = document.getElementById('installment-preview');
        if (!form || !preview) {
            return;
        }

        const moneyInputs = Array.from(form.querySelectorAll('.js-contract-money'));
        const totalMode = document.getElementById('total_value_mode');
        const totalValue = document.getElementById('total_value');
        const totalInstallments = document.getElementById('total_installments');
        const contractType = document.getElementById('contract_type');
        const previewMessage = document.getElementById('preview-message');
        let previewTimer;
        let previewRequest;

        function formatBrl(value) {
            const negative = /^\s*-/.test(String(value ?? ''));
            let raw = String(value ?? '').trim().replace(/^R\$\s*/i, '').replace(/\s/g, '').replace(/[^0-9.,]/g, '');
            if (!raw) {
                return '';
            }

            let integer = raw;
            let decimal = '';
            if (raw.includes(',')) {
                const parts = raw.split(',');
                decimal = parts.pop() || '';
                integer = parts.join('').replace(/\./g, '');
            } else if (raw.includes('.')) {
                const parts = raw.split('.');
                if (parts.length > 2) {
                    integer = parts.join('');
                } else {
                    integer = parts[0];
                    decimal = parts[1] || '';
                    if (decimal.length === 3 && integer.length <= 3) {
                        integer += decimal;
                        decimal = '';
                    }
                }
            }

            integer = (integer.replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0');
            decimal = decimal.replace(/\D/g, '').slice(0, 2).padEnd(2, '0');
            integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            return (negative ? '-R$ ' : 'R$ ') + integer + ',' + decimal;
        }

        function setText(id, value) {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value || '—';
            }
        }

        function previewDate(value) {
            if ({{ in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true) ? 'true' : 'false' }} && /^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
                return value.split('-').reverse().join('/');
            }
            return value;
        }

        function clearPreview() {
            ['preview-count', 'preview-first', 'preview-last', 'preview-planned-total-detail', 'preview-planned-total', 'preview-negotiated-total', 'preview-difference'].forEach(id => setText(id, null));
            if (totalMode?.value === 'automatic' && totalValue) totalValue.value = '';
        }

        function updateModeState() {
            const manual = totalMode && totalMode.value === 'manual';
            if (totalValue) {
                totalValue.readOnly = !manual;
                totalValue.toggleAttribute('required', manual && {{ $strictContractFields ? 'true' : 'false' }});
            }
            if (totalInstallments && contractType) {
                totalInstallments.toggleAttribute('required', {{ $strictContractFields ? 'true' : 'false' }} && contractType.value === 'one_time');
            }
        }

        function schedulePreview() {
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(updatePreview, 250);
        }

        async function updatePreview() {
            if (previewRequest) previewRequest.abort();
            const requiredIds = ['contract_type', 'start_date', 'end_date', 'billing_cycle', 'billing_day', 'installment_value'];
            if (requiredIds.some((id) => !document.getElementById(id)?.value)) {
                clearPreview();
                previewMessage.textContent = @json(trans('admin/contracts/general.preview_none'));
                return;
            }

            if (previewRequest) {
                previewRequest.abort();
            }
            previewRequest = new AbortController();
            const formData = new FormData(form);
            // The edit form spoofs PUT; preview always uses its dedicated POST route.
            formData.delete('_method');
            try {
                const response = await fetch(preview.dataset.previewUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    signal: previewRequest.signal,
                });
                const body = await response.json();
                if (!response.ok) {
                    throw new Error('preview-invalid');
                }

                const data = body.data || {};
                setText('preview-count', data.installments_count === undefined ? null : String(data.installments_count));
                setText('preview-first', previewDate(data.first_due_date));
                setText('preview-last', previewDate(data.last_due_date));
                setText('preview-planned-total-detail', data.planned_total ? formatBrl(data.planned_total) : 'R$ 0,00');
                setText('preview-planned-total', data.planned_total ? formatBrl(data.planned_total) : 'R$ 0,00');
                setText('preview-negotiated-total', data.negotiated_total === null || data.negotiated_total === undefined ? null : formatBrl(data.negotiated_total));
                setText('preview-difference', data.difference === null || data.difference === undefined ? null : formatBrl(data.difference));
                previewMessage.textContent = data.installments_count === 0
                    ? @json(trans('admin/contracts/general.preview_none'))
                    : (totalMode?.value === 'manual' && data.difference !== null && data.difference !== undefined
                        ? @json(trans('admin/contracts/general.manual_total_preserved'))
                        : '');

                if (totalMode?.value === 'automatic' && totalValue && data.planned_total !== undefined) {
                    totalValue.value = formatBrl(data.planned_total);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    clearPreview();
                    previewMessage.textContent = @json(trans('admin/contracts/general.preview_error'));
                }
            }
        }

        moneyInputs.forEach(function (input) {
            input.addEventListener('blur', function () {
                input.value = formatBrl(input.value);
                schedulePreview();
            });
            input.addEventListener('input', schedulePreview);
            if (input.value) {
                input.value = formatBrl(input.value);
            }
        });

        form.querySelectorAll('input, select, textarea').forEach(function (input) {
            input.addEventListener('change', function () {
                updateModeState();
                schedulePreview();
            });
        });
        totalMode?.addEventListener('change', updateModeState);
        updateModeState();
        schedulePreview();
    });
</script>
@endsection
