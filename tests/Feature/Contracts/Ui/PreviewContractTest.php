<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\User;
use Tests\TestCase;

class PreviewContractTest extends TestCase
{
    public function test_ui_preview_returns_the_same_eom_schedule_used_for_generation(): void
    {
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->postJson(route('contracts.preview'), [
                'contract_type' => 'recurring',
                'start_date' => '2028-01-01',
                'end_date' => '2028-03-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 31,
                'installment_value' => 'R$ 100,00',
            ])
            ->assertOk();

        $response->assertJsonPath('data.installments_count', 3)
            ->assertJsonPath('data.dates.1', '2028-02-29')
            ->assertJsonPath('data.planned_total', '300.00')
            ->assertJsonPath('data.negotiated_total', '300.00');
    }

    public function test_ui_preview_rejects_an_invalid_billing_day_in_portuguese(): void
    {
        $user = User::factory()->superuser()->create(['locale' => 'pt-BR']);

        $this->actingAs($user)
            ->postJson(route('contracts.preview'), [
                'contract_type' => 'recurring',
                'start_date' => '2028-01-01',
                'end_date' => '2028-03-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 32,
                'installment_value' => '100.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.billing_day.0', 'O campo Dia de Vencimento deve estar entre 1 e 31.');
    }
}
