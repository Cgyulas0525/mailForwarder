<?php

namespace App\Services\Import;

class ImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $credentialsRequired = 0,
    ) {}

    public function summary(): string
    {
        return sprintf(
            'létrehozva: %d, frissítve: %d, változatlan: %d, hitelesítést igényel: %d',
            $this->created,
            $this->updated,
            $this->unchanged,
            $this->credentialsRequired
        );
    }
}
