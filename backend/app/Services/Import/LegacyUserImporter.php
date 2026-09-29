<?php

namespace App\Services\Import;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyUserImporter
{
    /**
     * A Laravel user jelszó bcrypt hash, nem APP_KEY-es titkosítás.
     * A hash másolható. Az importált felhasználók adminok, mert az alkalmazás admin felület.
     * E-mail alapján idempotens. Üres forrásjelszó nem írja felül a meglévő hash-t.
     */
    public function import(?string $connection = null): ImportResult
    {
        $connection = $connection ?: (string) (config('forwarding.legacy_connection') ?: 'legacy_gmail_eval');
        LegacySource::assertReady($connection);

        try {
            if (config("database.connections.{$connection}.driver") === 'mysql') {
                DB::connection($connection)->statement('SET SESSION TRANSACTION READ ONLY');
            }
            $rows = DB::connection($connection)->table('users')->orderBy('id')->get();
        } catch (\Throwable) {
            throw new RuntimeException('A régi gmail_eval users tábla nem érhető el. Ellenőrizd a LEGACY_DB_* beállításokat.');
        }

        $result = new ImportResult();
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row->email ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $user = User::query()->firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;
            $user->name = filled($row->name) ? (string) $row->name : ($user->name ?: $email);
            $user->email_verified_at = $row->email_verified_at ?? $user->email_verified_at;
            $user->is_admin = true;

            $hash = (string) ($row->password ?? '');
            if ($hash !== '') {
                $user->password = $hash;
            } elseif ($isNew) {
                $result->credentialsRequired++;

                continue;
            }

            if (! $user->isDirty()) {
                $result->unchanged++;

                continue;
            }

            $user->save();
            if ($isNew) {
                $result->created++;
            } else {
                $result->updated++;
            }
        }

        return $result;
    }
}
