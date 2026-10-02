<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttachmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('attachments'));
        $this->assertTrue(Schema::hasColumns('attachments', [
            'id',
            'furniture_request_id',
            'enquiry_id',
            'storage_disk',
            'storage_key',
            'filename',
            'content_type',
            'size',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_attachment_belongs_to_a_furniture_request(): void
    {
        $request = FurnitureRequest::factory()->create();
        $attachment = Attachment::factory()->create(['furniture_request_id' => $request->id]);

        $this->assertTrue($attachment->furnitureRequest->is($request));
        $this->assertNull($attachment->enquiry_id);
    }

    public function test_attachment_belongs_to_an_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create();
        $attachment = Attachment::factory()->create([
            'furniture_request_id' => null,
            'enquiry_id' => $enquiry->id,
        ]);

        $this->assertTrue($attachment->enquiry->is($enquiry));
        $this->assertNull($attachment->furniture_request_id);
    }

    public function test_attachment_requires_exactly_one_parent(): void
    {
        $this->expectException(DomainException::class);

        Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => null]);
    }

    public function test_attachment_rejects_two_parents(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();

        $this->expectException(DomainException::class);

        Attachment::factory()->create([
            'furniture_request_id' => $request->id,
            'enquiry_id' => $enquiry->id,
        ]);
    }

    public function test_only_one_attachment_per_furniture_request(): void
    {
        $request = FurnitureRequest::factory()->create();
        Attachment::factory()->create(['furniture_request_id' => $request->id]);

        $this->expectException(QueryException::class);

        Attachment::factory()->create(['furniture_request_id' => $request->id]);
    }

    public function test_only_one_attachment_per_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create();
        Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);

        $this->expectException(QueryException::class);

        Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);
    }

    public function test_deleting_parent_cascades_attachment_metadata(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();
        $requestAttachment = Attachment::factory()->create(['furniture_request_id' => $request->id]);
        $enquiryAttachment = Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);

        $request->delete();
        $enquiry->delete();

        $this->assertNull(Attachment::find($requestAttachment->id));
        $this->assertNull(Attachment::find($enquiryAttachment->id));
    }
}
