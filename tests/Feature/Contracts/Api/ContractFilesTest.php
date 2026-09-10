<?php

namespace Tests\Feature\Contracts\Api;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractFilesTest extends TestCase
{
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
