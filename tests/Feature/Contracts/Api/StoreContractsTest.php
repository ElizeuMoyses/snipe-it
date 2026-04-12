<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class StoreContractsTest extends TestCase
{
    public function test_permission_required_to_store_contract()
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.contracts.store'), [
                'name' => 'API Contract',
            ])
            ->assertForbidden();
    }

    public function test_can_store_contract_via_api()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();

        $this->actingAsForApi(User::factory()->createContracts()->create())
            ->postJson(route('api.contracts.store'), [
                'name' => 'API Created Contract',
                'contract_type' => 'recurring',
                'status_label_id' => $statusLabel->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'billing_cycle' => 'monthly',
                'installment_value' => '2000.00',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('contracts', [
            'name' => 'API Created Contract',
            'contract_type' => 'recurring',
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $this->actingAsForApi(User::factory()->createContracts()->create())
            ->postJson(route('api.contracts.store'), [])
            ->assertOk()
            ->assertStatusMessageIs('error');
    }
}
