{{-- Modal de Detalhes da Assinatura EULA --}}
<div class="modal fade" id="signatureDetailModal" tabindex="-1" role="dialog" aria-labelledby="signatureDetailModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            {{-- Header do Modal --}}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="signatureDetailModalLabel">
                    <i class="fa fa-file-signature"></i>
                    Detalhes da Assinatura #<span id="modal-signature-id"></span>
                </h4>
            </div>

            {{-- Body do Modal --}}
            <div class="modal-body">
                {{-- Loading State --}}
                <div id="modal-loading" class="text-center" style="padding: 40px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p class="text-muted">Carregando detalhes...</p>
                </div>

                {{-- Conteúdo Principal --}}
                <div id="modal-content" style="display: none;">
                    {{-- Seção de Informações do Usuário --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h4 class="box-title">
                                        <i class="fa fa-user"></i> Informações do Usuário
                                    </h4>
                                </div>
                                <div class="box-body">
                                    <dl class="dl-horizontal">
                                        <dt>Nome Completo:</dt>
                                        <dd id="modal-user-name">-</dd>
                                        
                                        <dt>CPF:</dt>
                                        <dd id="modal-user-cpf">-</dd>
                                        
                                        <dt>Email:</dt>
                                        <dd id="modal-user-email">-</dd>
                                        
                                        <dt>ID do Usuário:</dt>
                                        <dd id="modal-user-id">-</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Seção de Informações do Item --}}
                        <div class="col-md-6">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h4 class="box-title">
                                        <i class="fa fa-cube"></i> Informações do Item
                                    </h4>
                                </div>
                                <div class="box-body">
                                    <dl class="dl-horizontal">
                                        <dt>Tipo do Item:</dt>
                                        <dd>
                                            <span id="modal-item-type-badge" class="label label-info">-</span>
                                        </dd>
                                        
                                        <dt>Nome do Item:</dt>
                                        <dd id="modal-item-name">-</dd>
                                        
                                        <dt>Asset Tag:</dt>
                                        <dd id="modal-item-asset-tag">-</dd>
                                        
                                        <dt>ID do Item:</dt>
                                        <dd id="modal-item-id">-</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Seção de Imagem da Assinatura --}}
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h4 class="box-title">
                                        <i class="fa fa-signature"></i> Assinatura Digital
                                    </h4>
                                </div>
                                <div class="box-body text-center">
                                    <div id="signature-image-container">
                                        {{-- Loading placeholder --}}
                                        <div id="signature-loading" class="signature-placeholder">
                                            <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                                            <p class="text-muted">Carregando assinatura...</p>
                                        </div>
                                        
                                        {{-- Imagem da assinatura --}}
                                        <div id="signature-image-wrapper" style="display: none;">
                                            <img id="modal-signature-image" 
                                                 src="" 
                                                 alt="Assinatura Digital" 
                                                 class="img-responsive signature-image"
                                                 style="max-height: 200px; margin: 0 auto; border: 1px solid #ddd; padding: 10px; background: white; cursor: pointer;"
                                                 data-toggle="modal" 
                                                 data-target="#signatureZoomModal">
                                        </div>
                                        
                                        {{-- Fallback quando não há imagem --}}
                                        <div id="signature-not-found" style="display: none;" class="signature-placeholder">
                                            <i class="fa fa-exclamation-triangle fa-2x text-warning"></i>
                                            <p class="text-muted">Imagem da assinatura não encontrada</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Seção de Metadados da Assinatura --}}
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h4 class="box-title">
                                        <i class="fa fa-info-circle"></i> Metadados da Assinatura
                                    </h4>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <dl class="dl-horizontal">
                                                <dt>Data/Hora:</dt>
                                                <dd id="modal-signature-datetime">
                                                    <i class="fa fa-calendar"></i> <span id="modal-datetime-value">-</span>
                                                </dd>
                                                
                                                <dt>Tipo de Dispositivo:</dt>
                                                <dd id="modal-device-type">
                                                    <i id="modal-device-icon" class="fa fa-desktop"></i> 
                                                    <span id="modal-device-text">-</span>
                                                </dd>
                                                
                                                <dt>Endereço IP:</dt>
                                                <dd id="modal-signature-ip">
                                                    <i class="fa fa-globe"></i> <span id="modal-ip-value">-</span>
                                                </dd>
                                            </dl>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <dl class="dl-horizontal">
                                                <dt>Coordenadas GPS:</dt>
                                                <dd id="modal-gps-coordinates">
                                                    <span id="modal-coordinates-value">-</span>
                                                </dd>
                                                
                                                <dt>Localização:</dt>
                                                <dd id="modal-location-info">
                                                    <div id="modal-location-available" style="display: none;">
                                                        <a href="#" id="modal-google-maps-link" target="_blank" class="btn btn-xs btn-info">
                                                            <i class="fa fa-map-marker"></i> Ver no Google Maps
                                                        </a>
                                                    </div>
                                                    <div id="modal-location-unavailable" style="display: none;">
                                                        <span class="text-muted">
                                                            <i class="fa fa-exclamation-triangle"></i> Localização não capturada
                                                        </span>
                                                    </div>
                                                </dd>
                                                
                                                <dt>ID da Assinatura:</dt>
                                                <dd id="modal-acceptance-id">
                                                    <code id="modal-acceptance-id-value">-</code>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer do Modal --}}
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Fechar
                </button>
                <a href="#" id="modal-export-pdf" class="btn btn-primary" target="_blank">
                    <i class="fa fa-download"></i> Exportar PDF
                </a>
            </div>
        </div>
    </div>
</div>
{
{-- Modal de Zoom da Assinatura --}}
<div class="modal fade" id="signatureZoomModal" tabindex="-1" role="dialog" aria-labelledby="signatureZoomModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="signatureZoomModalLabel">
                    <i class="fa fa-search-plus"></i> Assinatura Digital - Visualização Ampliada
                </h4>
            </div>
            <div class="modal-body text-center">
                <img id="zoom-signature-image" src="" alt="Assinatura Digital Ampliada" class="img-responsive" style="margin: 0 auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.signature-placeholder {
    padding: 40px;
    text-align: center;
    background-color: #f9f9f9;
    border: 2px dashed #ddd;
    border-radius: 4px;
    margin: 10px 0;
}

.signature-image {
    transition: transform 0.2s ease-in-out;
}

.signature-image:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.dl-horizontal dt {
    width: 120px;
}

.dl-horizontal dd {
    margin-left: 140px;
}

@media (max-width: 768px) {
    .dl-horizontal dt {
        width: auto;
        text-align: left;
        float: none;
    }
    
    .dl-horizontal dd {
        margin-left: 0;
    }
}
</style>
<script>
$(document).ready(function() {
    
    /**
     * Função para abrir o modal de detalhes da assinatura
     */
    function openSignatureDetailModal(signatureData) {
        // Resetar modal
        resetModal();
        
        // Mostrar loading
        $('#modal-loading').show();
        $('#modal-content').hide();
        
        // Abrir modal
        $('#signatureDetailModal').modal('show');
        
        // Simular delay para loading (remover em produção)
        setTimeout(function() {
            populateModalData(signatureData);
            $('#modal-loading').hide();
            $('#modal-content').show();
        }, 500);
    }
    
    /**
     * Função para resetar o modal
     */
    function resetModal() {
        // Resetar todos os campos
        $('#modal-signature-id').text('-');
        $('#modal-user-name').text('-');
        $('#modal-user-cpf').text('-');
        $('#modal-user-email').text('-');
        $('#modal-user-id').text('-');
        $('#modal-item-type-badge').text('-').removeClass().addClass('label label-info');
        $('#modal-item-name').text('-');
        $('#modal-item-asset-tag').text('-');
        $('#modal-item-id').text('-');
        $('#modal-datetime-value').text('-');
        $('#modal-device-text').text('-');
        $('#modal-device-icon').removeClass().addClass('fa fa-desktop');
        $('#modal-ip-value').text('-');
        $('#modal-coordinates-value').text('-');
        $('#modal-acceptance-id-value').text('-');
        
        // Resetar seção de imagem
        $('#signature-loading').show();
        $('#signature-image-wrapper').hide();
        $('#signature-not-found').hide();
        
        // Resetar localização
        $('#modal-location-available').hide();
        $('#modal-location-unavailable').hide();
        
        // Resetar link do PDF
        $('#modal-export-pdf').attr('href', '#');
    }
    
    /**
     * Função para popular os dados no modal
     */
    function populateModalData(data) {
        // Informações básicas
        $('#modal-signature-id').text(data.id || '-');
        $('#modal-acceptance-id-value').text(data.id || '-');
        
        // Informações do usuário
        $('#modal-user-name').text(data.user_name || '-');
        $('#modal-user-cpf').text(maskCPF(data.user_cpf) || '-');
        $('#modal-user-email').text(data.user_email || '-');
        $('#modal-user-id').text(data.user_id || '-');
        
        // Informações do item
        var itemTypeClass = getItemTypeClass(data.item_type);
        $('#modal-item-type-badge').text(data.item_type || '-')
            .removeClass().addClass('label ' + itemTypeClass);
        $('#modal-item-name').text(data.item_name || '-');
        $('#modal-item-asset-tag').text(data.item_asset_tag || 'N/A');
        $('#modal-item-id').text(data.item_id || '-');
        
        // Metadados da assinatura
        $('#modal-datetime-value').text(formatDateTime(data.accepted_at) || '-');
        
        // Tipo de dispositivo
        var deviceIcon = data.device_type === 'mobile' ? 'fa-mobile' : 'fa-desktop';
        var deviceText = data.device_type === 'mobile' ? 'Mobile' : 'Desktop';
        $('#modal-device-icon').removeClass().addClass('fa ' + deviceIcon);
        $('#modal-device-text').text(deviceText);
        
        $('#modal-ip-value').text(data.signature_ip || '-');
        
        // Coordenadas GPS
        if (data.signature_latitude && data.signature_longitude) {
            var coordinates = data.signature_latitude + ', ' + data.signature_longitude;
            $('#modal-coordinates-value').text(coordinates);
            
            // Link do Google Maps
            var mapsUrl = 'https://www.google.com/maps?q=' + data.signature_latitude + ',' + data.signature_longitude;
            $('#modal-google-maps-link').attr('href', mapsUrl);
            $('#modal-location-available').show();
            $('#modal-location-unavailable').hide();
        } else {
            $('#modal-coordinates-value').text('Não disponível');
            $('#modal-location-available').hide();
            $('#modal-location-unavailable').show();
        }
        
        // Imagem da assinatura
        loadSignatureImage(data.signature_filename);
        
        // Link do PDF
        if (data.id) {
            var pdfUrl = '/reports/eula-signatures/' + data.id + '/pdf';
            $('#modal-export-pdf').attr('href', pdfUrl);
        }
    }
    
    /**
     * Função para carregar a imagem da assinatura
     */
    function loadSignatureImage(filename) {
        if (!filename) {
            $('#signature-loading').hide();
            $('#signature-not-found').show();
            return;
        }
        
        var imageUrl = '{{ route("reports.eula-signatures.signature-image", ":filename") }}'.replace(':filename', filename);
        var img = new Image();
        
        img.onload = function() {
            $('#modal-signature-image').attr('src', imageUrl);
            $('#zoom-signature-image').attr('src', imageUrl);
            $('#signature-loading').hide();
            $('#signature-image-wrapper').show();
        };
        
        img.onerror = function() {
            console.error('❌ Erro ao carregar imagem da assinatura:', filename);
            $('#signature-loading').hide();
            $('#signature-not-found').show();
        };
        
        img.src = imageUrl;
    }
    
    /**
     * Função para mascarar CPF
     */
    function maskCPF(cpf) {
        if (!cpf) return '';
        // Mascarar CPF: 123.456.789-00 -> 123.***.**9-00
        return cpf.replace(/(\d{3})\.(\d{3})\.(\d{2})(\d{1})-(\d{2})/, '$1.***.***$4-$5');
    }
    
    /**
     * Função para obter classe CSS do tipo de item
     */
    function getItemTypeClass(itemType) {
        switch(itemType) {
            case 'Asset': return 'label-primary';
            case 'License': return 'label-success';
            case 'Accessory': return 'label-warning';
            case 'Consumable': return 'label-info';
            default: return 'label-default';
        }
    }
    
    /**
     * Função para formatar data/hora
     */
    function formatDateTime(datetime) {
        if (!datetime) return '';
        
        try {
            var date = new Date(datetime);
            return date.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        } catch (e) {
            return datetime;
        }
    }
    
    /**
     * Event listener para botões "Visualizar"
     */
    $(document).on('click', '.btn-view-signature', function(e) {
        e.preventDefault();
        
        // Sempre usar AJAX para buscar dados atualizados
        var signatureId = $(this).data('id');
        if (signatureId) {
            console.log('🔍 Carregando detalhes da assinatura ID:', signatureId);
            loadSignatureDataAjax(signatureId);
        } else {
            console.error('❌ ID da assinatura não encontrado no botão');
        }
    });
    
    /**
     * Função para carregar dados via AJAX (opcional)
     */
    function loadSignatureDataAjax(signatureId) {
        $.ajax({
            url: '{{ route("api.eula-signatures.index") }}/' + signatureId,
            method: 'GET',
            dataType: 'json',
            beforeSend: function() {
                // Mostrar loading
                resetModal();
                $('#modal-loading').show();
                $('#modal-content').hide();
                $('#signatureDetailModal').modal('show');
            },
            success: function(response) {
                if (response.success && response.data) {
                    populateModalData(response.data);
                    $('#modal-loading').hide();
                    $('#modal-content').show();
                } else {
                    $('#modal-loading').hide();
                    $('#modal-content').html(
                        '<div class="alert alert-warning">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' +
                        'Dados da assinatura não encontrados.' +
                        '</div>'
                    ).show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar assinatura:', xhr.responseText);
                $('#modal-loading').hide();
                $('#modal-content').html(
                    '<div class="alert alert-danger">' +
                    '<i class="fa fa-exclamation-triangle"></i> ' +
                    'Erro ao carregar detalhes da assinatura. Tente novamente.' +
                    '</div>'
                ).show();
            }
        });
    }
    
    /**
     * Melhorar acessibilidade do modal
     */
    $('#signatureDetailModal').on('shown.bs.modal', function() {
        $(this).find('.modal-content').focus();
    });
    
    /**
     * Fechar modal com ESC
     */
    $(document).keydown(function(e) {
        if (e.keyCode === 27 && $('#signatureDetailModal').hasClass('in')) {
            $('#signatureDetailModal').modal('hide');
        }
    });
    
    /**
     * Zoom da imagem da assinatura
     */
    $(document).on('click', '#modal-signature-image', function() {
        var imageSrc = $(this).attr('src');
        $('#zoom-signature-image').attr('src', imageSrc);
        $('#signatureZoomModal').modal('show');
    });
    
});
</script>