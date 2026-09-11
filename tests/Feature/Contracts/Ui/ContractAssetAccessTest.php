<?php

namespace Tests\Feature\Contracts\Ui;

use App\Events\ContractAssetLinkChanged;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContractAssetAccessTest extends TestCase
{
    public function test_foreign_company_asset_cannot_be_linked_by_submitting_its_id(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $foreign = Asset::factory()->for($companyB)->create();
        $user = $companyA->users()->save(User::factory()->make([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]));

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), ['asset_id' => $foreign->id])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.not_available'));

        $this->assertDatabaseMissing('contract_asset', ['contract_id' => $contract->id, 'asset_id' => $foreign->id]);
    }

    public function test_visible_asset_can_be_linked_once_and_detached(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create();
        $user = User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]);

        $this->actingAs($user);

        $this->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('success');
        $this->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.already_linked'));

        $this->assertSame(1, $contract->assets()->count());

        $this->delete(route('contracts.assets.detach', [$contract, $asset]))
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('success');
        $this->assertSame(0, $contract->assets()->count());
    }

    public function test_contract_editor_without_asset_view_cannot_link_an_asset(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1']),
        ]))->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])
            ->assertForbidden();

        $this->assertSame(0, $contract->assets()->count());
    }

    public function test_contract_editor_without_asset_view_sees_clear_selector_message(): void
    {
        $contract = Contract::factory()->create();
        $user = User::factory()->create([
            'permissions' => json_encode(['contracts.view' => '1', 'contracts.edit' => '1']),
        ]);

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(trans('admin/contracts/message.asset.no_permission'), false)
            ->assertDontSee('data-contract-asset-selector="true"', false);
    }

    public function test_contract_asset_selector_is_scoped_and_excludes_existing_links(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $candidate = Asset::factory()->for($companyA)->create([
            'asset_tag' => 'ISSUE-10-CANDIDATE',
            'name' => 'Issue 10 candidate',
        ]);
        $alreadyLinked = Asset::factory()->for($companyA)->create([
            'asset_tag' => 'ISSUE-10-LINKED',
            'name' => 'Issue 10 linked',
        ]);
        $foreign = Asset::factory()->for($companyB)->create([
            'asset_tag' => 'ISSUE-10-FOREIGN',
            'name' => 'Issue 10 foreign',
        ]);
        $contract->assets()->attach($alreadyLinked);
        $user = $companyA->users()->save(User::factory()->make([
            'permissions' => json_encode([
                'contracts.view' => '1',
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]));

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('data-ajax-url="'.route('contracts.assets.selectlist', $contract).'"', false)
            ->assertSee('data-contract-asset-selector="true"', false)
            ->assertSee('data-company-id="'.$companyA->id.'"', false)
            ->assertSee('for="contract_asset_id"', false)
            ->assertSee('aria-describedby="contract-asset-selector-help"', false)
            ->assertSee(trans('admin/contracts/message.asset.selector.help'), false);

        $response = $this->getJson(route('contracts.assets.selectlist', $contract), [
            'search' => 'ISSUE-10',
        ])->assertOk();

        $ids = collect($response->json('results'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($candidate->id, $ids);
        $this->assertNotContains($alreadyLinked->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_same_company_guard_remains_enforced_when_company_scoping_is_disabled(): void
    {
        $this->settings->disableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $foreign = Asset::factory()->for($companyB)->create();
        $user = $companyA->users()->save(User::factory()->make([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]));

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), ['asset_id' => $foreign->id])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.not_available'));

        $this->assertDatabaseMissing('contract_asset', ['contract_id' => $contract->id, 'asset_id' => $foreign->id]);
    }

    public function test_detaching_an_unlinked_asset_does_not_report_success(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create();
        $user = User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]);

        $this->actingAs($user)
            ->delete(route('contracts.assets.detach', [$contract, $asset]))
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.not_linked'))
            ->assertSessionMissing('success');
    }

    public function test_asset_view_permission_is_required_to_detach_an_asset(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create();
        $contract->assets()->attach($asset);

        $this->actingAs(User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1']),
        ]))->delete(route('contracts.assets.detach', [$contract, $asset]))
            ->assertForbidden();

        $this->assertDatabaseHas('contract_asset', ['contract_id' => $contract->id, 'asset_id' => $asset->id]);
    }

    public function test_closed_contract_keeps_asset_links_immutable_and_hides_mutation_ui(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create([
            'status_label_id' => ContractStatusLabel::factory()->cancelled(),
        ]);
        $contract->assets()->attach($asset);
        $user = User::factory()->create([
            'permissions' => json_encode([
                'contracts.view' => '1',
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(trans('admin/contracts/message.asset.contract_closed'), false)
            ->assertDontSee('id="contract-asset-link-form"', false)
            ->assertDontSee(trans('admin/contracts/general.unlink_asset'), false);

        $this->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.contract_closed'));
        $this->delete(route('contracts.assets.detach', [$contract, $asset]))
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.contract_closed'));

        $this->assertDatabaseHas('contract_asset', ['contract_id' => $contract->id, 'asset_id' => $asset->id]);
    }

    public function test_linking_and_unlinking_emit_one_after_commit_integration_event_without_mutating_asset_state(): void
    {
        Event::fake([ContractAssetLinkChanged::class]);

        $assetOwner = User::factory()->create();
        $asset = Asset::factory()->create([
            'assigned_to' => $assetOwner->id,
            'assigned_type' => User::class,
            'last_checkout' => now()->subDay(),
        ]);
        $contract = Contract::factory()->create();
        $user = User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]);
        $before = $asset->only(['assigned_to', 'assigned_type', 'status_id']);

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])
            ->assertSessionHas('success');

        $this->assertSame($before, $asset->fresh()->only(['assigned_to', 'assigned_type', 'status_id']));
        Event::assertDispatched(ContractAssetLinkChanged::class, function (ContractAssetLinkChanged $event) use ($contract, $asset, $user): bool {
            return $event->contract->is($contract)
                && $event->asset->is($asset)
                && $event->action === ContractAssetLinkChanged::LINKED
                && $event->actorId === $user->id
                && $event->occurredAt !== null;
        });

        $this->delete(route('contracts.assets.detach', [$contract, $asset]))
            ->assertSessionHas('success');

        $this->assertSame($before, $asset->fresh()->only(['assigned_to', 'assigned_type', 'status_id']));
        Event::assertDispatched(ContractAssetLinkChanged::class, function (ContractAssetLinkChanged $event) use ($contract, $asset, $user): bool {
            return $event->contract->is($contract)
                && $event->asset->is($asset)
                && $event->action === ContractAssetLinkChanged::UNLINKED
                && $event->actorId === $user->id
                && $event->occurredAt !== null;
        });
        Event::assertDispatchedTimes(ContractAssetLinkChanged::class, 2);
    }
}
