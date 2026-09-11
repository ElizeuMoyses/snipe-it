@extends('layouts/default')

@section('title')
{{ trans('admin/contract_types/general.view') }} - {{ $item->name }}
@parent
@stop

@section('content')
    <x-container columns="2">
        <x-page-column class="col-md-9 main-panel">
            <x-box>
                <table class="table">
                    <tbody>
                        <tr><td class="text-right" style="width:30%"><strong>{{ trans('general.name') }}:</strong></td><td>{{ $item->name }}</td></tr>
                        <tr><td class="text-right"><strong>{{ trans('admin/contract_types/general.code') }}:</strong></td><td><code>{{ $item->code }}</code></td></tr>
                        <tr><td class="text-right"><strong>{{ trans('admin/contract_types/general.is_active') }}:</strong></td><td>{{ $item->is_active ? trans('general.yes') : trans('general.no') }}</td></tr>
                        <tr><td class="text-right"><strong>{{ trans('admin/contract_types/table.contracts_count') }}:</strong></td><td>{{ $item->contracts_count }}</td></tr>
                        @if ($item->notes)
                            <tr><td class="text-right"><strong>{{ trans('general.notes') }}:</strong></td><td>{!! nl2br(e($item->notes)) !!}</td></tr>
                        @endif
                    </tbody>
                </table>
            </x-box>
        </x-page-column>
        <x-page-column class="col-md-3 hidden-print">
            <x-box class="side-box expanded">
                <div style="text-align:center;padding:10px;">
                    @can('update', $item)
                        <a href="{{ route('contract-types.edit', $item) }}" class="btn btn-warning btn-sm"><i class="fas fa-pencil-alt"></i> {{ trans('general.edit') }}</a>
                    @endcan
                    @can('delete', $item)
                        @if ($item->isDeletable())
                            <form method="POST" action="{{ route('contract-types.destroy', $item) }}" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ trans('general.are_you_sure') }}')"><i class="fas fa-trash"></i> {{ trans('general.delete') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </x-box>
        </x-page-column>
    </x-container>
@endsection
