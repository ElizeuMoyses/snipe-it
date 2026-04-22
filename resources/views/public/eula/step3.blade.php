@extends('layouts.public')

@section('title', 'Assinatura Digital')

@section('content')
<div class="row">
    <div class="col-md-12">
        {{-- Progress indicator --}}
        <div class="step-progress">
            <div class="progress">
                <div class="progress-bar progress-bar-primary" role="progressbar" style="width: 100%">
                    Etapa 3 de 3
                </div>
            </div>
            <div class="text-center">
                <small class="text-muted">Validação de Dados → Visualização do Termo → <strong>Assinatura Digital</strong></small>
            </div>
        </div>

        {{-- Page title --}}
        <div class="text-center">
            <h2>Assinatura Digital - Etapa 3 de 3</h2>
            <p class="lead text-muted">Assine digitalmente para finalizar o aceite do termo</p>
        </div>

        {{-- Error messages --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        {{-- Signature Form --}}
        <form id="signatureForm" method="POST" action="{{ route('eula.sign.finalize', $token) }}">
            @csrf
            
            {{-- Instructions --}}
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Instruções:</strong><br>
                        • <strong>No computador:</strong> Use o mouse para desenhar sua assinatura<br>
                        • <strong>No celular/tablet:</strong> Use o dedo para assinar na tela<br>
                        • Assine de forma legível dentro do campo abaixo
                    </div>

                    {{-- Signature Canvas --}}
                    <div class="signature-section">
                        <label class="control-label">
                            <strong><i class="fa fa-edit"></i> Assinatura Digital:</strong>
                        </label>
                        
                        <div class="signature-wrapper">
                            <div class="signature-canvas-container" id="canvasContainer">
                                <canvas id="signaturePad"></canvas>
                                <div class="signature-placeholder" id="signaturePlaceholder">
                                    <i class="fa fa-edit"></i>
                                    <p>Assine aqui</p>
                                    <div class="help-text">Use o mouse ou toque na tela</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="signature-controls">
                            <button type="button" id="undoSignature" class="btn btn-info" disabled>
                                <i class="fa fa-undo"></i> Desfazer
                            </button>
                            <button type="button" id="clearSignature" class="btn btn-warning">
                                <i class="fa fa-eraser"></i> Limpar Tudo
                            </button>
                            <span class="signature-status" id="signatureStatus">
                                <i class="fa fa-exclamation-circle text-warning"></i> Assinatura necessária
                            </span>
                        </div>
                    </div>

                    {{-- Hidden fields --}}
                    <input type="hidden" id="signatureData" name="signature_data" value="">
                    <input type="hidden" id="signatureMetadata" name="signature_metadata" value="">

                    {{-- Error display --}}
                    <div id="signatureError" class="alert alert-danger" style="display: none;">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span id="errorMessage">Por favor, assine no campo acima antes de continuar.</span>
                    </div>

            {{-- Action buttons --}}
            <div class="form-group text-center signature-buttons">
                <a href="{{ route('eula.sign.step2', $token) }}" class="btn btn-default btn-lg">
                    <i class="fa fa-arrow-left"></i> Voltar
                </a>
                <button type="button" id="finalizeSignature" class="btn btn-success btn-lg" disabled>
                    <i class="fa fa-check"></i> Finalizar Assinatura
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('js')
<script>
$(document).ready(function() {
    const canvas = document.getElementById('signaturePad');
    const placeholder = document.getElementById('signaturePlaceholder');
    const statusElement = document.getElementById('signatureStatus');
    const finalizeBtn = document.getElementById('finalizeSignature');
    const undoBtn = document.getElementById('undoSignature');

    let signaturePad;
    let signatureHistory = [];
    let isDrawing = false;
    let activeStroke = null;
    let emptyCanvasSnapshot = null;
    let resizeTimeout;
    const signatureMetadata = {
        startTime: null,
        endTime: null,
        strokes: [],
        geolocation: {
            latitude: null,
            longitude: null,
            accuracy: null,
            timestamp: null,
            error: null
        },
        device: {
            userAgent: navigator.userAgent,
            platform: navigator.platform,
            screenWidth: screen.width,
            screenHeight: screen.height,
            windowWidth: window.innerWidth,
            windowHeight: window.innerHeight,
            touchSupport: 'ontouchstart' in window,
            deviceType: /Mobile|Android|iPhone|iPad|iPod/i.test(navigator.userAgent) ? 'mobile' : 'desktop'
        }
    };

    function isFeatureAllowed(featureName) {
        const permissionsPolicy = document.permissionsPolicy || document.featurePolicy;

        if (!permissionsPolicy || typeof permissionsPolicy.allowsFeature !== 'function') {
            return true;
        }

        try {
            return permissionsPolicy.allowsFeature(featureName);
        } catch (error) {
            console.warn('Unable to evaluate permissions policy:', featureName, error);
            return true;
        }
    }

    function getPointerPosition(event) {
        if (!event) {
            return null;
        }

        const pointer = event.changedTouches && event.changedTouches.length
            ? event.changedTouches[0]
            : event.targetTouches && event.targetTouches.length
                ? event.targetTouches[0]
                : event;

        if (typeof pointer.clientX !== 'number' || typeof pointer.clientY !== 'number') {
            return null;
        }

        const rect = canvas.getBoundingClientRect();

        return {
            x: Math.round(pointer.clientX - rect.left),
            y: Math.round(pointer.clientY - rect.top),
            time: new Date().toISOString()
        };
    }

    function resetSignatureMetadata() {
        signatureMetadata.startTime = null;
        signatureMetadata.endTime = null;
        signatureMetadata.strokes = [];
        isDrawing = false;
        activeStroke = null;
    }

    function captureSignatureSnapshot() {
        if (!signaturePad) {
            return null;
        }

        const snapshot = signaturePad.toDataURL('image/png', 0.9);

        return snapshot === emptyCanvasSnapshot ? null : snapshot;
    }

    function restoreSignatureSnapshot(snapshot) {
        signaturePad.clear();

        if (snapshot) {
            signaturePad.fromDataURL(snapshot);
        }
    }

    if (navigator.geolocation && isFeatureAllowed('geolocation')) {
        console.log('Requesting geolocation...');
        navigator.geolocation.getCurrentPosition(
            function(position) {
                signatureMetadata.geolocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    altitude: position.coords.altitude,
                    altitudeAccuracy: position.coords.altitudeAccuracy,
                    heading: position.coords.heading,
                    speed: position.coords.speed,
                    timestamp: new Date(position.timestamp).toISOString()
                };
                console.log('Geolocation captured:', signatureMetadata.geolocation);
                console.log('Google Maps link:', `https://www.google.com/maps?q=${position.coords.latitude},${position.coords.longitude}`);
            },
            function(error) {
                signatureMetadata.geolocation.error = {
                    code: error.code,
                    message: error.message
                };
                console.warn('Geolocation error:', error.message);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        signatureMetadata.geolocation.error = navigator.geolocation
            ? 'Geolocation blocked by permissions policy'
            : 'Geolocation not supported by browser';
        console.warn(signatureMetadata.geolocation.error);
    }

    function setupCanvas() {
        const container = document.getElementById('canvasContainer');
        const signatureWrapper = document.querySelector('.signature-wrapper');
        const eulaContainer = document.querySelector('.eula-container');
        const containerWidth = container.clientWidth;
        const wrapperWidth = signatureWrapper ? signatureWrapper.clientWidth : 0;
        const eulaContainerWidth = eulaContainer ? eulaContainer.clientWidth : 0;
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        console.log('Container widths:', {
            canvasContainer: containerWidth,
            signatureWrapper: wrapperWidth,
            eulaContainer: eulaContainerWidth,
            window: windowWidth
        });

        const effectiveWidth = Math.max(containerWidth, wrapperWidth * 0.95);
        let canvasWidth;
        let canvasHeight;
        const isMobile = windowWidth <= 768;
        const isLandscape = windowWidth > windowHeight;

        if (isMobile) {
            if (isLandscape) {
                canvasWidth = Math.min(windowWidth - 60, 700);
                canvasHeight = Math.min(200, windowHeight * 0.3);
            } else {
                canvasWidth = Math.min(effectiveWidth - 20, windowWidth - 40);
                canvasHeight = Math.min(300, canvasWidth * 0.7);
            }
        } else if (effectiveWidth <= 1024) {
            canvasWidth = effectiveWidth - 40;
            canvasHeight = 400;
        } else if (effectiveWidth <= 1400) {
            canvasWidth = effectiveWidth - 60;
            canvasHeight = 500;
        } else {
            canvasWidth = effectiveWidth - 80;
            canvasHeight = 550;
        }

        canvasWidth = Math.max(canvasWidth, 300);
        canvasHeight = Math.max(canvasHeight, 150);

        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        canvas.style.width = canvasWidth + 'px';
        canvas.style.height = canvasHeight + 'px';

        console.log('Canvas setup:', {
            containerWidth: containerWidth,
            effectiveWidth: effectiveWidth,
            windowWidth: windowWidth,
            windowHeight: windowHeight,
            canvasWidth: canvasWidth,
            canvasHeight: canvasHeight,
            isMobile: isMobile,
            isLandscape: isLandscape,
            deviceType: isMobile ? (isLandscape ? 'mobile-landscape' : 'mobile-portrait') : 'desktop'
        });
    }

    function handleSignatureBegin(event) {
        const pointerPosition = getPointerPosition(event);

        placeholder.style.display = 'none';

        if (!signatureMetadata.startTime) {
            signatureMetadata.startTime = new Date().toISOString();
            console.log('Signature started at:', signatureMetadata.startTime);
        }

        signatureHistory.push(captureSignatureSnapshot());
        if (signatureHistory.length > 15) {
            signatureHistory.shift();
        }

        activeStroke = {
            startTime: new Date().toISOString(),
            startPoint: pointerPosition,
            endPoint: pointerPosition,
            pointCount: pointerPosition ? 1 : 0
        };

        signatureMetadata.strokes.push(activeStroke);
        isDrawing = true;
    }

    function updateActiveStroke(event) {
        if (!isDrawing || !activeStroke) {
            return;
        }

        const pointerPosition = getPointerPosition(event);

        if (!pointerPosition) {
            return;
        }

        activeStroke.endPoint = pointerPosition;
        activeStroke.pointCount += 1;
    }

    function handleSignatureEnd(event) {
        updateActiveStroke(event);
        signatureMetadata.endTime = new Date().toISOString();

        if (activeStroke) {
            activeStroke.endTime = signatureMetadata.endTime;
        }

        updateSignatureStatus();
        isDrawing = false;
        activeStroke = null;

        console.log('Stroke completed. Total strokes:', signatureMetadata.strokes.length);
    }

    function initSignaturePad() {
        if (signaturePad) {
            signaturePad.off();
        }

        if (typeof SignaturePad !== 'function') {
            console.error('SignaturePad library is not available in the current bundle.');
            $('#errorMessage').text('A biblioteca de assinatura não foi carregada. Recarregue a página ou entre em contato com o suporte.');
            $('#signatureError').show();
            signaturePad = null;
            return;
        }

        const penScale = canvas.width / 800;

        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 1)',
            penColor: 'rgb(0, 0, 0)',
            velocityFilterWeight: 0.7,
            minWidth: Math.max(0.5, 0.8 * penScale),
            maxWidth: Math.max(1.5, 2.2 * penScale),
            onBegin: handleSignatureBegin,
            onEnd: handleSignatureEnd
        });

        emptyCanvasSnapshot = signaturePad.toDataURL('image/png', 0.9);
    }

    function updateSignatureStatus() {
        const container = document.getElementById('canvasContainer');

        if (!signaturePad) {
            statusElement.innerHTML = '<i class="fa fa-exclamation-triangle text-danger"></i> Assinatura indisponível';
            finalizeBtn.disabled = true;
            undoBtn.disabled = true;
            placeholder.style.display = 'flex';
            container.classList.remove('has-signature');
            return;
        }

        const isEmpty = signaturePad.isEmpty();

        if (isEmpty) {
            statusElement.innerHTML = '<i class="fa fa-exclamation-circle text-warning"></i> Assinatura necessária';
            finalizeBtn.disabled = true;
            finalizeBtn.classList.remove('btn-success');
            finalizeBtn.classList.add('btn-default');
            undoBtn.disabled = true;
            placeholder.style.display = 'flex';
            container.classList.remove('has-signature');
        } else {
            statusElement.innerHTML = '<i class="fa fa-check-circle text-success"></i> Assinatura capturada';
            finalizeBtn.disabled = false;
            finalizeBtn.classList.remove('btn-default');
            finalizeBtn.classList.add('btn-success');
            undoBtn.disabled = signatureHistory.length === 0;
            placeholder.style.display = 'none';
            container.classList.add('has-signature');
        }

        console.log('Status updated:', {
            isEmpty: isEmpty,
            historyLength: signatureHistory.length,
            undoDisabled: undoBtn.disabled
        });
    }

    $('#clearSignature').on('click', function() {
        if (!signaturePad) {
            return;
        }

        signaturePad.clear();
        signatureHistory = [];
        resetSignatureMetadata();

        updateSignatureStatus();
        $('#signatureError').hide();
        console.log('Signature and metadata cleared');
    });

    $('#undoSignature').on('click', function() {
        if (!signaturePad || signatureHistory.length === 0) {
            return;
        }

        const previousState = signatureHistory.pop();

        restoreSignatureSnapshot(previousState);

        if (signatureMetadata.strokes.length > 0) {
            signatureMetadata.strokes.pop();
        }

        if (signatureMetadata.strokes.length === 0) {
            signatureMetadata.startTime = null;
            signatureMetadata.endTime = null;
        } else {
            signatureMetadata.endTime = new Date().toISOString();
        }

        console.log('Undo executed. Remaining history length:', signatureHistory.length);
        updateSignatureStatus();
    });

    function initialize() {
        setupCanvas();
        initSignaturePad();
        updateSignatureStatus();
    }

    setTimeout(function() {
        initialize();
    }, 100);

    function handleResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const signatureSnapshot = captureSignatureSnapshot();

            console.log('Handling resize/orientation change');

            initialize();

            if (signaturePad && signatureSnapshot) {
                restoreSignatureSnapshot(signatureSnapshot);
                updateSignatureStatus();
            }
        }, 400);
    }

    $(window).on('resize orientationchange', handleResize);

    if (screen && screen.orientation && typeof screen.orientation.addEventListener === 'function') {
        screen.orientation.addEventListener('change', function() {
            console.log('Screen orientation changed to:', screen.orientation.angle);
            setTimeout(handleResize, 500);
        });
    }

    $('#finalizeSignature').on('click', function(e) {
        e.preventDefault();

        $('#signatureError').hide();

        if (!signaturePad || signaturePad.isEmpty()) {
            $('#errorMessage').text('Por favor, assine no campo acima antes de continuar.');
            $('#signatureError').show();
            $('html, body').animate({
                scrollTop: $('#signatureError').offset().top - 100
            }, 500);
            return false;
        }

        const signatureData = signaturePad.toDataURL('image/png', 0.9);
        $('#signatureData').val(signatureData);

        signatureMetadata.endTime = new Date().toISOString();
        signatureMetadata.totalStrokes = signatureMetadata.strokes.length;
        signatureMetadata.canvasSize = {
            width: canvas.width,
            height: canvas.height
        };

        const metadataJson = JSON.stringify(signatureMetadata);
        $('#signatureMetadata').val(metadataJson);

        console.log('Signature metadata:', signatureMetadata);

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processando...');

        $.ajax({
            url: $('#signatureForm').attr('action'),
            method: 'POST',
            timeout: 30000,
            data: {
                _token: $('input[name="_token"]').val(),
                signature_data: signatureData,
                signature_metadata: metadataJson
            },
            success: function(response) {
                console.log('Signature processed successfully:', response);
                if (response.success && response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    alert('Assinatura processada com sucesso!');
                    window.location.reload();
                }
            },
            error: function(xhr, status) {
                console.error('Error processing signature:', status, xhr.responseText);

                let errorMessage = 'Erro ao processar assinatura.';

                if (status === 'timeout') {
                    errorMessage = 'Tempo limite excedido ao enviar a assinatura. Verifique sua conexão e tente novamente.';
                } else {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch (error) {
                        console.error('Error parsing response:', error);
                    }
                }

                $('#errorMessage').text(errorMessage);
                $('#signatureError').show();
                $('#finalizeSignature').prop('disabled', false).html('<i class="fa fa-check"></i> Finalizar Assinatura');
            }
        });
    });

    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
    }, { passive: false });

    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        updateActiveStroke(e);
    }, { passive: false });

    canvas.addEventListener('touchend', function(e) {
        e.preventDefault();
    }, { passive: false });

    canvas.addEventListener('mousemove', function(e) {
        updateActiveStroke(e);
    });

    canvas.addEventListener('mousedown', function(e) {
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        console.log('Mouse down:', {
            clientX: e.clientX,
            clientY: e.clientY,
            canvasX: x,
            canvasY: y,
            canvasWidth: canvas.width,
            canvasHeight: canvas.height,
            rectLeft: rect.left,
            rectTop: rect.top
        });
    });
});
</script>
@endpush

@push('css')
<style>
/* Aumentar largura do container para assinatura */
.eula-container {
    max-width: 1200px !important;
}

/* Signature Section Styles */
.signature-section {
    margin: 30px 0;
    text-align: center;
}

.signature-wrapper {
    margin: 20px 0;
    display: flex;
    justify-content: center;
    width: 100%;
}

.signature-canvas-container {
    position: relative;
    border: 3px solid #337ab7;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    overflow: hidden;
    width: 100%;
    max-width: 100%;
    display: block;
    transition: border-color 0.3s ease;
}

.signature-canvas-container:hover {
    border-color: #23527c;
}

.signature-canvas-container.has-signature {
    border-color: #5cb85c;
}

#signaturePad {
    display: block;
    touch-action: none;
    cursor: crosshair;
    margin: 0;
    padding: 0;
    border: none;
    outline: none;
    vertical-align: top;
}

.signature-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #999;
    pointer-events: none;
    background: rgba(248, 249, 250, 0.9);
    border-radius: 8px;
}

.signature-placeholder .fa {
    font-size: 3em;
    margin-bottom: 15px;
    color: #337ab7;
}

.signature-placeholder p {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    text-align: center;
    line-height: 1.4;
}

.signature-placeholder .help-text {
    font-size: 12px;
    color: #777;
    margin-top: 8px;
    font-weight: normal;
}

.signature-controls {
    margin-top: 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.signature-controls .btn {
    min-width: 120px;
    min-height: 36px;
}

.signature-status {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 4px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

.signature-buttons {
    margin-top: 40px;
}

.signature-buttons .btn {
    margin: 0 10px;
    min-width: 160px;
    min-height: 44px;
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .eula-container {
        max-width: 100% !important;
        padding: 15px !important;
    }
    
    .signature-section {
        margin: 20px 0;
    }
    
    .signature-canvas-container {
        border-width: 2px;
        margin: 0 5px;
        border-radius: 8px;
    }
    
    .signature-controls {
        flex-direction: row;
        gap: 8px;
        margin-top: 10px;
    }
    
    .signature-controls .btn {
        min-width: 100px;
        font-size: 12px;
        padding: 6px 12px;
    }
    
    .signature-buttons .btn {
        width: 100%;
        margin: 8px 0;
        font-size: 16px;
    }
    
    .signature-status {
        font-size: 12px;
        padding: 4px 8px;
    }
}

/* Mobile Landscape Optimizations */
@media (max-width: 768px) and (orientation: landscape) {
    .signature-section {
        margin: 15px 0;
    }
    
    .signature-wrapper {
        margin: 10px 0;
    }
    
    .signature-controls {
        margin-top: 8px;
    }
    
    .signature-buttons {
        margin-top: 20px;
    }
    
    .signature-buttons .btn {
        display: inline-block;
        width: auto;
        min-width: 140px;
        margin: 0 5px;
    }
}

/* Very small screens */
@media (max-width: 480px) {
    .col-md-8 {
        padding: 0 10px;
    }
    
    .signature-canvas-container {
        margin: 0;
    }
    
    .signature-controls {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .signature-status {
        width: 100%;
        text-align: center;
        margin-top: 5px;
    }
}

/* Touch device improvements */
@media (hover: none) and (pointer: coarse) {
    #signaturePad {
        cursor: default;
    }
    
    .signature-placeholder p {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}

/* High DPI displays */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .signature-canvas-container {
        border-width: 2px;
    }
}

/* Animation for status changes */
.signature-status {
    transition: all 0.3s ease;
}

/* Button states */
#finalizeSignature:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

#finalizeSignature:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Progress indicator consistency */
.step-progress {
    margin-bottom: 30px;
}

.step-progress .progress {
    height: 8px;
    margin-bottom: 10px;
}

/* Tablet optimizations */
@media (min-width: 769px) and (max-width: 1199px) {
    .eula-container {
        max-width: 1000px !important;
    }
}

/* Large desktop optimizations */
@media (min-width: 1200px) {
    .eula-container {
        max-width: 1200px !important;
    }
    
    .signature-section {
        margin: 40px 0;
    }
    
    .signature-canvas-container {
        border-width: 4px;
        border-radius: 15px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }
    
    .signature-placeholder .fa {
        font-size: 4em;
    }
    
    .signature-placeholder p {
        font-size: 18px;
    }
    
    .signature-controls {
        gap: 25px;
        margin-top: 20px;
    }
    
    .signature-controls .btn {
        min-width: 140px;
        min-height: 40px;
        font-size: 14px;
    }
    
    .signature-buttons .btn {
        min-width: 180px;
        min-height: 50px;
        font-size: 16px;
    }
}

/* Extra large desktop */
@media (min-width: 1400px) {
    .eula-container {
        max-width: 1400px !important;
    }
    
    .signature-canvas-container {
        border-width: 5px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.25);
    }
    
    .signature-placeholder .fa {
        font-size: 5em;
    }
    
    .signature-placeholder p {
        font-size: 20px;
    }
}
</style>
@endpush