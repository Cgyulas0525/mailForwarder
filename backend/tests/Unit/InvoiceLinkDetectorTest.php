<?php

namespace Tests\Unit;

use App\Services\Rules\InvoiceLinkDetector;
use PHPUnit\Framework\TestCase;

class InvoiceLinkDetectorTest extends TestCase
{
    public function test_szamlazz_link_is_required_host_and_path(): void
    {
        $detector = new InvoiceLinkDetector();

        $this->assertTrue($detector->isInvoiceUrl('https://www.szamlazz.hu/szamla/fiok/123/letoltes'));
        $this->assertTrue($detector->isInvoiceUrl('https://szamlazz.hu/szamla/fiok/1'));
        $this->assertFalse($detector->isInvoiceUrl('https://evil.example/szamla/fiok/1'));
        $this->assertFalse($detector->isInvoiceUrl('https://www.szamlazz.hu/masik/utvonal'));
    }

    public function test_html_entity_and_plain_text_and_foreign_anchor(): void
    {
        $detector = new InvoiceLinkDetector();
        $html = '<a href="https://www.szamlazz.hu/szamla/fiok/9?x=1&amp;y=2">Letöltöm a számlát</a>'
            .'<a href="https://evil.example/szamla/fiok/1">Letöltöm a számlát</a>';
        $text = 'Másolat: https://www.szamlazz.hu/szamla/fiok/9/plain';

        $links = $detector->detect($html, $text);

        $this->assertCount(2, $links);
        $this->assertStringContainsString('/szamla/fiok/9', $links[0]);
        $this->assertStringContainsString('y=2', $links[0]);
        $this->assertNotContains('https://evil.example/szamla/fiok/1', $links);
    }
}
