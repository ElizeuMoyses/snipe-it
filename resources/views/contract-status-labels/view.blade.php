@extends('layouts/default')

{{-- Page title --}}
@section('title')

  {{ trans('admin/contract_status_labels/general.view') }} -
  {{ $statuslabel->name }}

  @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container columns="2">
        <x-page-column class="col-md-9 main-panel">
            <x-box>
                <table class="table">
                    <tbody>
                        <tr>
                            <td class="text-right" style="width: 30%"><strong>{{ trans('general.name') }}:</strong></td>
                            <td>{{ $statuslabel->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>{{ trans('admin/contract_status_labels/general.scope') }}:</strong></td>
                            <td>{{ ucfirst($statuslabel->scope) }}</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>{{ trans('admin/contract_status_labels/general.meta_type') }}:</strong></td>
                            <td>{{ ucfirst($statuslabel->meta_type) }}</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>{{ trans('admin/contract_status_labels/general.color') }}:</strong></td>
                            <td>
                                @if($statuslabel->color)
                                    <span style="background-color: {{ $statuslabel->color }}; padding: 2px 10px; border-radius: 3px;">&nbsp;</span>
                                    {{ $statuslabel->color }}
                                @endif
                            </td>
                        </tr>
                        @if($statuslabel->icon)
                        <tr>
                            <td class="text-right"><strong>{{ trans('admin/contract_status_labels/general.icon') }}:</strong></td>
                            <td><i class="{{ $statuslabel->icon }}"></i> {{ $statuslabel->icon }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-right"><strong>{{ trans('admin/contract_status_labels/general.is_default') }}:</strong></td>
                            <td>{{ $statuslabel->is_default ? trans('general.yes') : trans('general.no') }}</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>{{ trans('admin/contract_status_labels/general.sort_order') }}:</strong></td>
                            <td>{{ $statuslabel->sort_order }}</td>
                        </tr>
                        @if($statuslabel->notes)
                        <tr>
                            <td class="text-right"><strong>{{ trans('general.notes') }}:</strong></td>
                            <td>{!! nl2br(e($statuslabel->notes)) !!}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </x-box>
        </x-page-column>
        <x-page-column class="col-md-3 hidden-print">
            <x-box class="side-box expanded">
                <div style="text-align: center; padding: 10px;">
                    @can('update', \App\Models\Contract::class)
                        <a href="{{ route('contract-status-labels.edit', $statuslabel->id) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-pencil-alt"></i> {{ trans('general.edit') }}
                        </a>
                    @endcan
                    @can('delete', \App\Models\Contract::class)
                        @if($statuslabel->isDeletable())
                            <form method="POST" action="{{ route('contract-status-labels.destroy', $statuslabel->id) }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ trans('general.are_you_sure') }}')">
                                    <i class="fas fa-trash"></i> {{ trans('general.delete') }}
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </x-box>
        </x-page-column>
    </x-container>
@endsection
