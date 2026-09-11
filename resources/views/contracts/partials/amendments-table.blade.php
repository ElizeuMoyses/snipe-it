@php
    $isBrazilian = in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true);
    $contractCurrency = $isBrazilian ? trans('general.currency') : $snipeSettings->default_currency;
    $formatAmendmentDate = function ($date) use ($isBrazilian) {
        if (! $date) {
            return '—';
        }

        return $isBrazilian
            ? $date->format('d/m/Y')
            : Helper::getFormattedDateObject($date, 'date', false);
    };
    $formatAmendmentMoney = fn ($value) => \App\Services\ContractFinancialSummary::formatCents(
        \App\Services\ContractFinancialSummary::toCents($value),
        $contractCurrency
    );
@endphp

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
                <td>
                    {{ \Illuminate\Support\Str::limit($amendment->description, 80) }}
                    @if($amendment->rectifies_amendment_id)
                        <br><small>{{ trans('admin/contracts/general.rectification_of') }} #{{ $amendment->rectifies_amendment_id }}</small>
                    @endif
                </td>
                <td>{{ $formatAmendmentDate($amendment->effective_date) }}</td>
                <td>
                    @if ($amendment->old_value !== null)
                        {{ $formatAmendmentMoney($amendment->old_value) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>
                    @if ($amendment->new_value !== null)
                        {{ $formatAmendmentMoney($amendment->new_value) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>
                    @if ($amendment->old_end_date)
                        {{ $formatAmendmentDate($amendment->old_end_date) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>
                    @if ($amendment->new_end_date)
                        {{ $formatAmendmentDate($amendment->new_end_date) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td>{{ $amendment->ticket_reference ?? '—' }}</td>
                <td>
                    @php
                        $uploadCount = $amendment->uploads->count();
                    @endphp
                    @if ($uploadCount > 0)
                        <span class="badge badge-info">{{ $uploadCount }}</span>
                    @endif
                    @can('files', $contract)
                        <button type="button" class="btn btn-sm btn-default"
                                data-toggle="modal"
                                data-target="#uploadModal-{{ $amendment->id }}"
                                title="{{ trans('admin/contracts/general.attach_file') }}"
                                aria-label="{{ trans('admin/contracts/general.attach_file') }}">
                            <i class="fas fa-paperclip" aria-hidden="true"></i>
                            <span class="visible-xs">{{ trans('admin/contracts/general.attach_file') }}</span>
                        </button>
                    @endcan
                </td>
                <td>{{ $amendment->adminuser?->present()->fullName ?? '—' }}</td>
                <td>
                    @can('update', $contract)
                        <nobr>
                            <a href="{{ route('contracts.amendments.edit', [$contract->id, $amendment->id]) }}" class="btn btn-warning btn-sm" title="{{ trans('button.edit') }}" aria-label="{{ trans('button.edit') }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                                <span class="visible-xs">{{ trans('button.edit') }}</span>
                            </a>

                            @if ($amendment->hasAppliedEffects())
                                <span class="btn btn-default btn-sm disabled"
                                      title="{{ trans('admin/contracts/message.amendment.delete.applied') }}"
                                      aria-label="{{ trans('admin/contracts/message.amendment.delete.applied') }}">
                                    <i class="fas fa-lock" aria-hidden="true"></i>
                                </span>
                            @else
                                <form method="POST" action="{{ route('contracts.amendments.destroy', [$contract->id, $amendment->id]) }}" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ trans('admin/contracts/message.amendment.delete.confirm') }}')">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </nobr>
                    @endcan
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Upload modals for each amendment. The file inventory itself lives in the Files tab. --}}
@if ($contract->amendments->count() > 0)
    @foreach ($contract->amendments as $amendment)
        @can('files', $contract)
            @include('modals.upload-file', [
                'item_type' => 'contract_amendments',
                'item_id'   => $amendment->id,
                'modal_id'  => 'uploadModal-' . $amendment->id,
            ])
        @endcan
    @endforeach
@endif
