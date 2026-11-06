<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComputerHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Sao_Paulo']);
        foreach (['IP' => '_snipeit_test_ip_91', 'Ult. User Log' => '_snipeit_test_user_92', 'Coordenadas' => '_snipeit_test_coords_93'] as $name => $column) {
            DB::table('custom_fields')->insert(['name' => $name, 'element' => 'text', 'db_column' => $column, 'field_encrypted' => 0]);
        }
    }

    public function beginDatabaseTransaction()
    {
        foreach (['_snipeit_test_ip_91','_snipeit_test_user_92','_snipeit_test_coords_93'] as $column) {
            if (!Schema::hasColumn('assets', $column)) {
                Schema::table('assets', fn (Blueprint $table) => $table->string($column)->nullable());
            }
        }
        parent::beginDatabaseTransaction();
    }

    public function test_dashboard_and_data_require_authentication(): void
    {
        User::factory()->superuser()->create();
        $this->get('/computer-health')->assertRedirect(route('login'));
        $this->getJson('/computer-health/data')->assertUnauthorized();
    }

    public function test_dashboard_requires_permission_to_view_assets(): void
    {
        $this->actingAs(User::factory()->create())->get('/computer-health')->assertForbidden();
        $this->getJson('/computer-health/data')->assertForbidden();
    }

    public function test_only_non_deleted_notebooks_and_desktops_are_counted(): void
    {
        $notebook = $this->computer();
        $desktop = $this->computer('Desktop');
        $this->computer('Monitor');
        $deleted = $this->computer();
        $deleted->delete();
        $this->actingAs(User::factory()->viewAssets()->create());

        $data = $this->getJson('/computer-health/data')->assertOk()->json();

        $this->assertEqualsCanonicalizing([$notebook->id, $desktop->id], array_column($data['assets'], 'id'));
        $this->assertSame(2, $data['source']['asset_count']);
        $this->get('/computer-health')->assertOk()->assertSee('computer-health-app');
    }

    public function test_generic_update_does_not_replace_inventory_date_and_partial_events_stay_partial(): void
    {
        $asset = $this->computer('Notebook', ['updated_at' => '2026-11-06 08:00:00', '_snipeit_test_ip_91' => '10.0.0.7', '_snipeit_test_user_92' => 'DOMAIN\\current']);
        $old = $this->event($asset, '2026-09-01 10:00:00', ['_snipeit_test_user_92' => ['old' => 'DOMAIN\\old', 'new' => 'DOMAIN\\current']]);
        $latest = $this->event($asset, '2026-10-01 10:00:00', ['_snipeit_test_ip_91' => ['old' => null, 'new' => '10.0.0.7']]);
        $this->event($asset, '2026-11-06 08:00:00', ['name' => ['old' => 'old name', 'new' => 'new name']]);
        $this->actingAs(User::factory()->viewAssets()->create());

        $row = $this->getJson('/computer-health/data')->assertOk()->json('assets.0');

        $this->assertSame('2026-10-01T10:00:00-03:00', $row['last_inventory_at']);
        $this->assertSame('2026-11-06T08:00:00-03:00', $row['updated_at']);
        $this->assertSame('DOMAIN\\current', $row['current']['reported_user']);
        $this->assertContains('DOMAIN\\old', $row['historical_users']);
        $detail = $this->getJson('/computer-health/'.$asset->id.'/history')->assertOk()->json();
        $this->assertSame([$latest->id, $old->id], array_column($detail['events'], 'id'));
        $this->assertCount(1, $detail['events'][0]['changes']);
        $this->assertSame('ip', $detail['events'][0]['changes'][0]['field']);
    }

    public function test_company_scope_applies_to_counts_and_history(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$company, $other] = Company::factory()->count(2)->create();
        $mine = $this->computer('Notebook', ['company_id' => $company->id]);
        $theirs = $this->computer('Desktop', ['company_id' => $other->id]);
        $this->actingAs(User::factory()->viewAssets()->for($company)->create());

        $data = $this->getJson('/computer-health/data')->assertOk()->json();

        $this->assertSame([$mine->id], array_column($data['assets'], 'id'));
        $this->assertSame(1, $data['source']['asset_count']);
        $this->getJson('/computer-health/'.$theirs->id.'/history')->assertNotFound();
    }

    public function test_malformed_metadata_is_flagged_and_absence_is_preserved(): void
    {
        $asset = $this->computer();
        Actionlog::factory()->create(['item_id' => $asset->id, 'item_type' => Asset::class, 'log_meta' => '{invalid', 'created_at' => '2026-11-01 09:00:00']);
        $this->actingAs(User::factory()->viewAssets()->create());

        $row = $this->getJson('/computer-health/data')->assertOk()->json('assets.0');

        $this->assertNull($row['last_inventory_at']);
        $this->assertNull($row['current']['ip']);
        $this->assertSame(1, $row['invalid_history_count']);
    }

    public function test_equal_timestamps_use_event_id_and_future_dates_are_not_rewritten(): void
    {
        $asset = $this->computer();
        $first = $this->event($asset, '2030-01-01 12:00:00', ['_snipeit_test_ip_91' => ['new' => '10.0.0.1']]);
        $second = $this->event($asset, '2030-01-01 12:00:00', ['_snipeit_test_ip_91' => ['new' => '10.0.0.2']]);
        $this->actingAs(User::factory()->viewAssets()->create());

        $detail = $this->getJson('/computer-health/'.$asset->id.'/history')->assertOk()->json();

        $this->assertSame([$second->id, $first->id], array_column($detail['events'], 'id'));
        $this->assertSame('2030-01-01T12:00:00-03:00', $detail['asset']['last_inventory_at']);
    }

    public function test_zero_dates_are_flagged_instead_of_becoming_ancient_evidence(): void
    {
        $asset = $this->computer();
        $this->event($asset, '0000-00-00 00:00:00', ['_snipeit_test_ip_91' => ['new' => '10.0.0.1']]);
        $this->actingAs(User::factory()->viewAssets()->create());
        $row = $this->getJson('/computer-health/data')->assertOk()->json('assets.0');
        $this->assertNull($row['last_inventory_at']);
        $this->assertSame(1, $row['invalid_history_count']);
    }

    public function test_assigned_user_is_preserved_independently_of_reported_user(): void
    {
        $owner = User::factory()->create(['first_name'=>'Pessoa','last_name'=>'Responsável','username'=>'owner.local']);
        $asset = $this->computer('Notebook', ['assigned_to'=>$owner->id,'assigned_type'=>User::class,'_snipeit_test_user_92'=>'DOMAIN\\different']);
        $this->actingAs(User::factory()->viewAssets()->create());
        $row = $this->getJson('/computer-health/data')->assertOk()->json('assets.0');
        $this->assertSame('owner.local', $row['assigned_user']['username'] ?? null);
        $this->assertSame('DOMAIN\\different', $row['current']['reported_user']);
        $this->getJson('/computer-health/'.$asset->id.'/history')->assertOk()->assertJsonPath('asset.assigned_user.username','owner.local');
    }

    public function test_history_returns_current_query_time(): void
    {
        $asset = $this->computer();
        $this->actingAs(User::factory()->viewAssets()->create());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06T12:10:00-03:00'));
        try {
            $this->getJson('/computer-health/'.$asset->id.'/history')->assertOk()->assertJsonPath('generated_at','2026-11-06T12:10:00-03:00');
        } finally { CarbonImmutable::setTestNow(); }
    }

    public function test_related_navigation_keeps_native_ids_and_respects_view_permissions(): void
    {
        $owner = User::factory()->create(['username'=>'navigation.owner']);
        $location = Location::factory()->create();
        $asset = $this->computer('Notebook', ['assigned_to'=>$owner->id,'assigned_type'=>User::class,'location_id'=>$location->id]);
        $this->actingAs(User::factory()->viewAssets()->create());
        $row = $this->getJson('/computer-health/data')->assertOk()->json('assets.0');
        $this->assertSame($owner->id, $row['assigned_user']['id'] ?? null);
        $this->assertSame($asset->model_id, $row['model_id'] ?? null);
        $this->assertSame($location->id, $row['location_id'] ?? null);
        $this->assertNull($row['links']['user']);
        $this->assertNull($row['links']['model']);
        $this->assertNull($row['links']['location']);
        $this->actingAs(User::factory()->superuser()->create());
        $row = $this->getJson('/computer-health/data')->assertOk()->json('assets.0');
        $this->assertSame(route('users.show', $owner->id), $row['links']['user']);
        $this->assertSame(route('models.show', $asset->model_id), $row['links']['model']);
        $this->assertSame(route('locations.show', $location->id), $row['links']['location']);
        $this->assertSame(route('hardware.show', $asset->id), $row['asset_url']);
    }

    private function computer(string $category = 'Notebook', array $attributes = []): Asset
    {
        $type = Category::where('name', $category)->first() ?? Category::factory()->create(['name' => $category]);
        $model = AssetModel::factory()->create(['category_id' => $type->id]);
        return Asset::factory()->create(array_merge(['model_id' => $model->id], $attributes));
    }

    private function event(Asset $asset, string $date, array $changes): Actionlog
    {
        return Actionlog::factory()->create(['item_id' => $asset->id, 'item_type' => Asset::class, 'action_type' => 'update', 'created_at' => $date, 'log_meta' => json_encode($changes)]);
    }
}
