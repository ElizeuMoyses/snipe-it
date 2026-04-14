<table
    data-cookie-id-table="contractAmendmentsTable"
    data-id-table="contractAmendmentsTable"
    id="contractAmendmentsTable"
    class="table table-striped snipe-table">
    <thead>
        <tr>
            <th>{{ trans('admin/contracts/general.amendment_type') }}</th>
            <th>{{ trans('admin/contracts/general.description') }}</th>
            <th>{{ trans('admin/contracts/general.effective_date') }}</th>
            <th>{{ trans('admin/contracts/general.old_value') }}</th>
            <th>{{ trans('admin/contracts/general.new_value') }}</th>
            <th>{{ trans('admin/contracts/general.old_end_date') }}</th>
            <th>{{ trans('admin/contracts/general.new_end_date') }}</th>
            <th>{{ trans('admin/contracts/general.ticket_reference') }}</th>
            <th>{{ trans('general.file_uploads') }}</th>
            <th>{{ trans('general.created_by') }}</th>
            <th>{{ trans('table.actions') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($contract->amendments as $amendment)
            <tr>
                <td>
                    <span class="label label-default">
                        {{ trans('admin/contracts/general.amendment_type_' . $amendment->amendment_type) }}
                    </span>
                </td>
                <td>{{ Str::limit($amendment->description, 80) }}</td>
                <td>{{ Helper::getFormattedDateObject($amendment->effective_date, 'date', false) }}</td>
                <td>
                    @if ($amendment->old_value)
                        {{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($amendment->old_value) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>
                    @if ($amendment->new_value)
                        {{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($amendment->new_value) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>
                    @if ($amendment->old_end_date)
                        {{ Helper::getFormattedDateObject($amendment->old_end_date, 'date', false) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>
                    @if ($amendment->new_end_date)
                        {{ Helper::getFormattedDateObject($amendment->new_end_date, 'date', false) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>{{ $amendment->ticket_reference ?? '—' }}</td>
                <td>
                    @php
                        $uploadCount = \App\Models\Actionlog::where('item_type', \App\Models\ContractAmendment::class)
                            ->where('item_id', $amendment->id)
                            ->where('action_type', 'uploaded')
                            ->count();
                    @endphp
                    @if ($uploadCount > 0)
                        <span class="badge badge-info">{{ $uploadCount }}</span>
                    @endif
                    @can('files', $contract)
                        <button type="button" class="btn btn-sm btn-default"
                                data-toggle="modal"
                                data-target="#uploadModal-{{ $amendment->id }}">
                            <i class="fas fa-paperclip"></i>
                        </button>
                    @endcan
                </td>
                <td>{{ $amendment->adminuser?->present()->fullName() ?? '—' }}</td>
                <td>
                    @can('update', $contract)
                        <nobr>
                            <a href="{{ route('contracts.amendments.edit', [$contract->id, $amendment->id]) }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>

                            <form method="POST" action="{{ route('contracts.amendments.destroy', [$contract->id, $amendment->id]) }}" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ trans('admin/contracts/message.amendment.delete.confirm') }}')">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </nobr>
                    @endcan
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($contract->amendments->count() > 0)
    {{-- Upload modals for each amendment --}}
    @foreach ($contract->amendments as $amendment)
        @can('files', $contract)
            @include('modals.upload-file', [
                'item_type' => 'contract_amendments',
                'item_id'   => $amendment->id,
                'modal_id'  => 'uploadModal-' . $amendment->id,
            ])
        @endcan
    @endforeach

    {{-- Files listing per amendment --}}
    <hr>
    <h4>{{ trans('general.file_uploads') }}</h4>
    @foreach ($contract->amendments as $amendment)
        @php
            $fileCount = \App\Models\Actionlog::where('item_type', \App\Models\ContractAmendment::class)
                ->where('item_id', $amendment->id)
                ->where('action_type', 'uploaded')
                ->count();
        @endphp
        @if ($fileCount > 0)
            <h5>{{ trans('admin/contracts/general.amendment_type_' . $amendment->amendment_type) }} &mdash; {{ $amendment->effective_date?->format('d/m/Y') }}</h5>
            <x-table.files :object_type="'contract_amendments'" :object_id="$amendment->id" />
        @endif
    @endforeach
@endif
