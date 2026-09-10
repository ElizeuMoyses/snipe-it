<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\Company;
use App\Models\ContractType;
use App\Models\Supplier;
use App\Models\User;
use Tests\TestCase;

class CreateContractTest extends TestCase
{
    public function test_permission_required_to_create_contract()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contracts.create'))
            ->assertForbidden();
    }

    public function test_page_renders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.create'))
            ->assertOk();
    }

    public function test_contract_create_requires_name()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.create'))
            ->post(route('contracts.store'), [
                'contract_type' => 'recurring',
                'contract_type_id' => $type->id,
                'status_label_id' => $statusLabel->id,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 1,
                'installment_value' => '1000.00',
                'description' => 'Contract description',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('contracts.create'));
        $this->assertFalse(Contract::where('contract_type', 'recurring')->exists());
    }

    public function test_contract_create_stores_successfully()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.create'))
            ->post(route('contracts.store'), [
                'name' => 'Test Valid Contract',
                'contract_number' => 'CTR-TEST-001',
                'contract_type' => 'recurring',
                'contract_type_id' => $type->id,
                'status_label_id' => $statusLabel->id,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 1,
                'installment_value' => '1500.00',
                'description' => 'Contract description',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('contracts.index'));
        $this->assertDatabaseHas('contracts', [
            'name' => 'Test Valid Contract',
            'contract_number' => 'CTR-TEST-001',
            'contract_type' => 'recurring',
        ]);
    }

    public function test_contract_create_validates_contract_type()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.create'))
            ->post(route('contracts.store'), [
                'name' => 'Invalid Type Contract',
                'contract_type' => 'invalid_type',
                'contract_type_id' => $type->id,
                'status_label_id' => $statusLabel->id,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'billing_cycle' => 'monthly',
                'billing_day' => 1,
                'installment_value' => '500.00',
                'description' => 'Contract description',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('contracts.create'));
        $this->assertFalse(Contract::where('name', 'Invalid Type Contract')->exists());
    }

    public function test_contract_create_validates_end_date_after_start_date()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contracts.create'))
            ->post(route('contracts.store'), [
                'name' => 'Bad Date Contract',
                'contract_type' => 'recurring',
                'contract_type_id' => $type->id,
                'status_label_id' => $statusLabel->id,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-01-01',
                'billing_cycle' => 'monthly',
                'billing_day' => 1,
                'installment_value' => '500.00',
                'description' => 'Contract description',
            ]);

        $response->assertStatus(302);
        $this->assertFalse(Contract::where('name', 'Bad Date Contract')->exists());
    }
}
