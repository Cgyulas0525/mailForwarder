<?php

namespace App\Services\Forward;

interface OutboundMailer
{
    public function send(OutboundMessage $message): void;
}
