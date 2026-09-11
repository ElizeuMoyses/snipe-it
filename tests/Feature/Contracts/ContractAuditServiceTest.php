<?php

namespace Tests\Feature\Contracts;

use App\Models\Actionlog;
use App\Models\Contract;
use App\Models\ContractAuditEvent;
use App\Models\ContractInstallment;
use App\Models\User;
use App\Services\Contracts\ContractAuditService;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ContractAuditServiceTest extends TestCase
{
    public function test_records_safe_idempotent_event_with_actor_and_parent(): void
    {
        $actor = User::factory()->superuser()->create();
        $this->actingAs($actor);
        $contract = Contract::factory()->create();
        $service = app(ContractAuditService::class);

        $event = $service->record(
            $contract,
            'contract.updated',
            $contract,
            [
                'name' => 'Old contract',
                'secret' => 'must not be stored',
                'notes' => 'password=old-value https://private.example/old',
            ],
            [
                'name' => 'New contract',
                'notes' => 'token=new-value https://private.example/new',
            ],
            [
                'reason' => 'authorization=Bearer-secret https://private.example/reason',
                'unknown_payload' => 'must not be stored',
            ],
            '00000000-0000-4000-8000-000000000013',
            'contract-updated-test-key',
        );

        $retry = $service->record(
            $contract,
            'contract.updated',
            $contract,
            [],
            ['name' => 'retry should not replace the first event'],
            [],
            '00000000-0000-4000-8000-000000000013',
            'contract-updated-test-key',
        );

        $this->assertSame($event->getKey(), $retry->getKey());
        $this->assertDatabaseCount('contract_audit_events', 1);

        $stored = $event->fresh();
        $this->assertSame($contract->id, $stored->contract_id);
        $this->assertSame($actor->id, $stored->actor_id);
        $this->assertSame('system', $stored->source);
        $this->assertSame('Old contract', data_get($stored->before, 'name'));
        $this->assertArrayNotHasKey('secret', $stored->before);
        $this->assertArrayNotHasKey('unknown_payload', $stored->metadata);
        $this->assertStringNotContainsString('https://', json_encode($stored->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('[redacted]', json_encode($stored->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_events_are_append_only(): void
    {
        $contract = Contract::factory()->create();
        $event = app(ContractAuditService::class)->record($contract, 'contract.created', $contract);

        $this->expectException(LogicException::class);
        $event->update(['action' => 'contract.updated']);
    }

    public function test_event_rolls_back_with_the_business_transaction(): void
    {
        $contract = Contract::factory()->create();
        $service = app(ContractAuditService::class);

        try {
            DB::transaction(function () use ($contract, $service): void {
                $service->record($contract, 'contract.updated', $contract);
                throw new RuntimeException('rollback test');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback test', $exception->getMessage());
        }

        $this->assertDatabaseCount('contract_audit_events', 0);
    }

    public function test_installment_generation_writes_one_batch_event(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->create([
            'contract_type' => 'recurring',
            'billing_cycle' => 'monthly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ]);

        $this->assertSame(3, $contract->generateInstallments());
        $this->assertSame(0, $contract->fresh()->generateInstallments());

        $event = ContractAuditEvent::query()
            ->where('contract_id', $contract->id)
            ->where('action', 'installments.generated')
            ->firstOrFail();

        $this->assertSame(3, data_get($event->metadata, 'count'));
        $this->assertCount(3, data_get($event->metadata, 'installment_ids'));
        $this->assertSame(1, ContractAuditEvent::query()->where('contract_id', $contract->id)->count());
    }

    public function test_history_keeps_soft_deleted_children_and_marks_legacy_rows(): void
    {
        $contract = Contract::factory()->create();
        $installment = ContractInstallment::factory()->create(['contract_id' => $contract->id]);
        $installment->delete();

        $legacyId = DB::table('action_logs')->insertGetId([
            'created_by' => null,
            'company_id' => $contract->company_id,
            'item_type' => ContractInstallment::class,
            'item_id' => $installment->id,
            'action_type' => 'update',
            'action_date' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $page = app(ContractAuditService::class)->historyFor($contract);
        $legacy = $page['rows']->first(fn (array $entry) => $entry['id'] === 'legacy:'.$legacyId);

        $this->assertNotNull($legacy);
        $this->assertTrue($legacy['is_historical']);
        $this->assertSame('installment.updated', $legacy['action']);
        $this->assertSame($installment->id, $legacy['entity_id']);
        $this->assertSame(1, app(ContractAuditService::class)->historyFor($contract, ['action' => 'installment.updated'])['total']);
    }

    public function test_api_history_returns_filtered_normalized_events(): void
    {
        $contract = Contract::factory()->create();
        $service = app(ContractAuditService::class);
        $service->record($contract, 'contract.created', $contract);
        $service->record($contract, 'contract.updated', $contract);

        $response = $this->actingAsForApi(User::factory()->viewContracts()->create())
            ->getJson(route('api.contracts.history', ['contract' => $contract->id, 'action' => 'contract.updated']));

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.action', 'contract.updated')
            ->assertJsonPath('rows.0.entity.type', 'contract');
    }
}
