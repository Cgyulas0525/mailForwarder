<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'source_message_id',
        'filename',
        'mime_type',
        'size_bytes',
        'stored_path',
        'skipped_reason',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(SourceMessage::class, 'source_message_id');
    }
}
