<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class ContractPaymentLocaleTest extends TestCase
{
    public function test_brazilian_payment_date_is_rendered_and_stored_without_month_day_swap(): void
    {
        $user = User::factory()->superuser()->create(['locale' => 'pt-BR']);
        $item = ContractInstallment::factory()->create();
        ContractStatusLabel::factory()->paid()->create(['is_default' => true]);
        $this->actingAs($user)->get(route('contracts.installments.pay', [$item->contract_id, $item->id]))
            ->assertOk()->assertSee('data-date-format="dd/mm/yyyy"', false);
        $this->post(route('contracts.installments.pay.store', [$item->contract_id, $item->id]), [
            'paid_value' => '150.00', 'payment_date' => '05/09/2026',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-05', $item->fresh()->payment_date->format('Y-m-d'));
    }

    public function test_impossible_brazilian_date_does_not_record_payment(): void
    {
        $item = ContractInstallment::factory()->create();
        $this->actingAs(User::factory()->superuser()->create(['locale' => 'pt-BR']))
            ->post(route('contracts.installments.pay.store', [$item->contract_id, $item->id]), [
                'paid_value' => '150.00', 'payment_date' => '31/02/2026',
            ])->assertSessionHasErrors('payment_date');
        $this->assertNull($item->fresh()->paid_value);
    }
}
