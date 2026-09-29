<?php

namespace App\Services\Mime;

use Carbon\CarbonInterface;

class MimeParseResult
{
    /**
     * @param  list<ParsedAttachment>  $attachments
     */
    public function __construct(
        public ?string $messageId,
        public string $fromRaw,
        public ?string $fromEmail,
        public ?string $subject,
        public ?CarbonInterface $receivedAt,
        public string $text,
        public string $html,
        public array $attachments,
    ) {}
}
