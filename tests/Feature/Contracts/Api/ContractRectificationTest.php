<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractAuditEvent;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractRectificationTest extends TestCase
{
    public function test_rectification_preserves_original_and_negotiated_total_and_audits_reference(): void
    {
        $this->actingAsForApi(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create(['installment_value' => '100.00', 'total_value' => '999.00', 'total_value_mode' => 'manual']);
        $original = $this->amendment($contract, ['amendment_type' => 'readjustment', 'new_value' => '100.00']);
        $installment = ContractInstallment::factory()->for($contract)->create(['due_date' => '2027-02-01', 'expected_value' => '100.00']);
        $data = ['amendment_type' => 'readjustment', 'description' => 'Synthetic rectification', 'effective_date' => '2027-01-01', 'new_value' => '90.00', 'rectifies_amendment_id' => $original->id];
        $token = $this->postJson(route('api.contracts.amendments.preview', $contract), $data)->assertOk()->json('preview_token');
        $this->postJson(route('api.contracts.amendments.store', $contract), $data + ['preview_token' => $token])->assertOk()->assertStatusMessageIs('success');
        $created = $contract->amendments()->latest('id')->first();
        $this->assertSame($original->id, $created->rectifies_amendment_id);
        $this->assertSame('100.00', $original->fresh()->new_value);
        $this->assertSame('90.00', $installment->fresh()->expected_value);
        $this->assertSame('90.00', $contract->fresh()->installment_value);
        $this->assertSame('999.00', $contract->fresh()->total_value);
        $event = ContractAuditEvent::where('contract_id', $contract->id)->where('action', 'amendment.created')->firstOrFail();
        $this->assertSame($original->id, $event->after['rectifies_amendment_id']);
    }

    public function test_foreign_and_deleted_amendment_references_are_rejected(): void
    {
        $this->actingAsForApi(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create();
        $foreign = $this->amendment(Contract::factory()->create());
        $deleted = $this->amendment($contract);
        $deleted->delete();
        foreach ([$foreign->id, $deleted->id] as $id) {
            $this->postJson(route('api.contracts.amendments.preview', $contract), ['amendment_type' => 'scope_change', 'description' => 'Synthetic', 'effective_date' => '2026-01-01', 'rectifies_amendment_id' => $id])->assertStatus(422)->assertJsonStructure(['messages' => ['rectifies_amendment_id']]);
        }
    }

    public function test_confirmation_rejects_a_reference_changed_after_preview(): void
    {
        $this->actingAsForApi(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create();
        $first = $this->amendment($contract);
        $second = $this->amendment($contract);
        $data = ['amendment_type' => 'scope_change', 'description' => 'Synthetic', 'effective_date' => '2026-01-01', 'rectifies_amendment_id' => $first->id];
        $data['preview_token'] = $this->postJson(route('api.contracts.amendments.preview', $contract), $data)->assertOk()->json('preview_token');
        $data['rectifies_amendment_id'] = $second->id;
        $this->postJson(route('api.contracts.amendments.store', $contract), $data)->assertStatus(422);
        $this->assertSame(2, $contract->amendments()->count());
    }
    private function amendment(Contract $contract, array $attributes = []): ContractAmendment
    {
        return ContractAmendment::create($attributes + ['contract_id' => $contract->id, 'amendment_type' => 'scope_change', 'description' => 'Synthetic original', 'effective_date' => '2026-01-01']);
    }
}
