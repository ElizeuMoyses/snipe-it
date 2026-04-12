<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\User;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class IndexContractsTest extends TestCase implements TestsPermissionsRequirement
{
    public function test_requires_permission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.contracts.index'))
            ->assertForbidden();
    }

    public function test_can_list_contracts()
    {
        Contract::factory()->count(3)->create();

        $this->actingAsForApi(User::factory()->viewContracts()->create())
            ->getJson(route('api.contracts.index'))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ]);
    }
}
