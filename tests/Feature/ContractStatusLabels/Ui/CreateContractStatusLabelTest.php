<?php

namespace Tests\Feature\ContractStatusLabels\Ui;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class CreateContractStatusLabelTest extends TestCase
{
    public function test_permission_required_to_create_status_label()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contract-status-labels.create'))
            ->assertForbidden();
    }

    public function test_page_renders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contract-status-labels.create'))
            ->assertOk();
    }

    public function test_status_label_stores_successfully()
    {
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contract-status-labels.create'))
            ->post(route('contract-status-labels.store'), [
                'name' => 'Test Active Label',
                'scope' => 'contract',
                'meta_type' => 'active',
                'color' => '#00ff00',
                'icon' => 'fas fa-check',
                'sort_order' => 1,
                'is_default' => 0,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('contract-status-labels.index'));
        $this->assertDatabaseHas('contract_status_labels', [
            'name' => 'Test Active Label',
            'scope' => 'contract',
            'meta_type' => 'active',
        ]);
    }

    public function test_status_label_requires_valid_meta_type()
    {
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contract-status-labels.create'))
            ->post(route('contract-status-labels.store'), [
                'name' => 'Bad Meta Type',
                'scope' => 'contract',
                'meta_type' => 'nonexistent_type',
                'color' => '#ff0000',
            ]);

        $response->assertStatus(302);
        $this->assertFalse(ContractStatusLabel::where('name', 'Bad Meta Type')->exists());
    }

    public function test_setting_default_clears_other_defaults()
    {
        $existing = ContractStatusLabel::factory()->create([
            'scope' => 'contract',
            'meta_type' => 'draft',
            'is_default' => true,
        ]);

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->post(route('contract-status-labels.store'), [
                'name' => 'New Default Draft',
                'scope' => 'contract',
                'meta_type' => 'draft',
                'color' => '#cccccc',
                'sort_order' => 0,
                'is_default' => 1,
            ]);

        $response->assertSessionHasNoErrors();

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_default);

        $newLabel = ContractStatusLabel::where('name', 'New Default Draft')->first();
        $this->assertNotNull($newLabel);
        $this->assertTrue((bool) $newLabel->is_default);
    }
}
