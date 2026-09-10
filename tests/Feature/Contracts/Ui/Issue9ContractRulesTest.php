<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\ContractType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContractInput;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Issue9ContractRulesTest extends TestCase
{
    public function test_create_requires_the_issue9_contract_fields(): void
    {
        $this->actingAs(User::factory()->superuser()->create());

        try {
            ContractInput::validate([], true);
            $this->fail('Expected the contract input to be rejected.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            foreach ([
                'name', 'contract_type', 'contract_type_id', 'status_label_id',
                'supplier_id', 'company_id', 'start_date', 'end_date',
                'billing_cycle', 'billing_day', 'installment_value', 'description',
            ] as $field) {
                $this->assertArrayHasKey($field, $errors);
            }
        }
    }

    public function test_automatic_total_and_preview_use_the_same_eom_calendar(): void
    {
        $payload = $this->validPayload([
            'start_date' => '2028-01-01',
            'end_date' => '2028-03-31',
            'billing_day' => 31,
            'installment_value' => 'R$ 100,00',
        ]);

        $data = ContractInput::validate($payload, true);

        $this->assertSame('300.00', $data['total_value']);

        $contract = new Contract;
        ContractInput::apply($contract, $data);
        $preview = $contract->installmentPreview();

        $this->assertSame(3, $preview['installments_count']);
        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], $preview['dates']);
        $this->assertSame('300.00', $preview['planned_total']);
        $this->assertSame('300.00', $preview['negotiated_total']);
        $this->assertSame('0.00', $preview['difference']);
    }

    public function test_manual_total_is_preserved_and_not_redistributed(): void
    {
        $data = ContractInput::validate($this->validPayload([
            'start_date' => '2028-01-01',
            'end_date' => '2028-03-31',
            'billing_day' => 31,
            'installment_value' => '100.00',
            'total_value_mode' => 'manual',
            'total_value' => 'R$ 250,00',
        ]), true);

        $this->assertSame('manual', $data['total_value_mode']);
        $this->assertSame('250.00', $data['total_value']);

        $contract = new Contract;
        ContractInput::apply($contract, $data);
        $preview = $contract->installmentPreview();

        $this->assertSame('300.00', $preview['planned_total']);
        $this->assertSame('250.00', $preview['negotiated_total']);
        $this->assertSame('-50.00', $preview['difference']);
    }

    public function test_incompatible_cycles_and_quantity_overflow_are_rejected(): void
    {
        try {
            ContractInput::validate($this->validPayload([
                'contract_type' => 'recurring',
                'billing_cycle' => 'one_time',
            ]), true);
            $this->fail('Expected recurring and one-time cycle to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_cycle', $exception->errors());
        }

        try {
            ContractInput::validate($this->validPayload([
                'contract_type' => 'one_time',
                'billing_cycle' => 'monthly',
                'total_installments' => 4,
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
            ]), true);
            $this->fail('Expected quantity outside the contract period to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('total_installments', $exception->errors());
        }
    }

    public function test_billing_day_before_start_moves_to_the_next_valid_cycle(): void
    {
        $contract = new Contract([
            'contract_type' => 'recurring',
            'start_date' => '2026-01-20',
            'end_date' => '2026-02-28',
            'billing_cycle' => 'monthly',
            'billing_day' => 15,
            'installment_value' => '100.00',
        ]);

        $this->assertSame(['2026-02-15'], array_map(
            static fn (Carbon $date) => $date->toDateString(),
            $contract->plannedInstallmentDates()
        ));
    }

    public function test_legacy_patch_can_omit_fields_that_did_not_exist(): void
    {
        $contract = Contract::factory()->create([
            'contract_type_id' => null,
            'supplier_id' => null,
            'company_id' => null,
            'end_date' => null,
            'billing_day' => null,
            'description' => null,
        ]);

        $data = ContractInput::validate(['name' => 'Legacy contract renamed'], false, $contract);

        $this->assertSame('Legacy contract renamed', $data['name']);
        $this->assertArrayNotHasKey('billing_day', $data);
        $this->assertArrayNotHasKey('contract_type_id', $data);
    }

    public function test_modern_update_rejects_empty_required_text_fields(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->create([
            'contract_type_id' => ContractType::where('code', 'recurring')->firstOrFail()->id,
            'company_id' => Company::factory()->create()->id,
            'billing_day' => 1,
            'description' => 'Existing description',
        ]);

        foreach (['name', 'description'] as $field) {
            try {
                ContractInput::validate([$field => ''], false, $contract);
                $this->fail('Expected empty '.$field.' to be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
    }

    private function validPayload(array $overrides = []): array
    {
        $this->actingAs(User::factory()->superuser()->create());
        $supplier = Supplier::factory()->create();
        $company = Company::factory()->create();
        $type = ContractType::where('code', 'recurring')->firstOrFail();
        $status = ContractStatusLabel::factory()->draft()->create();

        return array_merge([
            'name' => 'Issue 9 contract',
            'contract_type' => 'recurring',
            'contract_type_id' => $type->id,
            'status_label_id' => $status->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'installment_value' => '100.00',
            'description' => 'Issue 9 description',
        ], $overrides);
    }
}
