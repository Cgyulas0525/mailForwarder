<?php

namespace App\Services\Mail;

class FetchBatch
{
    /**
     * @param  list<ParsedMessage>  $messages
     * @param  list<array{uid: int, error: string}>  $failures
     */
    public function __construct(
        public int $uidValidity,
        public array $messages,
        public array $failures,
        public bool $exhausted,
    ) {}
}
