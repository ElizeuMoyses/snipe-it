<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractIsolationTest extends TestCase
{
    public function test_company_isolation_blocks_ui_reads_payments_and_amendments(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->withActiveStatus()->create();
        $installment = ContractInstallment::factory()->for($contract)->create();
        $this->actingAs(User::factory()->for($companyB)->create([
            'permissions' => json_encode(['contracts.view' => '1', 'contracts.edit' => '1', 'contracts.installments' => '1']),
        ]));
        $this->get(route('contracts.show', $contract))->assertRedirect(route('contracts.index'))->assertSessionHas('error');
        $this->get(route('contracts.installments.edit', [$contract, $installment->id]))->assertRedirect(route('contracts.index'))->assertSessionHas('error');
        $this->post(route('contracts.installments.pay.store', [$contract, $installment->id]), [
            'paid_value' => '10.00', 'payment_date' => '2026-09-10',
        ])->assertRedirect(route('contracts.index'))->assertSessionHas('error');
        $this->post(route('contracts.amendments.store', $contract), [
            'amendment_type' => 'termination', 'description' => 'Cross-company attempt', 'effective_date' => '2026-09-10',
        ])->assertRedirect(route('contracts.index'))->assertSessionHas('error');
        $this->assertNull($installment->fresh()->paid_value);
        $this->assertSame(0, $contract->amendments()->count());
    }

    public function test_installment_cannot_be_edited_or_paid_under_another_contract(): void
    {
        $installment = ContractInstallment::factory()->create();
        $other = Contract::factory()->create();
        $this->actingAs(User::factory()->superuser()->create());
        $this->get(route('contracts.installments.edit', [$other, $installment->id]))->assertRedirect(route('contracts.index'))->assertSessionHas('error');
        $this->post(route('contracts.installments.pay.store', [$other, $installment->id]), [
            'paid_value' => '10.00', 'payment_date' => '2026-09-10',
        ])->assertRedirect(route('contracts.index'))->assertSessionHas('error');
        $this->assertNull($installment->fresh()->paid_value);
    }
}
