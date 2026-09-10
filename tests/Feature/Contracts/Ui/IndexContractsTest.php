<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\User;
use Tests\TestCase;

class IndexContractsTest extends TestCase
{
    public function test_permission_required_to_list_contracts()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contracts.index'))
            ->assertForbidden();
    }

    public function test_index_page_renders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.index'))
            ->assertOk();
    }

    public function test_index_persists_contract_filters_in_form_and_data_url(): void
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.index', [
                'validity' => 'expired',
                'due_status' => 'overdue',
            ]))
            ->assertOk()
            ->assertSee('name="validity"', false)
            ->assertSee('value="expired" selected', false)
            ->assertSee('value="overdue" selected', false)
            ->assertSee('validity=expired', false)
            ->assertSee('due_status=overdue', false)
            ->assertSee(trans('button.clear'));
    }
}
