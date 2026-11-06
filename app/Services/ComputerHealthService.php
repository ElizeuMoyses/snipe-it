<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\CustomField;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/** Read-only inventory evidence, scoped by Asset's existing company scope. */
class ComputerHealthService
{
    private const FIELD_NAMES = [
        'IP' => ['key' => 'ip', 'label' => 'IP reportado'],
        'Ult. User Log' => ['key' => 'reported_user', 'label' => 'Usuário reportado'],
        'Coordenadas' => ['key' => 'coordinates', 'label' => 'Coordenadas reportadas'],
    ];

    public function snapshot(): array
    {
        $fields = $this->fields();
        $assets = $this->computers()->get();
        $inventory = $this->inventory($assets->modelKeys(), $fields);
        $assignedUsers = $this->assignedUsers($assets);
        $rows = $assets->map(fn (Asset $asset) => $this->row($asset, $fields, $inventory[$asset->id] ?? $this->emptyInventory(), $assignedUsers))->values()->all();

        return [
            'generated_at' => CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
            'assets' => $rows,
            'source' => [
                'asset_count' => count($rows),
                'inventory_event_count' => array_sum(array_column($rows, 'inventory_event_count')),
                'invalid_history_count' => array_sum(array_column($rows, 'invalid_history_count')),
                'timezone' => config('app.timezone'),
                'fields' => $fields,
                'missing_fields' => array_values(array_diff(array_keys(self::FIELD_NAMES), array_column($fields, 'name'))),
                'replica_captured_at' => config('computer-health.replica') ? config('computer-health.replica_captured_at') : null,
            ],
        ];
    }

    public function history(int $id): array
    {
        $asset = $this->computers()->find($id);
        // Snipe-IT maps ModelNotFoundException to HTTP 200 for JSON requests.
        abort_unless($asset, 404);
        $fields = $this->fields();
        $inventory = $this->inventory([$id], $fields, true)[$id] ?? $this->emptyInventory();

        return ['generated_at' => CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
            'asset' => $this->row($asset, $fields, $inventory, $this->assignedUsers([$asset])), 'events' => $inventory['events']];
    }

    private function computers(): Builder
    {
        return Asset::query()
            ->whereHas('model.category', fn (Builder $query) => $query->whereIn('name', ['Notebook', 'Desktop'])->where('category_type', 'asset'))
            ->with(['model.category', 'location', 'defaultLoc', 'assetstatus'])
            ->orderBy('assets.id');
    }

    private function fields(): array
    {
        $columns = array_flip(Schema::getColumnListing('assets'));
        $fields = [];
        foreach (CustomField::query()->whereIn('name', array_keys(self::FIELD_NAMES))->orderBy('id')->get() as $field) {
            // Encrypted fields are intentionally excluded, regardless of viewer role.
            if ($field->field_encrypted || !isset($columns[$field->db_column])) {
                continue;
            }
            $definition = self::FIELD_NAMES[$field->name];
            $fields[$field->db_column] = $definition + ['name' => $field->name];
        }

        return $fields;
    }

    private function emptyInventory(): array
    {
        return ['last' => null, 'last_id' => null, 'count' => 0, 'invalid' => 0, 'users' => [], 'events' => []];
    }

    private function inventory(array $ids, array $fields, bool $includeEvents = false): array
    {
        if (!$ids || !$fields) {
            return [];
        }
        $result = [];
        // IDs are already company scoped. Never load another company's history.
        $logs = DB::table('action_logs')->where('item_type', Asset::class)->whereIn('item_id', $ids)
            ->whereNull('deleted_at')->whereNotNull('log_meta')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->cursor(['id', 'item_id', 'created_at', 'action_type', 'action_source', 'log_meta']);

        foreach ($logs as $log) {
            $result[$log->item_id] ??= $this->emptyInventory();
            $item = &$result[$log->item_id];
            try {
                $metadata = json_decode($log->log_meta, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($metadata)) {
                    throw new \UnexpectedValueException('Inventory metadata must be an object');
                }
            } catch (\Throwable $error) {
                $item['invalid']++;
                unset($item);
                continue;
            }
            $changes = [];
            foreach ($fields as $column => $definition) {
                $change = $metadata[$column] ?? null;
                if (!is_array($change) || (!array_key_exists('old', $change) && !array_key_exists('new', $change))) {
                    continue;
                }
                $entry = ['field' => $definition['key'], 'label' => $definition['label']];
                foreach (['old', 'new'] as $side) {
                    if (array_key_exists($side, $change)) {
                        $entry[$side] = $this->text($change[$side]);
                        if ($definition['key'] === 'reported_user' && $entry[$side] !== null) {
                            $item['users'][$entry[$side]] = true;
                        }
                    }
                }
                $changes[] = $entry;
            }
            if (!$changes) {
                unset($item);
                continue;
            }
            $timestamp = $this->timestamp($log->created_at);
            if ($timestamp === null) {
                $item['invalid']++;
                unset($item);
                continue;
            }
            if ($item['last'] === null) {
                $item['last'] = $timestamp;
                $item['last_id'] = (int) $log->id;
            }
            $item['count']++;
            if ($includeEvents) {
                $item['events'][] = ['id' => (int) $log->id, 'created_at' => $timestamp,
                    'action_type' => $log->action_type, 'action_source' => $log->action_source, 'changes' => $changes];
            }
            unset($item);
        }

        return $result;
    }

    private function assignedUsers(iterable $assets): array
    {
        $ids = [];
        foreach ($assets as $asset) {
            if ($asset->assigned_type === User::class && $asset->assigned_to) {
                $ids[] = $asset->assigned_to;
            }
        }
        if (!$ids) {
            return [];
        }
        // Only users referenced by already authorized assets. Preserve links to
        // inactive/deleted users, as the native assignedTo relation intends.
        $users = [];
        foreach (DB::table('users')->whereIn('id', array_unique($ids))->get(['id','first_name','last_name','username','company_id']) as $user) {
            $model = (new User)->newFromBuilder((array) $user);
            $users[$user->id] = ['id' => (int) $user->id, 'name' => trim($user->first_name.' '.$user->last_name),
                'username' => $user->username, 'url' => $this->viewUrl($model, 'users.show')];
        }

        return $users;
    }

    private function row(Asset $asset, array $fields, array $inventory, array $assignedUsers): array
    {
        $current = ['ip' => null, 'reported_user' => null, 'coordinates' => null];
        foreach ($fields as $column => $definition) {
            $current[$definition['key']] = $this->text($asset->getRawOriginal($column));
        }
        $assigned = $asset->assigned_type === User::class ? ($assignedUsers[$asset->assigned_to] ?? null) : null;

        return [
            'id' => (int) $asset->id, 'tag' => (string) $asset->asset_tag,
            'name' => $this->text($asset->name), 'serial' => $this->text($asset->serial),
            'category' => $asset->model?->category?->name, 'model' => $asset->model?->name,
            'category_id' => $asset->model?->category_id ? (int) $asset->model->category_id : null,
            'model_id' => $asset->model_id ? (int) $asset->model_id : null,
            'status' => $asset->assetstatus?->name, 'archived' => (bool) $asset->archived,
            'location' => $asset->location?->name, 'default_location' => $asset->defaultLoc?->name,
            'location_id' => $asset->location_id ? (int) $asset->location_id : null,
            'default_location_id' => $asset->rtd_location_id ? (int) $asset->rtd_location_id : null,
            'assigned_user' => $assigned,
            'links' => [
                'model' => $this->viewUrl($asset->model, 'models.show'),
                'category' => $this->viewUrl($asset->model?->category, 'categories.show'),
                'location' => $this->viewUrl($asset->location, 'locations.show'),
                'default_location' => $this->viewUrl($asset->defaultLoc, 'locations.show'),
                'user' => $assigned['url'] ?? null,
            ],
            'current' => $current, 'historical_users' => array_keys($inventory['users']),
            'last_inventory_at' => $inventory['last'], 'last_inventory_event_id' => $inventory['last_id'],
            'inventory_event_count' => $inventory['count'], 'invalid_history_count' => $inventory['invalid'],
            'updated_at' => $this->timestamp($asset->getRawOriginal('updated_at')),
            'created_at' => $this->timestamp($asset->getRawOriginal('created_at')),
            'asset_url' => route('hardware.show', ['asset' => $asset->id]),
        ];
    }

    private function viewUrl(?Model $model, string $route): ?string
    {
        return $model && Gate::allows('view', $model) ? route($route, $model->getKey()) : null;
    }

    private function text($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        return $value === '' ? null : $value;
    }

    private function timestamp($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            $parsed = CarbonImmutable::parse($value, config('app.timezone'));
            if ($parsed->year < 1 || $parsed->format('Y-m-d H:i:s') !== substr((string) $value, 0, 19)) {
                return null;
            }

            return $parsed->toIso8601String();
        } catch (\Throwable $error) {
            return null;
        }
    }
}
