<?php

namespace Tests\Feature\ContractStatusLabels\Ui;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Tests\TestCase;

class DeleteContractStatusLabelTest extends TestCase
{
    public function test_permission_required_to_delete_status_label()
    {
        $statusLabel = ContractStatusLabel::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('contract-status-labels.destroy', $statusLabel))
            ->assertForbidden();
    }

    public function test_cannot_delete_status_label_in_use()
    {
        $statusLabel = ContractStatusLabel::factory()->draft()->create();
        Contract::factory()->create(['status_label_id' => $statusLabel->id]);

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contract-status-labels.index'))
            ->delete(route('contract-status-labels.destroy', $statusLabel))
            ->assertRedirect(route('contract-status-labels.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('contract_status_labels', ['id' => $statusLabel->id, 'deleted_at' => null]);
    }

    public function test_can_delete_unused_status_label()
    {
        $statusLabel = ContractStatusLabel::factory()->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('contract-status-labels.index'))
            ->delete(route('contract-status-labels.destroy', $statusLabel))
            ->assertRedirectToRoute('contract-status-labels.index')
            ->assertSessionHas('success');

        $this->assertSoftDeleted($statusLabel);
    }
}
