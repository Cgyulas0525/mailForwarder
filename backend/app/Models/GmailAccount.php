<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GmailAccount extends Model
{
    protected $fillable = [
        'display_name',
        'email',
        'password',
        'provider',
        'imap_username',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_mailbox',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'is_enabled',
        'status',
        'last_fetched_at',
        'last_attempt_at',
        'last_error',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
        ];
    }

    public static function gmailDefaults(): array
    {
        return [
            'provider' => 'gmail',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_mailbox' => 'INBOX',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
        ];
    }

    public static function settingsFromInput(array $data): array
    {
        $provider = $data['provider'] ?? 'gmail';

        if ($provider !== 'custom') {
            return self::gmailDefaults();
        }

        return [
            'provider' => 'custom',
            'imap_host' => $data['imap_host'],
            'imap_port' => (int) ($data['imap_port'] ?? 993),
            'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
            'imap_mailbox' => $data['imap_mailbox'] ?? 'INBOX',
            'smtp_host' => $data['smtp_host'],
            'smtp_port' => (int) ($data['smtp_port'] ?? 587),
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'imap_username' => $data['imap_username'] ?? null,
        ];
    }

    public function imapMailbox(): string
    {
        return $this->imap_mailbox ?: 'INBOX';
    }

    public function imapSelectCommand(): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $this->imapMailbox());

        return 'SELECT "'.$escaped.'"';
    }

    public function authUsername(): string
    {
        return $this->imap_username ?: $this->email;
    }

    public function imapStreamHost(): string
    {
        $host = $this->imap_host ?: 'imap.gmail.com';
        $encryption = $this->imap_encryption ?: 'ssl';

        if ($encryption === 'ssl') {
            return 'ssl://'.$host;
        }

        return $host;
    }

    public function imapPort(): int
    {
        return (int) ($this->imap_port ?: 993);
    }

    public function smtpDsn(): string
    {
        $host = $this->smtp_host ?: 'smtp.gmail.com';
        $port = (int) ($this->smtp_port ?: 587);
        $encryption = $this->smtp_encryption ?: 'tls';
        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';

        return sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($this->authUsername()),
            rawurlencode((string) $this->password),
            $host,
            $port
        );
    }

    public function hasStoredPassword(): bool
    {
        return filled($this->getRawOriginal('password'));
    }

    public function needsCredentials(): bool
    {
        return $this->status === 'credentials_required' || ! $this->hasStoredPassword();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SourceMessage::class);
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(ForwardingRule::class, 'forwarding_rule_account');
    }
}
