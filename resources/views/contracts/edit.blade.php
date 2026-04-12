@extends('layouts/edit-form', [
    'createText' => trans('admin/contracts/general.create'),
    'updateText' => trans('admin/contracts/general.update'),
    'helpTitle' => trans('admin/contracts/general.about_contracts_title'),
    'helpText' => trans('admin/contracts/general.about_contracts_text'),
    'formAction' => (isset($item->id)) ? route('contracts.update', ['contract' => $item->id]) : route('contracts.store'),
])

{{-- Page content --}}
@section('inputFields')

@include ('partials.forms.edit.name', ['translated_name' => trans('admin/contracts/general.name')])

<!-- Contract Number -->
<div class="form-group {{ $errors->has('contract_number') ? ' has-error' : '' }}">
    <label for="contract_number" class="col-md-3 control-label">{{ trans('admin/contracts/general.contract_number') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="contract_number" type="text" id="contract_number" value="{{ old('contract_number', $item->contract_number) }}">
        {!! $errors->first('contract_number', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Contract Type -->
<div class="form-group {{ $errors->has('contract_type') ? ' has-error' : '' }}">
    <label for="contract_type" class="col-md-3 control-label">{{ trans('admin/contracts/general.contract_type') }}</label>
    <div class="col-md-7 required">
        <select class="form-control" name="contract_type" id="contract_type">
            <option value="">{{ trans('general.select') }}</option>
            <option value="recurring" {{ old('contract_type', $item->contract_type) == 'recurring' ? 'selected' : '' }}>{{ trans('admin/contracts/general.type_recurring') }}</option>
            <option value="one_time" {{ old('contract_type', $item->contract_type) == 'one_time' ? 'selected' : '' }}>{{ trans('admin/contracts/general.type_one_time') }}</option>
        </select>
        {!! $errors->first('contract_type', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.supplier-select', ['translated_name' => trans('general.supplier'), 'fieldname' => 'supplier_id'])
@include ('partials.forms.edit.company-select', ['translated_name' => trans('general.company'), 'fieldname' => 'company_id'])

<!-- Status Label -->
<div class="form-group {{ $errors->has('status_label_id') ? ' has-error' : '' }}">
    <label for="status_label_id" class="col-md-3 control-label">{{ trans('admin/contracts/general.status_label') }}</label>
    <div class="col-md-7">
        <select class="js-data-ajax" data-endpoint="contract-status-labels" data-placeholder="{{ trans('admin/contracts/general.select_status') }}" name="status_label_id" style="width: 100%" id="status_label_id" aria-label="status_label_id">
            @if ($item->status_label_id)
                <option value="{{ $item->status_label_id }}" selected="selected" role="option" aria-selected="true">{{ $item->statusLabel->name ?? '' }}</option>
            @endif
        </select>
        {!! $errors->first('status_label_id', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Start Date -->
<div class="form-group {{ $errors->has('start_date') ? ' has-error' : '' }}">
    <label for="start_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.start_date') }}</label>
    <div class="col-md-7">
        <div class="input-group date" id="start_date_picker" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="start_date" id="start_date" value="{{ old('start_date', $item->start_date) }}">
            <span class="input-group-addon"><x-icon type="calendar" /></span>
        </div>
        {!! $errors->first('start_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- End Date -->
<div class="form-group {{ $errors->has('end_date') ? ' has-error' : '' }}">
    <label for="end_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.end_date') }}</label>
    <div class="col-md-7">
        <div class="input-group date" id="end_date_picker" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="end_date" id="end_date" value="{{ old('end_date', $item->end_date) }}">
            <span class="input-group-addon"><x-icon type="calendar" /></span>
        </div>
        {!! $errors->first('end_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Billing Cycle -->
<div class="form-group {{ $errors->has('billing_cycle') ? ' has-error' : '' }}">
    <label for="billing_cycle" class="col-md-3 control-label">{{ trans('admin/contracts/general.billing_cycle') }}</label>
    <div class="col-md-7">
        <select class="form-control" name="billing_cycle" id="billing_cycle">
            <option value="">{{ trans('general.select') }}</option>
            <option value="monthly" {{ old('billing_cycle', $item->billing_cycle) == 'monthly' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_monthly') }}</option>
            <option value="quarterly" {{ old('billing_cycle', $item->billing_cycle) == 'quarterly' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_quarterly') }}</option>
            <option value="semi_annual" {{ old('billing_cycle', $item->billing_cycle) == 'semi_annual' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_semi_annual') }}</option>
            <option value="annual" {{ old('billing_cycle', $item->billing_cycle) == 'annual' ? 'selected' : '' }}>{{ trans('admin/contracts/general.cycle_annual') }}</option>
        </select>
        {!! $errors->first('billing_cycle', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Installment Value -->
<div class="form-group {{ $errors->has('installment_value') ? ' has-error' : '' }}">
    <label for="installment_value" class="col-md-3 control-label">{{ trans('admin/contracts/general.installment_value') }}</label>
    <div class="col-md-7">
        <div class="input-group">
            <span class="input-group-addon">{{ $snipeSettings->default_currency }}</span>
            <input class="form-control" name="installment_value" type="text" id="installment_value" value="{{ old('installment_value', $item->installment_value) }}">
        </div>
        {!! $errors->first('installment_value', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Total Value -->
<div class="form-group {{ $errors->has('total_value') ? ' has-error' : '' }}">
    <label for="total_value" class="col-md-3 control-label">{{ trans('admin/contracts/general.total_value') }}</label>
    <div class="col-md-7">
        <div class="input-group">
            <span class="input-group-addon">{{ $snipeSettings->default_currency }}</span>
            <input class="form-control" name="total_value" type="text" id="total_value" value="{{ old('total_value', $item->total_value) }}">
        </div>
        {!! $errors->first('total_value', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Total Installments -->
<div class="form-group {{ $errors->has('total_installments') ? ' has-error' : '' }}">
    <label for="total_installments" class="col-md-3 control-label">{{ trans('admin/contracts/general.total_installments') }}</label>
    <div class="col-md-7">
        <input class="form-control" name="total_installments" type="number" id="total_installments" value="{{ old('total_installments', $item->total_installments) }}">
        {!! $errors->first('total_installments', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
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
        <textarea class="form-control" name="description" id="description" rows="3">{{ old('description', $item->description) }}</textarea>
        {!! $errors->first('description', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.notes')

@stop
