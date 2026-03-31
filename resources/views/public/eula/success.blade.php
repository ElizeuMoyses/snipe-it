@extends('layouts.public')

@section('title', 'Assinatura Concluída')

@section('content')
{{-- Success indicator --}}
<div class="step-progress">
    <div class="progress">
        <div class="progress-bar progress-bar-success" role="progressbar" style="width: 100%">
            Processo Concluído
        </div>
    </div>
    <div class="text-center">
        <small class="text-muted">Validação de Dados &rarr; Visualização do Termo &rarr; Assinatura Digital &rarr; <strong>Concluído</strong></small>
    </div>
</div>

{{-- Success content --}}
<div class="panel panel-success">
    <div class="panel-heading">
        <h3 class="panel-title text-center">
            <i class="fa fa-check-circle"></i>
            Assinatura Realizada com Sucesso!
        </h3>
    </div>
    <div class="panel-body text-center">
        <div class="success-icon">
            <i class="fa fa-check-circle fa-5x text-success"></i>
        </div>
        
        <h4 class="text-success" style="margin-top: 20px;">
            Termo de Aceite Assinado Digitalmente
        </h4>
        
        <p class="lead">
            Seu termo de aceite foi assinado digitalmente e processado com sucesso.
        </p>
        
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            <strong>Próximos passos:</strong><br>
            &bull; O equipamento será liberado para uso conforme os termos aceitos<br>
            &bull; Você receberá uma confirmação por email em breve<br>
            &bull; O registro foi salvo no sistema para auditoria
        </div>
        
        @if(isset($pdfUrl) && !empty($pdfUrl))
        <div class="download-section" style="margin-top: 30px;">
            <h5><i class="fa fa-file-pdf-o"></i> Comprovante de Assinatura</h5>
            <p>Baixe uma cópia do termo assinado para seus registros:</p>
            <a href="{{ $pdfUrl }}" class="btn btn-primary btn-lg" target="_blank">
                <i class="fa fa-download"></i>
                Baixar PDF do Termo Assinado
            </a>
        </div>
        @endif
        
        <div class="completion-info" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <small class="text-muted">
                <i class="fa fa-clock-o"></i>
                Assinatura processada em <?php echo date('d/m/Y H:i:s'); ?>
            </small>
        </div>
        
        <div class="final-actions" style="margin-top: 30px;">
            <button onclick="window.close()" class="btn btn-default btn-lg">
                <i class="fa fa-times"></i> Fechar Janela
            </button>
        </div>
    </div>
</div>

<div class="text-center" style="margin-top: 20px;">
    <p class="text-muted">
        <small>
            <i class="fa fa-shield"></i>
            Este processo foi concluído com segurança e está registrado no sistema.
        </small>
    </p>
</div>
@endsection

@push('css')
<style>
.success-icon {
    margin: 20px 0;
}

.download-section {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}

.completion-info {
    font-size: 0.9em;
}

.final-actions .btn {
    min-width: 200px;
}

/* Garantir que o container esteja sempre centralizado */
.eula-container {
    margin-left: auto !important;
    margin-right: auto !important;
}

@media (max-width: 768px) {
    .success-icon .fa {
        font-size: 3em !important;
    }
    
    .btn-lg {
        font-size: 16px;
        padding: 12px 20px;
        width: 100%;
        margin-bottom: 10px;
    }
    
    .final-actions .btn {
        width: 100%;
        min-width: auto;
    }
    
    .download-section {
        padding: 15px;
    }
    
    .panel-body {
        padding: 20px 15px;
    }
}

@media (min-width: 769px) {
    /* Garantir alinhamento correto no desktop */
    .panel {
        margin: 0 auto;
    }
    
    .success-icon .fa {
        font-size: 4em;
    }
}
</style>
@endpush
