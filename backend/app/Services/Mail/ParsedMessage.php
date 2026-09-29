<?php

namespace App\Services\Mail;

class ParsedMessage
{
    /**
     * @param  list<\App\Services\Mime\ParsedAttachment>  $attachments
     * @param  list<string>  $invoiceLinks
     */
    public function __construct(
        public int $uid,
        public int $uidValidity,
        public string $folder,
        public ?string $messageId,
        public string $fromRaw,
        public ?string $fromEmail,
        public ?string $subject,
        public ?\DateTimeInterface $receivedAt,
        public string $text,
        public string $html,
        public array $attachments,
        public array $invoiceLinks,
    ) {}
}
