<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class UpdateContractsTest extends TestCase
{
    public function test_permission_required_to_update_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->putJson(route('api.contracts.update', $contract))
            ->assertForbidden();
    }

    public function test_can_update_contract_via_api()
    {
        $contract = Contract::factory()->create();
        $newStatus = ContractStatusLabel::factory()->active()->create();

        $this->actingAsForApi(User::factory()->editContracts()->create())
            ->putJson(route('api.contracts.update', $contract), [
                'name' => 'Updated API Contract',
                'contract_type' => $contract->contract_type,
                'status_label_id' => $newStatus->id,
                'start_date' => $contract->start_date->format('Y-m-d'),
                'installment_value' => $contract->installment_value,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'name' => 'Updated API Contract',
        ]);
    }
}
