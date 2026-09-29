<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'source_message_id',
        'forward_recipient_id',
        'to_email',
        'status',
        'attempts',
        'last_error',
        'matched_rules',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_rules' => 'array',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SourceMessage::class, 'source_message_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(ForwardRecipient::class, 'forward_recipient_id');
    }
}
