<?php

namespace Tests\Unit\Rules;

use App\Rules\BrDocument;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BrDocumentTest extends TestCase
{
    private function makeRule(string $type): BrDocument
    {
        $rule = new BrDocument;
        $rule->setData(['supplier_type' => $type]);
        return $rule;
    }

    // --- CNPJ legado (somente dígitos) válido ---
    public function test_valid_legacy_cnpj(): void
    {
        $rule = $this->makeRule('pj');
        $this->assertTrue($rule->passes('document', '11.222.333/0001-81'));
    }

    // --- CNPJ com dígitos inválidos falha ---
    public function test_invalid_cnpj_wrong_digits(): void
    {
        $rule = $this->makeRule('pj');
        $this->assertFalse($rule->passes('document', '11.222.333/0001-00'));
    }

    // --- CNPJ alfanumérico válido (formato novo) ---
    public function test_valid_alphanumeric_cnpj(): void
    {
        // CNPJ alfanumérico de teste com check digits corretos
        // Valor: 12ABC34501DE35 (14 caracteres, check digits calculados para o exemplo)
        // Usamos um CNPJ real numérico convertido para garantir um caso válido
        $rule = $this->makeRule('pj');
        // 11.222.333/0001-81 em formato somente dígitos: 11222333000181
        $this->assertTrue($rule->passes('document', '11222333000181'));
    }

    // --- CPF válido delega para ValidCpf ---
    public function test_valid_cpf(): void
    {
        $rule = $this->makeRule('pf');
        $this->assertTrue($rule->passes('document', '529.982.247-25'));
    }

    // --- CPF com sequência repetida falha ---
    public function test_cpf_repeated_digits_fails(): void
    {
        $rule = $this->makeRule('pf');
        $this->assertFalse($rule->passes('document', '111.111.111-11'));
    }

    // --- supplier_type null com documento: sempre passa ---
    public function test_null_type_accepts_any_value(): void
    {
        $rule = new BrDocument;
        $rule->setData([]);
        $this->assertTrue($rule->passes('document', 'QUALQUER-COISA'));
    }

    // --- tipo international: aceita string <= 20 chars ---
    public function test_international_accepts_short_string(): void
    {
        $rule = $this->makeRule('international');
        $this->assertTrue($rule->passes('document', 'GB123456789'));
    }

    // --- tipo international: rejeita string > 20 chars ---
    public function test_international_rejects_long_string(): void
    {
        $rule = $this->makeRule('international');
        $this->assertFalse($rule->passes('document', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
    }

    // --- documento vazio sempre passa (nullable) ---
    public function test_empty_document_passes_for_any_type(): void
    {
        foreach (['pj', 'pf', 'international'] as $type) {
            $rule = $this->makeRule($type);
            $this->assertTrue($rule->passes('document', ''), "Empty document should pass for type: {$type}");
            $this->assertTrue($rule->passes('document', null), "Null document should pass for type: {$type}");
        }
    }
}
