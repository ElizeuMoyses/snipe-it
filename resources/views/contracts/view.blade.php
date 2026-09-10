@extends('layouts/default')

@php
    $paidInstallmentsCount = $contract->installments
        ->filter(fn ($installment) => $installment->statusLabel?->meta_type === 'paid')
        ->count();
    $archiveDetail = trans('admin/contracts/message.archive.detail', [
        'name' => $contract->name,
        'installments' => $contract->installments->count(),
        'amendments' => $contract->amendments->count(),
    ]);
@endphp

{{-- Page title --}}
@section('title')

  {{ trans('admin/contracts/general.view') }} -
  {{ $contract->name }}

  @parent
@stop

@section('header_right')
    <i class="fa-regular fa-2x fa-square-caret-right pull-right" id="expand-info-panel-button" data-tooltip="true" title="{{ trans('button.show_hide_info') }}"></i>
@endsection

@php
    $isBrazilian = in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true);
    $contractCurrency = $isBrazilian ? trans('general.currency') : $snipeSettings->default_currency;
    $contractDate = function ($date) use ($isBrazilian) {
        if (! $date) {
            return trans('admin/contracts/general.not_informed');
        }

        return $isBrazilian
            ? $date->format('d/m/Y')
            : Helper::getFormattedDateObject($date, 'date', false);
    };
    $contractMoney = fn ($value) => \App\Services\ContractFinancialSummary::formatCents(
        \App\Services\ContractFinancialSummary::toCents($value),
        $contractCurrency
    );
@endphp

{{-- Page content --}}
@section('content')
    <x-container columns="2">
        <x-page-column class="col-md-9 main-panel">
            @if($contract->trashed())
                <div class="alert alert-warning" role="status">
                    <strong>{{ trans('admin/contracts/general.archived_notice') }}</strong>
                    <br>
                    {{ $archiveDetail }}
                </div>
            @elseif($paidInstallmentsCount > 0)
                <div class="alert alert-warning" role="status">
                    {{ trans('admin/contracts/message.archive.blocked_paid', ['count' => $paidInstallmentsCount]) }}
                </div>
            @endif

            @include('contracts.partials.summary')
            <x-tabs class="contract-detail-tabs">
                <x-slot:tabnav>

                    <x-tabs.nav-item
                        name="installments"
                        class="active"
                        icon="fas fa-money-bill-wave"
                        label="{{ trans('admin/contracts/general.installments') }}"
                        count="{{ $contract->installments->count() }}"
                        tooltip="{{ trans('admin/contracts/general.installments') }}"
                        show_label="true"
                        show_count="true"
                    />

                    <x-tabs.nav-item
                        name="amendments"
                        icon="fas fa-file-signature"
                        label="{{ trans('admin/contracts/general.amendments') }}"
                        count="{{ $contract->amendments->count() }}"
                        tooltip="{{ trans('admin/contracts/general.amendments') }}"
                        show_label="true"
                        show_count="true"
                    />

                    <x-tabs.nav-item
                        name="contract-assets"
                        icon="fas fa-link"
                        label="{{ trans('admin/contracts/general.linked_assets') }}"
                        count="{{ $contract->assets->count() }}"
                        tooltip="{{ trans('admin/contracts/general.linked_assets') }}"
                        show_label="true"
                        show_count="true"
                    />

                    @can('view', $contract)
                        <x-tabs.nav-item
                            name="files"
                            icon_type="files"
                            label="{{ trans('general.files') }}"
                            count="{{ $totalUploadsCount }}"
                            tooltip="{{ trans('general.files') }}"
                            show_label="true"
                            show_count="true"
                        />
                    @endcan

                    @can('history', $contract)
                        <x-tabs.nav-item
                            name="history"
                            icon_type="history"
                            label="{{ trans('general.history') }}"
                            count="{{ $auditHistoryCount }}"
                            tooltip="{{ trans('general.history') }}"
                            show_label="true"
                            show_count="true"
                        />
                    @endcan

                    @if(! $contract->trashed())
                        <x-tabs.upload-tab :item="$contract"/>
                    @endif

                </x-slot:tabnav>

                <x-slot:tabpanes>

                    <!-- start installments tab pane -->
                    <x-tabs.pane name="installments" class="active in">
                        @can('installments', $contract)
                            @if(! $contract->trashed() && ! in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled']))
                                <div class="row" style="margin-bottom: 10px;">
                                    <div class="col-md-12 text-right">
                                        @if ($contract->installments->count() === 0)
                                            <form action="{{ route('contracts.installments.generate', $contract->id) }}"
                                                  method="POST" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm"
                                                        onclick="return confirm('{{ trans('admin/contracts/general.generate_installments_confirm') }}')">
                                                    {{ trans('admin/contracts/general.generate_installments') }}
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('contracts.installments.create', $contract->id) }}" class="btn btn-primary btn-sm">
                                            {{ trans('admin/contracts/general.create_installment') }}
                                        </a>
                                    </div>
                                </div>
                            @endif
                        @endcan
                        @include('contracts.partials.installments-table', ['contract' => $contract])
                    </x-tabs.pane>
                    <!-- end installments tab pane -->

                    <!-- start amendments tab pane -->
                    <x-tabs.pane name="amendments">
                        @can('update', $contract)
                            @if(! $contract->trashed() && ! in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled']))
                                <div class="row" style="margin-bottom: 10px;">
                                    <div class="col-md-12 text-right">
                                        <a href="{{ route('contracts.amendments.create', $contract->id) }}" class="btn btn-primary btn-sm">
                                            {{ trans('admin/contracts/general.create_amendment') }}
                                        </a>
                                    </div>
                                </div>
                            @endif
                        @endcan
                        @if($contract->amendments->count() > 0)
                            @include('contracts.partials.amendments-table', ['contract' => $contract])
                        @else
                            <div class="alert alert-info">
                                {{ trans('admin/contracts/message.amendment.no_amendments') }}
                            </div>
                        @endif
                    </x-tabs.pane>
                    <!-- end amendments tab pane -->

                    <!-- start assets tab pane -->
                    <x-tabs.pane name="contract-assets">
                        @can('update', $contract)
                            @can('view', \App\Models\Asset::class)
                                @if(! $contract->assetLinksAreLocked())
                                    <div class="row" style="margin-bottom: 10px;">
                                        <div class="col-md-12">
                                            <form method="POST" action="{{ route('contracts.assets.attach', $contract->id) }}" class="form-horizontal" id="contract-asset-link-form">
                                                @csrf
                                                @include('partials.forms.edit.asset-select', [
                                                    'translated_name' => trans('admin/contracts/general.select_asset'),
                                                    'fieldname' => 'asset_id',
                                                    'select_id' => 'contract_asset_id',
                                                    'asset_selector_div_id' => 'contract-asset-selector',
                                                    'company_id' => $contract->company_id,
                                                    'ajax_url' => route('contracts.assets.selectlist', $contract->id),
                                                    'contract_asset_selector' => true,
                                                    'status_id' => 'contract-asset-selector-status',
                                                    'describedby' => 'contract-asset-selector-help',
                                                    'placeholder' => trans('admin/contracts/general.select_asset'),
                                                    'required' => 'true',
                                                ])
                                                <div class="form-group">
                                                    <div class="col-md-7 col-md-offset-3">
                                                        <span id="contract-asset-selector-help" class="help-block">
                                                            {{ trans('admin/contracts/message.asset.selector.help') }}
                                                        </span>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-link" aria-hidden="true"></i>
                                                            {{ trans('admin/contracts/general.link_asset') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <div class="alert alert-info" role="status">
                                        {{ trans('admin/contracts/message.asset.contract_closed') }}
                                    </div>
                                @endif
                            @else
                                <div class="alert alert-warning" role="alert">
                                    {{ trans('admin/contracts/message.asset.no_permission') }}
                                </div>
                            @endcan
                        @endcan
                        @if($contract->assets->count() > 0)
                            @include('contracts.partials.assets-table', ['contract' => $contract])
                        @else
                            <div class="alert alert-info">
                                {{ trans('admin/contracts/message.asset.no_assets') }}
                            </div>
                        @endif
                    </x-tabs.pane>
                    <!-- end assets tab pane -->

                    <!-- start history tab pane -->
                    <x-tabs.pane name="history">
                        @can('history', $contract)
                            @php
                                $historyQuery = request()->only(['action', 'entity', 'created_by', 'source', 'search', 'from', 'to']);
                            @endphp
                            <form method="GET" action="{{ route('contracts.show', $contract->id) }}" class="form-inline" style="margin-bottom: 15px;">
                                <div class="form-group" style="margin: 4px 8px 4px 0;">
                                    <label class="sr-only" for="contract-history-search">{{ trans('admin/contracts/general.history_filter_search') }}</label>
                                    <input id="contract-history-search" name="search" class="form-control input-sm" value="{{ request('search') }}" placeholder="{{ trans('admin/contracts/general.history_filter_search') }}">
                                </div>
                                <div class="form-group" style="margin: 4px 8px 4px 0;">
                                    <label class="sr-only" for="contract-history-action">{{ trans('admin/contracts/general.history_filter_action') }}</label>
                                    <input id="contract-history-action" name="action" class="form-control input-sm" value="{{ request('action') }}" placeholder="{{ trans('admin/contracts/general.history_filter_action') }}">
                                </div>
                                <div class="form-group" style="margin: 4px 8px 4px 0;">
                                    <label class="sr-only" for="contract-history-entity">{{ trans('admin/contracts/general.history_filter_entity') }}</label>
                                    <input id="contract-history-entity" name="entity" class="form-control input-sm" value="{{ request('entity') }}" placeholder="{{ trans('admin/contracts/general.history_filter_entity') }}">
                                </div>
                                <div class="form-group" style="margin: 4px 8px 4px 0;">
                                    <label class="sr-only" for="contract-history-author">{{ trans('admin/contracts/general.history_filter_author') }}</label>
                                    <input id="contract-history-author" name="created_by" type="number" min="1" class="form-control input-sm" value="{{ request('created_by') }}" placeholder="{{ trans('admin/contracts/general.history_filter_author') }}">
                                </div>
                                <div class="form-group" style="margin: 4px 8px 4px 0;">
                                    <label class="sr-only" for="contract-history-from">{{ trans('admin/contracts/general.history_filter_from') }}</label>
                                    <input id="contract-history-from" name="from" type="date" class="form-control input-sm" value="{{ request('from') }}">
                                </div>
                                <div class="form-group" style="margin: 4px 8px 4px 0;">
                                    <label class="sr-only" for="contract-history-to">{{ trans('admin/contracts/general.history_filter_to') }}</label>
                                    <input id="contract-history-to" name="to" type="date" class="form-control input-sm" value="{{ request('to') }}">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" style="margin: 4px 8px 4px 0;">
                                    {{ trans('admin/contracts/general.history_filter_apply') }}
                                </button>
                                @if($historyQuery)
                                    <a class="btn btn-default btn-sm" style="margin: 4px 0;" href="{{ route('contracts.show', $contract->id) }}#history">
                                        {{ trans('admin/contracts/general.history_filter_clear') }}
                                    </a>
                                @endif
                            </form>

                            <x-table
                                name="contractAudit"
                                sort_order="desc"
                                sort_field="occurred_at"
                                :presenter="\App\Presenters\ContractAuditPresenter::dataTableLayout()"
                                :api_url="route('contracts.history', array_merge(['contract' => $contract->id], $historyQuery))"
                                show_advanced_search="false"
                                export_filename="contract-{{ $contract->id }}-history-{{ date('Y-m-d') }}"
                            />
                        @endcan
                    </x-tabs.pane>
                    <!-- end history tab pane -->

                    <!-- start files tab pane -->
                    <x-tabs.pane name="files" class="{{ $totalUploadsCount == 0 ? 'hidden-print' : '' }}">
                        <x-table.files object_type="contracts" :object="$contract"/>
                        @if($totalUploadsCount === 0)
                            <div class="alert alert-info">
                                {{ trans('admin/contracts/general.no_files') }}
                            </div>
                        @endif

                        {{-- Installment files --}}
                        @if($installmentUploadsCount > 0)
                            <h4 style="margin-top: 20px;">
                                <i class="fas fa-file-invoice"></i>
                                {{ trans('admin/contracts/general.installment_files') }}
                                <span class="badge">{{ $installmentUploadsCount }}</span>
                            </h4>
                            <div class="table-responsive">
                                <table class="table table-striped snipe-table">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>{{ trans('admin/contracts/general.installment_number') }}</th>
                                            <th>{{ trans('general.file_name') }}</th>
                                            <th>{{ trans('general.notes') }}</th>
                                            <th>{{ trans('general.created_by') }}</th>
                                            <th>{{ trans('general.created_at') }}</th>
                                            <th>{{ trans('table.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($contract->installments->sortBy('installment_number') as $installment)
                                            @foreach($installment->uploads as $upload)
                                                <tr>
                                                    <td><i class="{{ \App\Helpers\Helper::filetype_icon($upload->filename) }}"></i></td>
                                                    <td>#{{ $installment->installment_number }}</td>
                                                    <td>
                                                        <a href="{{ route('ui.files.show', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $upload->id]) }}">
                                                            {{ $upload->filename }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $upload->note }}</td>
                                                    <td>
                                                        @if($upload->adminuser)
                                                            {{ $upload->adminuser->display_name }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $isBrazilian ? $upload->created_at->format('d/m/Y H:i') : $upload->created_at->format('Y-m-d H:i') }}</td>
                                                    <td>
                                                        <a href="{{ route('ui.files.show', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $upload->id]) }}" class="btn btn-sm btn-default" data-tooltip="true" title="{{ trans('general.download') }}" aria-label="{{ trans('general.download') }}">
                                                            <i class="fas fa-download"></i>
                                                            <span class="sr-only">{{ trans('general.download') }}</span>
                                                        </a>
                                                        @can('files', $contract)
                                                            <form method="POST" action="{{ route('ui.files.destroy', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $upload->id]) }}" style="display:inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger" data-tooltip="true" title="{{ trans('button.delete') }}" aria-label="{{ trans('button.delete') }}" onclick="return confirm('{{ trans('general.are_you_sure') }}')">
                                                                    <i class="fas fa-trash"></i>
                                                                    <span class="sr-only">{{ trans('button.delete') }}</span>
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Amendment files are shown here once so the Files tab has a complete scope. --}}
                        @if($amendmentUploadsCount > 0)
                            <h4 style="margin-top: 20px;">
                                <i class="fas fa-file-signature" aria-hidden="true"></i>
                                {{ trans('admin/contracts/general.amendment_files') }}
                                <span class="badge">{{ $amendmentUploadsCount }}</span>
                            </h4>
                            @foreach($contract->amendments as $amendment)
                                @if($amendment->uploads->isNotEmpty())
                                    <h5>
                                        {{ trans('admin/contracts/general.amendment_type_' . $amendment->amendment_type) }}
                                        &mdash; {{ $contractDate($amendment->effective_date) }}
                                    </h5>
                                    <x-table.files
                                        :object_type="'contract_amendments'"
                                        :object="$amendment"
                                        :table_id="'amendment-'.$amendment->id.'-files'"
                                    />
                                @endif
                            @endforeach
                        @endif
                    </x-tabs.pane>
                    <!-- end files tab pane -->



                </x-slot:tabpanes>

            </x-tabs>
        </x-page-column>
        <x-page-column class="col-md-3 hidden-print">

            <x-box class="side-box expanded">
                <x-info-panel :infoPanelObj="$contract">

                    <x-slot:buttons>
                        @if($contract->trashed())
                            @can('restore', $contract)
                                <form method="POST" action="{{ route('contracts.restore', $contract->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning" title="{{ trans('admin/contracts/general.restore_contract') }}">
                                        <i class="fas fa-undo"></i>
                                        {{ trans('admin/contracts/general.restore_contract') }}
                                    </button>
                                </form>
                            @endcan
                        @else
                            <x-button :item="$contract" permission="update" :route="route('contracts.edit', $contract->id)" class="btn-warning"  />
                            @can('delete', $contract)
                                @if($contract->isDeletable())
                                    <a href="{{ route('contracts.destroy', $contract->id) }}"
                                       class="btn btn-danger delete-asset"
                                       data-toggle="modal"
                                       data-icon="fa-archive"
                                       data-require-reason="true"
                                       data-content="{{ $archiveDetail }} {{ trans('admin/contracts/message.archive.impact') }}"
                                       data-title="{{ trans('admin/contracts/general.archive_contract') }}"
                                       onClick="return false;">
                                        <i class="fas fa-archive"></i>
                                        {{ trans('admin/contracts/general.archive_contract') }}
                                    </a>
                                @else
                                    <button type="button" class="btn btn-danger disabled"
                                            title="{{ trans('admin/contracts/message.archive.blocked_paid', ['count' => $paidInstallmentsCount]) }}"
                                            disabled>
                                        <i class="fas fa-archive"></i>
                                        {{ trans('admin/contracts/general.archive_contract') }}
                                    </button>
                                @endif
                            @endcan
                        @endif
                    </x-slot:buttons>

                    <x-slot:info>

                        @if ($contract->contract_number)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.contract_number') }}: </strong>
                                {{ $contract->contract_number }}
                            </div>
                        @endif

                        @if ($contract->contract_type)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.billing_mode') }}: </strong>
                                {{ trans('admin/contracts/general.type_' . $contract->contract_type) }}
                            </div>
                        @endif

                        @if ($contract->contractType)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.contract_classification') }}: </strong>
                                {{ $contract->contractType->name }}
                            </div>
                        @endif

                        @if ($contract->statusLabel)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.status_label') }}: </strong>
                                @if($contract->statusLabel->color)
                                    <span style="color: {{ $contract->statusLabel->color }}"><i class="fas fa-circle"></i></span>
                                @endif
                                {{ $contract->statusLabel->name }}
                            </div>
                        @endif

                        @if ($contract->supplier)
                            <div class="col-md-12">
                                <strong>{{ trans('general.supplier') }}: </strong>
                                <a href="{{ route('suppliers.show', $contract->supplier->id) }}">{{ $contract->supplier->name }}</a>
                            </div>
                        @endif

                        @if ($contract->company)
                            <div class="col-md-12">
                                <strong>{{ trans('general.company') }}: </strong>
                                {{ $contract->company->name }}
                            </div>
                        @endif

                        @if ($contract->start_date)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.start_date') }}: </strong>
                                {{ $contractDate($contract->start_date) }}
                            </div>
                        @endif

                        @if ($contract->end_date)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.end_date') }}: </strong>
                                {{ $contractDate($contract->end_date) }}
                            </div>
                        @endif

                        @if ($contract->billing_cycle)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.billing_cycle') }}: </strong>
                                {{ trans('admin/contracts/general.cycle_' . $contract->billing_cycle) }}
                            </div>
                        @endif

                        @if ($contract->billing_day)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.billing_day') }}: </strong>
                                {{ $contract->billing_day }}
                            </div>
                        @endif

                        @if ($contract->installment_value !== null)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.installment_value') }}: </strong>
                                {{ \App\Services\ContractMoney::centsToBr(\App\Services\ContractMoney::toCents($contract->installment_value) ?? 0) }}
                            </div>
                        @endif

                        @if ($contract->total_value !== null)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.total_value') }}: </strong>
                                {{ \App\Services\ContractMoney::centsToBr(\App\Services\ContractMoney::toCents($contract->total_value) ?? 0) }}
                            </div>
                        @endif

                        @if ($contract->total_value !== null && $contract->total_value_mode)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.total_value_mode') }}: </strong>
                                {{ $contract->total_value_mode === 'manual' ? trans('admin/contracts/general.total_mode_manual') : trans('admin/contracts/general.total_mode_automatic') }}
                            </div>
                        @endif

                        @if (isset($contractPreview))
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.preview_planned_total') }}: </strong>
                                {{ \App\Services\ContractMoney::centsToBr(\App\Services\ContractMoney::toCents($contractPreview['planned_total']) ?? 0) }}
                            </div>
                            @if ($contractPreview['difference'] !== null)
                                <div class="col-md-12">
                                    <strong>{{ trans('admin/contracts/general.preview_difference') }}: </strong>
                                    {{ \App\Services\ContractMoney::signedCentsToBr(\App\Services\ContractMoney::signedToCents($contractPreview['difference']) ?? 0) }}
                                </div>
                            @endif
                        @endif

                        @if ($contract->total_installments)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.total_installments') }}: </strong>
                                {{ $contract->total_installments }}
                            </div>
                        @endif

                        @if ($contract->readjustment_index)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.readjustment_index') }}: </strong>
                                {{ $contract->readjustment_index }}
                            </div>
                        @endif

                        @if ($contract->readjustment_month)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.readjustment_month') }}: </strong>
                                {{ $contract->readjustment_month }}
                            </div>
                        @endif

                        @if ($contract->description)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.description') }}: </strong>
                                {!! nl2br(e($contract->description)) !!}
                            </div>
                        @endif

                        @if ($contract->notes)
                            <div class="col-md-12">
                                <strong>{{ trans('general.notes') }}: </strong>
                                {!! nl2br(e($contract->notes)) !!}
                            </div>
                        @endif

                        @if ($contract->adminuser)
                            <div class="col-md-12">
                                <strong>{{ trans('general.created_by') }}: </strong>
                                {{ $contract->adminuser->present()->fullName }}
                            </div>
                        @endif

                    </x-slot:info>

                </x-info-panel>
            </x-box>
        </x-page-column>

    </x-container>

@endsection

@push('css')
    <style>
        .contract-summary {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e5e5e5;
            background: #fff;
        }

        .contract-summary h2 {
            margin-top: 0;
        }

        .contract-summary-actions .btn {
            margin-left: 5px;
            margin-bottom: 5px;
        }

        .contract-summary-facts,
        .contract-financial-summary {
            clear: both;
            border-top: 1px solid #eeeeee;
            margin-top: 12px;
            padding-top: 12px;
        }

        .contract-summary-facts > div,
        .contract-financial-summary > div {
            min-height: 58px;
            margin-bottom: 10px;
        }

        .contract-financial-summary strong,
        .contract-summary-facts strong {
            display: block;
            margin-bottom: 4px;
        }

        .contract-detail-tabs .nav-tabs > li > a {
            white-space: nowrap;
        }

        .contract-detail-tabs .tab-label {
            display: inline-block;
        }

        @media (max-width: 767px) {
            .contract-summary-actions {
                float: none !important;
                clear: both;
                padding-top: 8px;
            }

            .contract-summary-actions .btn:first-child {
                margin-left: 0;
            }

            .contract-detail-tabs .nav-tabs > li > a {
                white-space: normal;
                text-align: left;
            }

        }
    </style>
@endpush

@push('js')
    <script nonce="{{ csrf_token() }}">
        document.addEventListener('DOMContentLoaded', function () {
            var tabContainer = document.querySelector('.contract-detail-tabs');
            if (!tabContainer) {
                return;
            }

            var tabs = function () {
                return Array.prototype.slice.call(tabContainer.querySelectorAll('[role="tab"]'));
            };

            var syncTabs = function (activeTab) {
                tabs().forEach(function (tab) {
                    var active = tab === activeTab;
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.setAttribute('tabindex', active ? '0' : '-1');
                });
            };

            tabContainer.addEventListener('keydown', function (event) {
                var currentTabs = tabs();
                var currentIndex = currentTabs.indexOf(event.target);
                if (currentIndex === -1 || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                var nextIndex = currentIndex;
                if (event.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % currentTabs.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (currentIndex - 1 + currentTabs.length) % currentTabs.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = currentTabs.length - 1;
                }

                currentTabs[nextIndex].focus();
                currentTabs[nextIndex].click();
            });

            if (window.jQuery) {
                jQuery(tabContainer).on('shown.bs.tab', function (event) {
                    syncTabs(event.target);
                });
            }

            var activeTab = tabContainer.querySelector('[role="tab"][aria-selected="true"]');
            if (activeTab) {
                syncTabs(activeTab);
            }
        });
    </script>
@endpush

@section('moar_scripts')
    @if(! $contract->trashed())
        @can('files', $contract)
            @include ('modals.upload-file', ['item_type' => 'contracts', 'item_id' => $contract->id])
        @endcan
    @endif

    @include ('partials.bootstrap-table', ['exportFile' => 'contracts-' . $contract->name . '-export', 'search' => false])
@endsection
