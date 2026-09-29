<?php

namespace App\Support;

class ErrorSanitizer
{
    public static function sanitize(?string $message): string
    {
        $message = (string) $message;
        $message = preg_replace('/smtp[s]?:\/\/[^@\s\/]+@/i', 'smtp://***@', $message) ?? $message;
        $message = preg_replace('/(password|passwd|app_key|authorization)\s*[=:]\s*\S+/i', '$1=***', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }

    public static function isUncertain(?string $message): bool
    {
        return (bool) preg_match('/timed?\s*out|timeout|connection reset|empty reply|operation timed/i', (string) $message);
    }
}
