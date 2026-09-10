<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractAmendment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractFilesTest extends TestCase
{
    public function test_oversized_file_is_rejected_without_persistence(): void
    {
        Storage::fake();
        $contract = Contract::factory()->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.files.store', ['object_type' => 'contracts', 'id' => $contract->id]), [
                'file' => [UploadedFile::fake()->image('oversized.png')->size((int) \App\Helpers\Helper::file_upload_max_size() + 1)],
            ])->assertStatusMessageIs('error');
        $this->assertSame(0, $contract->uploads()->count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_authorized_user_can_upload_amendment_files(): void
    {
        Storage::fake();
        $contract = Contract::factory()->create();
        $amendment = ContractAmendment::create([
            'contract_id' => $contract->id, 'amendment_type' => 'scope_change',
            'description' => 'Synthetic scope change', 'effective_date' => '2026-09-10',
        ]);
        $this->actingAsForApi(User::factory()->create([
            'permissions' => json_encode(['contracts.files' => '1']),
        ]))->postJson(route('api.files.store', ['object_type' => 'contract_amendments', 'id' => $amendment->id]), [
            'file' => [UploadedFile::fake()->image('amendment.png')],
        ])->assertOk()->assertStatusMessageIs('success');
        $this->assertSame(1, $amendment->uploads()->count());
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake();
        $contract = Contract::factory()->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.files.store', ['object_type' => 'contracts', 'id' => $contract->id]), [
                'file' => [UploadedFile::fake()->createWithContent('invalid.php', '<?php echo 1;')],
            ])->assertStatusMessageIs('error');
        $this->assertSame(0, $contract->uploads()->count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_installment_files_are_private_to_the_parent_company(): void
    {
        Storage::fake();
        $this->settings->enableMultipleFullCompanySupport();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $contract = Contract::factory()->for($companyA)->create();
        $installment = ContractInstallment::factory()->for($contract)->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.files.store', ['object_type' => 'contract_installments', 'id' => $installment->id]), [
                'file' => [UploadedFile::fake()->image('receipt.png')],
            ])->assertStatusMessageIs('success');
        $log = $installment->uploads()->sole();
        $user = User::factory()->for($companyB)->create([
            'permissions' => json_encode(['contracts.view' => '1', 'contracts.files' => '1']),
        ]);
        $this->actingAsForApi($user)
            ->getJson(route('api.files.show', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $log->id]))
            ->assertForbidden();
        $this->deleteJson(route('api.files.destroy', ['object_type' => 'contract_installments', 'id' => $installment->id, 'file_id' => $log->id]))
            ->assertForbidden();
        Storage::assertExists('private_uploads/contract_installments/'.$log->filename);
    }

    public function test_viewer_cannot_upload_contract_files(): void
    {
        Storage::fake();
        $contract = Contract::factory()->create();
        $this->actingAsForApi(User::factory()->viewContracts()->create())
            ->postJson(route('api.files.store', ['object_type' => 'contracts', 'id' => $contract->id]), [
                'file' => [UploadedFile::fake()->image('evidence.png')],
            ])->assertForbidden();
        $this->assertSame(0, $contract->uploads()->count());
    }

    public function test_file_lifecycle_and_contract_ownership(): void
    {
        Storage::fake();
        $contract = Contract::factory()->create();
        $other = Contract::factory()->create();
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.files.store', ['object_type' => 'contracts', 'id' => $contract->id]), [
                'file' => [UploadedFile::fake()->image('evidence.png')],
            ])->assertStatusMessageIs('success');
        $log = $contract->uploads()->sole();
        Storage::assertExists('private_uploads/contracts/'.$log->filename);

        $this->getJson(route('api.files.show', ['object_type' => 'contracts', 'id' => $other->id, 'file_id' => $log->id]))
            ->assertStatusMessageIs('error');
        $this->get(route('api.files.show', ['object_type' => 'contracts', 'id' => $contract->id, 'file_id' => $log->id]))
            ->assertOk();
        $this->deleteJson(route('api.files.destroy', ['object_type' => 'contracts', 'id' => $other->id, 'file_id' => $log->id]))
            ->assertStatusMessageIs('error');
        Storage::assertExists('private_uploads/contracts/'.$log->filename);
        $this->deleteJson(route('api.files.destroy', ['object_type' => 'contracts', 'id' => $contract->id, 'file_id' => $log->id]))
            ->assertStatusMessageIs('success');
        Storage::assertMissing('private_uploads/contracts/'.$log->filename);
        $this->assertSame(0, $contract->uploads()->count());
    }
}
