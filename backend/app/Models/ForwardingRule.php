<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ForwardingRule extends Model
{
    protected $fillable = ['name', 'is_active', 'match_mode', 'checks_invoice_link', 'subject_contains'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'checks_invoice_link' => 'boolean',
        ];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(GmailAccount::class, 'forwarding_rule_account');
    }

    public function senders(): BelongsToMany
    {
        return $this->belongsToMany(EmailSender::class, 'forwarding_rule_sender');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(ForwardRecipient::class, 'forwarding_rule_recipient');
    }
}
