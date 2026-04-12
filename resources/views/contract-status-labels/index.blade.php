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

            <x-table.contract-status-labels
                name="contract-status-labels"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table')
@stop
