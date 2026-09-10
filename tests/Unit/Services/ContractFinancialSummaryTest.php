<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Services\ContractFinancialSummary;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ContractFinancialSummaryTest extends TestCase
{
    public function test_summarizes_partial_paid_overdue_and_cancelled_installments_without_float_math(): void
    {
        $service = new ContractFinancialSummary;
        $contract = new Contract([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_value' => '200.00',
        ]);
        $contract->setRelation('statusLabel', new ContractStatusLabel(['meta_type' => 'active']));

        $contract->setRelation('installments', new Collection([
            $this->installment('pending', '100.00', '25.00', '2026-09-15', 1),
            $this->installment('overdue', '50.00', null, '2026-09-01', 2),
            $this->installment('cancelled', '75.00', null, '2026-10-15', 3),
        ]));

        $summary = $service->summarize($contract, Carbon::parse('2026-09-10'));

        $this->assertSame(20000, $summary['negotiated_total_cents']);
        $this->assertSame(15000, $summary['planned_total_cents']);
        $this->assertSame(2500, $summary['paid_total_cents']);
        $this->assertSame(12500, $summary['open_total_cents']);
        $this->assertSame(5000, $summary['overdue_total_cents']);
        $this->assertSame(7500, $summary['cancelled_total_cents']);
        $this->assertSame(5000, $summary['negotiated_planned_difference_cents']);
        $this->assertSame('2026-09-01', $summary['next_due_date']->toDateString());
        $this->assertSame(2, $summary['next_due_installment_number']);
    }

    public function test_formatting_is_brazilian_and_zero_is_not_hidden(): void
    {
        app()->setLocale('pt-BR');

        $this->assertSame(10005, ContractFinancialSummary::toCents('100,05'));
        $this->assertSame('R$ 1.000,05', ContractFinancialSummary::formatCents(100005, 'R$'));
        $this->assertSame('R$ 0,00', ContractFinancialSummary::formatCents(0, 'R$'));
    }

    private function installment(
        string $metaType,
        string $expected,
        ?string $paid,
        string $dueDate,
        int $number
    ): ContractInstallment {
        $installment = new ContractInstallment([
            'installment_number' => $number,
            'expected_value' => $expected,
            'paid_value' => $paid,
            'due_date' => $dueDate,
        ]);
        $installment->setRelation('statusLabel', new ContractStatusLabel(['meta_type' => $metaType]));

        return $installment;
    }
}
