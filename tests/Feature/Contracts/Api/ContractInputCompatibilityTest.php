<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\User;
use Tests\TestCase;

class ContractInputCompatibilityTest extends TestCase
{
    public function test_unrelated_legacy_patch_preserves_old_schedule(): void
    {
        $contract = Contract::factory()->create([
            'contract_type' => 'recurring', 'total_installments' => 5,
            'end_date' => null, 'contract_type_id' => null,
        ]);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.contracts.update', $contract), ['name' => 'Legacy revised'])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame('Legacy revised', $contract->fresh()->name);
        $this->assertSame(5, $contract->fresh()->total_installments);
        $this->assertDatabaseHas('contract_audit_events', [
            'contract_id' => $contract->id, 'action' => 'contract.updated',
        ]);
    }

    public function test_api_rejects_ambiguous_or_excess_precision_money(): void
    {
        $contract = Contract::factory()->create(['installment_value' => '100.00']);
        $this->actingAsForApi(User::factory()->superuser()->create());
        foreach (['1.234', '1,23', 123.456] as $amount) {
            $this->patchJson(route('api.contracts.update', $contract), ['installment_value' => $amount])
                ->assertOk()->assertJsonPath('status', 'error')
                ->assertJsonStructure(['messages' => ['installment_value']]);
        }
        $this->assertSame('100.00', $contract->fresh()->installment_value);
    }
}
