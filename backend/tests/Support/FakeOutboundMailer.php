<?php

namespace Tests\Support;

use App\Services\Forward\OutboundMailer;
use App\Services\Forward\OutboundMessage;

class FakeOutboundMailer implements OutboundMailer
{
    /** @var list<OutboundMessage> */
    public array $sent = [];

    public ?\Throwable $throw = null;

    public function send(OutboundMessage $message): void
    {
        if ($this->throw) {
            throw $this->throw;
        }

        $this->sent[] = $message;
    }
}
