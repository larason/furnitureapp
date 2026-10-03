<?php

namespace App\Services\Attachments;

use App\Models\AttachmentUploadCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UploadCapabilityService
{
    private const REQUEST_PARENT = 'furniture_request';

    private const ENQUIRY_PARENT = 'enquiry';

    private const TTL_MINUTES = 30;

    public function issueForRequest(int $requestId): string
    {
        return $this->issue(self::REQUEST_PARENT, $requestId);
    }

    public function issueForEnquiry(int $enquiryId): string
    {
        return $this->issue(self::ENQUIRY_PARENT, $enquiryId);
    }

    public function consumeForRequest(string $token, int $requestId): bool
    {
        return $this->consume($token, self::REQUEST_PARENT, $requestId);
    }

    public function consumeForEnquiry(string $token, int $enquiryId): bool
    {
        return $this->consume($token, self::ENQUIRY_PARENT, $enquiryId);
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
        return DB::transaction(function () use ($token, $parentType, $parentId): bool {
            $capability = AttachmentUploadCapability::query()
                ->where('parent_type', $parentType)
                ->where('parent_id', $parentId)
                ->where('token_hash', $this->tokenDigest($token))
                ->lockForUpdate()
                ->first();

            if ($capability === null || $capability->used_at !== null || $capability->expires_at->isPast()) {
                return false;
            }

            $capability->forceFill(['used_at' => now()])->save();

            return true;
        });
    }

    private function tokenDigest(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
