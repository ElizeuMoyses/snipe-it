<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractInstallmentGenerationTest extends TestCase
{
    public function test_recurring_monthly_generates_correct_installments()
    {
        $this->actingAs(User::factory()->superuser()->create());

        $contract = Contract::factory()->create([
            'contract_type' => 'recurring',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'installment_value' => 1000.00,
        ]);

        $count = $contract->generateInstallments();

        $this->assertEquals(6, $count);
        $this->assertEquals(6, $contract->installments()->count());
    }

    public function test_recurring_quarterly_generates_correct_installments()
    {
        $this->actingAs(User::factory()->superuser()->create());

        $contract = Contract::factory()->create([
            'contract_type' => 'recurring',
            'billing_cycle' => 'quarterly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'installment_value' => 3000.00,
        ]);

        $count = $contract->generateInstallments();

        $this->assertEquals(4, $count);
        $this->assertEquals(4, $contract->installments()->count());
    }

    public function test_one_time_generates_correct_installments()
    {
        $this->actingAs(User::factory()->superuser()->create());

        $contract = Contract::factory()->create([
            'contract_type' => 'one_time',
            'total_installments' => 3,
            'total_value' => 9000.00,
            'start_date' => '2026-01-01',
        ]);

        $count = $contract->generateInstallments();

        $this->assertEquals(3, $count);
        $this->assertEquals(3, $contract->installments()->count());
        // Each installment should be 3000
        $this->assertEquals(3000.00, $contract->installments()->first()->expected_value);
    }

    public function test_returns_zero_without_default_status()
    {
        $this->actingAs(User::factory()->superuser()->create());

        // Clear ALL seeded defaults so there's no default installment pending status
        DB::table('contract_status_labels')
            ->where('scope', 'installment')
            ->where('meta_type', 'pending')
            ->update(['is_default' => false]);

        $contract = Contract::factory()->create([
            'contract_type' => 'recurring',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        $count = $contract->generateInstallments();

        $this->assertEquals(0, $count);
        $this->assertEquals(0, $contract->installments()->count());
    }

    public function test_recurring_without_end_date_returns_zero()
    {
        $this->actingAs(User::factory()->superuser()->create());

        $contract = Contract::factory()->create([
            'contract_type' => 'recurring',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $count = $contract->generateInstallments();

        $this->assertEquals(0, $count);
    }
}
