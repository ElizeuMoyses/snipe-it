<div class="contract-stats-grid">
    {{-- Contratos Ativos --}}
    <a href="{{ route('contracts.index') }}" class="contract-stat-card active-contracts">
        <div class="contract-stat-card-content">
            <div class="contract-stat-card-info">
                <h3>{{ $activeCount }}</h3>
                <p>{{ trans('admin/contracts/general.active_contracts') }}</p>
            </div>
            <div class="contract-stat-card-icon">
                <i class="fas fa-file-contract"></i>
            </div>
        </div>
        <div class="contract-stat-card-footer">
            {{ trans('general.view_all') }}
            <i class="fas fa-arrow-circle-right"></i>
        </div>
    </a>

    {{-- Vencendo em 30 Dias --}}
    <a href="{{ route('contracts.index') }}" class="contract-stat-card expiring-soon">
        <div class="contract-stat-card-content">
            <div class="contract-stat-card-info">
                <h3>{{ $expiringSoonCount }}</h3>
                <p>{{ trans('admin/contracts/general.expiring_soon') }}</p>
            </div>
            <div class="contract-stat-card-icon">
                <i class="fas fa-calendar-days"></i>
            </div>
        </div>
        <div class="contract-stat-card-footer">
            {{ trans('general.view_all') }}
            <i class="fas fa-arrow-circle-right"></i>
        </div>
    </a>

    {{-- Parcelas em Atraso --}}
    <a href="{{ route('contracts.index') }}" class="contract-stat-card overdue">
        <div class="contract-stat-card-content">
            <div class="contract-stat-card-info">
                <h3>{{ $overdueCount }}</h3>
                <p>{{ trans('admin/contracts/general.overdue_installments') }}</p>
            </div>
            <div class="contract-stat-card-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
        <div class="contract-stat-card-footer">
            {{ trans('general.view_all') }}
            <i class="fas fa-arrow-circle-right"></i>
        </div>
    </a>

    {{-- Comprometido vs Pago no Mês --}}
    <a href="{{ route('contracts.index') }}" class="contract-stat-card monthly-paid">
        <div class="contract-stat-card-content">
            <div class="contract-stat-card-info">
                <h3>{{ Helper::formatCurrencyOutput($monthlyPaid) }}</h3>
                <p>{{ trans('admin/contracts/general.monthly_paid') }}</p>
                <p class="sub-info">
                    {{ trans('admin/contracts/general.monthly_committed') }}: {{ Helper::formatCurrencyOutput($monthlyCommitted) }}
                    ({{ $pendingMonthCount }} {{ trans('admin/contracts/general.pending_count') }})
                </p>
            </div>
            <div class="contract-stat-card-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
        <div class="contract-stat-card-footer">
            {{ trans('general.view_all') }}
            <i class="fas fa-arrow-circle-right"></i>
        </div>
    </a>
</div>
