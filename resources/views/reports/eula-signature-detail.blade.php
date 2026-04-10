@extends('layouts/default')

{{-- Page title --}}
@section('title')
Detalhes da Assinatura EULA #{{ $signature->id }}
@parent
@stop

@push('css')
<style>
[data-theme="dark"] .eula-signature-detail .btn-primary {
    background-color: #225b7d;
    border-color: #1d4b68;
    color: #ffffff !important;
}

[data-theme="dark"] .eula-signature-detail .btn-primary:hover,
[data-theme="dark"] .eula-signature-detail .btn-primary:focus {
    background-color: #1d4b68;
    border-color: #16394f;
}

[data-theme="dark"] .eula-signature-detail .signature-preview-frame {
    border-color: var(--box-header-bottom-border-color) !important;
}
</style>
@endpush

@section('content')
<div class="row eula-signature-detail">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h2 class="box-title">
                    <i class="fa fa-file-signature"></i>
                    Detalhes da Assinatura #{{ $signature->id }}
                </h2>
                <div class="box-tools pull-right">
                    <a href="{{ route('reports.eula-signatures') }}" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Voltar
                    </a>
                    @if($signature->signature_filename)
                    <a href="{{ route('reports.eula-signatures.pdf', $signature->id) }}" class="btn btn-primary btn-sm" target="_blank">
                        <i class="fa fa-file-pdf"></i> Exportar PDF
                    </a>
                    @endif
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    {{-- Informações do Usuário --}}
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
                                    <dd>
                                        @if($signature->assignedTo)
                                            {{ $signature->assignedTo->first_name }} {{ $signature->assignedTo->last_name }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </dd>

                                    <dt>Email:</dt>
                                    <dd>
                                        @if($signature->assignedTo && $signature->assignedTo->email)
                                            {{ $signature->assignedTo->email }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </dd>

                                    <dt>ID do Usuário:</dt>
                                    <dd>
                                        @if($signature->assignedTo)
                                            {{ $signature->assignedTo->id }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    {{-- Informações do Item --}}
                    <div class="col-md-6">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h4 class="box-title">
                                    <i class="fa fa-cube"></i> Informações do Item
                                </h4>
                            </div>
                            <div class="box-body">
                                <dl class="dl-horizontal">
                                    <dt>Tipo:</dt>
                                    <dd>
                                        <span class="label label-info">
                                            {{ class_basename($signature->checkoutable_type) }}
                                        </span>
                                    </dd>

                                    <dt>Nome:</dt>
                                    <dd>
                                        @if($signature->checkoutable)
                                            {{ $signature->checkoutable->name ?? $signature->checkoutable->present()->name() }}
                                        @else
                                            <span class="text-muted">Item removido</span>
                                        @endif
                                    </dd>

                                    @if($signature->checkoutable && $signature->checkoutable->asset_tag)
                                    <dt>Asset Tag:</dt>
                                    <dd>{{ $signature->checkoutable->asset_tag }}</dd>
                                    @endif

                                    <dt>ID do Item:</dt>
                                    <dd>{{ $signature->checkoutable_id }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Assinatura Digital --}}
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h4 class="box-title">
                                    <i class="fa fa-signature"></i> Assinatura Digital
                                </h4>
                            </div>
                            <div class="box-body text-center">
                                @if($signature->signature_filename)
                                    <div class="signature-preview-frame" style="border: 1px solid #ddd; border-radius: 4px; padding: 20px; display: inline-block; background: #fff;">
                                        <img src="{{ route('reports.eula-signatures.signature-image', $signature->signature_filename) }}"
                                             alt="Assinatura Digital"
                                             style="max-width: 500px; max-height: 200px;">
                                    </div>
                                @else
                                    <p class="text-muted">
                                        <i class="fa fa-times-circle"></i> Sem imagem de assinatura
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Detalhes da Aceitação --}}
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h4 class="box-title">
                                    <i class="fa fa-info-circle"></i> Detalhes da Aceitação
                                </h4>
                            </div>
                            <div class="box-body">
                                <dl class="dl-horizontal">
                                    <dt>Data da Assinatura:</dt>
                                    <dd>
                                        @if($signature->accepted_at)
                                            {{ \Carbon\Carbon::parse($signature->accepted_at)->format('d/m/Y H:i:s') }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </dd>

                                    <dt>Status:</dt>
                                    <dd>
                                        @if($signature->accepted_at)
                                            <span class="label label-success">Aceito</span>
                                        @elseif($signature->declined_at)
                                            <span class="label label-danger">Recusado</span>
                                        @else
                                            <span class="label label-warning">Pendente</span>
                                        @endif
                                    </dd>

                                    <dt>Arquivo da Assinatura:</dt>
                                    <dd>
                                        @if($signature->signature_filename)
                                            <code>{{ $signature->signature_filename }}</code>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </dd>

                                    <dt>Criado em:</dt>
                                    <dd>{{ $signature->created_at ? $signature->created_at->format('d/m/Y H:i:s') : 'N/A' }}</dd>

                                    <dt>Atualizado em:</dt>
                                    <dd>{{ $signature->updated_at ? $signature->updated_at->format('d/m/Y H:i:s') : 'N/A' }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
