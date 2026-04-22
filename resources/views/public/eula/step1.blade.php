@extends('layouts.public')

@section('title', 'Validação de Dados')

@section('content')
<div class="row">
    <div class="col-md-12">
        {{-- Progress indicator --}}
        <div class="step-progress">
            <div class="progress">
                <div class="progress-bar progress-bar-primary" role="progressbar" style="width: 33.33%">
                    Etapa 1 de 3
                </div>
            </div>
            <div class="text-center">
                <small class="text-muted">Validação de Dados → Visualização do Termo → Assinatura Digital</small>
            </div>
        </div>

        {{-- Page title --}}
        <div class="text-center">
            <h2>Validação de Dados - Etapa 1 de 3</h2>
            <p class="lead text-muted">Para assinar o termo, confirme seus dados pessoais</p>
        </div>

        {{-- Error messages --}}
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4><i class="fa fa-exclamation-triangle"></i> Atenção!</h4>
                @if ($errors->has('cpf') || $errors->has('full_name'))
                    <p><strong>Os dados informados não correspondem aos registros do sistema.</strong></p>
                    <p>Por favor, verifique:</p>
                    <ul style="margin-bottom: 0;">
                        @if ($errors->has('cpf'))
                            <li><strong>CPF:</strong> {{ $errors->first('cpf') }}</li>
                        @endif
                        @if ($errors->has('full_name'))
                            <li><strong>Nome:</strong> {{ $errors->first('full_name') }}</li>
                        @endif
                    </ul>
                    <hr style="margin: 10px 0;">
                    <p style="margin-bottom: 0;">
                        <i class="fa fa-info-circle"></i> 
                        <small>Certifique-se de que os dados estão exatamente como cadastrados no sistema. 
                        Após 5 tentativas incorretas, o acesso será bloqueado por 30 minutos.</small>
                    </p>
                @else
                    <ul style="margin-bottom: 0;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4><i class="fa fa-exclamation-triangle"></i> Erro</h4>
                {{ session('error') }}
            </div>
        @endif

        {{-- Validation form --}}
        <form method="POST" action="{{ route('eula.sign.validate', $token) }}" id="validationForm">
            @csrf
            
            <div class="row">
                <div class="col-md-6 col-md-offset-3">
                    {{-- CPF field --}}
                    <div class="form-group">
                        <label for="cpf" class="control-label">CPF *</label>
                        <input type="text" 
                               class="form-control" 
                               id="cpf" 
                               name="cpf" 
                               placeholder="000.000.000-00"
                               value="{{ old('cpf') }}"
                               maxlength="14"
                               required>
                        <small class="help-block">
                            Digite seu CPF no formato 000.000.000-00.<br>
                            @if(isset($masked_cpf))
                            <strong>Dica:</strong> O CPF esperado é parecido com <code>{{ $masked_cpf }}</code>
                            @endif
                        </small>
                    </div>

                    {{-- Full name field --}}
                    <div class="form-group">
                        <label for="full_name" class="control-label">Nome Completo *</label>
                        <input type="text" 
                               class="form-control" 
                               id="full_name" 
                               name="full_name" 
                               placeholder="Digite seu nome completo"
                               value="{{ old('full_name') }}"
                               maxlength="255"
                               required>
                        <small class="help-block">
                            Digite seu nome completo conforme cadastrado no sistema.<br>
                            @if(isset($masked_name))
                            <strong>Dica:</strong> O nome esperado é parecido com <code>{{ $masked_name }}</code>
                            @endif
                        </small>
                    </div>

                    {{-- Information box --}}
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Importante:</strong> Os dados informados devem corresponder exatamente aos dados cadastrados no sistema. 
                        Após 5 tentativas incorretas, o acesso será bloqueado temporariamente por 30 minutos.
                    </div>

                    {{-- Debug information (remover em produção) --}}
                    @if(config('app.debug'))
                    <div class="alert alert-warning">
                        <i class="fa fa-bug"></i>
                        <strong>Debug Info:</strong><br>
                        <small>
                            <strong>Nome esperado:</strong> {{ $user->first_name }} {{ $user->last_name }}<br>
                            <strong>CPF esperado:</strong> {{ $user->employee_num ? substr($user->employee_num, 0, 3) . '.***.**' . substr($user->employee_num, -2) : 'Não cadastrado' }}<br>
                            <strong>Usuário ID:</strong> {{ $user->id }}
                        </small>
                    </div>
                    @endif

                    {{-- Submit button --}}
                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary btn-lg" id="continueBtn">
                            <i class="fa fa-arrow-right"></i> Continuar
                        </button>
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
    function formatCPF(value) {
        const digits = value.replace(/\D/g, '').slice(0, 11);

        if (digits.length <= 3) {
            return digits;
        }

        if (digits.length <= 6) {
            return digits.replace(/^(\d{3})(\d+)/, '$1.$2');
        }

        if (digits.length <= 9) {
            return digits.replace(/^(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
        }

        return digits.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*$/, function(match, part1, part2, part3, part4) {
            return part4 ? `${part1}.${part2}.${part3}-${part4}` : `${part1}.${part2}.${part3}`;
        });
    }

    $('#cpf').on('input', function() {
        this.value = formatCPF(this.value);
    });

    $('#cpf').val(formatCPF($('#cpf').val()));

    // CPF validation function
    function validateCPF(cpf) {
        // Remove dots and dashes
        cpf = cpf.replace(/[^\d]+/g, '');
        
        // Check if has 11 digits
        if (cpf.length !== 11) return false;
        
        // Check if all digits are the same
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        
        // Validate first check digit
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let remainder = (sum * 10) % 11;
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf.charAt(9))) return false;
        
        // Validate second check digit
        sum = 0;
        for (let i = 0; i < 10; i++) {
            sum += parseInt(cpf.charAt(i)) * (11 - i);
        }
        remainder = (sum * 10) % 11;
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf.charAt(10))) return false;
        
        return true;
    }

    // Real-time CPF validation
    $('#cpf').on('blur', function() {
        const cpf = $(this).val();
        const formGroup = $(this).closest('.form-group');
        
        // Remove previous validation classes
        formGroup.removeClass('has-error has-success');
        formGroup.find('.help-block.error').remove();
        
        if (cpf && !validateCPF(cpf)) {
            formGroup.addClass('has-error');
            formGroup.append('<small class="help-block error text-danger">CPF inválido</small>');
        } else if (cpf) {
            formGroup.addClass('has-success');
        }
    });

    // Form validation before submit
    $('#validationForm').on('submit', function(e) {
        const cpf = $('#cpf').val();
        const fullName = $('#full_name').val().trim();
        
        // Validate CPF
        if (!validateCPF(cpf)) {
            e.preventDefault();
            alert('Por favor, digite um CPF válido.');
            $('#cpf').focus();
            return false;
        }
        
        // Validate full name
        if (fullName.length < 3) {
            e.preventDefault();
            alert('Por favor, digite seu nome completo.');
            $('#full_name').focus();
            return false;
        }
        
        // Show loading state
        $('#continueBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Validando...');
    });

    // Auto-focus on first field
    $('#cpf').focus();
});
</script>
@endpush