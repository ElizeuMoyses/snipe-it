<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractInstallmentBoundaryTest extends TestCase
{
    public function test_generation_rechecks_terminal_status_after_refreshing_the_contract(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->create([
            'contract_type' => 'recurring', 'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
        ]);
        // Simulate a cancellation committed after the controller loaded the contract.
        $cancelled = \App\Models\ContractStatusLabel::factory()->create([
            'scope' => 'contract', 'meta_type' => 'cancelled',
        ]);
        Contract::whereKey($contract->id)->update(['status_label_id' => $cancelled->id]);

        $this->assertSame(0, $contract->generateInstallments());
        $this->assertSame(0, $contract->installments()->count());
    }

    public function test_rejected_creation_rolls_back_one_time_and_recurring_generation(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        foreach (['one_time', 'recurring'] as $type) {
            $contract = Contract::factory()->create([
                'contract_type' => $type, 'billing_cycle' => 'monthly',
                'start_date' => '2026-01-01', 'end_date' => '2026-03-31',
                'total_installments' => 3, 'total_value' => '300.00',
            ]);
            $dispatcher = ContractInstallment::getEventDispatcher();
            ContractInstallment::setEventDispatcher(clone $dispatcher);
            ContractInstallment::creating(fn ($installment) => $installment->installment_number === 2 ? false : null);
            $failed = false;
            try {
                $contract->generateInstallments();
            } catch (\RuntimeException $exception) {
                $failed = true;
            } finally {
                ContractInstallment::setEventDispatcher($dispatcher);
            }
            $this->assertTrue($failed, 'Rejected creation must fail generation for '.$type);
            $this->assertSame(0, $contract->installments()->count());
        }
    }

    public function test_recurring_cycles_preserve_month_end_and_leap_year(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        foreach ([
            ['monthly', '2024-01-31', '2024-03-31', ['2024-01-31', '2024-02-29', '2024-03-31']],
            ['quarterly', '2024-01-31', '2024-10-31', ['2024-01-31', '2024-04-30', '2024-07-31', '2024-10-31']],
            ['semiannual', '2024-08-31', '2025-08-31', ['2024-08-31', '2025-02-28', '2025-08-31']],
            ['annual', '2024-02-29', '2028-02-29', ['2024-02-29', '2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29']],
        ] as [$cycle, $start, $end, $expected]) {
            $contract = Contract::factory()->recurring()->create([
                'billing_cycle' => $cycle, 'start_date' => $start, 'end_date' => $end,
            ]);
            $this->assertSame(count($expected), $contract->generateInstallments());
            $this->assertSame($expected, $contract->installments()->orderBy('installment_number')->get()
                ->map(fn ($installment) => $installment->due_date->format('Y-m-d'))->all());
            $this->assertSame(0, $contract->generateInstallments());
        }
    }

    public function test_failed_generation_rolls_back_all_created_installments(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->oneTime()->create([
            'total_installments' => 3, 'total_value' => '100.00', 'start_date' => '2026-01-01',
        ]);
        $dispatcher = ContractInstallment::getEventDispatcher();
        ContractInstallment::setEventDispatcher(clone $dispatcher);
        ContractInstallment::created(function ($installment) {
            if ($installment->installment_number === 2) {
                throw new \RuntimeException('Synthetic installment failure');
            }
        });
        try {
            $contract->generateInstallments();
            $this->fail('Expected generation failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic installment failure', $exception->getMessage());
        } finally {
            ContractInstallment::setEventDispatcher($dispatcher);
        }
        $this->assertSame(0, $contract->installments()->count());
    }

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
