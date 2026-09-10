<?php

namespace Tests\Feature\ContractTypes\Ui;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\User;
use Tests\TestCase;

class ContractTypeTest extends TestCase
{
    public function test_permission_required_to_manage_contract_types(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contract-types.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_and_view_a_contract_type(): void
    {
        $user = User::factory()->superuser()->create();

        $this->actingAs($user)
            ->get(route('contract-types.create'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('contract-types.store'), [
                'name' => 'Professional Services',
                'code' => 'professional_services',
                'is_active' => 1,
                'notes' => 'Issue 9 classification',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-types.index'));

        $type = ContractType::where('code', 'professional_services')->firstOrFail();

        $this->actingAs($user)
            ->get(route('contract-types.show', $type))
            ->assertOk()
            ->assertSee('Professional Services');
    }

    public function test_contract_type_in_use_cannot_be_deleted(): void
    {
        $type = ContractType::factory()->create();
        Contract::factory()->create(['contract_type_id' => $type->id]);

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contract-types.index'))
            ->delete(route('contract-types.destroy', $type))
            ->assertRedirect(route('contract-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('contract_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    }

    public function test_web_edit_can_deactivate_a_contract_type(): void
    {
        $type = ContractType::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('contract-types.update', $type), [
                'name' => $type->name,
                'code' => $type->code,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-types.index'));

        $this->assertFalse((bool) $type->fresh()->is_active);
    }

    public function test_unused_contract_type_can_be_soft_deleted(): void
    {
        $type = ContractType::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->delete(route('contract-types.destroy', $type))
            ->assertRedirect(route('contract-types.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($type);
    }
}
