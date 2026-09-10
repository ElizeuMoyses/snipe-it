@extends('layouts/default')

@section('title')
{{ trans('admin/contract_types/general.title') }}
@parent
@stop

@section('content')
    <x-container>
        <x-box>
            <x-table.contract-types name="contract-types" />
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table')
@stop
