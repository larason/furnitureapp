<?php

namespace App\Services\Attachments;

use App\Models\AttachmentUploadCapability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class UploadCapabilityService
{
    private const TTL_MINUTES = 30;

    public function issueForRequest(int $requestId): string
    {
        return $this->issue(AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE, $requestId);
    }

    public function issueForEnquiry(int $enquiryId): string
    {
        return $this->issue(AttachmentUploadCapability::ENQUIRY_PARENT_TYPE, $enquiryId);
    }

    public function consumeForRequest(string $token, int $requestId): bool
    {
        return $this->consume($token, AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE, $requestId);
    }

    public function consumeForEnquiry(string $token, int $enquiryId): bool
    {
        return $this->consume($token, AttachmentUploadCapability::ENQUIRY_PARENT_TYPE, $enquiryId);
    }

    public function hasAvailableForRequest(string $token, int $requestId): bool
    {
        return $this->availableCapabilityQuery($token, AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE, $requestId)->exists();
    }

    public function hasAvailableForEnquiry(string $token, int $enquiryId): bool
    {
        return $this->availableCapabilityQuery($token, AttachmentUploadCapability::ENQUIRY_PARENT_TYPE, $enquiryId)->exists();
    }

    private function issue(string $parentType, int $parentId): string
    {
        $token = 'uat_'.Str::random(64);

        AttachmentUploadCapability::query()->create([
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'token_hash' => $this->tokenDigest($token),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $token;
    }

    private function consume(string $token, string $parentType, int $parentId): bool
    {
        return $this->availableCapabilityQuery($token, $parentType, $parentId)
            ->delete() === 1;
    }

    /** @return Builder<AttachmentUploadCapability> */
    private function availableCapabilityQuery(string $token, string $parentType, int $parentId): Builder
    {
        return AttachmentUploadCapability::query()
            ->where('parent_type', $parentType)
            ->where('parent_id', $parentId)
            ->where('token_hash', $this->tokenDigest($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    private function tokenDigest(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
