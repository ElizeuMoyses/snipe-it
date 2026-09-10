<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractAmendmentRollbackTest extends TestCase
{
    public function test_failed_installment_save_rolls_back_the_whole_readjustment(): void
    {
        $this->assertReadjustmentRollback(false);
    }

    public function test_api_failed_installment_save_rolls_back_the_whole_readjustment(): void
    {
        $this->assertReadjustmentRollback(true);
    }

    private function assertReadjustmentRollback(bool $api): void
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->superuser()->create();
        $api ? $this->actingAsForApi($user) : $this->actingAs($user);
        $contract = Contract::factory()->withActiveStatus()->create(['installment_value' => '100.00']);
        $first = ContractInstallment::factory()->for($contract)->create(['expected_value' => '100.00', 'due_date' => '2026-10-01']);
        $second = ContractInstallment::factory()->for($contract)->create(['expected_value' => '100.00', 'due_date' => '2026-11-01']);
        $dispatcher = ContractInstallment::getEventDispatcher();
        ContractInstallment::setEventDispatcher(clone $dispatcher);
        ContractInstallment::saving(fn ($installment) => $installment->id === $second->id ? false : null);
        $failed = false;
        try {
            $this->post(route($api ? 'api.contracts.amendments.store' : 'contracts.amendments.store', $contract), [
                'amendment_type' => 'readjustment', 'description' => 'Synthetic failure',
                'old_value' => '100.00', 'new_value' => '125.00', 'effective_date' => '2026-09-01',
            ]);
        } catch (\RuntimeException $exception) {
            $failed = true;
        } finally {
            ContractInstallment::setEventDispatcher($dispatcher);
        }
        $this->assertTrue($failed, 'A rejected installment save must abort the amendment.');
        $this->assertSame('100.00', $contract->fresh()->installment_value);
        $this->assertSame('100.00', $first->fresh()->expected_value);
        $this->assertSame('100.00', $second->fresh()->expected_value);
        $this->assertSame(0, $contract->amendments()->count());
    }
}
