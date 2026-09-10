<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class ShowContractTest extends TestCase
{
    public function test_permission_required_to_view_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('contracts.show', $contract))
            ->assertForbidden();
    }

    public function test_show_page_renders()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->superuser()->create(['locale' => 'pt-BR']))
            ->get(route('contracts.show', $contract))
            ->assertOk();
    }

    public function test_show_page_exposes_summary_tabs_and_persisted_financial_treatment(): void
    {
        Carbon::setTestNow('2026-09-10');
        app()->setLocale('pt-BR');

        $pending = ContractStatusLabel::factory()->pending()->create();
        $overdue = ContractStatusLabel::factory()->overdue()->create();
        $cancelled = ContractStatusLabel::factory()->forInstallments()->cancelled()->create();
        $contract = Contract::factory()->withActiveStatus()->create([
            'name' => 'Contrato de teste do resumo',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'installment_value' => '100.00',
            'total_value' => '200.00',
        ]);

        ContractInstallment::factory()->create([
            'contract_id' => $contract->id,
            'installment_number' => 1,
            'reference_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'expected_value' => '100.00',
            'paid_value' => '25.00',
            'status_label_id' => $pending->id,
        ]);
        ContractInstallment::factory()->create([
            'contract_id' => $contract->id,
            'installment_number' => 2,
            'reference_date' => '2026-09-01',
            'due_date' => '2026-09-01',
            'expected_value' => '50.00',
            'status_label_id' => $overdue->id,
        ]);
        ContractInstallment::factory()->create([
            'contract_id' => $contract->id,
            'installment_number' => 3,
            'reference_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'expected_value' => '75.00',
            'status_label_id' => $cancelled->id,
        ]);

        try {
            $this->actingAs(User::factory()->superuser()->create(['locale' => 'pt-BR']))
                ->get(route('contracts.show', $contract))
                ->assertOk()
                ->assertSee(trans('admin/contracts/general.management_summary'))
                ->assertSee(trans('admin/contracts/general.negotiated_total'))
                ->assertSee('R$ 200,00')
                ->assertSee('R$ 150,00')
                ->assertSee('R$ 25,00')
                ->assertSee('R$ 125,00')
                ->assertSee('R$ 50,00')
                ->assertSee('R$ 75,00')
                ->assertSee('01/09/2026')
                ->assertSee('role="tab"', false)
                ->assertSee('aria-selected="true"', false)
                ->assertSee(trans('admin/contracts/general.installments'))
                ->assertSee(trans('admin/contracts/general.amendments'))
                ->assertSee(trans('admin/contracts/general.linked_assets'))
                ->assertSee(trans('general.files'))
                ->assertSee(trans('general.history'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
