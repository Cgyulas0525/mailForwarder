<?php

namespace App\Services\Import;

use RuntimeException;

class LegacySource
{
    public static function assertReady(string $connection): void
    {
        $all = config('database.connections');
        $config = is_array($all) ? ($all[$connection] ?? null) : null;
        if (! is_array($config)) {
            throw new RuntimeException('A régi gmail_eval adatbázis kapcsolata nincs beállítva (LEGACY_DB_HOST). Az import nem futott le.');
        }

        $driver = $config['driver'] ?? null;
        if ($driver === 'sqlite') {
            if (($config['database'] ?? '') === '') {
                throw new RuntimeException('A régi gmail_eval adatbázis kapcsolata nincs beállítva (LEGACY_DB_HOST). Az import nem futott le.');
            }

            return;
        }

        if ($driver === 'mysql') {
            $host = $config['host'] ?? null;
            if (! is_string($host) || $host === '') {
                throw new RuntimeException('A régi gmail_eval adatbázis kapcsolata nincs beállítva (LEGACY_DB_HOST). Az import nem futott le.');
            }

            return;
        }

        throw new RuntimeException('A régi gmail_eval adatbázis kapcsolata nincs beállítva (LEGACY_DB_HOST). Az import nem futott le.');
    }
}
