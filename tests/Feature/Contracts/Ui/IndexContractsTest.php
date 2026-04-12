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
}
