<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ArchiveContractsTest extends TestCase
{
    public function test_archived_contracts_are_listed_and_restored_through_the_api(): void
    {
        $user = User::factory()->superuser()->create();
        $contract = Contract::factory()->create();
        $installment = ContractInstallment::factory()->for($contract)->create();

        $this->actingAsForApi($user)
            ->deleteJson(route('api.contracts.destroy', $contract), [
                'reason' => 'API archive keeps the historical record.',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->actingAsForApi($user)
            ->getJson(route('api.contracts.index'))
            ->assertOk()
            ->assertJsonMissing([
                'id' => $contract->id,
            ]);

        $this->actingAsForApi($user)
            ->getJson(route('api.contracts.index', ['archived' => 1]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $contract->id,
                'is_archived' => true,
            ]);

        $this->actingAsForApi($user)
            ->getJson(route('api.contracts.show', $contract->id))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $contract->id,
                'is_archived' => true,
            ]);

        $this->actingAsForApi($user)
            ->postJson(route('api.contracts.restore', $contract->id), [
                'reason' => 'API restore approved after review.',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertNotSoftDeleted($contract->fresh());
        $this->assertDatabaseHas('contract_installments', [
            'id' => $installment->id,
            'contract_id' => $contract->id,
        ]);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $contract->id,
            'action_type' => 'restore',
            'note' => 'API restore approved after review.',
        ]);
    }

    public function test_api_cannot_archive_a_contract_with_a_paid_installment(): void
    {
        $contract = Contract::factory()->create();
        ContractInstallment::factory()->for($contract)->paid()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.contracts.destroy', $contract), [
                'reason' => 'Attempted archive with a paid obligation.',
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'status' => 'error',
            ]);

        $this->assertNotSoftDeleted($contract);
        $this->assertDatabaseMissing('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $contract->id,
            'action_type' => 'delete',
        ]);
    }
}
