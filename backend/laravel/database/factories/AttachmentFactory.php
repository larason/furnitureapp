<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\FurnitureRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'furniture_request_id' => FurnitureRequest::factory(),
            'enquiry_id' => null,
            'storage_disk' => config('attachments.disk'),
            'storage_key' => 'attachments/requests/'.fake()->uuid().'.pdf',
            'filename' => fake()->word().'.pdf',
            'content_type' => 'application/pdf',
            'size' => 1024,
        ];
    }

    public function forEnquiry(int $enquiryId): static
    {
        return $this->state(fn (): array => [
            'furniture_request_id' => null,
            'enquiry_id' => $enquiryId,
        ]);
    }
}
