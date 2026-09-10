<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ViewContractAmendmentTest extends TestCase
{
    public function test_contract_with_authored_amendment_renders(): void
    {
        Storage::fake();
        $user = User::factory()->superuser()->create();
        $this->actingAs($user);
        $contract = Contract::factory()->withActiveStatus()->create();
        $amendment = new ContractAmendment([
            'contract_id' => $contract->id,
            'amendment_type' => 'readjustment',
            'description' => 'Synthetic amendment visible in contract history',
            'effective_date' => '2026-09-10',
            'old_value' => '100.00', 'new_value' => '120.00',
        ]);
        $amendment->created_by = $user->id;
        $this->assertTrue($amendment->save());
        $this->post(route('ui.files.store', ['object_type' => 'contract_amendments', 'id' => $amendment->id]), [
            'file' => [UploadedFile::fake()->image('synthetic.png')],
        ])->assertRedirect();
        $this->assertSame(1, $amendment->uploads()->count());
        $this->get(route('contracts.show', $contract->id))
            ->assertOk()->assertSee($amendment->description)
            ->assertSee('id="uploadModal-'.$amendment->id.'"', false)
            ->assertSee(route('api.files.index', ['object_type' => 'contract_amendments', 'id' => $amendment->id]), false);
    }
}
