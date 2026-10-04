<?php

namespace Tests\Feature;

use App\Models\AttachmentUploadCapability;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AttachmentUploadCapabilityRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_expired_and_used_capabilities(): void
    {
        $request = FurnitureRequest::factory()->create();
        $now = now()->toImmutable();

        AttachmentUploadCapability::query()->create([
            'parent_type' => AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE,
            'parent_id' => $request->id,
            'token_hash' => hash('sha256', 'used'),
            'expires_at' => $now->addHour(),
            'used_at' => $now,
        ]);
        AttachmentUploadCapability::query()->create([
            'parent_type' => AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE,
            'parent_id' => $request->id,
            'token_hash' => hash('sha256', 'expired'),
            'expires_at' => $now->subMinute(),
        ]);
        AttachmentUploadCapability::query()->create([
            'parent_type' => AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE,
            'parent_id' => $request->id,
            'token_hash' => hash('sha256', 'active'),
            'expires_at' => $now->addHour(),
        ]);

        $this->artisan('attachments:prune-upload-capabilities')->assertSuccessful();

        $this->assertDatabaseCount('attachment_upload_capabilities', 1);
        $this->assertDatabaseHas('attachment_upload_capabilities', ['token_hash' => hash('sha256', 'active')]);
    }

    public function test_deleting_parent_removes_bound_capabilities(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();

        AttachmentUploadCapability::query()->create([
            'parent_type' => AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE,
            'parent_id' => $request->id,
            'token_hash' => hash('sha256', 'request'),
            'expires_at' => now()->addHour(),
        ]);
        AttachmentUploadCapability::query()->create([
            'parent_type' => AttachmentUploadCapability::ENQUIRY_PARENT_TYPE,
            'parent_id' => $enquiry->id,
            'token_hash' => hash('sha256', 'enquiry'),
            'expires_at' => now()->addHour(),
        ]);

        $request->delete();
        $enquiry->delete();

        $this->assertDatabaseCount('attachment_upload_capabilities', 0);
    }

    public function test_prune_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('attachments:prune-upload-capabilities')
            ->assertSuccessful();
    }
}
