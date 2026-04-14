<table
    data-cookie-id-table="contractAssetsTable"
    data-id-table="contractAssetsTable"
    id="contractAssetsTable"
    class="table table-striped snipe-table">
    <thead>
        <tr>
            <th>{{ trans('admin/hardware/table.asset_tag') }}</th>
            <th>{{ trans('admin/hardware/table.name') }}</th>
            <th>{{ trans('admin/hardware/form.model') }}</th>
            <th>{{ trans('admin/hardware/table.serial') }}</th>
            <th>{{ trans('admin/hardware/table.status') }}</th>
            @can('update', $contract)
                <th>{{ trans('table.actions') }}</th>
            @endcan
        </tr>
    </thead>
    <tbody>
        @foreach ($contract->assets as $asset)
            <tr>
                <td>
                    @can('view', $asset)
                        <a href="{{ route('hardware.show', $asset->id) }}">
                            {{ $asset->asset_tag }}
                        </a>
                    @else
                        {{ $asset->asset_tag }}
                    @endcan
                </td>
                <td>{{ $asset->name ?? '—' }}</td>
                <td>{{ $asset->model?->name ?? '—' }}</td>
                <td>{{ $asset->serial ?? '—' }}</td>
                <td>
                    @if ($asset->assetstatus)
                        <span class="label" style="background-color: {{ $asset->assetstatus->color ?? '#888' }}">
                            {{ $asset->assetstatus->name }}
                        </span>
                    @else
                        &mdash;
                    @endif
                </td>
                @can('update', $contract)
                    <td>
                        <form method="POST" action="{{ route('contracts.assets.detach', [$contract->id, $asset->id]) }}" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="return confirm('{{ trans('admin/contracts/message.asset.detach.confirm') }}')">
                                <i class="fas fa-unlink" aria-hidden="true"></i>
                                <span class="hidden-xs">{{ trans('admin/contracts/general.unlink_asset') }}</span>
                            </button>
                        </form>
                    </td>
                @endcan
            </tr>
        @endforeach
    </tbody>
</table>
