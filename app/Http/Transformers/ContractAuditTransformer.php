<?php

namespace App\Http\Transformers;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractInstallment;
use App\Models\ContractAuditEvent;
use App\Models\User;
use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ContractAuditTransformer
{
    public function transform(Collection $entries, int $total): array
    {
        $rows = $entries->map(fn (array $entry) => $this->transformEntry($entry))->values()->all();

        return (new DatatablesTransformer)->transformDatatables($rows, $total);
    }

    public function transformEntry(array $entry): array
    {
        $subject = $entry['subject'];
        $actor = $entry['actor'];
        $entityType = strtolower(class_basename($entry['entity_type']));

        return [
            'id' => $entry['id'],
            'icon' => $this->icon($entry['action']),
            'action' => $entry['action'],
            'action_label' => trans('admin/contracts/general.history_actions.'.str_replace('.', '_', $entry['action'])),
            'action_type' => $entry['action'],
            'entity' => [
                'type' => $entityType,
                'id' => $entry['entity_id'] !== null ? (int) $entry['entity_id'] : null,
                'label' => $this->subjectLabel($subject, $entityType, $entry['entity_id']),
            ],
            'entity_type' => $entityType,
            'entity_id' => $entry['entity_id'] !== null ? (int) $entry['entity_id'] : null,
            'entity_label' => $this->subjectLabel($subject, $entityType, $entry['entity_id']),
            'actor' => $this->actor($actor, $entry['actor_id']),
            'actor_name' => $actor?->display_name ?: trans('admin/contracts/general.system'),
            'created_by' => $actor?->display_name ?: trans('admin/contracts/general.system'),
            'occurred_at' => $this->date($entry['occurred_at']),
            'created_at' => $this->date($entry['occurred_at']),
            'source' => $entry['source'],
            'correlation_id' => $entry['correlation_id'],
            'before' => $entry['before'],
            'after' => $entry['after'],
            'metadata' => $entry['metadata'],
            'details' => $this->details($entry),
            'is_historical' => $entry['is_historical'],
            'historical_label' => $entry['is_historical'] ? trans('admin/contracts/general.history_legacy') : null,
        ];
    }

    private function actor(?User $actor, ?int $actorId): ?array
    {
        if (! $actor && ! $actorId) {
            return null;
        }

        return [
            'id' => $actorId,
            'name' => $actor?->display_name ?: trans('general.deleted'),
        ];
    }

    private function subjectLabel($subject, string $entityType, $entityId): string
    {
        if ($subject instanceof Asset) {
            return trim(implode(' - ', array_filter([$subject->asset_tag, $subject->name, $subject->serial]))) ?: '#'.$entityId;
        }

        if ($subject instanceof Contract) {
            return $subject->name ?: '#'.$entityId;
        }

        if ($subject instanceof ContractInstallment) {
            return $subject->installment_number ? '#'.$subject->installment_number : '#'.$entityId;
        }

        if ($subject instanceof ContractAmendment) {
            return trim(implode(' - ', array_filter([$subject->amendment_type, $subject->description]))) ?: '#'.$entityId;
        }

        return ucfirst($entityType).' #'.$entityId;
    }

    private function date($date): array
    {
        return Helper::getFormattedDateObject($date instanceof Carbon ? $date : Carbon::parse($date), 'datetime');
    }

    private function details(array $entry): string
    {
        $parts = [];
        foreach ([$entry['before'], $entry['after']] as $index => $values) {
            foreach ($values as $field => $value) {
                $parts[$field][$index === 0 ? 'old' : 'new'] = is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        $changes = [];
        foreach ($parts as $field => $change) {
            if (array_key_exists('old', $change) && array_key_exists('new', $change) && $change['old'] === $change['new']) { continue; }
            $changes[] = $field.': '.($change['old'] ?? '').' → '.($change['new'] ?? '');
        }

        foreach ($entry['metadata'] as $field => $value) {
            if (is_scalar($value)) {
                if (array_key_exists('old', $change) && array_key_exists('new', $change) && $change['old'] === $change['new']) { continue; }
            $changes[] = $field.': '.$value;
            }
        }

        return implode('; ', $changes);
    }

    private function icon(string $action): string
    {
        return match (true) {
            str_contains($action, 'deleted') => 'fas fa-trash',
            str_contains($action, 'restored') => 'fas fa-trash-arrow-up',
            str_contains($action, 'file.') => 'fas fa-paperclip',
            str_contains($action, 'attached'), str_contains($action, 'detached') => 'fas fa-link',
            str_contains($action, 'paid') => 'fas fa-money-bill-wave',
            str_contains($action, 'generated') => 'fas fa-layer-group',
            str_contains($action, 'status') => 'fas fa-arrows-rotate',
            str_contains($action, 'amendment') => 'fas fa-file-signature',
            str_contains($action, 'created') => 'fas fa-plus',
            default => 'fas fa-pen',
        };
    }
}
