@extends('layouts/edit-form', [
    'createText' => trans('admin/contracts/general.create_installment'),
    'updateText' => trans('admin/contracts/general.update_installment'),
    'topSubmit' => true,
    'formAction' => (isset($item->id))
        ? route('contracts.installments.update', [$contract->id, $item->id])
        : route('contracts.installments.store', $contract->id),
])

{{-- Page content --}}
@section('inputFields')

<!-- Contract Context -->
<div class="form-group">
    <label class="col-md-3 control-label">{{ trans('admin/contracts/general.contracts') }}</label>
    <div class="col-md-7">
        <p class="form-control-static">
            <a href="{{ route('contracts.show', $contract->id) }}">{{ $contract->name }}</a>
            @if($contract->contract_number)
                ({{ $contract->contract_number }})
            @endif
        </p>
    </div>
</div>

<!-- Installment Number -->
<div class="form-group {{ $errors->has('installment_number') ? ' has-error' : '' }}">
    <label for="installment_number" class="col-md-3 control-label">{{ trans('admin/contracts/general.installment_number') }}</label>
    <div class="col-md-7 required">
        <input class="form-control" name="installment_number" type="number" min="1" id="installment_number"
               value="{{ old('installment_number', $item->installment_number ?? ($contract->installments()->count() + 1)) }}">
        {!! $errors->first('installment_number', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Reference Date -->
<div class="form-group {{ $errors->has('reference_date') ? ' has-error' : '' }}">
    <label for="reference_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.reference_date') }}</label>
    <div class="col-md-7 required">
        <div class="input-group date" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="reference_date" id="reference_date"
                   value="{{ old('reference_date', $item->reference_date ? $item->reference_date->format('Y-m-d') : '') }}">
            <span class="input-group-addon"><x-icon type="calendar" /></span>
        </div>
        {!! $errors->first('reference_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Due Date -->
<div class="form-group {{ $errors->has('due_date') ? ' has-error' : '' }}">
    <label for="due_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.due_date') }}</label>
    <div class="col-md-7 required">
        <div class="input-group date" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="due_date" id="due_date"
                   value="{{ old('due_date', $item->due_date ? $item->due_date->format('Y-m-d') : '') }}">
            <span class="input-group-addon"><x-icon type="calendar" /></span>
        </div>
        {!! $errors->first('due_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Expected Value -->
<div class="form-group {{ $errors->has('expected_value') ? ' has-error' : '' }}">
    <label for="expected_value" class="col-md-3 control-label">{{ trans('admin/contracts/general.expected_value') }}</label>
    <div class="col-md-7 required">
        <div class="input-group">
            <span class="input-group-addon">{{ $snipeSettings->default_currency }}</span>
            <input class="form-control" name="expected_value" type="text" id="expected_value"
                   value="{{ old('expected_value', $item->expected_value ?? $contract->installment_value) }}">
        </div>
        {!! $errors->first('expected_value', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.notes')

@stop
