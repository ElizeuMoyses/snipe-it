@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/contracts/general.contracts') }}
@parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>

            <x-table
                name="contract"
                buttons="contractButtons"
                fixed_right_number="1"
                fixed_number="1"
                api_url="{{ route('api.contracts.index') }}"
                :presenter="\App\Presenters\ContractPresenter::dataTableLayout()"
                export_filename="export-contracts-{{ date('Y-m-d') }}"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'contracts-export', 'search' => true])
@stop
