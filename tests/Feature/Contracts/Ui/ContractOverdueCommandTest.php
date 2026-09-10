<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractOverdueCommandTest extends TestCase
{
    public function test_only_past_due_pending_installments_are_changed(): void
    {
        $past = ContractInstallment::factory()->create(['due_date' => now()->subDay()->toDateString()]);
        $today = ContractInstallment::factory()->create(['due_date' => now()->toDateString()]);
        $paid = ContractInstallment::factory()->paid()->create(['due_date' => now()->subDay()->toDateString()]);
        $this->artisan('contracts:check-overdue')->assertSuccessful();
        $this->artisan('contracts:check-overdue')->assertSuccessful();
        $this->assertSame('overdue', $past->fresh()->statusLabel->meta_type);
        $this->assertSame('pending', $today->fresh()->statusLabel->meta_type);
        $this->assertSame('paid', $paid->fresh()->statusLabel->meta_type);
    }

    public function test_overdue_job_does_not_overwrite_payment_after_initial_selection(): void
    {
        $installment = ContractInstallment::factory()->create(['due_date' => '2020-01-01']);
        $paid = ContractStatusLabel::defaultForMetaType('installment', 'paid');
        $dispatcher = ContractInstallment::getEventDispatcher();
        ContractInstallment::setEventDispatcher(clone $dispatcher);
        $injected = false;
        ContractInstallment::retrieved(function ($item) use ($installment, $paid, &$injected) {
            if (! $injected && $item->id === $installment->id) {
                $injected = true;
                DB::table('contract_installments')->where('id', $item->id)->update([
                    'status_label_id' => $paid->id, 'paid_value' => '10.00', 'payment_date' => '2026-09-10',
                ]);
            }
        });
        try {
            $this->artisan('contracts:check-overdue')->assertSuccessful();
        } finally {
            ContractInstallment::setEventDispatcher($dispatcher);
        }
        $this->assertTrue($injected);
        $this->assertSame($paid->id, $installment->fresh()->status_label_id);
        $this->assertSame('10.00', $installment->fresh()->paid_value);
    }
}
