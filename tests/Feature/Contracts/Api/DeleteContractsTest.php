<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteContractsTest extends TestCase implements TestsPermissionsRequirement
{
    public function test_requires_permission()
    {
        $contract = Contract::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.contracts.destroy', $contract))
            ->assertForbidden();

        $this->assertNotSoftDeleted($contract);
    }

    public function test_cannot_delete_contract_with_paid_installments()
    {
        $paidStatus = ContractStatusLabel::factory()->paid()->create();
        $contract = Contract::factory()->create();

        ContractInstallment::factory()->create([
            'contract_id' => $contract->id,
            'status_label_id' => $paidStatus->id,
        ]);

        $this->actingAsForApi(User::factory()->deleteContracts()->create())
            ->deleteJson(route('api.contracts.destroy', $contract))
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($contract);
    }

    public function test_can_delete_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAsForApi(User::factory()->deleteContracts()->create())
            ->deleteJson(route('api.contracts.destroy', $contract))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($contract);
    }
}
