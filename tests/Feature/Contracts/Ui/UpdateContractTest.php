<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class UpdateContractTest extends TestCase
{
    public function test_permission_required_to_edit_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('contracts.edit', $contract))
            ->assertForbidden();
    }

    public function test_edit_page_renders()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.edit', $contract))
            ->assertOk();
    }

    public function test_contract_can_be_updated()
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.edit', $contract))
            ->put(route('contracts.update', $contract), [
                'name' => 'Updated Contract Name',
                'contract_number' => $contract->contract_number,
                'contract_type' => $contract->contract_type,
                'status_label_id' => $contract->status_label_id,
                'start_date' => $contract->start_date->format('Y-m-d'),
                'installment_value' => '2500.00',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('contracts.index'));
        $contract->refresh();
        $this->assertEquals('Updated Contract Name', $contract->name);
    }
}
