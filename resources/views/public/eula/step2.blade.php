@extends('layouts.public')

@section('title', 'Termo de Aceite')

@section('content')
<div class="row">
    <div class="col-md-12">
        {{-- Progress indicator --}}
        <div class="step-progress">
            <div class="progress">
                <div class="progress-bar progress-bar-primary" role="progressbar" style="width: 66.66%">
                    Etapa 2 de 3
                </div>
            </div>
            <div class="text-center">
                <small class="text-muted">Validação de Dados → <strong>Visualização do Termo</strong> → Assinatura Digital</small>
            </div>
        </div>

        {{-- Page title --}}
        <div class="text-center">
            <h2>Termo de Aceite - Etapa 2 de 3</h2>
            <p class="lead text-muted">Leia atentamente o termo de uso e aceite para prosseguir</p>
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

        {{-- Item information --}}
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-info-circle"></i> Informações do Item
                </h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Nome do Item:</strong><br>
                        {{ $item->name ?? 'N/A' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Categoria:</strong><br>
                        {{ $item->model->category->name ?? 'N/A' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Tag do Ativo:</strong><br>
                        {{ $item->asset_tag ?? 'N/A' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- EULA content --}}
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-file-text-o"></i> Termo de Uso e Licença (EULA)
                </h4>
            </div>
            <div class="panel-body">
                <div class="eula-content" id="eulaContent" style="height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background-color: #f9f9f9;">
                    <div id="eulaRendered">
                        @if(!empty($eula))
                            @php
                                $parsedown = new \Parsedown();
                                $parsedown->setSafeMode(true);
                            @endphp
                            {!! $parsedown->text($eula) !!}
                        @else
                            <p>Termo de uso não disponível.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Agreement form --}}
        <form method="POST" action="{{ route('eula.sign.accept', $token) }}" id="eulaForm">
            @csrf
            
            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    {{-- Agreement checkbox --}}
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="agreeCheckbox" name="agree" value="1" required>
                                <strong>Li e concordo com os termos acima</strong>
                            </label>
                        </div>
                        <small class="help-block">Você deve marcar esta opção para prosseguir com a assinatura</small>
                    </div>

                    {{-- Information box --}}
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Atenção:</strong> Ao concordar com os termos, você será direcionado para a etapa de assinatura digital. 
                        Se não concordar, o processo será finalizado e registrado como recusado.
                    </div>

                    {{-- Action buttons --}}
                    <div class="form-group text-center">
                        <div class="btn-group" role="group">
                            <button type="button" 
                                    name="action" 
                                    value="decline" 
                                    class="btn btn-danger btn-lg"
                                    id="declineBtn">
                                <i class="fa fa-times"></i> Não Concordo
                            </button>
                            
                            <button type="button" 
                                    name="action" 
                                    value="accept" 
                                    class="btn btn-success btn-lg" 
                                    id="acceptBtn"
                                    disabled>
                                <i class="fa fa-check"></i> Concordo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('js')
<script>
$(document).ready(function() {
    // Enable/disable accept button based on checkbox
    $('#agreeCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('#acceptBtn').prop('disabled', !isChecked);
        
        if (isChecked) {
            $('#acceptBtn').removeClass('btn-default').addClass('btn-success');
        } else {
            $('#acceptBtn').removeClass('btn-success').addClass('btn-default');
        }
    });

    // Function to submit form with specific action (mobile-friendly)
    function submitFormWithAction(action) {
        console.log('Submitting form with action:', {
            action: action,
            checkboxChecked: $('#agreeCheckbox').is(':checked'),
            isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
            userAgent: navigator.userAgent
        });
        
        // Remove any existing action input
        $('input[name="action"]').remove();
        
        // Create a hidden input with the action value
        $('<input>').attr({
            type: 'hidden',
            name: 'action',
            value: action
        }).appendTo('#eulaForm');
        
        // Submit the form
        $('#eulaForm')[0].submit();
    }
    
    // Accept button click handler
    $('#acceptBtn').on('click touchend', function(e) {
        e.preventDefault();
        
        // Check if checkbox is checked
        if (!$('#agreeCheckbox').is(':checked')) {
            alert('Você deve marcar a opção "Li e concordo com os termos acima" para prosseguir.');
            return false;
        }
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processando...');
        
        // Submit form
        submitFormWithAction('accept');
    });
    
    // Decline button click handler
    $('#declineBtn').on('click touchend', function(e) {
        e.preventDefault();
        
        if (!confirm('Tem certeza que deseja recusar este termo? Esta ação não poderá ser desfeita.')) {
            return false;
        }
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processando...');
        
        // Submit form
        submitFormWithAction('decline');
    });

    // Scroll to top of EULA content when page loads
    $('.eula-content').scrollTop(0);

    // Add visual feedback when scrolling through EULA
    $('.eula-content').on('scroll', function() {
        const scrollTop = $(this).scrollTop();
        const scrollHeight = $(this)[0].scrollHeight;
        const clientHeight = $(this)[0].clientHeight;
        const scrollPercent = (scrollTop / (scrollHeight - clientHeight)) * 100;
        
        // Optional: Add a progress indicator for EULA reading
        if (scrollPercent > 80 && !$('#agreeCheckbox').is(':checked')) {
            // User has scrolled through most of the content
            $('#agreeCheckbox').closest('.form-group').addClass('highlight-checkbox');
        }
    });
});
</script>
@endpush

@push('css')
<style>
.eula-content {
    font-size: 14px;
    line-height: 1.6;
}

/* GitHub Flavored Markdown Styles */
.eula-content h1 {
    font-size: 24px;
    font-weight: 600;
    color: #24292e;
    border-bottom: 1px solid #e1e4e8;
    padding-bottom: 10px;
    margin-top: 24px;
    margin-bottom: 16px;
}

.eula-content h2 {
    font-size: 20px;
    font-weight: 600;
    color: #24292e;
    border-bottom: 1px solid #e1e4e8;
    padding-bottom: 8px;
    margin-top: 20px;
    margin-bottom: 12px;
}

.eula-content h3 {
    font-size: 16px;
    font-weight: 600;
    color: #24292e;
    margin-top: 16px;
    margin-bottom: 8px;
}

.eula-content h4, .eula-content h5, .eula-content h6 {
    font-size: 14px;
    font-weight: 600;
    color: #24292e;
    margin-top: 12px;
    margin-bottom: 6px;
}

.eula-content p {
    margin-bottom: 16px;
    text-align: justify;
    color: #24292e;
}

.eula-content ul, .eula-content ol {
    margin-bottom: 16px;
    padding-left: 30px;
}

.eula-content li {
    margin-bottom: 4px;
}

.eula-content blockquote {
    padding: 0 16px;
    margin: 0 0 16px 0;
    color: #6a737d;
    border-left: 4px solid #dfe2e5;
    background-color: #f6f8fa;
}

.eula-content code {
    padding: 2px 4px;
    font-size: 85%;
    background-color: rgba(27,31,35,0.05);
    border-radius: 3px;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
}

.eula-content pre {
    padding: 16px;
    overflow: auto;
    font-size: 85%;
    line-height: 1.45;
    background-color: #f6f8fa;
    border-radius: 6px;
    margin-bottom: 16px;
}

.eula-content pre code {
    background-color: transparent;
    padding: 0;
}

.eula-content table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 16px;
}

.eula-content table th,
.eula-content table td {
    padding: 6px 13px;
    border: 1px solid #dfe2e5;
}

.eula-content table th {
    background-color: #f6f8fa;
    font-weight: 600;
}

.eula-content strong {
    font-weight: 600;
}

.eula-content em {
    font-style: italic;
}

.eula-content hr {
    height: 4px;
    padding: 0;
    margin: 24px 0;
    background-color: #e1e4e8;
    border: 0;
}

.highlight-checkbox {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { background-color: transparent; }
    50% { background-color: #fff3cd; }
    100% { background-color: transparent; }
}

.btn-group .btn {
    margin: 0 10px;
    min-height: 44px; /* iOS recommended touch target size */
    touch-action: manipulation; /* Improve touch responsiveness */
}

/* Mobile-specific improvements */
@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-group .btn {
        margin: 0;
        width: 100%;
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .eula-content {
        height: 300px; /* Smaller height on mobile */
    }
}

.panel-info .panel-heading {
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.panel-info {
    border-color: #bce8f1;
}
</style>
@endpush