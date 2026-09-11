<?php

namespace Tests\Feature\Contracts;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAuditEvent;
use App\Models\User;
use App\Services\ContractAssetLinkService;
use App\Services\Contracts\ContractAuditService;
use RuntimeException;
use Tests\TestCase;

class ContractAuditIntegrationTest extends TestCase
{
    public function test_duplicate_asset_requests_do_not_duplicate_history(): void
    {
        $contract = Contract::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $contract->company_id]);
        $this->actingAs(User::factory()->superuser()->create());
        $service = app(ContractAssetLinkService::class);

        $service->attach($contract, $asset->id);
        $service->attach($contract, $asset->id);
        $service->detach($contract, $asset->id);
        $service->detach($contract, $asset->id);

        $this->assertSame(['asset.attached', 'asset.detached'],
            ContractAuditEvent::orderBy('id')->pluck('action')->all());
        $this->assertSame(0, $contract->assets()->count());
        $this->assertSame(2, \App\Models\Actionlog::where('item_type', Asset::class)->where('item_id', $asset->id)->where('target_type', Contract::class)->where('target_id', $contract->id)->count());
        $this->assertSame(2, app(ContractAuditService::class)->historyFor($contract)['total']);
    }

    public function test_history_accepts_the_application_default_page_size(): void
    {
        $contract = Contract::factory()->create();
        app(ContractAuditService::class)->record($contract, 'installments.generated', $contract, [], [], ['generated_count' => 3]);
        $this->actingAs(User::factory()->superuser()->create())
            ->getJson(route('contracts.history', ['contract' => $contract->id, 'limit' => 200]))
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_utc_event_uses_application_timezone_for_display_and_date_filter(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $contract = Contract::factory()->create();
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-11 01:30:00', 'UTC'));
        try {
            $service = app(ContractAuditService::class);
            $service->record($contract, 'contract.created');
            $page = $service->historyFor($contract, ['from' => '2026-09-10', 'to' => '2026-09-10']);
            $this->assertSame(1, $page['total']);
            $this->assertSame('2026-09-10 22:30', $page['rows']->first()['occurred_at']->format('Y-m-d H:i'));
            $this->assertSame(0, $service->historyFor($contract, ['from' => '2026-09-11'])['total']);
        } finally { \Carbon\Carbon::setTestNow(); }
    }

    public function test_history_text_fields_escape_markup_before_table_rendering(): void
    {
        $contract = Contract::factory()->create(['name' => '<img src=x onerror=alert(1)>']);
        $service = app(ContractAuditService::class);
        $service->record($contract, 'contract.updated', $contract, [], $service->snapshot($contract));
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->getJson(route('contracts.history', $contract))->assertOk();
        foreach (['entity_label', 'details'] as $field) {
            $this->assertStringNotContainsString('<img', $response->json('rows.0.'.$field));
            $this->assertStringContainsString('&lt;img', $response->json('rows.0.'.$field));
        }
    }

    public function test_audit_failure_rolls_back_asset_link(): void
    {
        $contract = Contract::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $contract->company_id]);
        $this->mock(ContractAuditService::class)->shouldReceive('snapshot')->andReturn([]);
        app(ContractAuditService::class)->shouldReceive('record')->once()
            ->andThrow(new RuntimeException('Synthetic audit failure'));

        try {
            app(ContractAssetLinkService::class)->attach($contract, $asset->id);
            $this->fail('Expected the audit failure to abort the link.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }

        $this->assertSame(0, $contract->assets()->count());
        $this->assertDatabaseCount('contract_audit_events', 0);
    }

    public function test_history_does_not_leak_between_companies(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$own, $other] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($other)->create();
        app(ContractAuditService::class)->record($contract, 'contract.created');
        $user = User::factory()->for($own)->viewContracts()->create();

        $this->actingAsForApi($user)->getJson(route('api.contracts.history', $contract))
            ->assertOk()->assertJsonPath('status', 'error')
            ->assertJsonMissingPath('rows')->assertJsonMissingPath('total');
    }
}
