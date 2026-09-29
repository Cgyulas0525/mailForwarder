<?php

namespace Tests\Support;

use App\Models\GmailAccount;
use App\Services\Mail\FetchBatch;
use App\Services\Mail\MailboxClient;
use App\Services\Mail\ParsedMessage;

class ArrayMailboxClient implements MailboxClient
{
    public int $calls = 0;

    public int $uidValidity = 100;

    /** @var list<ParsedMessage> */
    public array $messages = [];

    /** @var list<array{uid: int, error: string}> */
    public array $failures = [];

    public function testConnection(GmailAccount $account, ?string $password = null): array
    {
        $this->calls++;

        return ['success' => true, 'message' => 'IMAP kapcsolat sikeres.'];
    }

    public function fetchBatch(GmailAccount $account, int $minUid, int $batchSize): FetchBatch
    {
        $this->calls++;
        $messages = array_values(array_filter(
            $this->messages,
            fn (ParsedMessage $message) => $message->uid >= $minUid && $message->uidValidity === $this->uidValidity
        ));
        usort($messages, fn (ParsedMessage $a, ParsedMessage $b) => $a->uid <=> $b->uid);
        $failures = array_values(array_filter($this->failures, fn (array $failure) => $failure['uid'] >= $minUid));
        $slice = array_slice($messages, 0, $batchSize);
        $exhausted = count($messages) <= $batchSize && count($failures) === 0;

        return new FetchBatch($this->uidValidity, $slice, $failures, $exhausted);
    }
}
