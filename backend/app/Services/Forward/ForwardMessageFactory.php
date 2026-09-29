<?php

namespace App\Services\Forward;

use App\Models\Delivery;
use App\Services\Mime\ParsedAttachment;
use Illuminate\Support\Facades\Storage;

class ForwardMessageFactory
{
    public function make(Delivery $delivery): OutboundMessage
    {
        $delivery->loadMissing('message.account', 'message.attachments');
        $message = $delivery->message;
        $account = $message->account;
        $subject = $this->subject($message->subject);
        $links = $message->invoice_links ?? [];
        $when = optional($message->received_at)->timezone('Europe/Budapest')->format('Y. m. d. H:i');

        $text = implode("\n", array_filter([
            '---------- Továbbított üzenet ----------',
            'Feladó: '.($message->from_raw ?: $message->from_email),
            'Dátum: '.($when ?: 'ismeretlen'),
            'Tárgy: '.($message->subject ?: '(nincs tárgy)'),
            '',
            $message->text_body ?: '',
            $links ? '' : null,
            $links ? "Számlalinkek:\n".implode("\n", $links) : null,
        ], fn ($line) => $line !== null));

        $linkHtml = '';
        foreach ($links as $link) {
            $safe = htmlspecialchars($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $linkHtml .= '<p><a href="'.$safe.'">'.$safe.'</a></p>';
        }

        $html = '<p>---------- Továbbított üzenet ----------</p>'
            .'<p>Feladó: '.htmlspecialchars((string) ($message->from_raw ?: $message->from_email), ENT_QUOTES | ENT_HTML5, 'UTF-8').'<br>'
            .'Dátum: '.htmlspecialchars((string) $when, ENT_QUOTES | ENT_HTML5, 'UTF-8').'<br>'
            .'Tárgy: '.htmlspecialchars((string) ($message->subject ?: '(nincs tárgy)'), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>'
            .'<pre>'.htmlspecialchars((string) $message->text_body, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</pre>'
            .$linkHtml;

        return new OutboundMessage(
            account: $account,
            to: $delivery->to_email,
            subject: $subject,
            text: $text,
            html: $html,
            attachments: $this->attachments($delivery),
            delivery: $delivery,
        );
    }

    private function subject(?string $subject): string
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            return 'Fwd: Üzenet';
        }

        if (preg_match('/^(fw|fwd|forward|továbbítás|továbbított)\s*:/iu', $subject)) {
            return $subject;
        }

        return 'Fwd: '.$subject;
    }

    /**
     * @return list<ParsedAttachment>
     */
    private function attachments(Delivery $delivery): array
    {
        $items = [];
        foreach ($delivery->message->attachments as $attachment) {
            if (! $attachment->stored_path) {
                continue;
            }
            if (! Storage::disk('local')->exists($attachment->stored_path)) {
                continue;
            }
            $items[] = new ParsedAttachment(
                $attachment->filename,
                $attachment->mime_type ?: 'application/octet-stream',
                Storage::disk('local')->get($attachment->stored_path),
            );
        }

        return $items;
    }
}
