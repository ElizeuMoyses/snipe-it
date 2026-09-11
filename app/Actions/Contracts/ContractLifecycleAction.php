<?php

namespace App\Actions\Contracts;

use App\Enums\ActionType;
use App\Models\Actionlog;
use App\Models\Contract;
use RuntimeException;

/**
 * Applies the contract lifecycle rules shared by the web and API endpoints.
 *
 * Archiving is intentionally a parent-only soft delete. Related installments,
 * amendments, asset links, uploads and existing history remain available for
 * consultation. The audit record is written in the same transaction as the
 * lifecycle change.
 */
class ContractLifecycleAction
{
    /**
     * Archive a contract without changing its financial state.
     *
     * @return array{status: string, contract?: Contract, paid_count?: int, pending_count?: int, amendment_count?: int}
     */
    public function archive(Contract $contract, string $reason): array
    {
        return $contract->getConnection()->transaction(function () use ($contract, $reason): array {
            $lockedContract = Contract::withTrashed()
                ->whereKey($contract->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedContract->trashed()) {
                return [
                    'status' => 'already_archived',
                    'contract' => $lockedContract,
                ];
            }

            $paidCount = $lockedContract->installments()
                ->withTrashed()
                ->paid()
                ->count();

            $pendingCount = $lockedContract->installments()
                ->withTrashed()
                ->pending()
                ->count();

            $amendmentCount = $lockedContract->amendments()
                ->withTrashed()
                ->count();

            if ($paidCount > 0) {
                return [
                    'status' => 'blocked_paid',
                    'contract' => $lockedContract,
                    'paid_count' => $paidCount,
                    'pending_count' => $pendingCount,
                    'amendment_count' => $amendmentCount,
                ];
            }

            $before = app(\App\Services\Contracts\ContractAuditService::class)->snapshot($lockedContract);
            if (! $lockedContract->delete()) {
                throw new RuntimeException('The contract could not be archived.');
            }

            $this->writeAuditLog(
                contract: $lockedContract,
                action: ActionType::Delete,
                reason: $reason,
                metadata: [
                    'previous_snapshot' => $before,
                    'operation' => 'archive',
                    'paid_installments' => $paidCount,
                    'pending_installments' => $pendingCount,
                    'amendments' => $amendmentCount,
                    'archived_at' => $lockedContract->deleted_at?->toIso8601String(),
                ],
            );

            return [
                'status' => 'archived',
                'contract' => $lockedContract,
                'paid_count' => $paidCount,
                'pending_count' => $pendingCount,
                'amendment_count' => $amendmentCount,
            ];
        });
    }

    /**
     * Restore an archived contract after validating active-number conflicts.
     *
     * @return array{status: string, contract?: Contract}
     */
    public function restore(Contract $contract, ?string $reason = null): array
    {
        return $contract->getConnection()->transaction(function () use ($contract, $reason): array {
            $lockedContract = Contract::withTrashed()
                ->whereKey($contract->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedContract->trashed()) {
                return [
                    'status' => 'not_archived',
                    'contract' => $lockedContract,
                ];
            }

            if ($lockedContract->contract_number
                && Contract::query()
                    ->where('id', '<>', $lockedContract->getKey())
                    ->where('contract_number', $lockedContract->contract_number)
                    ->exists()) {
                return [
                    'status' => 'restore_conflict',
                    'contract' => $lockedContract,
                ];
            }

            $before = app(\App\Services\Contracts\ContractAuditService::class)->snapshot($lockedContract);
            if (! $lockedContract->restore()) {
                throw new RuntimeException('The contract could not be restored.');
            }

            $this->writeAuditLog(
                contract: $lockedContract,
                action: ActionType::Restore,
                reason: $reason ?: trans('admin/contracts/message.archive.restore_reason'),
                metadata: [
                    'previous_snapshot' => $before,
                    'operation' => 'restore',
                    'restored_at' => now()->toIso8601String(),
                    'installments_preserved' => true,
                    'amendments_preserved' => true,
                    'uploads_regenerated' => false,
                ],
            );

            return [
                'status' => 'restored',
                'contract' => $lockedContract,
            ];
        });
    }

    /**
     * Write the existing action log record used by the application.
     *
     * The unified history references this record so legacy and new views
     * represent the same operation without duplicates.
     */
    private function writeAuditLog(Contract $contract, ActionType $action, string $reason, array $metadata): void
    {
        $audit = new Actionlog;
        $audit->item_type = Contract::class;
        $audit->item_id = $contract->getKey();
        $audit->created_by = auth()->id();
        $audit->note = $reason;
        $audit->log_meta = json_encode(['archived' => ['old' => $action === ActionType::Restore, 'new' => $action === ActionType::Delete]], JSON_THROW_ON_ERROR);

        if (! $audit->logaction($action)) {
            throw new RuntimeException('The contract audit event could not be recorded.');
        }
        $service = app(\App\Services\Contracts\ContractAuditService::class);
        $service->record($contract,
            $action === ActionType::Delete ? 'contract.deleted' : 'contract.restored',
            $contract, $metadata['previous_snapshot'] ?? [], $service->snapshot($contract),
            ['reason' => $reason, 'operation' => $metadata['operation'], 'actionlog_id' => $audit->id]);
    }
}
