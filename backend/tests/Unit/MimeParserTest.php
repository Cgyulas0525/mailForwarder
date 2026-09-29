<?php

namespace Tests\Unit;

use App\Services\Mime\MimeParser;
use App\Services\Rules\InvoiceLinkDetector;
use PHPUnit\Framework\TestCase;

class MimeParserTest extends TestCase
{
    public function test_multipart_html_plain_and_attachment(): void
    {
        $raw = <<<'RAWMSG'
From: Szamla <szamla@pelda.hu>
Subject: =?UTF-8?B?U3rDoW1sYQ==?=
Date: Tue, 29 Sep 2026 10:00:00 +0200
Message-ID: <abc@pelda.hu>
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="BOUND"

--BOUND
Content-Type: multipart/alternative; boundary="ALT"

--ALT
Content-Type: text/plain; charset=ISO-8859-2
Content-Transfer-Encoding: quoted-printable

Sz=E1mla: https://www.szamlazz.hu/szamla/fiok/99/letoltes

--ALT
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

<p><a href=3D"https://www.szamlazz.hu/szamla/fiok/99/letoltes?x=3D1&amp;y=3D2">Let=C3=B6lt=C3=B6m a sz=C3=A1ml=C3=A1t</a></p>
<p><a href=3D"https://evil.example/szamla/fiok/1">Let=C3=B6lt=C3=B6m a sz=C3=A1ml=C3=A1t</a></p>

--ALT--
--BOUND
Content-Type: application/pdf; name="szamla.pdf"
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="szamla.pdf"

JVBERi0xLjEK

--BOUND--
RAWMSG;

        $parsed = (new MimeParser())->parse($raw);
        $links = (new InvoiceLinkDetector())->detect($parsed->html, $parsed->text);

        $this->assertSame('szamla@pelda.hu', $parsed->fromEmail);
        $this->assertSame('Számla', $parsed->subject);
        $this->assertSame('abc@pelda.hu', $parsed->messageId);
        $this->assertStringContainsString('szamlazz.hu/szamla/fiok/99/letoltes', $parsed->text);
        $this->assertNotEmpty($parsed->html);
        $this->assertCount(1, $parsed->attachments);
        $this->assertSame('szamla.pdf', $parsed->attachments[0]->filename);
        $this->assertSame("%PDF-1.1\n", $parsed->attachments[0]->content);
        $this->assertCount(2, $links);
        $this->assertFalse(collect($links)->contains(fn ($url) => str_contains($url, 'evil.example')));
    }
}
