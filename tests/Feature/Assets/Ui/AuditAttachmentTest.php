<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditAttachmentTest extends TestCase
{
    public function test_uploaded_audit_image_can_be_downloaded_from_its_history_link(): void
    {
        [$asset, $log, $contents] = $this->uploadAuditImage();
        $this->actingAs(User::factory()->viewAssets()->create());

        $response = $this->get($log->uploads_file_url())
            ->assertOk()
            ->assertDownload($log->filename);

        $this->assertSame($contents, file_get_contents($response->baseResponse->getFile()->getPathname()));
        Storage::assertExists('private_uploads/audits/'.$log->filename);
    }

    public function test_uploaded_audit_image_can_be_opened_inline(): void
    {
        [$asset, $log, $contents] = $this->uploadAuditImage();
        $this->actingAs(User::factory()->viewAssets()->create());

        $response = $this->get($log->uploads_file_url().'?inline=true')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_audit_attachment_requires_authentication(): void
    {
        [$asset, $log] = $this->uploadAuditImage();
        auth()->logout();

        $this->assertGuest();
        $this->get($log->uploads_file_url())->assertForbidden();
    }

    public function test_audit_attachment_requires_permission_to_view_the_asset(): void
    {
        [$asset, $log] = $this->uploadAuditImage();

        $this->actingAs(User::factory()->create())
            ->get($log->uploads_file_url())
            ->assertForbidden();
    }

    public function test_audit_attachment_cannot_be_requested_through_another_asset(): void
    {
        [$asset, $log] = $this->uploadAuditImage();
        $other = Asset::factory()->create();

        $this->from(route('hardware.show', $other))
            ->get(route('ui.files.show', ['object_type' => 'audits', 'id' => $other->id, 'file_id' => $log->id]))
            ->assertRedirect(route('hardware.show', $other).'#files')
            ->assertSessionHas('error', trans('general.file_upload_status.invalid_id'));
    }

    public function test_audit_attachment_is_private_to_the_assets_company(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        [$company, $otherCompany] = Company::factory()->count(2)->create();
        [$asset, $log] = $this->uploadAuditImage(['company_id' => $company->id]);

        $this->actingAs(User::factory()->viewAssets()->for($otherCompany)->create())
            ->get($log->uploads_file_url())
            ->assertForbidden();
    }

    public function test_missing_audit_attachment_reports_file_not_found(): void
    {
        [$asset, $log] = $this->uploadAuditImage();
        Storage::delete('private_uploads/audits/'.$log->filename);

        $this->from(route('hardware.show', $asset))
            ->get($log->uploads_file_url())
            ->assertRedirect(route('hardware.show', $asset).'#files')
            ->assertSessionHas('error', trans('general.file_upload_status.file_not_found'));
    }

    public function test_html_audit_attachment_is_downloaded_even_when_inline_is_requested(): void
    {
        [$asset, $log, $contents] = $this->uploadAuditImage([], UploadedFile::fake()->createWithContent(
            'audit-evidence.html', '<!doctype html><html><body><script>window.auditFixture = true;</script></body></html>'
        ));

        $response = $this->get($log->uploads_file_url().'?inline=true')
            ->assertOk()
            ->assertDownload($log->filename);

        $this->assertSame($contents, file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_api_html_audit_attachment_is_downloaded_even_when_inline_is_requested(): void
    {
        [$asset, $log, $contents] = $this->uploadAuditImage([], UploadedFile::fake()->createWithContent(
            'audit-evidence.html', '<!doctype html><html><body><script>window.auditFixture = true;</script></body></html>'
        ));

        $response = $this->actingAsForApi(User::factory()->viewAssets()->create())
            ->get(route('api.files.show', ['object_type' => 'audits', 'id' => $asset->id, 'file_id' => $log->id]).'?inline=true')
            ->assertOk()
            ->assertDownload($log->filename);

        $this->assertSame($contents, file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    private function uploadAuditImage(array $attributes = [], ?UploadedFile $image = null): array
    {
        Storage::fake();
        $asset = Asset::factory()->create($attributes);
        $image ??= UploadedFile::fake()->image('audit-evidence.png');
        $contents = file_get_contents($image->getPathname());

        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('asset.audit.store', $asset), [
                'image' => $image,
                'note' => 'Synthetic audit attachment regression test',
                'redirect_option' => 'item',
            ])
            ->assertRedirect(route('hardware.show', $asset));

        $log = $asset->audits()->sole();
        Storage::assertExists('private_uploads/audits/'.$log->filename);

        return [$asset, $log, $contents];
    }
}
