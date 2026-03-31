@extends('layouts.public')

@section('title', 'Assinatura Concluída')

@section('content')
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
        
        <div class="final-actions" style="margin-top: 30px;">
            <button onclick="window.close()" class="btn btn-default">
                <i class="fa fa-times"></i> Fechar Janela
            </button>
        </div>
    </div>
</div>
@endsection

@push('css')
<style>
.success-icon {
    margin: 20px 0;
}
</style>
@endpush