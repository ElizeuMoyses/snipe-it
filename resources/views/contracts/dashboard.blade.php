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
@push('css')
<style>
/* Modern Contract Dashboard Cards - matches main dashboard design */
.contract-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.contract-stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    position: relative;
    overflow: hidden;
    display: block;
    text-decoration: none;
    color: inherit;
}

.contract-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    text-decoration: none;
    color: inherit;
}

.contract-stat-card:focus {
    outline: 2px solid var(--card-color);
    outline-offset: 2px;
}

.contract-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--card-color);
}

.contract-stat-card.active-contracts { --card-color: #1abc9c; }
.contract-stat-card.expiring-soon   { --card-color: #f39c12; }
.contract-stat-card.overdue         { --card-color: #e74c3c; }
.contract-stat-card.monthly-paid    { --card-color: #3498db; }

.contract-stat-card-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.contract-stat-card-info h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
    line-height: 1;
}

.contract-stat-card-info p {
    margin: 0.5rem 0 0 0;
    color: #7f8c8d;
    font-weight: 500;
    font-size: 1rem;
}

.contract-stat-card-info .sub-info {
    margin: 0.25rem 0 0 0;
    color: #95a5a6;
    font-size: 0.8rem;
    font-weight: 400;
}

.contract-stat-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-color);
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.contract-stat-card-footer {
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid #ecf0f1;
    display: flex;
    align-items: center;
    color: var(--card-color);
    font-weight: 500;
    font-size: 0.9rem;
}

.contract-stat-card-footer i {
    margin-left: 0.5rem;
    transition: transform 0.3s ease;
}

.contract-stat-card:hover .contract-stat-card-footer i {
    transform: translateX(4px);
}
</style>
@endpush

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
