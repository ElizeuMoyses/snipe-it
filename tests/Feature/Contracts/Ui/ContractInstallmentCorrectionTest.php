<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\ContractAuditEvent;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class ContractInstallmentCorrectionTest extends TestCase
{
    public function test_unprivileged_user_cannot_reopen_or_bulk_delete(): void
    {
        $item = ContractInstallment::factory()->paid()->create();
        $this->actingAs(User::factory()->create());
        $this->post('/contracts/'.$item->contract_id.'/installments/'.$item->id.'/reopen', ['reason' => 'Unauthorized'])->assertForbidden();
        $this->post('/contracts/'.$item->contract_id.'/installments/bulk-delete', ['installment_ids' => [$item->id]])->assertForbidden();
        $this->assertNotNull($item->fresh()->paid_value);
    }

    public function test_failed_audit_rolls_back_reopened_payment(): void
    {
        $item = ContractInstallment::factory()->paid()->create(['paid_value' => '150.00']);
        ContractStatusLabel::factory()->pending()->create(['is_default' => true]);
        $this->actingAs(User::factory()->superuser()->create());
        $this->mock(\App\Services\Contracts\ContractAuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic audit failure'));
        });
        $this->post('/contracts/'.$item->contract_id.'/installments/'.$item->id.'/reopen', ['reason' => 'Correction'])->assertStatus(500);
        $this->assertSame('150.00', $item->fresh()->paid_value);
        $this->assertSame('paid', $item->fresh()->statusLabel->meta_type);
    }

    public function test_reopening_payment_requires_reason_and_preserves_audit(): void
    {
        $item = ContractInstallment::factory()->paid()->create(['paid_value' => '150.00']);
        ContractStatusLabel::factory()->pending()->create(['is_default' => true]);
        $this->actingAs(User::factory()->superuser()->create());
        $url = '/contracts/'.$item->contract_id.'/installments/'.$item->id.'/reopen';
        $this->get($url)->assertOk()->assertSee('name="reason"', false);
        $this->post($url, [])->assertSessionHasErrors('reason');
        $this->assertSame('150.00', $item->fresh()->paid_value);
        $this->post($url, ['reason' => 'Payment recorded on wrong installment'])->assertSessionHas('success');
        $this->assertNull($item->fresh()->paid_value);
        $this->assertSame('pending', $item->fresh()->statusLabel->meta_type);
        $event = ContractAuditEvent::where('action', 'installment.status_changed')->latest('id')->firstOrFail();
        $this->assertStringContainsString('150.00', json_encode($event->getAttributes()));
        $this->assertStringContainsString('Payment recorded on wrong installment', json_encode($event->getAttributes()));
        $this->post($url, ['reason' => 'Repeat'])->assertSessionHas('error');
    }

    public function test_bulk_delete_is_atomic_when_selection_contains_paid_installment(): void
    {
        $pending = ContractInstallment::factory()->create();
        $paid = ContractInstallment::factory()->paid()->create(['contract_id' => $pending->contract_id]);
        $this->actingAs(User::factory()->superuser()->create())
            ->post('/contracts/'.$pending->contract_id.'/installments/bulk-delete', [
                'installment_ids' => [$pending->id, $paid->id],
            ])->assertSessionHasErrors('installment_ids');
        $this->assertNull($pending->fresh()->deleted_at);
        $this->assertNull($paid->fresh()->deleted_at);
    }

    public function test_bulk_delete_removes_only_selected_children_and_audits_each(): void
    {
        $one = ContractInstallment::factory()->create();
        $two = ContractInstallment::factory()->create(['contract_id' => $one->contract_id]);
        $other = ContractInstallment::factory()->create();
        $this->actingAs(User::factory()->superuser()->create());
        $url = '/contracts/'.$one->contract_id.'/installments/bulk-delete';
        $this->post($url, ['installment_ids' => [$one->id, $other->id]])->assertNotFound();
        $this->assertNull($one->fresh()->deleted_at);
        $this->post($url, ['installment_ids' => [$one->id, $two->id]])->assertSessionHas('success');
        $this->assertSoftDeleted($one);
        $this->assertSoftDeleted($two);
        $this->assertNull($other->fresh()->deleted_at);
        $this->assertSame(2, ContractAuditEvent::where('action', 'installment.deleted')->count());
    }
}
