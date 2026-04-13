<div class="row">
    {{-- Contratos Ativos --}}
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ $activeCount }}</h3>
                <p>{{ trans('admin/contracts/general.active_contracts') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-file-contract"></i>
            </div>
            <a href="{{ route('contracts.index') }}" class="small-box-footer">
                {{ trans('general.viewall') }} <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- Vencendo em 30 Dias --}}
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>{{ $expiringSoonCount }}</h3>
                <p>{{ trans('admin/contracts/general.expiring_soon') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-calendar-days"></i>
            </div>
            <a href="{{ route('contracts.index') }}" class="small-box-footer">
                {{ trans('general.viewall') }} <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- Parcelas em Atraso --}}
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3>{{ $overdueCount }}</h3>
                <p>{{ trans('admin/contracts/general.overdue_installments') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <a href="{{ route('contracts.index') }}" class="small-box-footer">
                {{ trans('general.viewall') }} <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- Comprometido vs Pago no Mês --}}
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>{{ Helper::formatCurrencyOutput($monthlyPaid) }}</h3>
                <p>{{ trans('admin/contracts/general.monthly_paid') }}</p>
                <p class="text-muted" style="font-size: 12px;">
                    {{ trans('admin/contracts/general.monthly_committed') }}: {{ Helper::formatCurrencyOutput($monthlyCommitted) }}
                    ({{ $pendingMonthCount }} {{ trans('admin/contracts/general.pending_count') }})
                </p>
            </div>
            <div class="icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <a href="{{ route('contracts.index') }}" class="small-box-footer">
                {{ trans('general.viewall') }} <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>
