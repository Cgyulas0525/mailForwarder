<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceMessage extends Model
{
    protected $fillable = [
        'gmail_account_id',
        'folder',
        'uidvalidity',
        'uid',
        'message_id',
        'from_raw',
        'from_email',
        'subject',
        'received_at',
        'text_body',
        'html_body',
        'invoice_links',
    ];

    protected $hidden = [
        'html_body',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'invoice_links' => 'array',
            'uidvalidity' => 'integer',
            'uid' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GmailAccount::class, 'gmail_account_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
