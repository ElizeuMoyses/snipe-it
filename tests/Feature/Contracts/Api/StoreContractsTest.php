<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\Company;
use App\Models\ContractType;
use App\Models\Supplier;
use App\Models\User;
use Tests\TestCase;

class StoreContractsTest extends TestCase
{
    public function test_api_can_defer_installment_generation(): void
    {
        $status = ContractStatusLabel::factory()->draft()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();
        $this->actingAsForApi(User::factory()->createContracts()->create())
            ->postJson(route('api.contracts.store'), [
                'name' => 'Deferred API Contract',
                'contract_type' => 'recurring',
                'contract_type_id' => $type->id,
                'status_label_id' => $status->id,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'start_date' => '2026-01-31',
                'end_date' => '2026-03-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 31,
                'installment_value' => '100.00',
                'description' => 'Contract description',
                'auto_generate_installments' => false,
            ])->assertOk()->assertStatusMessageIs('success');

        $contract = Contract::where('name', 'Deferred API Contract')->firstOrFail();
        $this->assertSame(0, $contract->installments()->count());
    }

    public function test_permission_required_to_store_contract()
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.contracts.store'), [
                'name' => 'API Contract',
            ])
            ->assertForbidden();
    }

    public function test_can_store_contract_via_api()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();

        $this->actingAsForApi(User::factory()->createContracts()->create())
            ->postJson(route('api.contracts.store'), [
                'name' => 'API Created Contract',
                'contract_type' => 'recurring',
                'contract_type_id' => $type->id,
                'status_label_id' => $statusLabel->id,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 1,
                'installment_value' => '2000.00',
                'description' => 'Contract description',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('contracts', [
            'name' => 'API Created Contract',
            'contract_type' => 'recurring',
        ]);
        $this->assertSame(12, Contract::where('name', 'API Created Contract')->firstOrFail()->installments()->count());
    }

    public function test_store_validates_required_fields()
    {
        $this->actingAsForApi(User::factory()->createContracts()->create())
            ->postJson(route('api.contracts.store'), [])
            ->assertOk()
            ->assertStatusMessageIs('error');
    }
}
