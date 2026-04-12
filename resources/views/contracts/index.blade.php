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

            <x-table.contracts
                name="contracts"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'contracts-export', 'search' => true])
@stop
