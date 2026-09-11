<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class ContractAmendmentPreviewTest extends TestCase
{
    public function test_readjustment_preview_exposes_complete_impact_from_server_value(): void
    {
        $contract = $this->activeContract(['installment_value' => '100.00']);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-08-01', 'expected_value' => '100.00',
        ]);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-09-01', 'expected_value' => '100.00',
        ]);
        ContractInstallment::factory()->for($contract)->paid()->create([
            'due_date' => '2026-10-01', 'expected_value' => '100.00', 'paid_value' => '100.00',
        ]);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-08-15', 'expected_value' => '90.00',
            'status_label_id' => ContractStatusLabel::factory()->overdue(),
        ]);

        // Soft-deleted installments must not inflate the operational preview.
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-10-15', 'expected_value' => '999.00',
        ])->delete();

        $response = $this->preview($contract, [
            'amendment_type' => 'readjustment',
            'description' => 'Synthetic readjustment preview',
            'effective_date' => '2026-09-01',
            'old_value' => '100.00',
            'new_value' => '125.00',
        ]);

        $response->assertOk()
            ->assertJsonPath('preview_valid', true)
            ->assertJsonPath('type', 'readjustment')
            ->assertJsonPath('old_value', '100.00')
            ->assertJsonPath('new_value', '125.00')
            ->assertJsonPath('impact.current_value', '100.00')
            ->assertJsonPath('impact.difference', '25.00')
            ->assertJsonPath('impact.difference_absolute', '25.00')
            ->assertJsonPath('impact.difference_percent', '25.00')
            ->assertJsonPath('impact.affected_installments.count', 1)
            ->assertJsonPath('impact.affected_installments.value_before', '100.00')
            ->assertJsonPath('impact.affected_installments.value', '125.00')
            ->assertJsonPath('impact.preserved_installments.count', 3)
            ->assertJsonPath('impact.preserved_installments.value', '290.00');
        $this->assertNotEmpty($response->json('preview_token'));
        $this->assertCount(7, $response->json('details'));
    }

    public function test_preview_returns_structured_422_for_invalid_dates_and_client_old_value(): void
    {
        $contract = $this->activeContract(['installment_value' => '100.00']);

        $this->postJson(route('api.contracts.amendments.preview', $contract), [
            'amendment_type' => 'renewal',
            'description' => 'Invalid date preview',
            'effective_date' => '2026-09-01',
            'old_end_date' => 'not-a-date',
            'new_end_date' => '2026-12-31',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('payload', null)
            ->assertJsonStructure(['messages' => ['old_end_date']]);

        $this->postJson(route('api.contracts.amendments.preview', $contract), [
            'amendment_type' => 'readjustment',
            'description' => 'Adulterated historical value',
            'effective_date' => '2026-09-01',
            'old_value' => '999.99',
            'new_value' => '125.00',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['messages' => ['old_value']]);

        $this->assertDatabaseCount('contract_amendments', 0);
    }

    public function test_renewal_preview_and_store_share_the_original_calendar(): void
    {
        $contract = $this->activeContract([
            'start_date' => '2026-01-31',
            'end_date' => '2026-03-31',
            'billing_day' => null,
            'installment_value' => '100.00',
        ]);
        $input = [
            'amendment_type' => 'renewal',
            'description' => 'Synthetic calendar renewal',
            'effective_date' => '2026-04-01',
            'old_end_date' => '2026-03-31',
            'new_end_date' => '2026-06-30',
        ];

        $preview = $this->preview($contract, $input)
            ->assertJsonPath('impact.current_end_date', '2026-03-31')
            ->assertJsonPath('impact.new_end_date', '2026-06-30')
            ->assertJsonPath('impact.period_added_days', 91)
            ->assertJsonPath('impact.generated_installments.count', 3)
            ->assertJsonPath('impact.generated_installments.value', '300.00')
            ->assertJsonPath('impact.generated_installments.dates', [
                '2026-04-30', '2026-05-31', '2026-06-30',
            ]);

        $input['preview_token'] = $preview->json('preview_token');
        $this->postJson(route('api.contracts.amendments.store', $contract), $input)
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertSame([
            '2026-04-30', '2026-05-31', '2026-06-30',
        ], $contract->fresh()->installments()->orderBy('due_date')->get(['due_date'])
            ->map(fn ($row) => $row->due_date->format('Y-m-d'))->all());
    }

    public function test_termination_preview_explains_status_preservation_and_financial_limit(): void
    {
        $contract = $this->activeContract();
        ContractInstallment::factory()->for($contract)->create(['due_date' => '2026-09-01', 'expected_value' => '100.00']);
        ContractInstallment::factory()->for($contract)->create(['due_date' => '2026-10-01', 'expected_value' => '200.00']);
        ContractInstallment::factory()->for($contract)->paid()->create(['due_date' => '2026-08-01', 'expected_value' => '300.00']);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-08-15', 'expected_value' => '50.00',
            'status_label_id' => ContractStatusLabel::factory()->overdue(),
        ]);

        $this->preview($contract, [
            'amendment_type' => 'termination',
            'description' => 'Synthetic termination preview',
            'effective_date' => '2026-09-01',
        ])->assertJsonPath('impact.result_status.meta_type', 'cancelled')
            ->assertJsonPath('impact.cancelled_installments.count', 1)
            ->assertJsonPath('impact.cancelled_installments.value', '200.00')
            ->assertJsonPath('impact.preserved_installments.count', 3)
            ->assertJsonPath('impact.preserved_installments.value', '450.00')
            ->assertJsonPath('impact.financial_policy', 'no_automatic_fine_refund_proration')
            ->assertJsonPath('details.4.label', trans('admin/contracts/message.amendment.preview_details.financial_policy'));
    }

    public function test_scope_change_preview_is_documentary_only(): void
    {
        $contract = $this->activeContract();

        $this->preview($contract, [
            'amendment_type' => 'scope_change',
            'description' => 'Synthetic documentary scope change',
            'effective_date' => '2026-09-01',
        ])->assertJsonPath('type', 'scope_change')
            ->assertJsonPath('preview_valid', true)
            ->assertJsonPath('has_side_effects', false)
            ->assertJsonPath('impact.documentary_only', true)
            ->assertJsonPath('impact.financial_effect', 'none');
    }

    public function test_stale_preview_token_is_rejected_after_installment_change(): void
    {
        $contract = $this->activeContract(['installment_value' => '100.00']);
        $installment = ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-10-01', 'expected_value' => '100.00',
        ]);
        $input = [
            'amendment_type' => 'readjustment',
            'description' => 'Stale preview',
            'effective_date' => '2026-09-01',
            'old_value' => '100.00',
            'new_value' => '125.00',
        ];
        $token = $this->preview($contract, $input)->json('preview_token');

        $installment->expected_value = '101.00';
        $installment->save();

        $this->postJson(route('api.contracts.amendments.store', $contract), $input + [
            'preview_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['messages' => ['preview_token']]);

        $this->assertDatabaseCount('contract_amendments', 0);
        $this->assertSame('100.00', $contract->fresh()->installment_value);
    }

    public function test_readjustment_persists_server_snapshot_and_applied_amendment_cannot_be_deleted(): void
    {
        $contract = $this->activeContract(['installment_value' => '100.00']);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-10-01', 'expected_value' => '100.00',
        ]);

        $this->postJson(route('api.contracts.amendments.store', $contract), [
            'amendment_type' => 'readjustment',
            'description' => 'Server snapshot',
            'effective_date' => '2026-09-01',
            'new_value' => '125.00',
        ])->assertOk()->assertStatusMessageIs('success');

        $amendment = ContractAmendment::query()->firstOrFail();
        $this->assertSame('100.00', $amendment->old_value);
        $this->assertSame('125.00', $amendment->new_value);

        $this->deleteJson(route('api.contracts.amendments.destroy', [$contract, $amendment]))
            ->assertStatus(422)
            ->assertJsonStructure(['messages' => ['amendment']]);
        $this->assertNull($amendment->fresh()->deleted_at);
        $this->assertSame('125.00', $contract->fresh()->installment_value);
    }

    public function test_preview_is_stale_when_calendar_changes_within_same_timestamp(): void
    {
        $contract = $this->activeContract(['billing_day' => 20]);
        $input = [
            'amendment_type' => 'renewal',
            'description' => 'Synthetic calendar change',
            'effective_date' => '2026-12-31',
            'old_end_date' => '2026-12-31',
            'new_end_date' => '2027-03-31',
        ];
        $token = $this->preview($contract, $input)->json('preview_token');
        \Illuminate\Support\Facades\DB::table('contracts')->where('id', $contract->id)
            ->update(['billing_day' => 21]);

        $this->postJson(route('api.contracts.amendments.store', $contract), $input + [
            'preview_token' => $token,
        ])->assertStatus(422)->assertJsonStructure(['messages' => ['preview_token']]);
        $this->assertDatabaseCount('contract_amendments', 0);
    }
    public function test_portuguese_preview_formats_currency_for_brazil(): void
    {
        $contract = $this->activeContract(['installment_value' => '10000.00']);
        app()->setLocale('pt-BR');
        $result = app(\App\Services\ContractAmendmentPreviewService::class)->build([
            'amendment_type' => 'readjustment',
            'description' => 'Synthetic currency format',
            'effective_date' => '2026-10-01',
            'new_value' => '10500.00',
        ], $contract);
        $this->assertSame('R$ 10.000,00', $result['details'][0]['value']);
        $this->assertSame('R$ 10.500,00', $result['details'][1]['value']);
    }
    private function activeContract(array $attributes = []): Contract
    {
        $this->actingAsForApi(User::factory()->superuser()->create());

        return Contract::factory()->withActiveStatus()->create(array_merge([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'installment_value' => '100.00',
        ], $attributes));
    }

    private function preview(Contract $contract, array $input)
    {
        return $this->postJson(route('api.contracts.amendments.preview', $contract), $input)
            ->assertOk();
    }
}
