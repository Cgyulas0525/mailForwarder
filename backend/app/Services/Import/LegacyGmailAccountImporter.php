<?php

namespace App\Services\Import;

use App\Models\GmailAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyGmailAccountImporter
{
    /**
     * A régi status mező kapcsolati állapot, nem adminisztratív kikapcsolás.
     * Új fiók csak akkor engedélyezett, ha a jelszó a régi kulccsal visszafejthető.
     * Meglévő fiók is_enabled értéke és működő jelszava hiányzó vagy hibás forrásjelszó esetén nem változik.
     */
    public function import(?string $connection = null): ImportResult
    {
        $connection = $connection ?: (string) (config('forwarding.legacy_connection') ?: 'legacy_gmail_eval');
        LegacySource::assertReady($connection);

        try {
            if (config("database.connections.{$connection}.driver") === 'mysql') {
                DB::connection($connection)->statement('SET SESSION TRANSACTION READ ONLY');
            }
            $rows = DB::connection($connection)->table('gmail_accounts')->orderBy('id')->get();
        } catch (\Throwable $e) {
            throw new RuntimeException('A régi gmail_eval adatbázis nem érhető el. Ellenőrizd a LEGACY_DB_* beállításokat.');
        }

        $result = new ImportResult();
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row->email ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $account = GmailAccount::query()->firstOrNew(['email' => $email]);
            $isNew = ! $account->exists;
            $this->fillPublicFields($account, $row, $isNew);
            $plain = $this->decryptLegacy($row->password ?? null);
            $needsCredentials = $this->applyPassword($account, $plain, $isNew);

            if ($needsCredentials) {
                $result->credentialsRequired++;
            }

            if (! $account->isDirty()) {
                $result->unchanged++;

                continue;
            }

            $account->save();
            if ($isNew) {
                $result->created++;
            } else {
                $result->updated++;
            }
        }

        return $result;
    }

    private function fillPublicFields(GmailAccount $account, object $row, bool $isNew): void
    {
        $defaults = GmailAccount::gmailDefaults();
        $provider = $row->provider ?? ($isNew ? 'gmail' : $account->provider);
        if ($isNew && ($provider === null || $provider === '' || $provider === 'gmail')) {
            $account->fill($defaults);
        }

        $map = [
            'provider' => $row->provider ?? null,
            'imap_username' => $row->imap_username ?? null,
            'imap_host' => $row->imap_host ?? null,
            'imap_port' => $row->imap_port ?? null,
            'imap_encryption' => $row->imap_encryption ?? null,
            'imap_mailbox' => $row->imap_mailbox ?? null,
            'smtp_host' => $row->smtp_host ?? null,
            'smtp_port' => $row->smtp_port ?? null,
            'smtp_encryption' => $row->smtp_encryption ?? null,
        ];

        foreach ($map as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $account->{$field} = $value;
        }

        if ($isNew) {
            $legacyStatus = (string) ($row->status ?? 'pending');
            $account->status = in_array($legacyStatus, ['pending', 'active', 'error'], true) ? $legacyStatus : 'pending';
            $account->is_enabled = false;
        }
    }

    private function applyPassword(GmailAccount $account, ?string $plain, bool $isNew): bool
    {
        if ($plain !== null && $plain !== '') {
            if ($plain !== $account->password) {
                $account->password = $plain;
            }
            if ($account->status === 'credentials_required') {
                $account->status = 'pending';
            }
            if ($isNew) {
                $account->is_enabled = true;
            }

            return false;
        }

        if ($isNew || ! $account->hasStoredPassword()) {
            $account->password = null;
            $account->status = 'credentials_required';
            $account->is_enabled = false;

            return true;
        }

        return $account->status === 'credentials_required';
    }

    private function decryptLegacy(mixed $payload): ?string
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $key = config('forwarding.legacy_app_key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        try {
            $rawKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
            if (! is_string($rawKey) || strlen($rawKey) !== 32) {
                return null;
            }

            return (new Encrypter($rawKey, 'AES-256-CBC'))->decryptString($payload);
        } catch (DecryptException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
