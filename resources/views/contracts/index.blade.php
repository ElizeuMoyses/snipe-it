@extends('layouts/default')

@php
    $showArchived = request()->boolean('archived') || request()->boolean('deleted');
@endphp

{{-- Page title --}}
@section('title')
{{ $showArchived ? trans('admin/contracts/general.archived_contracts') : trans('admin/contracts/general.contracts') }}
@parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>

            @php
                $hasContractFilters = request()->filled('company_id')
                    || request()->filled('supplier_id')
                    || request()->filled('status_label_id')
                    || request()->filled('validity')
                    || request()->filled('due_status');
            @endphp

            <form method="GET" action="{{ route('contracts.index') }}" class="contract-filters" aria-describedby="contract-filters-help">
                <div class="row">
                    <div class="col-md-2 col-sm-6">
                        <label for="contract-company-filter">{{ trans('general.company') }}</label>
                        <select id="contract-company-filter" name="company_id" class="form-control">
                            <option value="">{{ trans('admin/contracts/general.all_companies') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label for="contract-supplier-filter">{{ trans('general.supplier') }}</label>
                        <select id="contract-supplier-filter" name="supplier_id" class="form-control">
                            <option value="">{{ trans('admin/contracts/general.all_suppliers') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label for="contract-status-filter">{{ trans('admin/contracts/general.status_label') }}</label>
                        <select id="contract-status-filter" name="status_label_id" class="form-control">
                            <option value="">{{ trans('admin/contracts/general.all_statuses') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected((string) request('status_label_id') === (string) $status->id)>
                                    {{ $status->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label for="contract-validity-filter">{{ trans('admin/contracts/general.validity') }}</label>
                        <select id="contract-validity-filter" name="validity" class="form-control">
                            <option value="">{{ trans('admin/contracts/general.all_validities') }}</option>
                            @foreach (['current', 'expiring', 'expired', 'indefinite', 'future'] as $validity)
                                <option value="{{ $validity }}" @selected(request('validity') === $validity)>
                                    {{ trans('admin/contracts/general.validity_' . $validity) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label for="contract-due-filter">{{ trans('admin/contracts/general.due_status') }}</label>
                        <select id="contract-due-filter" name="due_status" class="form-control">
                            <option value="">{{ trans('admin/contracts/general.all_due_statuses') }}</option>
                            <option value="upcoming" @selected(request('due_status') === 'upcoming')>
                                {{ trans('admin/contracts/general.due_upcoming') }}
                            </option>
                            <option value="overdue" @selected(request('due_status') === 'overdue')>
                                {{ trans('admin/contracts/general.due_overdue') }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-1 col-sm-12 contract-filter-actions">
                        <label class="sr-only" for="contract-filter-submit">{{ trans('button.apply') }}</label>
                        <button id="contract-filter-submit" type="submit" class="btn btn-primary">
                            <i class="fas fa-filter" aria-hidden="true"></i>
                            {{ trans('button.apply') }}
                        </button>
                        @if ($hasContractFilters)
                            <a href="{{ route('contracts.index') }}" class="btn btn-link">
                                {{ trans('button.clear') }}
                            </a>
                        @endif
                    </div>
                </div>
                <p id="contract-filters-help" class="help-block">
                    {{ trans('admin/contracts/general.list_filters_help') }}
                    @if ($hasContractFilters)
                        {{ trans('admin/contracts/general.active_filters_help') }}
                    @endif
                </p>
            </form>

            <x-table.contracts
                name="contracts"
                :route="$contractsApiUrl"
                :table_header="$showArchived ? trans('admin/contracts/general.archived_contracts') : trans('admin/contracts/general.contracts')"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'contracts-export', 'search' => true, 'showArchived' => $showArchived])
@stop

@push('css')
    <style>
        .contract-filters {
            margin-bottom: 20px;
            padding: 15px;
            background: #f7f7f7;
            border: 1px solid #e5e5e5;
        }

        .contract-filters .form-control,
        .contract-filter-actions .btn {
            margin-top: 5px;
        }

        .contract-filter-actions {
            padding-top: 25px;
        }

        .contract-list-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        @media (max-width: 767px) {
            .contract-filter-actions {
                padding-top: 15px;
            }

            .contract-filter-actions .btn {
                margin-right: 5px;
            }
        }
    </style>
@endpush
