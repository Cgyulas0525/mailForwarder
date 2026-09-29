<?php

namespace App\Services\Mail;

use App\Models\GmailAccount;

interface MailboxClient
{
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(GmailAccount $account, ?string $password = null): array;

    public function fetchBatch(GmailAccount $account, int $minUid, int $batchSize): FetchBatch;
}
