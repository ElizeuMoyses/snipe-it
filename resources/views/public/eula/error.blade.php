@extends('layouts.public')

@section('title')
    {{ trans('general.error') }} - {{ trans('general.eula') }}
@stop

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-danger">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-exclamation-triangle"></i>
                        @switch($errorType ?? 'general')
                            @case('invalid_token')
                                Link Inválido
                                @break
                            @case('expired_token')
                                Link Expirado
                                @break
                            @case('blocked_token')
                                Acesso Bloqueado
                                @break
                            @case('already_signed')
                                Termo Já Assinado
                                @break
                            @default
                                Erro no Processo
                        @endswitch
                    </h3>
                </div>
                <div class="panel-body text-center">
                    <div class="error-icon">
                        <i class="fa fa-exclamation-triangle fa-5x text-danger"></i>
                    </div>
                    
                    @switch($errorType ?? 'general')
                        @case('invalid_token')
                            <h4 class="text-danger" style="margin-top: 20px;">
                                Link de Assinatura Inválido
                            </h4>
                            <p class="lead">
                                O link que você acessou não é válido ou foi corrompido.
                            </p>
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i>
                                <strong>O que fazer:</strong><br>
                                Verifique se o link foi copiado corretamente ou solicite um novo link ao administrador do sistema.
                            </div>
                            @break
                            
                        @case('expired_token')
                            <h4 class="text-danger" style="margin-top: 20px;">
                                Link de Assinatura Expirado
                            </h4>
                            <p class="lead">
                                Este link de assinatura expirou e não pode mais ser utilizado.
                            </p>
                            <div class="alert alert-warning">
                                <i class="fa fa-clock-o"></i>
                                <strong>O que fazer:</strong><br>
                                Entre em contato com o administrador do sistema para solicitar um novo link de assinatura.
                                Os links têm validade de 30 dias por motivos de segurança.
                            </div>
                            @break
                            
                        @case('blocked_token')
                            <h4 class="text-danger" style="margin-top: 20px;">
                                Acesso Temporariamente Bloqueado
                            </h4>
                            <p class="lead">
                                Muitas tentativas incorretas foram detectadas. O acesso foi bloqueado temporariamente.
                            </p>
                            <div class="alert alert-danger">
                                <i class="fa fa-shield"></i>
                                <strong>Bloqueio de Segurança:</strong><br>
                                Aguarde 30 minutos antes de tentar novamente ou entre em contato com o administrador.
                            </div>
                            @break
                            
                        @case('already_signed')
                            <h4 class="text-warning" style="margin-top: 20px;">
                                Termo Já Assinado
                            </h4>
                            <p class="lead">
                                Este termo de aceite já foi assinado anteriormente.
                            </p>
                            <div class="alert alert-info">
                                <i class="fa fa-check-circle"></i>
                                <strong>Status:</strong><br>
                                O processo de assinatura já foi concluído. Não é necessária nenhuma ação adicional.
                            </div>
                            @break
                            
                        @default
                            <h4 class="text-danger" style="margin-top: 20px;">
                                Erro no Processo de Assinatura
                            </h4>
                            <p class="lead">
                                Ocorreu um erro inesperado durante o processo de assinatura.
                            </p>
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong>O que fazer:</strong><br>
                                Tente novamente em alguns minutos ou entre em contato com o suporte técnico.
                            </div>
                    @endswitch
                    
                    @if(isset($customMessage))
                        <div class="alert alert-info" style="margin-top: 20px;">
                            <i class="fa fa-info-circle"></i>
                            {{ $customMessage }}
                        </div>
                    @endif
                    
                    <div class="action-buttons" style="margin-top: 30px;">
                        @if(in_array($errorType ?? 'general', ['expired_token', 'invalid_token']))
                            <p class="text-muted">
                                <strong>Precisa de ajuda?</strong><br>
                                Entre em contato com o administrador do sistema para obter um novo link de assinatura.
                            </p>
                        @elseif(($errorType ?? 'general') === 'blocked_token')
                            <a href="{{ url()->current() }}" class="btn btn-warning">
                                <i class="fa fa-refresh"></i>
                                Tentar Novamente
                            </a>
                            <p class="text-muted" style="margin-top: 10px;">
                                <small>Aguarde 30 minutos antes de tentar novamente</small>
                            </p>
                        @elseif(($errorType ?? 'general') === 'already_signed')
                            <p class="text-muted">
                                O processo foi concluído com sucesso anteriormente.
                            </p>
                        @else
                            <a href="{{ url()->current() }}" class="btn btn-primary">
                                <i class="fa fa-refresh"></i>
                                Tentar Novamente
                            </a>
                        @endif
                    </div>
                    
                    <div class="error-details" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <small class="text-muted">
                            <i class="fa fa-clock-o"></i>
                            Erro detectado em {{ now()->format('d/m/Y H:i:s') }}
                            @if(isset($errorCode))
                                | Código: {{ $errorCode }}
                            @endif
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <p class="text-muted">
                    <small>
                        Se o problema persistir, entre em contato com o suporte técnico.
                    </small>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.error-icon {
    margin: 20px 0;
}

.action-buttons {
    padding: 20px;
}

.error-details {
    font-size: 0.9em;
}

.alert {
    text-align: left;
    margin: 20px 0;
}

.alert strong {
    display: block;
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    
    .col-md-8 {
        margin: 0;
        width: 100%;
    }
    
    .error-icon .fa {
        font-size: 3em !important;
    }
    
    .btn {
        font-size: 16px;
        padding: 12px 20px;
        margin: 5px;
    }
    
    .alert {
        font-size: 14px;
        padding: 15px;
    }
}
</style>
@stop