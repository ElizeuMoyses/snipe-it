@php
    $isBrazilian = in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true);
    $contractCurrency = $isBrazilian ? trans('general.currency') : $snipeSettings->default_currency;
    $formatDate = function ($date) use ($isBrazilian) {
        if (! $date) {
            return trans('admin/contracts/general.not_informed');
        }

        return $isBrazilian
            ? $date->format('d/m/Y')
            : \App\Helpers\Helper::getFormattedDateObject($date, 'date', false);
    };
    $formatMoney = fn ($cents) => \App\Services\ContractFinancialSummary::formatCents($cents, $contractCurrency);
@endphp

<section class="contract-summary" aria-labelledby="contract-summary-title">
    <div class="clearfix">
        <h2 id="contract-summary-title" class="h4 pull-left">
            {{ trans('admin/contracts/general.management_summary') }}
        </h2>
        <div class="pull-right contract-summary-actions">
            @if(! $contract->trashed())
            @can('update', $contract)
                <a href="{{ route('contracts.edit', $contract->id) }}" class="btn btn-warning btn-sm">
                    <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                    {{ trans('admin/contracts/general.edit_contract') }}
                </a>
                @if (! $financialSummary['is_closed'])
                    <a href="{{ route('contracts.amendments.create', $contract->id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-file-signature" aria-hidden="true"></i>
                        {{ trans('admin/contracts/general.create_amendment') }}
                    </a>
                @endif
            @else
                <span class="text-muted">{{ trans('admin/contracts/general.actions_limited_by_permission') }}</span>
            @endcan
            @endif
        </div>
    </div>

    <div class="row contract-summary-facts">
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.status_label') }}</strong>
            <div>
                @if ($contract->statusLabel?->color)
                    <span class="label" style="background-color: {{ $contract->statusLabel->color }}">
                        @if ($contract->statusLabel->icon)
                            <i class="fa {{ $contract->statusLabel->icon }}" aria-hidden="true"></i>
                        @endif
                        {{ $contract->statusLabel->name }}
                    </span>
                @else
                    {{ trans('admin/contracts/general.not_informed') }}
                @endif
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.validity') }}</strong>
            <div>{{ trans('admin/contracts/general.validity_' . $financialSummary['validity']) }}</div>
            <small class="text-muted">
                {{ $formatDate($contract->start_date) }}
                @if ($contract->end_date)
                    &ndash; {{ $formatDate($contract->end_date) }}
                @endif
            </small>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('general.supplier') }}</strong>
            <div>{{ $contract->supplier?->name ?? trans('admin/contracts/general.not_informed') }}</div>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.next_due') }}</strong>
            <div>
                @if ($financialSummary['next_due_date'])
                    {{ $formatDate($financialSummary['next_due_date']) }}
                    @if ($financialSummary['next_due_installment_number'])
                        <span class="text-muted">(#{{ $financialSummary['next_due_installment_number'] }})</span>
                    @endif
                @else
                    {{ trans('admin/contracts/general.no_next_due') }}
                @endif
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('general.company') }}</strong>
            <div>{{ $contract->company?->name ?? trans('admin/contracts/general.not_informed') }}</div>
        </div>
    </div>

    <div class="row contract-summary-facts">
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.closing_or_renewal') }}</strong>
            <div>
                @if ($financialSummary['is_closed'])
                    {{ trans('admin/contracts/general.closed') }}
                @elseif ($financialSummary['renewal_count'] > 0)
                    {{ trans('admin/contracts/general.renewal_count', ['count' => $financialSummary['renewal_count']]) }}
                @else
                    {{ trans('admin/contracts/general.no_renewal_registered') }}
                @endif
            </div>
            @if ($financialSummary['last_renewal_date'])
                <small class="text-muted">{{ $formatDate($financialSummary['last_renewal_date']) }}</small>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.installments') }}</strong>
            <div>{{ number_format($financialSummary['installments_count']) }}</div>
            <small class="text-muted">
                {{ trans('admin/contracts/general.open_count', ['count' => $financialSummary['open_count']]) }}
            </small>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.files_scope') }}</strong>
            <div>{{ number_format($totalUploadsCount) }}</div>
            <small class="text-muted">
                {{ trans('admin/contracts/general.files_scope_breakdown', ['contract' => $contractUploadCount, 'installments' => $installmentUploadsCount, 'amendments' => $amendmentUploadsCount]) }}
            </small>
        </div>
    </div>

    <div class="row contract-financial-summary">
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.negotiated_total') }}</strong>
            <div>
                @if ($financialSummary['negotiated_total_cents'] !== null)
                    {{ $formatMoney($financialSummary['negotiated_total_cents']) }}
                @else
                    {{ trans('admin/contracts/general.not_informed') }}
                @endif
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.planned_total') }}</strong>
            <div>{{ $formatMoney($financialSummary['planned_total_cents']) }}</div>
            <small class="text-muted">{{ trans('admin/contracts/general.cancelled_excluded') }}</small>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.paid_total') }}</strong>
            <div>{{ $formatMoney($financialSummary['paid_total_cents']) }}</div>
            <small class="text-muted">{{ trans('admin/contracts/general.recorded_payments') }}</small>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.open_total') }}</strong>
            <div>{{ $formatMoney($financialSummary['open_total_cents']) }}</div>
            <small class="text-muted">{{ trans('admin/contracts/general.open_total_help') }}</small>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.overdue_total') }}</strong>
            <div>{{ $formatMoney($financialSummary['overdue_total_cents']) }}</div>
        </div>
        <div class="col-md-3 col-sm-6">
            <strong>{{ trans('admin/contracts/general.cancelled_total') }}</strong>
            <div>{{ $formatMoney($financialSummary['cancelled_total_cents']) }}</div>
        </div>
    </div>

    @if ($financialSummary['negotiated_planned_difference_cents'] !== null)
        <p class="help-block">
            <strong>{{ trans('admin/contracts/general.negotiated_planned_difference') }}:</strong>
            {{ $formatMoney($financialSummary['negotiated_planned_difference_cents']) }}.
            {{ trans('admin/contracts/general.negotiated_planned_difference_help') }}
        </p>
    @endif
    <p class="help-block">
        {{ trans('admin/contracts/general.financial_summary_help') }}
    </p>
</section>
