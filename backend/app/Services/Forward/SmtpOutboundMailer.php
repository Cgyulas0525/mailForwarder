<?php

namespace App\Services\Forward;

use App\Models\GmailAccount;
use App\Support\ErrorSanitizer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SmtpOutboundMailer implements OutboundMailer
{
    public function send(OutboundMessage $message): void
    {
        $account = $message->account;
        $email = (new Email())
            ->from(new Address($account->email, $account->display_name ?: $account->email))
            ->to($message->to)
            ->subject($message->subject)
            ->text($message->text)
            ->html($message->html);

        foreach ($message->attachments as $attachment) {
            $email->attach($attachment->content, $attachment->filename, $attachment->mimeType);
        }

        try {
            $mailer = new Mailer(Transport::fromDsn($this->dsnFor($account)));
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $safe = ErrorSanitizer::sanitize($e->getMessage());
            if (ErrorSanitizer::isUncertain($safe)) {
                throw new UncertainDeliveryException($safe, previous: $e);
            }

            throw new \RuntimeException($safe, previous: $e);
        }
    }

    public function dsnFor(GmailAccount $account): string
    {
        if (! config('forwarding.live_smtp')) {
            return (string) config('forwarding.capture_dsn');
        }

        return $account->smtpDsn();
    }
}
