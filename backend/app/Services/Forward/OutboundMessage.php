<?php

namespace App\Services\Forward;

use App\Models\Delivery;
use App\Models\GmailAccount;
use App\Services\Mime\ParsedAttachment;

class OutboundMessage
{
    /**
     * @param  list<ParsedAttachment>  $attachments
     */
    public function __construct(
        public GmailAccount $account,
        public string $to,
        public string $subject,
        public string $text,
        public string $html,
        public array $attachments,
        public Delivery $delivery,
    ) {}
}
