@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/contracts/general.dashboard') }}
    @parent
@stop

@section('header_right')
    @can('create', \App\Models\Contract::class)
        <a href="{{ route('contracts.create') }}" class="btn btn-primary pull-right" style="margin-right: 5px;">
            {{ trans('general.create') }}
        </a>
    @endcan
    <a href="{{ route('contracts.index') }}" class="btn btn-default pull-right" style="margin-right: 5px;">
        {{ trans('admin/contracts/general.contracts') }}
    </a>
@stop

{{-- Page content --}}
@section('content')

    {{-- Summary Cards --}}
    @include('contracts.partials.dashboard-cards')

    <div class="row" style="margin-top: 20px;">
        {{-- Próximos Vencimentos --}}
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h2 class="box-title">
                        <i class="fas fa-calendar-alt"></i>
                        {{ trans('admin/contracts/general.upcoming_installments') }}
                    </h2>
                </div>
                <div class="box-body">
                    @if($upcomingInstallments->isEmpty())
                        <p class="text-muted text-center" style="padding: 20px;">
                            {{ trans('admin/contracts/general.no_upcoming') }}
                        </p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ trans('admin/contracts/table.due_date') }}</th>
                                        <th>{{ trans('general.name') }}</th>
                                        <th>{{ trans('general.supplier') }}</th>
                                        <th>{{ trans('admin/contracts/table.expected_value') }}</th>
                                        <th>{{ trans('admin/contracts/table.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcomingInstallments as $installment)
                                        <tr>
                                            <td>{{ Helper::getFormattedDateObject($installment->due_date, 'date', false) }}</td>
                                            <td>
                                                <a href="{{ route('contracts.show', $installment->contract_id) }}">
                                                    {{ $installment->contract->name ?? '' }}
                                                </a>
                                            </td>
                                            <td>{{ $installment->contract->supplier->name ?? '—' }}</td>
                                            <td>{{ Helper::formatCurrencyOutput($installment->expected_value) }}</td>
                                            <td>
                                                @if($installment->statusLabel)
                                                    <span class="label" style="background-color: {{ $installment->statusLabel->color ?? '#999' }}">
                                                        @if($installment->statusLabel->icon)
                                                            <i class="fa {{ $installment->statusLabel->icon }}"></i>
                                                        @endif
                                                        {{ $installment->statusLabel->name }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Parcelas em Atraso --}}
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h2 class="box-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        {{ trans('admin/contracts/general.overdue_installments') }}
                    </h2>
                </div>
                <div class="box-body">
                    @if($overdueInstallments->isEmpty())
                        <p class="text-muted text-center" style="padding: 20px;">
                            {{ trans('admin/contracts/general.no_overdue') }}
                        </p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ trans('admin/contracts/table.due_date') }}</th>
                                        <th>{{ trans('general.name') }}</th>
                                        <th>{{ trans('general.supplier') }}</th>
                                        <th>{{ trans('admin/contracts/table.expected_value') }}</th>
                                        <th>{{ trans('admin/contracts/table.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($overdueInstallments as $installment)
                                        <tr>
                                            <td>
                                                <span class="text-danger">
                                                    {{ Helper::getFormattedDateObject($installment->due_date, 'date', false) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('contracts.show', $installment->contract_id) }}">
                                                    {{ $installment->contract->name ?? '' }}
                                                </a>
                                            </td>
                                            <td>{{ $installment->contract->supplier->name ?? '—' }}</td>
                                            <td>{{ Helper::formatCurrencyOutput($installment->expected_value) }}</td>
                                            <td>
                                                @if($installment->statusLabel)
                                                    <span class="label" style="background-color: {{ $installment->statusLabel->color ?? '#999' }}">
                                                        @if($installment->statusLabel->icon)
                                                            <i class="fa {{ $installment->statusLabel->icon }}"></i>
                                                        @endif
                                                        {{ $installment->statusLabel->name }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@stop
