<?php

namespace App\Console\Commands;

use App\Models\ContractInstallment;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Services\Contracts\ContractAuditService;
use Illuminate\Console\Command;

class ContractOverdueCheck extends Command
{
    protected $signature = 'contracts:check-overdue';
    protected $description = 'Mark pending installments past due date as overdue';

    public function handle(): int
    {
        $defaultOverdue = ContractStatusLabel::defaultForMetaType('installment', 'overdue');

        if (! $defaultOverdue) {
            $this->error('No default status label found for installment/overdue. Run migrations with seeds first.');
            return self::FAILURE;
        }

        $installments = ContractInstallment::pending()
            ->where('due_date', '<', now()->startOfDay())
            ->with(['contract', 'statusLabel'])
            ->get();

        $total = $installments->count();
        $updated = 0;
        $errors = 0;

        foreach ($installments as $installment) {
            try {
                $installment->getConnection()->transaction(function () use ($installment, $defaultOverdue, &$updated) {
                    $contract = Contract::whereKey($installment->contract_id)->lockForUpdate()->first();
                    if (! $contract) {
                        return;
                    }
                    $installment = $contract->installments()->lockForUpdate()->find($installment->id);
                    if (! $installment || $installment->statusLabel?->meta_type !== 'pending'
                        || ! $installment->due_date->lt(now()->startOfDay())) {
                        return;
                    }
                    $oldStatusName = $installment->statusLabel->name;
                    $before = app(ContractAuditService::class)->snapshot($installment);

                    $installment->status_label_id = $defaultOverdue->id;
                    if (! $installment->save()) {
                        throw new \RuntimeException('Overdue status update failed');
                    }

                    app(ContractAuditService::class)->record(
                        $contract,
                        'installment.status_changed',
                        $installment,
                        $before,
                        app(ContractAuditService::class)->snapshot($installment),
                        [
                            'operation' => 'contracts.check-overdue',
                            'source' => 'scheduler',
                            'status_before' => $oldStatusName,
                            'status_after' => $defaultOverdue->name,
                        ],
                        null,
                        'overdue:installment:'.$installment->id.':'.$defaultOverdue->id,
                    );

                    $updated++;
                });
            } catch (\Exception $e) {
                $this->error("Failed to update installment #{$installment->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Contract overdue check complete: {$total} found, {$updated} updated, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
