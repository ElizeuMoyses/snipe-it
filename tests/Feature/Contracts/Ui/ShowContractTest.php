<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\User;
use Tests\TestCase;

class ShowContractTest extends TestCase
{
    public function test_permission_required_to_view_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('contracts.show', $contract))
            ->assertForbidden();
    }

    public function test_show_page_renders()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.show', $contract))
            ->assertOk();
    }
}
