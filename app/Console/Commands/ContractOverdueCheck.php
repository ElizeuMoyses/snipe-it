<?php

namespace App\Console\Commands;

use App\Enums\ActionType;
use App\Models\Actionlog;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
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
                $oldStatusName = $installment->statusLabel->name;

                $installment->status_label_id = $defaultOverdue->id;
                $installment->save();

                $log = new Actionlog();
                $log->item_type = ContractInstallment::class;
                $log->item_id = $installment->id;
                $log->target_type = $installment->contract ? get_class($installment->contract) : null;
                $log->target_id = $installment->contract_id;
                $log->created_by = null;
                $log->company_id = $installment->contract->company_id ?? null;
                $log->log_meta = json_encode([
                    'status_label' => ['old' => $oldStatusName, 'new' => $defaultOverdue->name],
                    'meta_type' => ['old' => 'pending', 'new' => 'overdue'],
                ]);
                $log->logaction(ActionType::Update);

                $updated++;
            } catch (\Exception $e) {
                $this->error("Failed to update installment #{$installment->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Contract overdue check complete: {$total} found, {$updated} updated, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
