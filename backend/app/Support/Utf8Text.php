<?php

namespace App\Support;

class Utf8Text
{
    public static function isBinary(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (str_contains($value, "\0")) {
            return true;
        }

        return str_starts_with($value, "\x89PNG")
            || str_starts_with($value, "\xFF\xD8\xFF")
            || str_starts_with($value, 'GIF87a')
            || str_starts_with($value, 'GIF89a')
            || str_starts_with($value, '%PDF');
    }

    public static function sanitize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (self::isBinary($value)) {
            return '';
        }

        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $clean === false ? '' : $clean;
    }
}
