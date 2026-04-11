<?php
?>
@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.unaccepted_asset_report') }}
    @parent
@stop

@section('header_right')

    <div class="btn-toolbar" role="toolbar">
        <div class="btn-group mr-2" role="group">
            @if($showDeleted)
                <a href="{{ route('reports/unaccepted_assets') }}" class="btn btn-default" ><i class="fa fa-trash icon-white" aria-hidden="true"></i> {{ trans('general.hide_deleted') }}</a>
            @else
                <a href="{{ route('reports/unaccepted_assets', ['deleted' => 'deleted']) }}" class="btn btn-default" ><i class="fa fa-trash icon-white" aria-hidden="true"></i> {{ trans('general.show_deleted') }}</a>
            @endif
        </div>
        <div class="btn-group mr-2" role="group">
            <form method="POST" action="{{ route('reports/export/unaccepted_assets') }}" accept-charset="UTF-8" class="form-horizontal">
            {{csrf_field()}}
            <button type="submit" class="btn btn-default"><i class="fa fa-download icon-white" aria-hidden="true"></i> {{ trans('general.download_all') }}</button>
            </form>
        </div>
    </div>
@stop

{{-- Page content --}}
@section('content')

{{-- EULA Summary Stats - same design as dashboard stat-cards --}}
@if(isset($eulaStats) && $eulaStats['total'] > 0)
<style>
.eula-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.eula-stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    position: relative;
    overflow: hidden;
    text-decoration: none;
}
.eula-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.eula-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--card-color);
}
.eula-stat-card.critical { --card-color: #e74c3c; }
.eula-stat-card.warning  { --card-color: #f39c12; }
.eula-stat-card.ok       { --card-color: #27ae60; }
.eula-stat-card.total    { --card-color: #2980b9; }

.eula-stat-card-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.eula-stat-card-info h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
    line-height: 1;
}
.eula-stat-card-info p {
    margin: 0.35rem 0 0 0;
    color: #7f8c8d;
    font-weight: 500;
    font-size: 0.9rem;
}
.eula-stat-card-info small {
    color: #95a5a6;
    font-size: 0.75rem;
}
.eula-stat-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-color);
    color: white;
    font-size: 1.5rem;
}

/* Dark mode */
[data-theme="dark"] .eula-stat-card {
    background: var(--box-bg, #2d3236);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.28);
}
[data-theme="dark"] .eula-stat-card-info h3 {
    color: var(--header-color, #e0e0e0);
}
[data-theme="dark"] .eula-stat-card-info p,
[data-theme="dark"] .eula-stat-card-info small {
    color: var(--text-color, #a0a0a0);
}
</style>

<div class="eula-stats-grid">
    <div class="eula-stat-card critical">
        <div class="eula-stat-card-content">
            <div class="eula-stat-card-info">
                <h3>{{ $eulaStats['critical'] }}</h3>
                <p>{{ trans('general.eula_critical') }}</p>
                <small>{{ trans('general.eula_over_30_days') }}</small>
            </div>
            <div class="eula-stat-card-icon">
                <i class="fa fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
    <div class="eula-stat-card warning">
        <div class="eula-stat-card-content">
            <div class="eula-stat-card-info">
                <h3>{{ $eulaStats['warning'] }}</h3>
                <p>{{ trans('general.eula_attention') }}</p>
                <small>{{ trans('general.eula_7_to_30_days') }}</small>
            </div>
            <div class="eula-stat-card-icon">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>
    <div class="eula-stat-card ok">
        <div class="eula-stat-card-content">
            <div class="eula-stat-card-info">
                <h3>{{ $eulaStats['ok'] }}</h3>
                <p>{{ trans('general.eula_recent') }}</p>
                <small>{{ trans('general.eula_under_7_days') }}</small>
            </div>
            <div class="eula-stat-card-icon">
                <i class="fa fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="eula-stat-card total">
        <div class="eula-stat-card-content">
            <div class="eula-stat-card-info">
                <h3>{{ $eulaStats['total'] }}</h3>
                <p>{{ trans('general.eula_total_pending') }}</p>
                <small>{{ trans('general.eula_oldest') }}: {{ $eulaStats['oldest_days'] }} {{ trans('general.days') }}</small>
            </div>
            <div class="eula-stat-card-icon">
                <i class="fa fa-file-text-o"></i>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="box box-default">
      <div class="box-body">
            <table
                data-cookie-id-table="unacceptedAssetsReport"
                data-id-table="unacceptedAssetsReport"
                data-side-pagination="client"
                data-sort-order="asc"
                data-sort-name="created_at"
                data-advanced-search="false"
                id="unacceptedAssetsReport"
                data-fixed-number="false"
                data-fixed-right-number="false"
                class="table table-striped snipe-table"
                data-export-options='{
                    "fileName": "maintenance-report-{{ date('Y-m-d') }}",
                    "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                    }'>
            <thead>
              <tr role="row">
                <th class="col-sm-1" data-field="created_at" data-searchable="false" data-sortable="true">{{ trans('general.date') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('general.type') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('admin/companies/table.title') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('general.category') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('admin/hardware/form.model') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('general.name') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('admin/hardware/table.asset_tag') }}</th>
                <th class="col-sm-1" data-sortable="true" >{{ trans('admin/hardware/table.checkoutto') }}</th>
                <th class="col-sm-1" data-field="days_pending" data-sortable="true" data-sorter="numericSorter">{{ trans('general.days_pending') }}</th>
                <th class="col-sm-1" data-sortable="false">Link EULA</th>
                <th class="col-md-1"><span class="line"></span>{{ trans('table.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @if ($itemsForReport)
                  @foreach ($itemsForReport as $item)
                      <tr @if($item->acceptance->trashed()) style="text-decoration: line-through" @endif>
                          {{-- Created date --}}
                          <td>
                              {{ Helper::getFormattedDateObject($item->acceptance->created_at, 'datetime', false) }}
                          </td>
                          {{-- Item Type --}}
                          <td>{{ $item->type }}</td>
                          {{-- Company name --}}
                          <td>{{ $item->plain_text_company }}</td>

                          {{-- Category --}}
                          <td>{{ $item->plain_text_category }}</td>

                          {{-- Model --}}
                          <td>{{ $item->plain_text_model }}</td>

                          {{-- Name --}}
                          <td>{{ $item->plain_text_name }}</td>

                          {{-- Asset tag or blank --}}
                          <td>{{ $item->asset_tag }}</td>

                          {{-- Assigned To (with soft-delete strike if needed) --}}
                          <td @if(!$item->assignee || (method_exists($item->assignee, 'trashed') && $item->assignee->trashed())) style="text-decoration: line-through" @endif>
                              {!! $item->assignee
                                  ? optional($item->assignee->present())->nameUrl() ?? e($item->assignee->name)
                                  : trans('admin/reports/general.deleted_user') !!}
                          </td>

                          {{-- Days Pending --}}
                          @php
                              $daysPending = $item->acceptance->getDaysPending();
                              $priorityClass = $item->acceptance->getPriorityClass();
                          @endphp
                          <td data-value="{{ $daysPending }}" class="text-center">
                              @if($priorityClass === 'danger-high')
                                  <span class="label label-danger" style="font-size: 13px; padding: 4px 10px;"><i class="fa fa-exclamation-circle"></i> {{ $daysPending }} {{ trans('general.days') }}</span>
                              @elseif($priorityClass === 'danger')
                                  <span class="label label-warning" style="font-size: 13px; padding: 4px 10px; background-color: #e67e22;"><i class="fa fa-exclamation-triangle"></i> {{ $daysPending }} {{ trans('general.days') }}</span>
                              @elseif($priorityClass === 'warning')
                                  <span class="label label-warning" style="font-size: 13px; padding: 4px 10px;"><i class="fa fa-clock-o"></i> {{ $daysPending }} {{ trans('general.days') }}</span>
                              @else
                                  <span class="label label-success" style="font-size: 13px; padding: 4px 10px;"><i class="fa fa-check-circle"></i> {{ $daysPending }} {{ trans('general.days') }}</span>
                              @endif
                          </td>

                          {{-- Copy EULA signing link --}}
                          <td class="text-nowrap">
                              @if($item->sign_url && $item->acceptance->token && !is_null($item->acceptance->token_expires_at) && $item->acceptance->token_expires_at->isFuture())
                                  <div class="input-group input-group-sm" style="max-width: 280px;">
                                      <input type="text" class="form-control" value="{{ $item->sign_url }}" readonly id="link-{{ $item->acceptance_id }}" style="font-size: 11px;">
                                      <span class="input-group-btn">
                                          <button class="btn btn-sm btn-info copy-link-btn" type="button" data-clipboard-target="#link-{{ $item->acceptance_id }}" data-tooltip="true" title="Copiar link do termo">
                                              <i class="fa fa-clipboard"></i>
                                          </button>
                                      </span>
                                  </div>
                              @elseif($item->acceptance->token && !is_null($item->acceptance->token_expires_at) && $item->acceptance->token_expires_at->isPast())
                                  <span class="label label-danger" data-tooltip="true" title="Token expirado em {{ $item->acceptance->token_expires_at->format('d/m/Y') }}"><i class="fa fa-exclamation-triangle"></i> Expirado</span>
                              @else
                                  <span class="label label-default"><i class="fa fa-clock-o"></i> Sem link</span>
                              @endif
                          </td>

                          {{-- Actions: send reminder / delete --}}
                          <td class="text-nowrap">

                                  @unless($item->acceptance->trashed())
                                      <form method="post" class="white-space: nowrap;" action="{{ route('reports/unaccepted_assets_sent_reminder') }}">
                                          @csrf
                                          <input type="hidden" name="acceptance_id" value="{{ $item->acceptance_id }}">
                                          @if ($item->assignee && $item->assignee->email)
                                              <button class="btn btn-sm btn-warning" data-tooltip="true" data-title="{{ trans('admin/reports/general.send_reminder') }}">
                                                  <i class="fa fa-repeat" aria-hidden="true"></i>
                                              </button>
                                          @else
                                              <span data-tooltip="true" data-title="{{ trans('admin/reports/general.cannot_send_reminder') }}">
                                                  <a class="btn btn-sm btn-warning disabled" href="#">
                                                        <i class="fa fa-repeat" aria-hidden="true"></i>
                                                  </a>
                                              </span>
                                          @endif
                                          <a href="{{ route('reports/unaccepted_assets_delete', ['acceptanceId' => $item->acceptance_id]) }}"
                                             class="btn btn-sm btn-danger delete-asset"
                                             data-tooltip="true"
                                             data-toggle="modal"
                                             data-content="{{ trans('general.delete_confirm', ['item' => trans('admin/reports/general.acceptance_request')]) }}"
                                             data-title="{{ trans('general.delete') }}"
                                             onClick="return false;">
                                              <i class="fa fa-trash"></i>
                                          </a>
                                      </form>
                                  @endunless
                          </td>
                      </tr>
                  @endforeach
              @endif
            </tbody>
            <tfoot>
              <tr>
              </tr>
            </tfoot>
          </table>
      </div>
    </div>
  </div>
</div>

@stop

@section('moar_scripts')

<script>
// Must be defined before bootstrap-table initializes so data-sorter="numericSorter" can resolve it.
// Strips HTML tags first (avoids extracting digits from style attrs like "13px"),
// then uses parseInt which stops at the first non-numeric char.
window.numericSorter = function(a, b) {
    var textA = String(a).replace(/<[^>]*>/g, '');
    var textB = String(b).replace(/<[^>]*>/g, '');
    var numA = parseInt(textA, 10) || 0;
    var numB = parseInt(textB, 10) || 0;
    return numA - numB;
};
</script>

    @include ('partials.bootstrap-table')

<script>
$(function() {
    // Delegated event — works even after bootstrap-table re-renders rows (sort/page/filter)
    $(document).on('click', '.copy-link-btn', function() {
        var btn = $(this);
        var targetId = btn.data('clipboard-target');
        var text = $(targetId).val();

        function onSuccess() {
            var orig = btn.html();
            btn.html('<i class="fa fa-check"></i>').removeClass('btn-info').addClass('btn-success');
            setTimeout(function() { btn.html(orig).removeClass('btn-success').addClass('btn-info'); }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(function() {
                // fallback
                fallbackCopy(targetId);
                onSuccess();
            });
        } else {
            fallbackCopy(targetId);
            onSuccess();
        }
    });

    function fallbackCopy(targetId) {
        var input = $(targetId);
        input.select();
        input[0].setSelectionRange(0, 99999);
        document.execCommand('copy');
    }
});
</script>
@stop
