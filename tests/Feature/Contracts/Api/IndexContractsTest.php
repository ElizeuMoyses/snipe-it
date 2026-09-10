<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class IndexContractsTest extends TestCase implements TestsPermissionsRequirement
{
    public function test_requires_permission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.contracts.index'))
            ->assertForbidden();
    }

    public function test_can_list_contracts()
    {
        Contract::factory()->count(3)->create();

        $this->actingAsForApi(User::factory()->viewContracts()->create())
            ->getJson(route('api.contracts.index'))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ]);
    }

    public function test_filters_by_validity_and_due_date_and_returns_brazilian_contract_fields(): void
    {
        Carbon::setTestNow('2026-09-10');
        app()->setLocale('pt-BR');

        $pending = ContractStatusLabel::factory()->pending()->create();
        $activeContract = Contract::factory()->withActiveStatus()->create([
            'name' => 'Contrato com parcela próxima',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'installment_value' => '100.00',
            'total_value' => '200.00',
        ]);
        ContractInstallment::factory()->create([
            'contract_id' => $activeContract->id,
            'installment_number' => 1,
            'reference_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'expected_value' => '100.00',
            'paid_value' => '25.00',
            'status_label_id' => $pending->id,
        ]);

        $expiredContract = Contract::factory()->withActiveStatus()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);
        ContractInstallment::factory()->create([
            'contract_id' => $expiredContract->id,
            'due_date' => '2025-12-01',
            'status_label_id' => $pending->id,
        ]);

        try {
            $this->actingAsForApi(User::factory()->superuser()->create(['locale' => 'pt-BR']))
                ->getJson(route('api.contracts.index', [
                    'company_id' => $activeContract->company_id,
                    'supplier_id' => $activeContract->supplier_id,
                    'status_label_id' => $activeContract->status_label_id,
                    'validity' => 'current',
                    'due_status' => 'upcoming',
                    'sort' => 'next_due_date',
                    'order' => 'asc',
                ]))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('rows.0.id', $activeContract->id)
                ->assertJsonPath('rows.0.next_due_date.date', '2026-09-15')
                ->assertJsonPath('rows.0.next_due_date.formatted', '15/09/2026')
                ->assertJsonPath('rows.0.total_value', '200.00')
                ->assertJsonPath('rows.0.total_value_formatted', 'R$ 200,00')
                ->assertJsonPath('rows.0.validity', 'Vigente');
        } finally {
            Carbon::setTestNow();
        }
    }
}
