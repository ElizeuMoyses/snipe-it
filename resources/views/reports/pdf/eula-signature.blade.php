{{-- PDF Template for EULA Signature - Used by TCPDF --}}
{{-- Variables: $signature (CheckoutAcceptance), $settings (optional), $eulaText (optional) --}}
<style>
    body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #333; }
    h1 { font-size: 16pt; color: #2c3e50; text-align: center; margin-bottom: 5px; }
    h2 { font-size: 12pt; color: #34495e; border-bottom: 1px solid #bdc3c7; padding-bottom: 3px; margin-top: 15px; }
    .subtitle { text-align: center; font-size: 9pt; color: #7f8c8d; margin-bottom: 20px; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.info td { padding: 4px 8px; font-size: 9pt; vertical-align: top; }
    table.info td.label { font-weight: bold; width: 35%; color: #2c3e50; background-color: #f8f9fa; }
    table.info td.value { width: 65%; }
    .eula-content { font-size: 8pt; line-height: 1.4; border: 1px solid #ddd; padding: 8px; background-color: #fafafa; margin: 8px 0; }
    .signature-section { margin-top: 15px; text-align: center; }
    .footer-note { font-size: 7pt; color: #95a5a6; text-align: center; margin-top: 20px; border-top: 1px solid #ecf0f1; padding-top: 5px; }
</style>

<h1>Comprovante de Assinatura Digital - EULA</h1>
<div class="subtitle">
    @if(isset($settings) && $settings->site_name)
        {{ $settings->site_name }} &mdash;
    @endif
    Documento gerado em {{ now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
</div>

{{-- Informações do Usuário --}}
<h2>Dados do Responsável</h2>
<table class="info">
    <tr>
        <td class="label">Nome Completo:</td>
        <td class="value">{{ $signature->assignedTo ? $signature->assignedTo->display_name : 'N/A' }}</td>
    </tr>
    @if($signature->assignedTo && $signature->assignedTo->employee_num)
    <tr>
        <td class="label">Matrícula / CPF:</td>
        <td class="value">{{ $signature->assignedTo->employee_num }}</td>
    </tr>
    @endif
    @if($signature->assignedTo && $signature->assignedTo->email)
    <tr>
        <td class="label">E-mail:</td>
        <td class="value">{{ $signature->assignedTo->email }}</td>
    </tr>
    @endif
    @if($signature->assignedTo && $signature->assignedTo->department)
    <tr>
        <td class="label">Departamento:</td>
        <td class="value">{{ $signature->assignedTo->department->name ?? 'N/A' }}</td>
    </tr>
    @endif
</table>

{{-- Informações do Item --}}
<h2>Item Aceito</h2>
<table class="info">
    <tr>
        <td class="label">Tipo:</td>
        <td class="value">
            @php
                $typeMap = [
                    'App\\Models\\Asset' => 'Ativo (Hardware)',
                    'App\\Models\\Accessory' => 'Acessório',
                    'App\\Models\\Consumable' => 'Consumível',
                    'App\\Models\\LicenseSeat' => 'Licença',
                    'App\\Models\\Component' => 'Componente',
                ];
            @endphp
            {{ $typeMap[$signature->checkoutable_type] ?? class_basename($signature->checkoutable_type) }}
        </td>
    </tr>
    @if($signature->checkoutable)
    <tr>
        <td class="label">Item:</td>
        <td class="value">
            @if(method_exists($signature->checkoutable, 'present'))
                {{ $signature->checkoutable->present()->name() }}
            @else
                {{ $signature->checkoutable->name ?? 'N/A' }}
            @endif
        </td>
    </tr>
    @if(isset($signature->checkoutable->asset_tag))
    <tr>
        <td class="label">Asset Tag:</td>
        <td class="value">{{ $signature->checkoutable->asset_tag }}</td>
    </tr>
    @endif
    @if(isset($signature->checkoutable->serial))
    <tr>
        <td class="label">Nº Série:</td>
        <td class="value">{{ $signature->checkoutable->serial }}</td>
    </tr>
    @endif
    @if($signature->checkoutable->model ?? null)
    <tr>
        <td class="label">Modelo:</td>
        <td class="value">{{ $signature->checkoutable->model->name ?? 'N/A' }}</td>
    </tr>
    @endif
    @endif
</table>

{{-- Informações da Assinatura --}}
<h2>Dados da Assinatura</h2>
<table class="info">
    <tr>
        <td class="label">ID da Aceitação:</td>
        <td class="value">#{{ $signature->id }}</td>
    </tr>
    <tr>
        <td class="label">Data de Aceitação:</td>
        <td class="value">
            {{ $signature->accepted_at
                ? $signature->accepted_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s')
                : 'N/A' }}
        </td>
    </tr>
    @if($signature->signature_device_type)
    <tr>
        <td class="label">Dispositivo:</td>
        <td class="value">{{ $signature->signature_device_type }}</td>
    </tr>
    @endif
    @if($signature->signature_ip)
    <tr>
        <td class="label">Endereço IP:</td>
        <td class="value">{{ $signature->signature_ip }}</td>
    </tr>
    @endif
    @if($signature->signature_latitude && $signature->signature_longitude)
    <tr>
        <td class="label">Geolocalização:</td>
        <td class="value">{{ $signature->signature_latitude }}, {{ $signature->signature_longitude }}</td>
    </tr>
    @endif
</table>

{{-- Texto do EULA --}}
@if(isset($eulaText) && $eulaText)
<h2>Termo de Uso (EULA) Aceito</h2>
<div class="eula-content">
    {!! $eulaText !!}
</div>
@endif

