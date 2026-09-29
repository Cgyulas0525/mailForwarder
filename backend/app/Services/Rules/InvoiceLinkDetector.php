<?php

namespace App\Services\Rules;

use DOMDocument;
use DOMElement;

class InvoiceLinkDetector
{
    /**
     * @return list<string>
     */
    public function detect(?string $html, ?string $text): array
    {
        $links = [];

        foreach ($this->fromHtml((string) $html) as $url) {
            $links[$url] = $url;
        }

        foreach ($this->fromText((string) $text) as $url) {
            $links[$url] = $url;
        }

        return array_values($links);
    }

    public function isInvoiceUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/^\s*javascript:/i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $hostOk = $host === 'szamlazz.hu' || str_ends_with($host, '.szamlazz.hu');

        return $hostOk && str_contains($path, '/szamla/fiok/');
    }

    /**
     * @return list<string>
     */
    private function fromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $links = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }
            $href = trim($anchor->getAttribute('href'));
            if ($this->isInvoiceUrl($href)) {
                $links[] = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    private function fromText(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('#https?://[^\s<>"\']+#i', $text, $matches);
        $links = [];
        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,);');
            if ($this->isInvoiceUrl($url)) {
                $links[] = $url;
            }
        }

        return $links;
    }
}
