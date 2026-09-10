<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\User;
use Tests\TestCase;

class ContractAmendmentStaleTest extends TestCase
{
    public function test_valid_renewal_returns_success_and_updates_contract(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create([
            'start_date' => '2026-01-31', 'end_date' => '2026-06-30',
            'billing_day' => null,
        ]);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.contracts.amendments.store', $contract), [
                'amendment_type' => 'renewal', 'description' => 'Valid renewal',
                'effective_date' => '2026-07-01', 'old_end_date' => '2026-06-30',
                'new_end_date' => '2026-09-30',
            ])->assertOk()->assertStatusMessageIs('success');
        $this->assertSame('2026-09-30', $contract->fresh()->end_date->format('Y-m-d'));
        $this->assertSame(3, $contract->installments()->count());
        $this->assertSame(['2026-07-31', '2026-08-31', '2026-09-30'],
            $contract->installments()->orderBy('due_date')->get()->map(fn ($row) => $row->due_date->format('Y-m-d'))->all());
    }

    public function test_renewal_rejects_an_outdated_previous_end_date(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create([
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.contracts.amendments.store', $contract), [
                'amendment_type' => 'renewal', 'description' => 'Stale renewal',
                'effective_date' => '2026-07-01', 'old_end_date' => '2026-06-30',
                'new_end_date' => '2026-09-30',
            ])->assertOk()->assertStatusMessageIs('error');
        $this->assertSame('2026-12-31', $contract->fresh()->end_date->format('Y-m-d'));
        $this->assertDatabaseMissing('contract_amendments', ['contract_id' => $contract->id]);
    }
}
