<?php
namespace Tests\Feature\Contracts\Ui;

use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractInstallmentTotalsTest extends TestCase
{
    public function test_footer_formats_cents_without_multiplying_them_again(): void
    {
        $item = ContractInstallment::factory()->paid()->create(['expected_value' => '100.00', 'paid_value' => '100.00']);
        $response = $this->actingAs(User::factory()->superuser()->create(['locale' => 'pt-BR']))
            ->get(route('contracts.show', $item->contract_id))->assertOk();
        preg_match('/<tfoot>(.*?)<\/tfoot>/s', $response->getContent(), $footer);
        $this->assertSame(2, substr_count($footer[1] ?? '', 'R$ 100,00'));
        $this->assertStringNotContainsString('10.000,00', $footer[1] ?? '');
    }
}
