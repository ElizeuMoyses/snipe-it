<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\User;
use Tests\TestCase;

class ContractAmendmentRectificationUiTest extends TestCase
{
    public function test_applied_amendment_shows_rectification_action_and_prefills_same_contract_reference(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create();
        $amendment = ContractAmendment::create([
            'contract_id' => $contract->id,
            'amendment_type' => 'readjustment',
            'description' => 'Synthetic applied amendment',
            'effective_date' => '2026-09-01',
            'old_value' => '100.00',
            'new_value' => '110.00',
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(route('contracts.amendments.create', [
                'contract' => $contract->id,
                'rectifies_amendment_id' => $amendment->id,
            ]), false)
            ->assertSee(trans('admin/contracts/amendment_ux.rectify_action'));

        $this->get(route('contracts.amendments.create', [
            'contract' => $contract->id,
            'rectifies_amendment_id' => $amendment->id,
        ]))
            ->assertOk()
            ->assertSee('value="'.$amendment->id.'" selected', false)
            ->assertSee(trans('admin/contracts/amendment_ux.rectification_help', ['id' => $amendment->id]));

        $this->get(route('contracts.amendments.edit', [$contract->id, $amendment->id]))
            ->assertOk()
            ->assertSee('id="effective_date"', false)
            ->assertSee('readonly', false)
            ->assertSee(trans('admin/contracts/amendment_ux.date_immutable_help'));
    }

    public function test_rectification_query_cannot_select_an_amendment_from_another_contract(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create();
        $foreignContract = Contract::factory()->withActiveStatus()->create();
        $foreignAmendment = ContractAmendment::create([
            'contract_id' => $foreignContract->id,
            'amendment_type' => 'scope_change',
            'description' => 'Foreign amendment',
            'effective_date' => '2026-09-01',
        ]);

        $this->get(route('contracts.amendments.create', [
            'contract' => $contract->id,
            'rectifies_amendment_id' => $foreignAmendment->id,
        ]))
            ->assertRedirect(route('contracts.show', $contract->id))
            ->assertSessionHas('error', trans('admin/contracts/amendment_ux.invalid_rectification'));
    }

    public function test_documentary_edit_does_not_change_applied_date_or_values_and_is_audited(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create();
        $amendment = ContractAmendment::create([
            'contract_id' => $contract->id,
            'amendment_type' => 'readjustment',
            'description' => 'Original description',
            'effective_date' => '2026-09-01',
            'old_value' => '100.00',
            'new_value' => '110.00',
        ]);

        $this->put(route('contracts.amendments.update', [$contract->id, $amendment->id]), [
            'description' => 'Documented correction note',
            'ticket_reference' => 'QA-RECT-01',
            'effective_date' => '2026-10-01',
            'new_value' => '999.00',
        ])->assertRedirect(route('contracts.show', $contract->id).'#amendments');

        $amendment->refresh();
        $this->assertSame('Documented correction note', $amendment->description);
        $this->assertSame('2026-09-01', $amendment->effective_date->format('Y-m-d'));
        $this->assertSame('110.00', $amendment->new_value);
        $this->assertDatabaseHas('contract_audit_events', [
            'contract_id' => $contract->id,
            'action' => 'amendment.updated',
            'auditable_type' => ContractAmendment::class,
            'auditable_id' => $amendment->id,
        ]);
    }
}
