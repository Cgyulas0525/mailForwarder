<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailboxSyncState extends Model
{
    protected $fillable = ['gmail_account_id', 'folder', 'uidvalidity', 'last_uid'];

    protected function casts(): array
    {
        return [
            'uidvalidity' => 'integer',
            'last_uid' => 'integer',
        ];
    }
}
