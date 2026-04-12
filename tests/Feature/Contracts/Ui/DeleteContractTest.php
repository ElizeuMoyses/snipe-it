<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class DeleteContractTest extends TestCase
{
    public function test_permission_required_to_delete_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('contracts.destroy', $contract))
            ->assertForbidden();

        $this->assertNotSoftDeleted($contract);
    }

    public function test_cannot_delete_contract_with_paid_installments()
    {
        $contract = Contract::factory()->create();

        // Use the seeded "Pago" status to avoid idsForMetaType static cache miss
        $paidStatus = ContractStatusLabel::where('scope', 'installment')
            ->where('meta_type', 'paid')
            ->firstOrFail();

        ContractInstallment::factory()->create([
            'contract_id' => $contract->id,
            'status_label_id' => $paidStatus->id,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.index'))
            ->delete(route('contracts.destroy', $contract))
            ->assertRedirect(route('contracts.index'));

        $this->assertNotSoftDeleted($contract);
    }

    public function test_can_delete_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.index'))
            ->delete(route('contracts.destroy', $contract))
            ->assertRedirectToRoute('contracts.index')
            ->assertSessionHas('success');

        $this->assertSoftDeleted($contract);
    }
}
