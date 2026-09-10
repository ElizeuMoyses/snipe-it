<?php

namespace Tests\Feature\ContractTypes\Api;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\User;
use Tests\TestCase;

class ContractTypesTest extends TestCase
{
    public function test_api_can_create_update_and_list_a_contract_type(): void
    {
        $user = User::factory()->superuser()->create();

        $response = $this->actingAsForApi($user)
            ->postJson(route('api.contract-types.store'), [
                'name' => 'Managed Service',
                'code' => 'managed_service',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $type = ContractType::findOrFail($response['payload']['id']);

        $this->actingAsForApi($user)
            ->putJson(route('api.contract-types.update', $type), [
                'name' => 'Managed Service Updated',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->actingAsForApi($user)
            ->getJson(route('api.contract-types.index'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Managed Service Updated']);
    }

    public function test_api_cannot_delete_a_contract_type_in_use(): void
    {
        $type = ContractType::factory()->create();
        Contract::factory()->create(['contract_type_id' => $type->id]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->deleteJson(route('api.contract-types.destroy', $type))
            ->assertOk()
            ->assertStatusMessageIs('error');

        $this->assertDatabaseHas('contract_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    }
}
