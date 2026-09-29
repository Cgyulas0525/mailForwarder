<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ForwardRecipient extends Model
{
    protected $fillable = ['name', 'email', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(ForwardingRule::class, 'forwarding_rule_recipient');
    }
}
