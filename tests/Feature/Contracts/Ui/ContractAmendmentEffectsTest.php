<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class ContractAmendmentEffectsTest extends TestCase
{
    public function test_status_lookup_includes_labels_created_after_an_earlier_lookup(): void
    {
        ContractStatusLabel::idsForMetaType('installment', 'pending');
        $label = ContractStatusLabel::factory()->pending()->create();
        $this->assertContains($label->id, ContractStatusLabel::idsForMetaType('installment', 'pending'));
    }

    public function test_readjustment_preserves_paid_and_earlier_installments(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create(['installment_value' => '100.00']);
        $earlier = ContractInstallment::factory()->for($contract)->create(['due_date' => '2026-08-01', 'expected_value' => '100.00']);
        $pending = ContractInstallment::factory()->for($contract)->create(['due_date' => '2026-09-01', 'expected_value' => '100.00']);
        $paid = ContractInstallment::factory()->for($contract)->paid()->create(['due_date' => '2026-10-01', 'expected_value' => '100.00', 'paid_value' => '100.00']);
        $amendment = new ContractAmendment(['effective_date' => '2026-09-01', 'new_value' => '125.00']);

        $result = $contract->applyReadjustment($amendment);
        $this->assertSame(1, $result['updated_count']);
        $this->assertSame('125.00', $pending->fresh()->expected_value);
        $this->assertSame('100.00', $earlier->fresh()->expected_value);
        $this->assertSame('100.00', $paid->fresh()->expected_value);
        $this->assertSame('100.00', $paid->fresh()->paid_value);
    }

    public function test_termination_preserves_paid_overdue_and_effective_date_installments(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create();
        $onDate = ContractInstallment::factory()->for($contract)->create(['due_date' => '2026-09-01']);
        $future = ContractInstallment::factory()->for($contract)->create(['due_date' => '2026-10-01']);
        $paid = ContractInstallment::factory()->for($contract)->paid()->create(['due_date' => '2026-10-01']);
        $overdue = ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-08-01', 'status_label_id' => ContractStatusLabel::factory()->overdue(),
        ]);
        $result = $contract->applyTermination(new ContractAmendment(['effective_date' => '2026-09-01']));
        $this->assertSame(1, $result['cancelled_count']);
        $this->assertSame('cancelled', $future->fresh()->statusLabel->meta_type);
        $this->assertSame('pending', $onDate->fresh()->statusLabel->meta_type);
        $this->assertSame('paid', $paid->fresh()->statusLabel->meta_type);
        $this->assertSame('overdue', $overdue->fresh()->statusLabel->meta_type);
        $this->assertSame('cancelled', $contract->fresh()->statusLabel->meta_type);
    }
}
