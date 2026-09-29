<?php

namespace App\Services\Sync;

class SyncResult
{
    public function __construct(
        public int $stored = 0,
        public int $failed = 0,
        public int $planned = 0,
        public ?string $error = null,
    ) {}
}
