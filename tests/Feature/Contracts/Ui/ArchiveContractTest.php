<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ArchiveContractTest extends TestCase
{
    public function test_archive_preserves_children_pivot_and_audit_history(): void
    {
        $user = User::factory()->superuser()->create();
        $contract = Contract::factory()->create();
        $installment = ContractInstallment::factory()->for($contract)->create();
        $amendment = new ContractAmendment([
            'contract_id' => $contract->id,
            'amendment_type' => 'scope_change',
            'description' => 'Preserved amendment',
            'effective_date' => now()->toDateString(),
        ]);
        $amendment->save();
        $asset = Asset::factory()->create();
        $contract->assets()->attach($asset->id, ['created_at' => now()]);

        $reason = 'Record archived after the supplier relationship ended.';

        $this->actingAs($user)
            ->from(route('contracts.show', $contract))
            ->delete(route('contracts.destroy', $contract), ['reason' => $reason])
            ->assertRedirectToRoute('contracts.index');

        $this->assertSoftDeleted($contract);
        $this->assertDatabaseHas('contract_installments', [
            'id' => $installment->id,
            'contract_id' => $contract->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('contract_amendments', [
            'id' => $amendment->id,
            'contract_id' => $contract->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('contract_asset', [
            'contract_id' => $contract->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $contract->id,
            'action_type' => 'delete',
            'note' => $reason,
        ]);
        $event = \App\Models\ContractAuditEvent::where('contract_id', $contract->id)->sole();
        $this->assertSame('contract.deleted', $event->action);
        $this->assertSame($reason, $event->metadata['reason']);
        $history = app(\App\Services\Contracts\ContractAuditService::class)
            ->historyFor($contract->fresh());
        $this->assertSame(1, $history['total']);
    }

    public function test_archived_contract_remains_consultable_and_can_be_restored_without_regeneration(): void
    {
        $user = User::factory()->superuser()->create();
        $contract = Contract::factory()->create();
        $installment = ContractInstallment::factory()->for($contract)->create();

        $this->actingAs($user)
            ->delete(route('contracts.destroy', $contract), ['reason' => 'Keep the historical record.'])
            ->assertRedirectToRoute('contracts.index');

        $this->get(route('contracts.show', $contract->id))
            ->assertOk()
            ->assertSee(trans('admin/contracts/general.archived_notice'))
            ->assertSee($contract->name)
            ->assertSee(trans('admin/contracts/message.archive.detail', [
                'name' => $contract->name,
                'installments' => 1,
                'amendments' => 0,
            ]));

        $this->post(route('contracts.restore', $contract->id), ['reason' => 'Record confirmed for reuse.'])
            ->assertRedirectToRoute('contracts.show', $contract->id)
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($contract->fresh());
        $this->assertDatabaseHas('contract_installments', [
            'id' => $installment->id,
            'contract_id' => $contract->id,
        ]);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $contract->id,
            'action_type' => 'restore',
            'note' => 'Record confirmed for reuse.',
        ]);
    }

    public function test_restore_rejects_active_contract_number_conflict(): void
    {
        $user = User::factory()->superuser()->create();
        $archived = Contract::factory()->create(['contract_number' => 'CTR-RESTORE-CONFLICT']);
        $this->actingAs($user)
            ->delete(route('contracts.destroy', $archived), ['reason' => 'Archive before conflict test.']);

        Contract::factory()->create(['contract_number' => 'CTR-RESTORE-CONFLICT']);

        $this->post(route('contracts.restore', $archived->id))
            ->assertRedirectToRoute('contracts.show', $archived->id)
            ->assertSessionHas('error', trans('admin/contracts/message.archive.restore_conflict'));

        $this->assertSoftDeleted($archived);
        $this->assertDatabaseMissing('action_logs', [
            'item_type' => Contract::class,
            'item_id' => $archived->id,
            'action_type' => 'restore',
        ]);
    }
}
