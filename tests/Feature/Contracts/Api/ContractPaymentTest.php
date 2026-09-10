<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractPaymentTest extends TestCase
{
    public function test_payment_cannot_overwrite_an_already_paid_installment(): void
    {
        $installment = ContractInstallment::factory()->paid()->create(['paid_value' => '150.00']);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.contracts.installments.pay.store', [$installment->contract_id, $installment->id]), [
                'paid_value' => '999.00', 'payment_date' => '2026-09-10',
            ])->assertStatus(422)->assertStatusMessageIs('error');

        $this->assertSame('150.00', $installment->fresh()->paid_value);
    }

    public function test_negative_payment_does_not_change_pending_installment(): void
    {
        $installment = ContractInstallment::factory()->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.contracts.installments.pay.store', [$installment->contract_id, $installment->id]), [
                'paid_value' => '-1.00', 'payment_date' => '2026-09-10',
            ])->assertStatusMessageIs('error');

        $this->assertNull($installment->fresh()->paid_value);
    }
}
