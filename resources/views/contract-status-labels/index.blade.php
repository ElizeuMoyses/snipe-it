@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/contract_status_labels/general.title') }}
@parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>

            <x-table
                name="contractStatusLabel"
                buttons="contractStatusLabelButtons"
                fixed_right_number="1"
                fixed_number="1"
                api_url="{{ route('api.contract-status-labels.index') }}"
                :presenter="\App\Presenters\ContractStatusLabelPresenter::dataTableLayout()"
                export_filename="export-contract-status-labels-{{ date('Y-m-d') }}"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table')
@stop
