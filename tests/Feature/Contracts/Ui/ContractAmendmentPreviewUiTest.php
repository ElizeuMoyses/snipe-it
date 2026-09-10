<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\User;
use Tests\TestCase;

class ContractAmendmentPreviewUiTest extends TestCase
{
    public function test_form_renders_preview_details_and_field_error_hooks(): void
    {
        $user = User::factory()->superuser()->create();
        $this->actingAs($user);
        $contract = Contract::factory()->withActiveStatus()->create();

        $this->get(route('contracts.amendments.create', $contract))
            ->assertOk()
            ->assertSee('id="preview-details"', false)
            ->assertSee('id="preview-error-old_value"', false)
            ->assertSee('id="preview-error-new_end_date"', false)
            ->assertSee('id="preview_token"', false)
            ->assertSee('renderPreviewDetails', false)
            ->assertSee('preview_valid', false)
            ->assertSee('showPreviewErrors', false);
    }

    public function test_ui_confirmation_without_a_successful_preview_is_rejected(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create(['installment_value' => '100.00']);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-10-01', 'expected_value' => '100.00',
        ]);

        $this->post(route('contracts.amendments.store', $contract), [
            'amendment_type' => 'readjustment',
            'description' => 'Missing preview',
            'effective_date' => '2026-09-01',
            'old_value' => '100.00',
            'new_value' => '125.00',
        ])->assertRedirect()
            ->assertSessionHasErrors('preview_token');

        $this->assertDatabaseCount('contract_amendments', 0);
        $this->assertSame('100.00', $contract->fresh()->installment_value);
    }

    public function test_ui_uses_preview_token_and_server_value_when_confirming_readjustment(): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create(['installment_value' => '100.00']);
        ContractInstallment::factory()->for($contract)->create([
            'due_date' => '2026-10-01', 'expected_value' => '100.00',
        ]);
        $input = [
            'amendment_type' => 'readjustment',
            'description' => 'Valid UI preview',
            'effective_date' => '2026-09-01',
            'old_value' => '100.00',
            'new_value' => '125.00',
        ];

        $preview = $this->postJson(route('contracts.amendments.preview', $contract), $input)
            ->assertOk();
        $input['preview_token'] = $preview->json('preview_token');

        $this->post(route('contracts.amendments.store', $contract), $input)
            ->assertRedirect(route('contracts.show', $contract->id).'#amendments');

        $this->assertDatabaseHas('contract_amendments', [
            'contract_id' => $contract->id,
            'old_value' => '100.00',
            'new_value' => '125.00',
        ]);
    }
}
