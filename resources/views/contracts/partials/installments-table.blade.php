{{-- Installments table partial for contract view --}}
@if ($contract->installments->count() > 0)
    <div class="table-responsive">
        <table
            data-cookie="true"
            data-cookie-id-table="contractInstallmentsTable"
            class="table table-striped snipe-table"
            id="contractInstallmentsTable">
            <thead>
                <tr>
                    <th>{{ trans('admin/contracts/general.installment_number') }}</th>
                    <th>{{ trans('admin/contracts/general.reference_date') }}</th>
                    <th>{{ trans('admin/contracts/general.due_date') }}</th>
                    <th>{{ trans('admin/contracts/general.expected_value') }}</th>
                    <th>{{ trans('admin/contracts/general.paid_value') }}</th>
                    <th>{{ trans('admin/contracts/general.payment_date') }}</th>
                    <th>{{ trans('admin/contracts/general.payment_method') }}</th>
                    <th>{{ trans('admin/contracts/general.ticket_reference') }}</th>
                    <th>{{ trans('admin/contracts/general.status_label') }}</th>
                    <th>{{ trans('general.actions') }}</th>
                </tr>
            </thead>
            @php
                // Pre-load all installment-scope statuses once to avoid N+1 queries in the loop
                $allInstallmentStatuses = \App\Models\ContractStatusLabel::where('scope', 'installment')
                    ->orderBy('sort_order')
                    ->get();
            @endphp
            <tbody>
                @foreach ($contract->installments->sortBy('installment_number') as $installment)
                    <tr>
                        <td>{{ $installment->installment_number }}</td>
                        <td>{{ $installment->reference_date ? $installment->reference_date->format('m/Y') : '—' }}</td>
                        <td>{{ $installment->due_date ? $installment->due_date->format('Y-m-d') : '—' }}</td>
                        <td>{{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($installment->expected_value) }}</td>
                        <td>{{ $installment->paid_value !== null ? $snipeSettings->default_currency . Helper::formatCurrencyOutput($installment->paid_value) : '—' }}</td>
                        <td>{{ $installment->payment_date ? $installment->payment_date->format('Y-m-d') : '—' }}</td>
                        <td>{{ $installment->payment_method ?? '—' }}</td>
                        <td>{{ $installment->ticket_reference ?? '—' }}</td>
                        <td>
                            @if($installment->statusLabel)
                                <span class="label" style="background-color: {{ $installment->statusLabel->color ?? '#999' }}">
                                    @if($installment->statusLabel->icon)
                                        <i class="fa {{ $installment->statusLabel->icon }}"></i>
                                    @endif
                                    {{ $installment->statusLabel->name }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <nobr>
                                @can('installments', $contract)
                                    @php
                                        $currentMeta = $installment->statusLabel?->meta_type;
                                        $isTerminal = $installment->statusLabel?->isTerminal();
                                    @endphp

                                    {{-- Register Payment button (only for pending/overdue) --}}
                                    @if(in_array($currentMeta, ['pending', 'overdue']))
                                        <a href="{{ route('contracts.installments.pay', [$contract->id, $installment->id]) }}"
                                           class="btn btn-sm btn-success" data-tooltip="true"
                                           title="{{ trans('admin/contracts/general.register_payment') }}">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                    @endif

                                    {{-- Edit button (only for non-terminal) --}}
                                    @if(! $isTerminal)
                                        <a href="{{ route('contracts.installments.edit', [$contract->id, $installment->id]) }}"
                                           class="btn btn-sm btn-warning" data-tooltip="true"
                                           title="{{ trans('button.edit') }}">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                    @endif

                                    {{-- Status change dropdown (only for non-terminal) --}}
                                    @if(! $isTerminal)
                                        @php
                                            // Allowed destination meta_types (excluding paid — only via payment)
                                            $allowedDestinationMetaTypes = match($currentMeta) {
                                                'pending'  => ['pending', 'cancelled'],
                                                'overdue'  => ['overdue'],
                                                default    => [],
                                            };

                                            $eligibleStatuses = $allInstallmentStatuses
                                                ->whereIn('meta_type', $allowedDestinationMetaTypes)
                                                ->where('id', '!=', $installment->status_label_id)
                                                ->sortByDesc(fn ($s) => $s->meta_type === $currentMeta ? 1 : 0);
                                        @endphp

                                        @if($eligibleStatuses->isNotEmpty())
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown">
                                                    {{ trans('admin/contracts/general.change_status') }} <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-right">
                                                    {{-- Same meta_type (free transition) --}}
                                                    @foreach($eligibleStatuses->where('meta_type', $currentMeta) as $status)
                                                        <li>
                                                            <form method="POST" action="{{ route('contracts.installments.status.update', [$contract->id, $installment->id]) }}" style="display:inline;">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="status_label_id" value="{{ $status->id }}">
                                                                <button type="submit" class="btn btn-link">
                                                                    <span class="label" style="background-color: {{ $status->color }}">
                                                                        <i class="fa {{ $status->icon }}"></i> {{ $status->name }}
                                                                    </span>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endforeach

                                                    {{-- Separator between meta_types --}}
                                                    @if($eligibleStatuses->where('meta_type', $currentMeta)->isNotEmpty() && $eligibleStatuses->where('meta_type', '!=', $currentMeta)->isNotEmpty())
                                                        <li role="separator" class="divider"></li>
                                                    @endif

                                                    {{-- Cross meta_type (restricted transition) --}}
                                                    @foreach($eligibleStatuses->where('meta_type', '!=', $currentMeta) as $status)
                                                        <li>
                                                            <form method="POST" action="{{ route('contracts.installments.status.update', [$contract->id, $installment->id]) }}" style="display:inline;">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="status_label_id" value="{{ $status->id }}">
                                                                <button type="submit" class="btn btn-link">
                                                                    <span class="label" style="background-color: {{ $status->color }}">
                                                                        <i class="fa {{ $status->icon }}"></i> {{ $status->name }}
                                                                    </span>
                                                                    <small class="text-muted">({{ $status->meta_type }})</small>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @endif

                                    {{-- Delete button (only for non-terminal) --}}
                                    @if(! $isTerminal)
                                        <form method="POST" action="{{ route('contracts.installments.destroy', [$contract->id, $installment->id]) }}" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" data-tooltip="true"
                                                    title="{{ trans('button.delete') }}"
                                                    onclick="return confirm('{{ trans('general.are_you_sure') }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Attach file (for all installments) --}}
                                    @can('files', App\Models\Contract::class)
                                        <a href="#" data-toggle="modal" data-target="#uploadFileModalInstallment"
                                           class="btn btn-sm btn-default js-installment-upload-btn" data-tooltip="true"
                                           title="{{ trans('button.upload') }}"
                                           data-installment-id="{{ $installment->id }}"
                                           data-action-url="{{ url('contract_installments/' . $installment->id . '/files') }}">
                                            <i class="fas fa-paperclip"></i>
                                        </a>
                                    @endcan
                                @endcan
                            </nobr>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>{{ trans('admin/contracts/general.totals') }}</strong></td>
                    <td><strong>{{ $snipeSettings->default_currency }}{{ Helper::formatCurrencyOutput($contract->installments->sum('expected_value')) }}</strong></td>
                    <td><strong>{{ $contract->installments->whereNotNull('paid_value')->sum('paid_value') > 0 ? $snipeSettings->default_currency . Helper::formatCurrencyOutput($contract->installments->whereNotNull('paid_value')->sum('paid_value')) : '—' }}</strong></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Single shared upload modal for installments --}}
    @can('files', App\Models\Contract::class)
        <div class="modal fade" id="uploadFileModalInstallment" tabindex="-1" role="dialog" aria-labelledby="uploadFileModalInstallmentLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="uploadFileModalInstallmentLabel">{{ trans('general.file_upload') }}</h4>
                    </div>
                    <form method="POST" id="installmentUploadForm" action="" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="btn btn-theme btn-block">
                                        {{ trans('button.select_files') }}
                                        <input type="file" name="file[]" multiple class="js-uploadFile" data-maxsize="{{ Helper::file_upload_max_size() }}" accept="{{ config('filesystems.allowed_upload_mimetypes') }}" style="display:none" required>
                                    </label>
                                </div>
                                <div class="col-md-12">
                                    <p class="help-block">{{ trans('general.upload_filetypes_help', ['allowed_filetypes' => config('filesystems.allowed_upload_extensions'), 'size' => Helper::file_upload_max_size_readable()]) }}</p>
                                </div>
                                <div class="col-md-12">
                                    <x-input.textarea name="notes" :value="old('notes')" :placeholder="trans('general.notes')" rows="3" aria-label="file" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="#" class="pull-left" data-dismiss="modal">{{ trans('button.cancel') }}</a>
                            <button type="submit" class="btn btn-theme" formnovalidate>{{ trans('button.upload') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script nonce="{{ csrf_token() }}">
            document.addEventListener('DOMContentLoaded', function() {
                var uploadBtns = document.querySelectorAll('.js-installment-upload-btn');
                var form = document.getElementById('installmentUploadForm');

                // Set form action from pre-built URL on button click
                uploadBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var actionUrl = this.getAttribute('data-action-url');
                        if (actionUrl) {
                            form.action = actionUrl;
                        }
                    });
                });

                // Guard: prevent submit if action is still empty
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!form.action || form.action === window.location.href || form.action === '') {
                            e.preventDefault();
                            alert('{{ trans("general.error") }}: Upload action URL not set. Please close this modal and try again.');
                        }
                    });
                }
            });
        </script>
    @endcan
@else
    <div class="col-md-12">
        <p>{{ trans('general.no_results') }}</p>
    </div>
@endif
