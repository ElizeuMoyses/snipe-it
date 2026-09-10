<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\User;
use Tests\TestCase;

class PreviewContractsTest extends TestCase
{
    public function test_api_preview_is_not_shadowed_by_the_contract_resource_route(): void
    {
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.contracts.preview'), [
                'contract_type' => 'recurring',
                'start_date' => '2028-01-01',
                'end_date' => '2028-03-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 31,
                'installment_value' => '100.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.dates.0', '2028-01-31')
            ->assertJsonPath('data.dates.1', '2028-02-29')
            ->assertJsonPath('data.planned_total', '300.00');
    }
}
