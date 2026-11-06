@extends('layouts/default')

@section('title')
    Saúde dos computadores
    @parent
@stop

@section('header_right')
    <a href="{{ route('hardware.index') }}" class="btn btn-default btn-sm"><x-icon type="assets" /> {{ trans('general.assets') }}</a>
    <button type="button" class="btn btn-default btn-sm" id="ch-source"><i class="fa fa-info-circle" aria-hidden="true"></i> Sobre os dados</button>
    <button type="button" class="btn btn-theme btn-sm" id="ch-refresh"><i class="fas fa-sync-alt" aria-hidden="true"></i> Atualizar visão</button>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/computer-health.css') }}?v=20261106-native">
@endpush

@section('content')
@php
    $healthLabels = [
        'locale' => app()->getLocale(),
        'tag' => trans('admin/hardware/table.asset_tag'),
        'name' => trans('admin/hardware/form.name'),
        'serial' => trans('admin/hardware/table.serial'),
        'model' => trans('admin/hardware/form.model'),
        'category' => trans('general.category'),
        'location' => trans('admin/hardware/table.location'),
        'defaultLocation' => trans('admin/hardware/form.default_location'),
        'status' => trans('admin/hardware/table.status'),
        'assigned' => trans('admin/hardware/form.checkedout_to').' (usuário)',
        'details' => trans('general.details'),
        'history' => trans('general.history'),
        'loading' => trans('general.loading'),
        'none' => trans('general.no_value'),
        'all' => trans('general.all'),
        'actions' => trans('table.actions'),
    ];
    $bands = ['critical'=>'Crítica','high'=>'Alta','attention'=>'Atenção','unknown'=>'Sem histórico','future'=>'Data futura','recent'=>'Recente'];
@endphp
<div id="computer-health-app" data-source="{{ route('computer-health.data') }}" data-history="{{ url('computer-health') }}" data-labels="{{ json_encode($healthLabels) }}">
    <x-container>
        <p class="help-block">
            Notebooks e desktops
            @if(config('computer-health.replica'))<span class="label label-default">Cópia local</span>@endif
            <span id="ch-updated"></span>
        </p>
        <div id="ch-error" class="alert alert-danger" role="alert" hidden></div>
        <p id="ch-loading" role="status"><x-icon type="spinner" /> {{ trans('general.loading') }}</p>
    </x-container>

    <div id="ch-dashboard" hidden>
        <x-container :columns="4">
            <x-page-column class="col-lg-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-light-blue"><x-icon type="assets" /></span>
                    <div class="info-box-content"><span class="info-box-text">Computadores</span><span id="ch-total" class="info-box-number" aria-live="polite">0</span><span id="ch-total-foot" class="progress-description"></span></div>
                </div>
            </x-page-column>
            <x-page-column class="col-lg-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Prioridade crítica</span><span id="ch-critical" class="info-box-number">0</span><span id="ch-critical-foot" class="progress-description"></span></div>
                </div>
            </x-page-column>
            <x-page-column class="col-lg-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-yellow"><i class="fas fa-clock" aria-hidden="true"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Alta / atenção</span><span id="ch-attention" class="info-box-number">0</span><span id="ch-attention-foot" class="progress-description"></span></div>
                </div>
            </x-page-column>
            <x-page-column class="col-lg-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-gray"><i class="fa fa-question-circle" aria-hidden="true"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Sem histórico</span><span id="ch-unknown" class="info-box-number">0</span><span class="progress-description">Sem alteração registrada</span></div>
                </div>
            </x-page-column>
        </x-container>

        <x-container>
            <p class="help-block"><i class="fa fa-info-circle" aria-hidden="true"></i> A prioridade usa a última alteração de IP, usuário reportado ou coordenadas. Ausência de mudança não comprova que o computador está offline.</p>
            <x-box header="Filtros">
                <form id="ch-filters" autocomplete="off">
                    <div class="row">
                        <div class="form-group col-md-4"><label class="control-label" for="ch-search">{{ trans('general.search') }}</label><x-input.text id="ch-search" name="search" type="search" placeholder="Marcação, nome, serial, IP ou usuário" /></div>
                        <div class="form-group col-md-2"><label class="control-label" for="ch-category">{{ trans('general.category') }}</label><x-input.select id="ch-category" name="category" class="form-control" style="width:100%" :options="[''=>trans('general.all')]" /></div>
                        <div class="form-group col-md-2"><label class="control-label" for="ch-location">{{ trans('admin/hardware/table.location') }}</label><x-input.select id="ch-location" name="location" class="form-control" style="width:100%" :options="[''=>trans('general.all')]" /></div>
                        <div class="form-group col-md-2"><label class="control-label" for="ch-status">{{ trans('admin/hardware/table.status') }}</label><x-input.select id="ch-status" name="status" class="form-control" style="width:100%" :options="[''=>trans('general.all')]" /></div>
                        <div class="form-group col-md-2"><label class="control-label" for="ch-band">Prioridade</label><x-input.select id="ch-band" name="band" class="form-control" style="width:100%" :options="[''=>trans('general.all')]+$bands" /></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4"><label class="control-label" for="ch-model">{{ trans('admin/hardware/form.model') }}</label><x-input.select id="ch-model" name="model" class="form-control" style="width:100%" :options="[''=>trans('general.all')]" /></div>
                        <div class="form-group col-md-3"><label class="control-label" for="ch-user-scope">Pesquisar usuário em</label><x-input.select id="ch-user-scope" name="userScope" class="form-control" style="width:100%" :options="['assigned'=>trans('admin/hardware/form.checkedout_to').' (usuário)','reported'=>'Usuário reportado atual','historical'=>'Usuários no histórico']" /></div>
                        <div class="form-group col-md-3"><label class="control-label" for="ch-user">Nome ou login</label><x-input.text id="ch-user" name="user" type="search" placeholder="Nome ou login" /></div>
                        <div class="form-group col-md-2"><label class="control-label" aria-hidden="true">&nbsp;</label><div><button id="ch-clear" type="button" class="btn btn-default"><i class="fa fa-times" aria-hidden="true"></i> Limpar filtros</button></div></div>
                    </div>
                </form>
                <p class="help-block" id="ch-active-scope"></p>
            </x-box>
        </x-container>

        <x-container :columns="2">
            <x-page-column class="col-md-6">
                <x-box header="Tempo sem alteração de inventário">
                    <p id="ch-coverage" class="help-block"></p>
                    @foreach($bands as $band => $label)
                        <a href="#computerHealthTable" id="ch-dist-{{ $band }}" data-band="{{ $band }}" aria-label="Filtrar prioridade: {{ $label }}">
                            <x-progressbar :text="$label" :percent="0" columns="12" use_well="false"><span id="ch-count-{{ $band }}">0</span></x-progressbar>
                        </a>
                    @endforeach
                    <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#ch-criteria-content" aria-expanded="false" aria-controls="ch-criteria-content"><i class="fa fa-cog" aria-hidden="true"></i> <span id="ch-criteria-summary">Critérios de prioridade</span></button>
                    <div class="collapse" id="ch-criteria-content">
                        <form id="ch-thresholds">
                            <div class="row">
                                <div class="form-group col-sm-4"><label for="ch-threshold-attention">Atenção (dias)</label><x-input.text id="ch-threshold-attention" name="attention" type="number" min="1" max="3650" step="1" value="7" required /></div>
                                <div class="form-group col-sm-4"><label for="ch-threshold-high">Alta (dias)</label><x-input.text id="ch-threshold-high" name="high" type="number" min="1" max="3650" step="1" value="15" required /></div>
                                <div class="form-group col-sm-4"><label for="ch-threshold-critical">Crítica (dias)</label><x-input.text id="ch-threshold-critical" name="critical" type="number" min="1" max="3650" step="1" value="30" required /></div>
                            </div>
                            <button type="submit" class="btn btn-theme btn-sm">Aplicar critérios</button>
                            <button type="button" class="btn btn-default btn-sm" id="ch-threshold-reset">Restaurar 7 / 15 / 30</button>
                            <p id="ch-threshold-error" class="text-danger" role="alert" hidden></p>
                        </form>
                    </div>
                </x-box>
            </x-page-column>
            <x-page-column class="col-md-6">
                <x-box header="Prioridade por local">
                    <p class="help-block">Até cinco locais no recorte atual</p>
                    <div id="ch-locations"></div>
                    <p class="help-block"><span class="label label-danger">Crítica</span> <span class="label label-warning">Alta / atenção</span> <span class="label label-default">Demais</span></p>
                </x-box>
            </x-page-column>
        </x-container>

        <x-container>
            <x-box header="Computadores">
                <div id="computerHealthToolbar" class="form-inline">
                    <div class="form-group"><label for="ch-sort">Ordenar</label> <select id="ch-sort" class="form-control input-sm"><option value="priority">Prioridade</option><option value="oldest">Inventário mais antigo</option><option value="newest">Inventário mais recente</option><option value="tag">{{ trans('admin/hardware/table.asset_tag') }}</option></select></div>
                    <button type="button" class="btn btn-default btn-sm" id="ch-export"><x-icon type="download" /> {{ trans('admin/hardware/table.dl_csv') }}</button>
                    <span class="text-muted" id="ch-result-count" aria-live="polite"></span>
                </div>
                <table id="computerHealthTable" class="table table-striped snipe-table" data-cookie-id-table="computerHealthTable" data-id-table="computerHealthTable" data-side-pagination="client" data-toolbar="#computerHealthToolbar" data-search="false" data-advanced-search="false" data-show-export="false" data-show-refresh="false" data-click-to-select="false" data-fixed-columns="false" data-fixed-right-number="0" data-sort-name="queue_order" data-sort-order="asc">
                    <caption class="sr-only">Computadores no recorte e evidências de inventário</caption>
                    <thead><tr><th data-field="tag">{{ trans('admin/hardware/table.asset_tag') }}</th></tr></thead>
                </table>
            </x-box>
        </x-container>
    </div>

    <div id="ch-detail" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="ch-detail-title" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('general.close') }}"><span aria-hidden="true">&times;</span></button><h4 id="ch-detail-title" class="modal-title">{{ trans('general.details') }}</h4></div>
            <div class="modal-body">
                <p id="ch-detail-loading" role="status">{{ trans('general.loading') }}</p><div id="ch-detail-error" class="alert alert-danger" role="alert" hidden></div>
                <div id="ch-detail-content" hidden>
                    <p id="ch-detail-priority"></p>
                    <x-tabs :mobile_dropdown="false">
                        <x-slot:tabnav><x-tabs.nav-item name="ch-device-details" class="active" :label="trans('general.details')" icon="fa fa-info-circle" :show_label="true" /><x-tabs.nav-item name="ch-device-history" label="Histórico de inventário" icon="fa fa-history" :show_label="true" /></x-slot:tabnav>
                        <x-slot:tabpanes>
                            <x-tabs.pane name="ch-device-details" class="active in"><x-page-data id="ch-detail-fields" /></x-tabs.pane>
                            <x-tabs.pane name="ch-device-history">
                                <p class="help-block">Cada alteração mostra apenas os campos registrados naquele evento. Dados ausentes não são preenchidos com outro registro.</p><p id="ch-history-count" class="help-block" role="status" aria-live="polite"></p>
                                <table id="computerHealthHistoryTable" class="table table-striped snipe-table" data-cookie-id-table="computerHealthHistoryTable" data-id-table="computerHealthHistoryTable" data-side-pagination="client" data-advanced-search="false" data-show-export="false" data-show-refresh="false" data-show-print="false" data-show-fullscreen="false" data-click-to-select="false" data-fixed-columns="false" data-fixed-right-number="0" data-sort-name="sequence" data-sort-order="asc"><thead><tr><th data-field="created_at">{{ trans('general.date') }}</th></tr></thead></table>
                            </x-tabs.pane>
                        </x-slot:tabpanes>
                    </x-tabs>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('general.close') }}</button></div>
        </div></div>
    </div>

    <div id="ch-source-dialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="ch-source-title" aria-hidden="true">
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('general.close') }}"><span aria-hidden="true">&times;</span></button><h4 id="ch-source-title" class="modal-title">Sobre os dados</h4></div>
            <div class="modal-body">
                <x-page-data id="ch-source-summary" />
                <h4>Última alteração de inventário</h4><p>O histórico registra mudanças em IP, Ult. User Log e Coordenadas. Se os valores não mudarem, pode não existir novo evento. A data não mede cada comunicação, sessão ou presença online.</p>
                <h4>Usuários e local</h4><p>O usuário alocado no cadastro é diferente do usuário reportado pelo dispositivo. O local do cadastro é diferente das coordenadas reportadas. Valores ausentes continuam sem informação.</p>
                <p>Coordenadas válidas abrem o Google Maps ao clicar. Não há consulta automática ao mapa nem geolocalização.</p>
                <h4>Critérios de prioridade</h4><p>Os limites são ajustáveis nesta visualização e não alteram os ativos nem seu histórico. Datas futuras aparecem separadamente.</p>
                <p id="ch-source-fields" class="help-block"></p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('general.close') }}</button></div>
        </div></div>
    </div>
</div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
@stop

@push('js')
    <script type="module" src="{{ asset('js/computer-health.js') }}?v=20261106-native"></script>
@endpush
