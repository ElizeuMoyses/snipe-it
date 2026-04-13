@extends('layouts/default')

{{-- Page title --}}
@section('title')

  {{ trans('admin/contracts/general.view') }} -
  {{ $contract->name }}

  @parent
@stop

@section('header_right')
    <i class="fa-regular fa-2x fa-square-caret-right pull-right" id="expand-info-panel-button" data-tooltip="true" title="{{ trans('button.show_hide_info') }}"></i>
@endsection

{{-- Page content --}}
@section('content')
    <x-container columns="2">
        <x-page-column class="col-md-9 main-panel">
            <x-tabs>
                <x-slot:tabnav>

                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#installments" role="tab">
                            {{ trans('admin/contracts/general.installments') }}
                            @if($contract->installments->count() > 0)
                                <badge class="badge badge-secondary">{{ $contract->installments->count() }}</badge>
                            @endif
                        </a>
                    </li>

                    <x-tabs.files-tab :item="$contract" count="{{ $contract->uploads()->count() }}"/>
                    <x-tabs.upload-tab :item="$contract"/>

                </x-slot:tabnav>

                <x-slot:tabpanes>

                    <!-- start installments tab pane -->
                    <x-tabs.pane name="installments">
                        @can('installments', $contract)
                            @if(! in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled']))
                                <div class="row" style="margin-bottom: 10px;">
                                    <div class="col-md-12 text-right">
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

                    <!-- start files tab pane -->
                    <x-tabs.pane name="files" class="{{ $contract->uploads->count() == 0 ? 'hidden-print' : '' }}">
                        <x-table.files object_type="contracts" :object="$contract"/>
                    </x-tabs.pane>
                    <!-- end files tab pane -->

                </x-slot:tabpanes>

            </x-tabs>
        </x-page-column>
        <x-page-column class="col-md-3 hidden-print">

            <x-box class="side-box expanded">
                <x-info-panel :infoPanelObj="$contract">

                    <x-slot:buttons>
                        <x-button :item="$contract" permission="update" :route="route('contracts.edit', $contract->id)" class="btn-warning"  />
                        <x-button.delete :item="$contract" />
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
                                <strong>{{ trans('admin/contracts/general.contract_type') }}: </strong>
                                {{ trans('admin/contracts/general.type_' . $contract->contract_type) }}
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
                                {{ $contract->start_date }}
                            </div>
                        @endif

                        @if ($contract->end_date)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.end_date') }}: </strong>
                                {{ $contract->end_date }}
                            </div>
                        @endif

                        @if ($contract->billing_cycle)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.billing_cycle') }}: </strong>
                                {{ trans('admin/contracts/general.cycle_' . $contract->billing_cycle) }}
                            </div>
                        @endif

                        @if ($contract->installment_value)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.installment_value') }}: </strong>
                                {{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($contract->installment_value) }}
                            </div>
                        @endif

                        @if ($contract->total_value)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.total_value') }}: </strong>
                                {{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($contract->total_value) }}
                            </div>
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

@section('moar_scripts')
    @can('files', $contract)
        @include ('modals.upload-file', ['item_type' => 'contracts', 'item_id' => $contract->id])
    @endcan

    @include ('partials.bootstrap-table', ['exportFile' => 'contracts-' . $contract->name . '-export', 'search' => false])
@endsection
