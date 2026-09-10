<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\User;
use Tests\TestCase;

class ContractInstallmentBoundaryTest extends TestCase
{
    public function test_rounding_up_and_zero_totals_do_not_overcharge(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        foreach (['100.01', '100.02', '0.00', '0.01'] as $total) {
            $contract = Contract::factory()->oneTime()->create([
                'total_installments' => 3,
                'total_value' => $total,
                'installment_value' => '50.00',
                'start_date' => '2026-01-01',
            ]);
            $contract->generateInstallments();
            $this->assertSame((int) str_replace('.', '', $total),
                (int) round($contract->installments()->sum('expected_value') * 100));
        }
    }

    public function test_repeating_one_time_generation_does_not_duplicate_installments(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->oneTime()->create([
            'total_installments' => 3,
            'total_value' => '100.00',
            'start_date' => '2026-01-01',
        ]);
        $this->assertSame(3, $contract->generateInstallments());
        $this->assertSame(0, $contract->generateInstallments());
        $this->assertSame(3, $contract->installments()->count());
    }

    public function test_one_time_installments_preserve_every_cent(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->oneTime()->create([
            'total_installments' => 3,
            'total_value' => '100.00',
            'start_date' => '2026-01-01',
        ]);
        $contract->generateInstallments();

        $this->assertSame(10000, (int) round($contract->installments()->sum('expected_value') * 100));
    }

    public function test_one_time_month_end_does_not_skip_february(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->oneTime()->create([
            'total_installments' => 3,
            'total_value' => '300.00',
            'start_date' => '2026-01-31',
        ]);
        $contract->generateInstallments();

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'],
            $contract->installments()->orderBy('installment_number')->get()
                ->map(fn ($installment) => $installment->due_date->format('Y-m-d'))->all());
    }

    public function test_recurring_month_end_preserves_the_original_billing_day(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->recurring()->create([
            'start_date' => '2026-01-31',
            'end_date' => '2026-04-30',
        ]);
        $contract->generateInstallments();

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
            $contract->installments()->orderBy('installment_number')->get()
                ->map(fn ($installment) => $installment->due_date->format('Y-m-d'))->all());
    }
}
