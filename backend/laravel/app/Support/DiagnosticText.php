<?php

namespace App\Support;

final class DiagnosticText
{
    public const MAX_LENGTH = 500;

    private const REDACTED = '[REDACTED]';

    private const SECRET_PATTERNS = [
        '/\b(?:\d[ -]?){13,19}\b/',
        '/\b(?:cvv|cvc)\b\s*[:=]?\s*\d{3,4}/i',
        '/sk_(?:live|test)_[A-Za-z0-9]+/',
        '/whsec_[A-Za-z0-9]+/',
        '/\bBearer\s+[A-Za-z0-9._~+\/-]+(=*)/i',
        '/\b(?:authorization|authorisation|auth)\s*(?:header)?\s*[:=]\s*(?:Bearer\s+)?[A-Za-z0-9._~+\/-]+(=*)/i',
        '/AKIA[0-9A-Z]{16}/',
        '/\b[0-9a-f]{32,64}\b/i',
    ];

    public static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $redacted = self::redact($trimmed);

        return mb_substr($redacted, 0, self::MAX_LENGTH);
    }

    private static function redact(string $value): string
    {
        foreach (self::SECRET_PATTERNS as $pattern) {
            $value = (string) preg_replace($pattern, self::REDACTED, $value);
        }

        return $value;
    }
}
