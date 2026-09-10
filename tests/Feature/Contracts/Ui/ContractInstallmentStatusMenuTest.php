<?php

namespace Tests\Feature\Contracts\Ui;

use App\Enums\ActionType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractInstallmentStatusMenuTest extends TestCase
{
    public function test_status_dialog_only_offers_allowed_transitions(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create();
        $current = ContractStatusLabel::factory()->forInstallments()->pending()->create([
            'name' => 'Synthetic Pending',
        ]);
        $sameMeta = ContractStatusLabel::factory()->forInstallments()->pending()->create([
            'name' => 'Synthetic Pending Review',
        ]);
        $cancelled = ContractStatusLabel::factory()->create([
            'scope' => 'installment',
            'meta_type' => 'cancelled',
            'name' => 'Synthetic Cancelled',
        ]);
        $paid = ContractStatusLabel::factory()->forInstallments()->paid()->create([
            'name' => 'Synthetic Paid',
        ]);
        $installment = ContractInstallment::factory()->for($contract)->create([
            'status_label_id' => $current->id,
        ]);

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.show', $contract));

        $response->assertOk()
            ->assertSee('data-target="#installmentStatusModal"', false)
            ->assertSee('aria-labelledby="installmentStatusTitle"', false)
            ->assertSee('name="status_label_id" required', false)
            ->assertSee('Synthetic Pending Review')
            ->assertSee('Synthetic Cancelled')
            ->assertDontSee('Synthetic Paid');
        $this->assertNotSame($sameMeta->id, $installment->fresh()->status_label_id);
    }

    public function test_terminal_installment_does_not_render_status_control(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create();
        $installment = ContractInstallment::factory()->for($contract)->paid()->create();

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->get(route('contracts.show', $contract));

        $response->assertOk()
            ->assertDontSee('data-target="#installmentStatusModal"', false);

        $this->assertSame(1, $contract->installments()->count());
        $this->assertTrue($installment->fresh()->statusLabel->isTerminal());
        $this->assertSame(0, substr_count($response->getContent(), 'data-target="#installmentStatusModal"'));
        $this->assertSame('paid', $installment->fresh()->statusLabel->meta_type);
    }

    public function test_pending_installment_can_only_use_allowed_status_transition(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create();
        $current = ContractStatusLabel::factory()->forInstallments()->pending()->create([
            'name' => 'Synthetic Pending',
        ]);
        $cancelled = ContractStatusLabel::factory()->create([
            'scope' => 'installment',
            'meta_type' => 'cancelled',
            'name' => 'Synthetic Cancelled',
        ]);
        $paid = ContractStatusLabel::factory()->forInstallments()->paid()->create([
            'name' => 'Synthetic Paid',
        ]);
        $installment = ContractInstallment::factory()->for($contract)->create([
            'status_label_id' => $current->id,
        ]);
        $user = User::factory()->superuser()->create();

        $this->actingAs($user)
            ->patch(route('contracts.installments.status.update', [$contract, $installment]), [
                'status_label_id' => $paid->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame($current->id, $installment->fresh()->status_label_id);

        $this->actingAs($user)
            ->patch(route('contracts.installments.status.update', [$contract, $installment]), [
                'status_label_id' => $cancelled->id,
            ])
            ->assertRedirect(route('contracts.show', $contract).'#installments');

        $this->assertSame('cancelled', $installment->fresh()->statusLabel->meta_type);

    }

    public function test_status_update_rejects_a_contract_scope_status(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create();
        $current = ContractStatusLabel::factory()->forInstallments()->pending()->create([
            'name' => 'Synthetic Pending',
        ]);
        $contractStatus = ContractStatusLabel::factory()->active()->create([
            'name' => 'Synthetic Contract Active',
        ]);
        $installment = ContractInstallment::factory()->for($contract)->create([
            'status_label_id' => $current->id,
        ]);
        $user = User::factory()->superuser()->create();

        $this->from(route('contracts.show', $contract))
            ->actingAs($user)
            ->patch(route('contracts.installments.status.update', [$contract, $installment]), [
                'status_label_id' => $contractStatus->id,
            ])
            ->assertRedirect(route('contracts.show', $contract))
            ->assertSessionHas('error');

        $this->assertSame($current->id, $installment->fresh()->status_label_id);
    }

    public function test_status_update_rejects_an_installment_id_from_another_contract(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create();
        $otherContract = Contract::factory()->withActiveStatus()->create();
        $otherInstallment = ContractInstallment::factory()->for($otherContract)->create();
        $target = ContractStatusLabel::factory()->create([
            'scope' => 'installment',
            'meta_type' => 'cancelled',
            'name' => 'Synthetic Cancelled',
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->patch(route('contracts.installments.status.update', [$contract, $otherInstallment]), [
                'status_label_id' => $target->id,
            ])
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('error');

        $this->assertSame('pending', $otherInstallment->fresh()->statusLabel->meta_type);
    }

    public function test_status_update_respects_full_multiple_company_support(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->withActiveStatus()->create();
        $current = ContractStatusLabel::factory()->forInstallments()->pending()->create([
            'name' => 'Synthetic Pending',
        ]);
        $target = ContractStatusLabel::factory()->create([
            'scope' => 'installment',
            'meta_type' => 'cancelled',
            'name' => 'Synthetic Cancelled',
        ]);
        $installment = ContractInstallment::factory()->for($contract)->create([
            'status_label_id' => $current->id,
        ]);
        $user = User::factory()->for($companyB)->create([
            'permissions' => json_encode([
                'contracts.view' => '1',
                'contracts.installments' => '1',
            ]),
        ]);

        $this->actingAs($user)
            ->patch(route('contracts.installments.status.update', [$contract, $installment]), [
                'status_label_id' => $target->id,
            ])
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('error');

        $this->assertSame($current->id, $installment->fresh()->status_label_id);
    }

    public function test_payment_commit_blocks_a_stale_status_cancellation(): void
    {
        $contract = Contract::factory()->withActiveStatus()->create();
        $current = ContractStatusLabel::factory()->forInstallments()->pending()->create([
            'name' => 'Synthetic Pending',
        ]);
        ContractStatusLabel::factory()->forInstallments()->paid()->create([
            'name' => 'Synthetic Paid Default',
            'is_default' => true,
        ]);
        $paid = ContractStatusLabel::defaultForMetaType('installment', 'paid');
        $this->assertNotNull($paid);
        $cancelled = ContractStatusLabel::factory()->create([
            'scope' => 'installment',
            'meta_type' => 'cancelled',
            'name' => 'Synthetic Cancelled',
        ]);
        $installment = ContractInstallment::factory()->for($contract)->create([
            'status_label_id' => $current->id,
        ]);
        $user = User::factory()->superuser()->create();

        $this->actingAs($user)
            ->post(route('contracts.installments.pay.store', [$contract, $installment]), [
                'paid_value' => '150.00',
                'payment_date' => '2026-09-10',
            ])
            ->assertRedirect(route('contracts.show', $contract).'#installments');

        $this->from(route('contracts.show', $contract))
            ->actingAs($user)
            ->patch(route('contracts.installments.status.update', [$contract, $installment]), [
                'status_label_id' => $cancelled->id,
            ])
            ->assertRedirect(route('contracts.show', $contract))
            ->assertSessionHas('error');

        $fresh = $installment->fresh();
        $this->assertSame($paid->id, $fresh->status_label_id);
        $this->assertSame('150.00', $fresh->paid_value);
    }
}
