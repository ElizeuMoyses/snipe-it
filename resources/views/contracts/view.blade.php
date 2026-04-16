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

                    <x-tabs.nav-item
                        name="installments"
                        icon="fas fa-money-bill-wave"
                        label="{{ trans('admin/contracts/general.installments') }}"
                        count="{{ $contract->installments->count() }}"
                        tooltip="{{ trans('admin/contracts/general.installments') }}"
                    />

                    <x-tabs.nav-item
                        name="amendments"
                        icon="fas fa-file-signature"
                        label="{{ trans('admin/contracts/general.amendments') }}"
                        count="{{ $contract->amendments->count() }}"
                        tooltip="{{ trans('admin/contracts/general.amendments') }}"
                    />

                    <x-tabs.nav-item
                        name="contract-assets"
                        icon="fas fa-link"
                        label="{{ trans('admin/contracts/general.linked_assets') }}"
                        count="{{ $contract->assets->count() }}"
                        tooltip="{{ trans('admin/contracts/general.linked_assets') }}"
                    />

                    @php
                        $installmentUploadsCount = $contract->installments->sum(fn ($i) => $i->uploads->count());
                        $totalUploadsCount = $contract->uploads()->count() + $installmentUploadsCount;
                    @endphp
                    <x-tabs.files-tab :item="$contract" count="{{ $totalUploadsCount }}"/>
                    <x-tabs.upload-tab :item="$contract"/>

                </x-slot:tabnav>

                <x-slot:tabpanes>

                    <!-- start installments tab pane -->
                    <x-tabs.pane name="installments">
                        @can('installments', $contract)
                            @if(! in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled']))
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
                            @if(! in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled']))
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
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-md-12">
                                    <form method="POST" action="{{ route('contracts.assets.attach', $contract->id) }}" class="form-inline">
                                        @csrf
                                        <div class="form-group" style="margin-right: 10px;">
                                            <select name="asset_id" class="js-data-ajax" data-endpoint="hardware" data-placeholder="{{ trans('admin/contracts/general.select_asset') }}" style="min-width: 300px;">
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-link"></i>
                                            {{ trans('admin/contracts/general.link_asset') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
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

                    <!-- start files tab pane -->
                    <x-tabs.pane name="files" class="{{ $totalUploadsCount == 0 ? 'hidden-print' : '' }}">
                        <x-table.files object_type="contracts" :object="$contract"/>

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
                                                    <td>{{ $upload->created_at->format('Y-m-d H:i') }}</td>
                                                    <td>
                                                        <a href="{{ route('ui.files.show', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $upload->id]) }}" class="btn btn-sm btn-default" data-tooltip="true" title="{{ trans('general.download') }}">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        @can('files', $contract)
                                                            <form method="POST" action="{{ route('ui.files.destroy', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $upload->id]) }}" style="display:inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger" data-tooltip="true" title="{{ trans('button.delete') }}" onclick="return confirm('{{ trans('general.are_you_sure') }}')">
                                                                    <i class="fas fa-trash"></i>
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

                        @if ($contract->billing_day)
                            <div class="col-md-12">
                                <strong>{{ trans('admin/contracts/general.billing_day') }}: </strong>
                                {{ $contract->billing_day }}
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
