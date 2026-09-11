@extends('layouts/default')

@section('title')
    {{ trans('admin/contracts/general.register_payment') }} - {{ $contract->name }}
    @parent
@stop

@section('content')

<div class="row">
    <div class="col-md-8 col-md-offset-2">

        {{-- Installment Info (readonly) --}}
        <div class="box box-default">
            <div class="box-header with-border">
                <h2 class="box-title">{{ trans('admin/contracts/general.installment_info') }}</h2>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>{{ trans('admin/contracts/general.installment_number') }}:</strong>
                        {{ $installment->installment_number }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ trans('admin/contracts/general.reference_date') }}:</strong>
                        {{ $installment->reference_date ? $installment->reference_date->format('m/Y') : '—' }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ trans('admin/contracts/general.due_date') }}:</strong>
                        {{ $installment->due_date ? $installment->due_date->format('Y-m-d') : '—' }}
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-4">
                        <strong>{{ trans('admin/contracts/general.expected_value') }}:</strong>
                        {{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($installment->expected_value) }}
                    </div>
                    <div class="col-md-4">
                        <strong>{{ trans('admin/contracts/general.status_label') }}:</strong>
                        @if($installment->statusLabel)
                            <span class="label" style="background-color: {{ $installment->statusLabel->color ?? '#999' }}">
                                @if($installment->statusLabel->icon)
                                    <i class="fa {{ $installment->statusLabel->icon }}"></i>
                                @endif
                                {{ $installment->statusLabel->name }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Form --}}
        <div class="box box-default">
            <div class="box-header with-border">
                <h2 class="box-title">{{ trans('admin/contracts/general.payment_details') }}</h2>
            </div>
            <div class="box-body">
                <form method="POST" action="{{ route('contracts.installments.pay.store', [$contract->id, $installment->id]) }}" class="form-horizontal" autocomplete="off">
                    @csrf

                    <!-- Paid Value -->
                    <div class="form-group {{ $errors->has('paid_value') ? ' has-error' : '' }}">
                        <label for="paid_value" class="col-md-3 control-label">{{ trans('admin/contracts/general.paid_value') }}</label>
                        <div class="col-md-7 required">
                            <div class="input-group">
                                <span class="input-group-addon">{{ $snipeSettings->default_currency }}</span>
                                <input class="form-control" name="paid_value" type="text" id="paid_value"
                                       value="{{ old('paid_value', $installment->expected_value) }}">
                            </div>
                            {!! $errors->first('paid_value', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>

                    <!-- Payment Date -->
                    <div class="form-group {{ $errors->has('payment_date') ? ' has-error' : '' }}">
                        <label for="payment_date" class="col-md-3 control-label">{{ trans('admin/contracts/general.payment_date') }}</label>
                        <div class="col-md-7 required">
                            <div class="input-group date" data-provide="datepicker" data-date-format="yyyy-mm-dd" data-autoclose="true">
                                <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="payment_date" id="payment_date"
                                       value="{{ old('payment_date', date('Y-m-d')) }}">
                                <span class="input-group-addon"><x-icon type="calendar" /></span>
                            </div>
                            {!! $errors->first('payment_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-group {{ $errors->has('payment_method') ? ' has-error' : '' }}">
                        <label for="payment_method" class="col-md-3 control-label">{{ trans('admin/contracts/general.payment_method') }}</label>
                        <div class="col-md-7">
                            <select class="form-control" name="payment_method" id="payment_method">
                                <option value="">{{ trans('general.select') }}</option>
                                <option value="Boleto" {{ old('payment_method') == 'Boleto' ? 'selected' : '' }}>{{ trans('admin/contracts/general.method_boleto') }}</option>
                                <option value="PIX" {{ old('payment_method') == 'PIX' ? 'selected' : '' }}>{{ trans('admin/contracts/general.method_pix') }}</option>
                                <option value="Bank Transfer" {{ old('payment_method') == 'Bank Transfer' ? 'selected' : '' }}>{{ trans('admin/contracts/general.method_transfer') }}</option>
                                <option value="Credit Card" {{ old('payment_method') == 'Credit Card' ? 'selected' : '' }}>{{ trans('admin/contracts/general.method_card') }}</option>
                                <option value="Other" {{ old('payment_method') == 'Other' ? 'selected' : '' }}>{{ trans('admin/contracts/general.method_other') }}</option>
                            </select>
                            {!! $errors->first('payment_method', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>

                    <!-- Ticket Reference -->
                    <div class="form-group {{ $errors->has('ticket_reference') ? ' has-error' : '' }}">
                        <label for="ticket_reference" class="col-md-3 control-label">{{ trans('admin/contracts/general.ticket_reference') }}</label>
                        <div class="col-md-7">
                            <input class="form-control" name="ticket_reference" type="text" id="ticket_reference"
                                   value="{{ old('ticket_reference') }}" placeholder="TI-2026-0342">
                            {!! $errors->first('ticket_reference', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group {{ $errors->has('notes') ? ' has-error' : '' }}">
                        <label for="notes" class="col-md-3 control-label">{{ trans('general.notes') }}</label>
                        <div class="col-md-7">
                            <textarea class="form-control" name="notes" id="notes" rows="3">{{ old('notes') }}</textarea>
                            {!! $errors->first('notes', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="form-group">
                        <div class="col-md-7 col-md-offset-3">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> {{ trans('admin/contracts/general.register_payment') }}
                            </button>
                            <a href="{{ route('contracts.show', $contract->id) }}" class="btn btn-default">
                                {{ trans('button.cancel') }}
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

@stop
