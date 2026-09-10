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

            <x-table.contracts
                name="contracts"
                :route="route('api.contracts.index', $showArchived ? ['archived' => 1] : [])"
                :table_header="$showArchived ? trans('admin/contracts/general.archived_contracts') : trans('admin/contracts/general.contracts')"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'contracts-export', 'search' => true, 'showArchived' => $showArchived])
@stop
