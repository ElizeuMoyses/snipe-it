<?php

namespace App\Services\Contracts;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractAuditEvent;
use App\Models\ContractInstallment;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContractAuditService
{
    public const ACTIONS = [
        'contract.created',
        'contract.updated',
        'contract.deleted',
        'contract.restored',
        'installment.created',
        'installment.updated',
        'installment.deleted',
        'installments.generated',
        'installment.paid',
        'installment.status_changed',
        'amendment.created',
        'amendment.updated',
        'amendment.deleted',
        'amendment.applied',
        'amendment.retified',
        'file.uploaded',
        'file.deleted',
        'asset.attached',
        'asset.detached',
    ];

    private const SNAPSHOT_FIELDS = [
        Contract::class => [
            'id', 'name', 'contract_number', 'contract_type', 'status_label_id',
            'supplier_id', 'company_id', 'start_date', 'end_date', 'billing_cycle',
            'billing_day', 'installment_value', 'total_value', 'total_installments',
            'readjustment_index', 'readjustment_month', 'description', 'notes', 'deleted_at',
        ],
        ContractInstallment::class => [
            'id', 'contract_id', 'installment_number', 'reference_date', 'due_date',
            'expected_value', 'paid_value', 'payment_date', 'payment_method',
            'status_label_id', 'ticket_reference', 'notes', 'deleted_at',
        ],
        ContractAmendment::class => [
            'id', 'contract_id', 'amendment_type', 'description', 'old_value', 'new_value',
            'old_end_date', 'new_end_date', 'effective_date', 'ticket_reference', 'notes', 'deleted_at',
        ],
        Asset::class => [
            'id', 'asset_tag', 'name', 'serial', 'company_id',
        ],
    ];

    private const METADATA_FIELDS = [
        'actionlog_id',
        'amendment_id',
        'amendment_type',
        'asset_id',
        'asset_name',
        'asset_tag',
        'affected_count',
        'cancelled_count',
        'count',
        'documented_only',
        'effect_type',
        'file_id',
        'filename',
        'generated_count',
        'installment_ids',
        'kept_overdue_count',
        'legacy_action_type',
        'new_end_date',
        'old_end_date',
        'operation',
        'paid_count',
        'preserved_count',
        'reason',
        'side_effects',
        'status_after',
        'status_before',
        'type',
        'updated_count',
    ];

    private const EFFECT_FIELDS = [
        'cancelled_count',
        'generated_count',
        'kept_overdue_count',
        'new_end_date',
        'new_value',
        'old_value',
        'type',
        'updated_count',
    ];

    public function record(
        Contract $contract,
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?string $correlationId = null,
        ?string $idempotencyKey = null,
    ): ContractAuditEvent {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Unsupported contract audit action: '.$action);
        }

        if (! $contract->exists || ! $contract->getKey()) {
            throw new InvalidArgumentException('Contract audit events require a persisted contract.');
        }

        $subject ??= $contract;
        $this->assertSubjectBelongsToContract($contract, $subject);

        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        if ($idempotencyKey !== null) {
            $existing = ContractAuditEvent::query()
                ->where('contract_id', $contract->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $event = new ContractAuditEvent([
            'contract_id' => $contract->getKey(),
            'company_id' => $contract->company_id,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'action' => $action,
            'actor_id' => Auth::id(),
            'actor_type' => Auth::check() ? 'user' : 'system',
            'source' => $this->source($metadata),
            'correlation_id' => $correlationId ?: (string) Str::uuid(),
            'idempotency_key' => $idempotencyKey,
            'before' => $this->sanitizeSnapshot($before, $subject::class),
            'after' => $this->sanitizeSnapshot($after, $subject::class),
            'metadata' => $this->sanitizeMetadata($metadata),
            'occurred_at' => now()->utc(),
        ]);

        try {
            $event->saveOrFail();
        } catch (QueryException $exception) {
            // A concurrent retry may win the unique idempotency key race.
            if ($idempotencyKey !== null) {
                $existing = ContractAuditEvent::query()
                    ->where('contract_id', $contract->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            throw $exception;
        }

        return $event;
    }

    public function recordForSubject(
        Model $subject,
        string $action,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?string $correlationId = null,
        ?string $idempotencyKey = null,
    ): ?ContractAuditEvent {
        $contract = $this->resolveContract($subject);

        return $contract
            ? $this->record($contract, $action, $subject, $before, $after, $metadata, $correlationId, $idempotencyKey)
            : null;
    }

    public function resolveContract(Model $subject): ?Contract
    {
        if ($subject instanceof Contract) {
            return $subject;
        }

        if ($subject instanceof ContractInstallment || $subject instanceof ContractAmendment) {
            return Contract::withTrashed()->withoutGlobalScopes()->find($subject->contract_id);
        }

        return null;
    }

    public function snapshot(Model $model): array
    {
        $fields = self::SNAPSHOT_FIELDS[$model::class] ?? ['id'];
        $snapshot = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $model->getAttributes())) {
                continue;
            }

            $value = $model->getAttribute($field);
            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            if (is_scalar($value) || $value === null) {
                $snapshot[$field] = is_string($value) ? $this->sanitizeString($value) : $value;
            }
        }

        return $snapshot;
    }

    /**
     * Return normalized new and legacy history without writing retroactive rows.
     * The result is intentionally an array of safe read models so UI and API share
     * the same filtering, ordering and pagination rules.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, total: int}
     */
    public function historyFor(Contract $contract, array $filters = [], ?int $offset = null, ?int $limit = null): array
    {
        $events = ContractAuditEvent::query()
            ->with(['actor', 'auditable'])
            ->where('contract_id', $contract->getKey())
            ->get();

        $coveredActionlogIds = $events
            ->map(fn (ContractAuditEvent $event) => data_get($event->metadata, 'actionlog_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $installmentIds = ContractInstallment::withTrashed()
            ->withoutGlobalScopes()
            ->where('contract_id', $contract->getKey())
            ->pluck('id');
        $amendmentIds = ContractAmendment::withTrashed()
            ->withoutGlobalScopes()
            ->where('contract_id', $contract->getKey())
            ->pluck('id');

        $legacyQuery = Actionlog::query()
            ->withoutGlobalScopes()
            ->with(['adminuser', 'item', 'target'])
            ->where(function ($query) use ($contract, $installmentIds, $amendmentIds) {
                $query->where(function ($query) use ($contract) {
                    $query->where('item_type', Contract::class)
                        ->where('item_id', $contract->getKey());
                })->orWhere(function ($query) use ($installmentIds) {
                    $query->where('item_type', ContractInstallment::class)
                        ->whereIn('item_id', $installmentIds);
                })->orWhere(function ($query) use ($amendmentIds) {
                    $query->where('item_type', ContractAmendment::class)
                        ->whereIn('item_id', $amendmentIds);
                })->orWhere(function ($query) use ($contract) {
                    $query->where('target_type', Contract::class)
                        ->where('target_id', $contract->getKey());
                });
            });

        if ($coveredActionlogIds) {
            $legacyQuery->whereNotIn('id', $coveredActionlogIds);
        }

        $legacyLogs = $legacyQuery->get();

        $entries = collect($events->map(fn (ContractAuditEvent $event) => $this->eventEntry($event))->all())
            ->merge($legacyLogs->map(fn (Actionlog $log) => $this->legacyEntry($log))->all())
            ->filter(fn (array $entry) => $this->matchesFilters($entry, $filters))
            ->sort(function (array $left, array $right): int {
                $time = $right['occurred_at']->getTimestamp() <=> $left['occurred_at']->getTimestamp();
                if ($time !== 0) {
                    return $time;
                }

                $sequence = $right['sequence'] <=> $left['sequence'];
                if ($sequence !== 0) {
                    return $sequence;
                }

                return $right['source_rank'] <=> $left['source_rank'];
            })
            ->values();

        $total = $entries->count();
        $offset ??= 0;
        $limit ??= $this->defaultLimit();
        $offset = max(0, $offset);
        $limit = min(100, max(1, $limit));

        return [
            'rows' => $entries->slice($offset, $limit)->values(),
            'total' => $total,
        ];
    }

    private function eventEntry(ContractAuditEvent $event): array
    {
        return [
            'id' => (string) $event->getKey(),
            'sequence' => (int) $event->getKey(),
            'source_rank' => 2,
            'record' => $event,
            'action' => $event->action,
            'entity_type' => $event->auditable_type,
            'entity_id' => $event->auditable_id,
            'actor_id' => $event->actor_id,
            'actor' => $event->actor,
            'subject' => $event->auditable,
            'occurred_at' => $event->occurred_at ?: $event->created_at,
            'source' => $event->source,
            'correlation_id' => $event->correlation_id,
            'before' => $event->before ?: [],
            'after' => $event->after ?: [],
            'metadata' => $event->metadata ?: [],
            'is_historical' => false,
        ];
    }

    private function legacyEntry(Actionlog $log): array
    {
        $entityType = match ($log->item_type) {
            Contract::class => 'contract',
            ContractInstallment::class => 'installment',
            ContractAmendment::class => 'amendment',
            default => 'legacy',
        };

        $action = match ($log->action_type) {
            'create' => $entityType.'.created',
            'update' => $entityType.'.updated',
            'delete' => $entityType.'.deleted',
            'restore' => $entityType.'.restored',
            'uploaded' => 'file.uploaded',
            'upload deleted' => 'file.deleted',
            default => 'legacy.'.(string) Str::of((string) $log->action_type)->slug('_'),
        };

        $occurredAt = $log->action_date ?: $log->created_at;

        return [
            'id' => 'legacy:'.$log->getKey(),
            'sequence' => (int) $log->getKey(),
            'source_rank' => 1,
            'record' => $log,
            'action' => $action,
            'entity_type' => $log->item_type,
            'entity_id' => $log->item_id,
            'actor_id' => $log->created_by,
            'actor' => $log->adminuser,
            'subject' => $log->item,
            'occurred_at' => $occurredAt instanceof DateTimeInterface ? Carbon::instance($occurredAt) : Carbon::parse($occurredAt),
            'source' => 'legacy',
            'correlation_id' => null,
            'before' => [],
            'after' => [],
            'metadata' => array_filter([
                'legacy_action_type' => $log->action_type,
                'filename' => $log->filename,
            ], fn ($value) => $value !== null && $value !== ''),
            'is_historical' => true,
        ];
    }

    private function matchesFilters(array $entry, array $filters): bool
    {
        $action = trim((string) ($filters['action'] ?? $filters['action_type'] ?? ''));
        if ($action !== '' && strcasecmp($entry['action'], $action) !== 0) {
            return false;
        }

        $entity = trim((string) ($filters['entity'] ?? ''));
        if ($entity !== '') {
            $entityKey = strtolower(str_replace(['_', '-', ' '], '', $entity));
            $entityCandidates = [
                strtolower(class_basename($entry['entity_type'])),
                match ($entry['entity_type']) {
                    Contract::class => 'contract',
                    ContractInstallment::class => 'installment',
                    ContractAmendment::class => 'amendment',
                    Asset::class => 'asset',
                    default => strtolower(class_basename($entry['entity_type'])),
                },
            ];
            if (! in_array($entityKey, $entityCandidates, true)) {
                return false;
            }
        }

        if (isset($filters['created_by']) && $filters['created_by'] !== ''
            && (int) $entry['actor_id'] !== (int) $filters['created_by']) {
            return false;
        }

        if (! empty($filters['source']) && strcasecmp($entry['source'], $filters['source']) !== 0) {
            return false;
        }

        if (! empty($filters['from'])) {
            $from = Carbon::parse($filters['from'])->startOfDay();
            if ($entry['occurred_at']->lt($from)) {
                return false;
            }
        }

        if (! empty($filters['to'])) {
            $to = Carbon::parse($filters['to'])->endOfDay();
            if ($entry['occurred_at']->gt($to)) {
                return false;
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                $entry['action'],
                class_basename($entry['entity_type']),
                (string) ($entry['entity_id'] ?? ''),
                (string) ($entry['actor']?->display_name ?? ''),
                json_encode($entry['before'], JSON_UNESCAPED_UNICODE),
                json_encode($entry['after'], JSON_UNESCAPED_UNICODE),
                json_encode($entry['metadata'], JSON_UNESCAPED_UNICODE),
            ]));
            if (! str_contains($haystack, strtolower($search))) {
                return false;
            }
        }

        return true;
    }

    private function assertSubjectBelongsToContract(Contract $contract, Model $subject): void
    {
        $supported = array_keys(self::SNAPSHOT_FIELDS);
        if (! in_array($subject::class, $supported, true)) {
            throw new InvalidArgumentException('Unsupported contract audit subject: '.$subject::class);
        }

        if ($subject instanceof Contract && (int) $subject->getKey() !== (int) $contract->getKey()) {
            throw new InvalidArgumentException('Contract audit subject does not match the parent contract.');
        }

        if (($subject instanceof ContractInstallment || $subject instanceof ContractAmendment)
            && (int) $subject->contract_id !== (int) $contract->getKey()) {
            throw new InvalidArgumentException('Contract audit child does not match the parent contract.');
        }
    }

    private function sanitizeSnapshot(array $snapshot, string $subjectType): array
    {
        $allowed = self::SNAPSHOT_FIELDS[$subjectType] ?? ['id'];
        $sanitized = [];

        foreach ($snapshot as $key => $value) {
            if (! in_array((string) $key, $allowed, true)) {
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[(string) $key] = is_string($value) ? $this->sanitizeString($value) : $value;
            }
        }

        return $sanitized;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];
        foreach ($metadata as $key => $value) {
            if (! in_array((string) $key, self::METADATA_FIELDS, true)) {
                continue;
            }

            if (in_array($key, ['effect_type', 'side_effects'], true) && is_array($value)) {
                $sanitized[$key] = $this->sanitizeEffectSummary($value);
                continue;
            }

            if (in_array($key, ['installment_ids'], true) && is_array($value)) {
                $sanitized[$key] = array_values(array_map('intval', array_slice($value, 0, 1000)));
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            }
        }

        return $sanitized;
    }

    private function sanitizeEffectSummary(array $summary): array
    {
        $sanitized = [];
        foreach ($summary as $key => $value) {
            if (! in_array((string) $key, self::EFFECT_FIELDS, true)) {
                continue;
            }
            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            if (is_scalar($value) || $value === null) {
                $sanitized[(string) $key] = is_string($value) ? $this->sanitizeString($value) : $value;
            }
        }

        return $sanitized;
    }

    private function normalizeIdempotencyKey(?string $key): ?string
    {
        if ($key === null || trim($key) === '') {
            return null;
        }

        return Str::limit(trim($key), 191, '');
    }

    private function sanitizeString(string $value, int $limit = 255): string
    {
        $value = preg_replace('/https?:\\/\\/[^\\s]+/iu', '[redacted-url]', $value) ?? $value;
        $value = preg_replace(
            '/\\b(password|passwd|secret|token|api[_-]?key|authorization|bearer|credential)\\s*[:=]\\s*[^\\s,;]+/iu',
            '$1=[redacted]',
            $value,
        ) ?? $value;

        return Str::limit($value, $limit, '');
    }

    private function source(array $metadata = []): string
    {
        if (isset($metadata['source'])
            && in_array($metadata['source'], ['ui', 'api', 'scheduler', 'system'], true)) {
            return $metadata['source'];
        }

        if (app()->runningInConsole()) {
            return 'system';
        }

        return request()->is('api/*') || request()->expectsJson() ? 'api' : 'ui';
    }

    private function defaultLimit(): int
    {
        return app()->bound('api_limit_value') ? (int) app('api_limit_value') : 50;
    }
}
