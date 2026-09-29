<?php

namespace App\Support;

class AddressParser
{
    public static function extract(?string $from): ?string
    {
        $from = trim((string) $from);
        if ($from === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            $email = strtolower(trim($matches[1]));
        } else {
            $email = strtolower($from);
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
