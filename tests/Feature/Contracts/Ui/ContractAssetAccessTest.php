<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Tests\TestCase;

class ContractAssetAccessTest extends TestCase
{
    public function test_foreign_company_asset_cannot_be_linked_by_submitting_its_id(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $foreign = Asset::factory()->for($companyB)->create();
        $this->actingAs(User::factory()->for($companyA)->create([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]))->post(route('contracts.assets.attach', $contract), ['asset_id' => $foreign->id])
            ->assertRedirect(route('hardware.index'))->assertSessionHas('error');
        $this->assertDatabaseMissing('contract_asset', ['contract_id' => $contract->id, 'asset_id' => $foreign->id]);
    }

    public function test_visible_asset_can_be_linked_once_and_detached(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create();
        $this->actingAs(User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1', 'assets.view' => '1']),
        ]));
        $this->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])->assertSessionHas('success');
        $this->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])->assertSessionHas('error');
        $this->assertSame(1, $contract->assets()->count());
        $this->delete(route('contracts.assets.detach', [$contract, $asset]))->assertSessionHas('success');
        $this->assertSame(0, $contract->assets()->count());
    }

    public function test_contract_editor_without_asset_view_cannot_link_an_asset(): void
    {
        $asset = Asset::factory()->create();
        $contract = Contract::factory()->create();
        $this->actingAs(User::factory()->create([
            'permissions' => json_encode(['contracts.edit' => '1']),
        ]))->post(route('contracts.assets.attach', $contract), ['asset_id' => $asset->id])->assertForbidden();
        $this->assertSame(0, $contract->assets()->count());
    }
}
