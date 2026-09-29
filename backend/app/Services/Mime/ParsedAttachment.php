<?php

namespace App\Services\Mime;

class ParsedAttachment
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public string $content,
    ) {}

    public function size(): int
    {
        return strlen($this->content);
    }
}
