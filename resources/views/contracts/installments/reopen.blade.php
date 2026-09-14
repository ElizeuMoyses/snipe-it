@extends('layouts/default')
@section('title'){{ trans('admin/contracts/installment_ux.reopen') }} - {{ $contract->name }} @parent @stop
@section('content')
<div class="row"><div class="col-md-8 col-md-offset-2"><div class="box box-default">
    <div class="box-header with-border"><h2 class="box-title">{{ trans('admin/contracts/installment_ux.reopen') }} — {{ trans('admin/contracts/general.installment_number') }} {{ $installment->installment_number }}</h2></div>
    <div class="box-body">
        <p>{{ trans('admin/contracts/installment_ux.reopen_help') }}</p>
        <p><strong>{{ trans('admin/contracts/general.payment_date') }}:</strong> {{ $installment->payment_date?->format(in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true) ? 'd/m/Y' : 'Y-m-d') }}</p>
        <form method="POST" action="{{ route('contracts.installments.reopen', [$contract->id, $installment->id]) }}">
            @csrf
            <div class="form-group {{ $errors->has('reason') ? 'has-error' : '' }}">
                <label for="reason">{{ trans('admin/contracts/installment_ux.reason') }}</label>
                <textarea id="reason" class="form-control" name="reason" required maxlength="1000" rows="3">{{ old('reason') }}</textarea>
                @error('reason')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-warning">{{ trans('admin/contracts/installment_ux.reopen_submit') }}</button>
            <a class="btn btn-default" href="{{ route('contracts.show', $contract->id) }}#installments">{{ trans('button.cancel') }}</a>
        </form>
    </div>
</div></div></div>
@stop
