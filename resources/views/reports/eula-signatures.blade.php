@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('general.eula_signatures_report') }}
@parent
@stop

@section('header_right')
<a href="{{ route('reports.eula-signatures.export') }}" class="btn btn-default">
    <i class="fas fa-download" aria-hidden="true"></i>
    {{ trans('general.download_all') }}
</a>
@stop

{{-- Page content --}}
@section('content')

<div class="row" id="statsCards" style="display: none;">
    <!-- Statistics Cards will be loaded here -->
</div>

<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-body">
                <div class="table-responsive">
                    <table
                        data-columns="{{ \App\Presenters\EulaSignaturePresenter::dataTableLayout() }}"
                        data-cookie-id-table="eulaSignaturesReport"
                        data-id-table="eulaSignaturesReport"
                        data-side-pagination="server"
                        data-sort-order="desc"
                        data-sort-name="accepted_at"
                        id="eulaSignaturesReport"
                        data-url="{{ route('api.eula-signatures.index') }}"
                        class="table table-striped snipe-table"
                        data-export-options='{
                            "fileName": "eula-signatures-report-{{ date('Y-m-d') }}",
                            "ignoreColumn": ["actions","checkbox"]
                        }'
                        data-escape="false"
                        data-show-header="true"
                        data-show-columns="true"
                        data-show-refresh="true"
                        data-show-toggle="true">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', [
    'exportFile' => 'eula-signatures-export',
    'search' => true
])

<script>
$(document).ready(function() {
    // Monitor bootstrap table events
    $('#eulaSignaturesReport').on('load-success.bs.table', function (e, data) {
        loadStatistics();
    });
    
    $('#eulaSignaturesReport').on('load-error.bs.table', function (e, status) {
        console.error('Bootstrap Table Load Error:', status);
    });
    
    // Load statistics cards
    function loadStatistics() {
        // Get current search parameter to apply same filter to stats
        var currentSearch = $('#eulaSignaturesReport').bootstrapTable('getOptions').queryParams().search || '';
        
        // Load statistics from API endpoint
        $.ajax({
            url: '{{ route("api.eula-signatures.stats") }}',
            method: 'GET',
            data: {
                search: currentSearch
            },
            dataType: 'json',
            success: function(stats) {
                displayStatistics(stats);
            },
            error: function() {
                // Fallback to empty stats
                displayStatistics({
                    total: 0,
                    last_7_days: 0,
                    last_30_days: 0,
                    desktop_count: 0,
                    mobile_count: 0,
                    with_geolocation: 0
                });
            }
        });
    }
    
    // Display statistics cards in Snipe-IT style
    function displayStatistics(stats) {
        const statsHtml = `
            <div class="col-lg-2 col-xs-6">
                <div class="info-box">
                    <span class="info-box-icon bg-green">
                        <i class="fas fa-file-signature"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total</span>
                        <span class="info-box-number">${stats.total || 0}</span>
                        <div class="progress">
                            <div class="progress-bar bg-green" style="width: 100%"></div>
                        </div>
                        <span class="progress-description">Assinaturas</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-xs-6">
                <div class="info-box">
                    <span class="info-box-icon bg-yellow">
                        <i class="fas fa-calendar-week"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">7 Dias</span>
                        <span class="info-box-number">${stats.last_7_days || 0}</span>
                        <div class="progress">
                            <div class="progress-bar bg-yellow" style="width: ${stats.total > 0 ? (stats.last_7_days / stats.total * 100) : 0}%"></div>
                        </div>
                        <span class="progress-description">Recentes</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-xs-6">
                <div class="info-box">
                    <span class="info-box-icon bg-blue">
                        <i class="fas fa-desktop"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Desktop</span>
                        <span class="info-box-number">${stats.desktop_count || 0}</span>
                        <div class="progress">
                            <div class="progress-bar bg-blue" style="width: ${stats.total > 0 ? (stats.desktop_count / stats.total * 100) : 0}%"></div>
                        </div>
                        <span class="progress-description">Dispositivos</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-xs-6">
                <div class="info-box">
                    <span class="info-box-icon bg-purple">
                        <i class="fas fa-mobile-alt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Mobile</span>
                        <span class="info-box-number">${stats.mobile_count || 0}</span>
                        <div class="progress">
                            <div class="progress-bar bg-purple" style="width: ${stats.total > 0 ? (stats.mobile_count / stats.total * 100) : 0}%"></div>
                        </div>
                        <span class="progress-description">Dispositivos</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-xs-6">
                <div class="info-box">
                    <span class="info-box-icon bg-red">
                        <i class="fas fa-map-marker-alt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">GPS</span>
                        <span class="info-box-number">${stats.with_geolocation || 0}</span>
                        <div class="progress">
                            <div class="progress-bar bg-red" style="width: ${stats.total > 0 ? (stats.with_geolocation / stats.total * 100) : 0}%"></div>
                        </div>
                        <span class="progress-description">Com localização</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-xs-6">
                <div class="info-box">
                    <span class="info-box-icon bg-aqua">
                        <i class="fas fa-percentage"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Taxa GPS</span>
                        <span class="info-box-number">${stats.total > 0 ? Math.round((stats.with_geolocation / stats.total) * 100) : 0}%</span>
                        <div class="progress">
                            <div class="progress-bar bg-aqua" style="width: ${stats.total > 0 ? (stats.with_geolocation / stats.total * 100) : 0}%"></div>
                        </div>
                        <span class="progress-description">${stats.with_geolocation} de ${stats.total}</span>
                    </div>
                </div>
            </div>
        `;
        
        $('#statsCards').html(statsHtml).show();
    }
    
    // Add custom CSS for rounded cards
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            #statsCards .info-box {
                border-radius: 10px !important;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
                border: none !important;
                margin-bottom: 20px;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            
            #statsCards .info-box:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
            }
            
            #statsCards .info-box-icon {
                border-radius: 10px 0 0 10px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            #statsCards .info-box-content {
                padding: 10px 15px !important;
            }
            
            #statsCards .info-box-number {
                font-weight: bold !important;
                font-size: 18px !important;
            }
            
            #statsCards .progress {
                margin: 5px 0 !important;
                height: 4px !important;
                border-radius: 2px !important;
            }
            
            #statsCards .progress-bar {
                border-radius: 2px !important;
            }
        `)
        .appendTo('head');
});
</script>

{{-- Modal de Detalhes da Assinatura EULA --}}
<div class="modal fade" id="signatureDetailModal" tabindex="-1" role="dialog" aria-labelledby="signatureDetailModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="signatureDetailModalLabel">
                    <i class="fa fa-file-signature"></i>
                    Detalhes da Assinatura #<span id="modal-signature-id"></span>
                </h4>
            </div>
            <div class="modal-body">
                <div id="modal-loading" class="text-center" style="padding: 40px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p class="text-muted">Carregando detalhes...</p>
                </div>
                <div id="modal-content" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fa fa-user"></i> Informações do Usuário</h5>
                            <dl class="dl-horizontal">
                                <dt>Nome:</dt>
                                <dd id="modal-user-name">-</dd>
                                <dt>Email:</dt>
                                <dd id="modal-user-email">-</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fa fa-cube"></i> Informações do Item</h5>
                            <dl class="dl-horizontal">
                                <dt>Tipo:</dt>
                                <dd id="modal-item-type">-</dd>
                                <dt>Nome:</dt>
                                <dd id="modal-item-name">-</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <h5><i class="fa fa-info-circle"></i> Dados da Assinatura</h5>
                            <dl class="dl-horizontal">
                                <dt>Data/Hora:</dt>
                                <dd id="modal-datetime">-</dd>
                                <dt>Dispositivo:</dt>
                                <dd id="modal-device">-</dd>
                                <dt>IP:</dt>
                                <dd id="modal-ip">-</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal" aria-label="Fechar modal">
                    <i class="fa fa-times" aria-hidden="true"></i> Fechar
                </button>
                <a href="#" id="modal-export-pdf" class="btn btn-primary" target="_blank" aria-label="Exportar PDF da assinatura">
                    <i class="fa fa-download" aria-hidden="true"></i> Exportar PDF
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Event listener para botões "Visualizar"
    $(document).on('click', '.btn-view-signature', function(e) {
        e.preventDefault();
        
        var signatureId = $(this).data('id');
        if (signatureId) {
            loadSignatureDetails(signatureId);
        }
    });
    
    function loadSignatureDetails(signatureId) {
        // Resetar modal
        $('#modal-signature-id').text(signatureId);
        $('#modal-user-name').text('-');
        $('#modal-user-email').text('-');
        $('#modal-item-type').text('-');
        $('#modal-item-name').text('-');
        $('#modal-datetime').text('-');
        $('#modal-device').text('-');
        $('#modal-ip').text('-');
        
        // Mostrar loading
        $('#modal-loading').show();
        $('#modal-content').hide();
        $('#signatureDetailModal').modal('show');
        
        // Buscar dados via AJAX usando rota web
        $.ajax({
            url: '{{ url("/reports/eula-signatures") }}/' + signatureId + '/json',
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success && response.data) {
                    var data = response.data;
                    
                    // Popular dados
                    $('#modal-user-name').text(data.user_name || '-');
                    $('#modal-user-email').text(data.user_email || '-');
                    $('#modal-item-type').text(data.item_type || '-');
                    $('#modal-item-name').text(data.item_name || '-');
                    
                    if (data.accepted_at) {
                        var date = new Date(data.accepted_at);
                        $('#modal-datetime').text(date.toLocaleString('pt-BR'));
                    }
                    
                    $('#modal-device').text(data.device_type || '-');
                    $('#modal-ip').text(data.signature_ip || '-');
                    
                    // Link do PDF
                    if (data.id) {
                        $('#modal-export-pdf').attr('href', '{{ url("/reports/eula-signatures") }}/' + data.id + '/pdf');
                    }
                    
                    $('#modal-loading').hide();
                    $('#modal-content').show();
                } else {
                    $('#modal-loading').hide();
                    $('#modal-content').html('<div class="alert alert-warning">Dados não encontrados.</div>').show();
                }
            },
            error: function(xhr, status, error) {
                $('#modal-loading').hide();
                
                var errorMsg = 'Erro ao carregar dados.';
                if (xhr.status === 401) {
                    errorMsg = 'Erro de autenticação. Recarregue a página e tente novamente.';
                } else if (xhr.status === 404) {
                    errorMsg = 'Assinatura não encontrada.';
                }
                
                $('#modal-content').html('<div class="alert alert-danger">' + errorMsg + '</div>').show();
            }
        });
    }
    
    // Melhorar acessibilidade do modal
    $('#signatureDetailModal').on('shown.bs.modal', function () {
        $(this).find('.modal-content').attr('tabindex', -1).focus();
    });
    
    $('#signatureDetailModal').on('hidden.bs.modal', function () {
        // Retornar foco para o botão que abriu o modal
        $('.btn-view-signature:focus').blur();
    });
});
</script>
@stop