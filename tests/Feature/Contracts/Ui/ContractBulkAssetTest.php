<?php

namespace Tests\Feature\Contracts\Ui;

use App\Events\ContractAssetLinkChanged;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContractBulkAssetTest extends TestCase
{
    public function test_contract_asset_selector_supports_multiple_assets(): void
    {
        $contract = Contract::factory()->create();
        $user = User::factory()->superuser()->create();

        $this->actingAs($user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('name="asset_ids[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('id="contract_asset_id"', false)
            ->assertSee(trans('admin/contracts/bulk_assets.selector.help'), false)
            ->assertSee(trans('admin/contracts/bulk_assets.attach.button'), false)
            ->assertSee('id="contractAssetSelectedCount"', false);
    }

    public function test_multiple_assets_are_linked_atomically_with_audit_and_events(): void
    {
        Event::fake([ContractAssetLinkChanged::class]);

        $contract = Contract::factory()->create();
        $assets = Asset::factory()->count(2)->create([
            'company_id' => $contract->company_id,
        ]);
        $user = User::factory()->create([
            'permissions' => json_encode([
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), [
                'asset_ids' => $assets->pluck('id')->all(),
            ])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('success', trans('admin/contracts/bulk_assets.attach.success', ['count' => 2]));

        $this->assertSame(2, $contract->assets()->count());
        $this->assertSame(2, ContractAuditEvent::query()
            ->where('contract_id', $contract->id)
            ->where('action', 'asset.attached')
            ->count());
        Event::assertDispatchedTimes(ContractAssetLinkChanged::class, 2);
    }

    public function test_bulk_link_is_atomic_when_one_asset_is_not_available(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $valid = Asset::factory()->for($companyA)->create();
        $foreign = Asset::factory()->for($companyB)->create();
        $user = $companyA->users()->save(User::factory()->make([
            'permissions' => json_encode([
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]));

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), [
                'asset_ids' => [$valid->id, $foreign->id],
            ])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/message.asset.not_available'));

        $this->assertDatabaseMissing('contract_asset', [
            'contract_id' => $contract->id,
            'asset_id' => $valid->id,
        ]);
        $this->assertDatabaseMissing('contract_asset', [
            'contract_id' => $contract->id,
            'asset_id' => $foreign->id,
        ]);
    }

    public function test_bulk_link_rejects_existing_links_without_partial_changes(): void
    {
        $contract = Contract::factory()->create();
        $alreadyLinked = Asset::factory()->create(['company_id' => $contract->company_id]);
        $newAsset = Asset::factory()->create(['company_id' => $contract->company_id]);
        $contract->assets()->attach($alreadyLinked);
        $user = User::factory()->create([
            'permissions' => json_encode([
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), [
                'asset_ids' => [$alreadyLinked->id, $newAsset->id],
            ])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('error', trans('admin/contracts/bulk_assets.attach.already_linked'));

        $this->assertDatabaseHas('contract_asset', [
            'contract_id' => $contract->id,
            'asset_id' => $alreadyLinked->id,
        ]);
        $this->assertDatabaseMissing('contract_asset', [
            'contract_id' => $contract->id,
            'asset_id' => $newAsset->id,
        ]);
    }

    public function test_bulk_link_requires_asset_view_permission_for_every_selected_asset(): void
    {
        $contract = Contract::factory()->create();
        $assets = Asset::factory()->count(2)->create([
            'company_id' => $contract->company_id,
        ]);
        $user = User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1']),
        ]);

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), [
                'asset_ids' => $assets->pluck('id')->all(),
            ])
            ->assertForbidden();

        $this->assertSame(0, $contract->assets()->count());
    }

    public function test_validation_error_preserves_visible_selected_assets_in_the_form(): void
    {
        $contract = Contract::factory()->create();
        $asset = Asset::factory()->create([
            'company_id' => $contract->company_id,
        ]);
        $user = User::factory()->create([
            'permissions' => json_encode([
                'contracts.view' => '1',
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]);

        $this->actingAs($user)
            ->from(route('contracts.show', $contract))
            ->post(route('contracts.assets.attach', $contract), [
                'asset_ids' => [$asset->id, 'invalid'],
            ])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHasErrors('asset_ids.1');

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('value="'.$asset->id.'" selected="selected"', false);
    }

    public function test_scalar_asset_id_remains_supported(): void
    {
        $contract = Contract::factory()->create();
        $asset = Asset::factory()->create([
            'company_id' => $contract->company_id,
        ]);
        $user = User::factory()->create([
            'permissions' => json_encode([
                'contracts.edit' => '1',
                'assets.view' => '1',
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])
            ->assertRedirect(route('contracts.show', $contract).'#contract-assets')
            ->assertSessionHas('success', trans('admin/contracts/message.asset.attach.success'));

        $this->assertDatabaseHas('contract_asset', [
            'contract_id' => $contract->id,
            'asset_id' => $asset->id,
        ]);
    }
}
