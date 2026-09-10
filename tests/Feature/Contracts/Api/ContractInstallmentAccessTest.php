<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractInstallmentAccessTest extends TestCase
{
    public function test_view_permission_does_not_allow_payment(): void
    {
        $installment = ContractInstallment::factory()->create();
        $this->actingAsForApi(User::factory()->viewContracts()->create())
            ->postJson(route('api.contracts.installments.pay.store', [$installment->contract_id, $installment->id]), [
                'paid_value' => 100, 'payment_date' => '2026-09-10',
            ])->assertForbidden();
        $this->assertNull($installment->fresh()->paid_value);
    }

    public function test_installment_cannot_be_read_under_another_contract(): void
    {
        $installment = ContractInstallment::factory()->create();
        $other = Contract::factory()->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.contracts.installments.show', [$other->id, $installment->id]))
            ->assertOk()->assertStatusMessageIs('error')->assertMessagesAre('ContractInstallment not found');
    }

    public function test_company_isolation_applies_to_installment_reads_and_payments(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $installment = ContractInstallment::factory()->for($contract)->create();
        $user = User::factory()->for($companyB)->create([
            'permissions' => json_encode(['contracts.view' => '1', 'contracts.installments' => '1']),
        ]);

        $this->actingAsForApi($user)
            ->getJson(route('api.contracts.installments.show', [$contract->id, $installment->id]))
            ->assertOk()->assertStatusMessageIs('error')->assertMessagesAre('Contract not found');
        $this->postJson(route('api.contracts.installments.pay.store', [$contract->id, $installment->id]), [
            'paid_value' => 100, 'payment_date' => '2026-09-10',
        ])->assertOk()->assertStatusMessageIs('error')->assertMessagesAre('Contract not found');
        $this->assertDatabaseHas('contract_installments', ['id' => $installment->id, 'paid_value' => null]);
    }
}
