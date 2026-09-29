<?php

namespace App\Services\Mime;

class HtmlSanitizer
{
    public function sanitize(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|iframe|object)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $allowed = '<a><br><p><div><span><strong><em><ul><ol><li><table><tr><td><th><tbody><thead>';
        $stripped = strip_tags($html, $allowed);
        $stripped = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/href\s*=\s*([\'"])\s*javascript:[^\'"]*\1/i', 'href="#"', $stripped) ?? $stripped;

        return $stripped;
    }
}
