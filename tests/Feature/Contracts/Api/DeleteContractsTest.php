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
            ->deleteJson(route('api.contracts.destroy', $contract), [
                'reason' => 'Paid obligation requires the termination flow.',
            ])
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($contract);
    }

    public function test_requires_reason_to_archive_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAsForApi(User::factory()->deleteContracts()->create())
            ->deleteJson(route('api.contracts.destroy', $contract))
            ->assertOk()
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($contract);
        $this->assertDatabaseMissing('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $contract->id,
            'action_type' => 'delete',
        ]);
    }

    public function test_can_archive_contract_and_record_reason()
    {
        $contract = Contract::factory()->create();
        $reason = 'Supplier relationship ended without paid obligations.';

        $this->actingAsForApi(User::factory()->deleteContracts()->create())
            ->deleteJson(route('api.contracts.destroy', $contract), ['reason' => $reason])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($contract);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $contract->id,
            'action_type' => 'delete',
            'note' => $reason,
        ]);
    }
}
