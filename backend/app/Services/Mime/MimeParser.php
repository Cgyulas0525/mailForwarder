<?php

namespace App\Services\Mime;

use App\Support\AddressParser;
use Carbon\Carbon;

class MimeParser
{
    public function parse(string $raw): MimeParseResult
    {
        [$headerBlob, $body] = $this->splitHeaders($raw);
        $headers = $this->parseHeaders($headerBlob);
        $contentType = $headers['content-type'] ?? 'text/plain; charset=UTF-8';
        $parsed = $this->parsePart($headers, $contentType, $body);

        $receivedAt = null;
        if (! empty($headers['date'])) {
            try {
                $receivedAt = Carbon::parse($headers['date']);
            } catch (\Throwable) {
                $receivedAt = null;
            }
        }

        return new MimeParseResult(
            messageId: $this->cleanMessageId($headers['message-id'] ?? null),
            fromRaw: $this->decodeHeader($headers['from'] ?? ''),
            fromEmail: AddressParser::extract($this->decodeHeader($headers['from'] ?? '')),
            subject: $this->decodeHeader($headers['subject'] ?? ''),
            receivedAt: $receivedAt,
            text: $parsed['text'],
            html: $parsed['html'],
            attachments: $parsed['attachments'],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitHeaders(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);
        $parts = preg_split("/\n\n/", $raw, 2);
        $header = $parts[0] ?? '';
        $body = $parts[1] ?? '';

        return [str_replace("\n", "\r\n", $header), str_replace("\n", "\r\n", $body)];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = preg_replace("/\n[ \t]+/", ' ', $raw) ?? $raw;
        $headers = [];
        foreach (explode("\n", $raw) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            $headers[$key] = trim($value);
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{text: string, html: string, attachments: list<ParsedAttachment>}
     */
    private function parsePart(array $headers, string $contentType, string $body): array
    {
        $type = strtolower(trim(strtok($contentType, ';') ?: 'text/plain'));
        $params = $this->contentTypeParams($contentType);
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '7bit');
        $decoded = $this->decodeBody($body, $encoding);

        if (str_starts_with($type, 'multipart/')) {
            $boundary = $params['boundary'] ?? '';
            $text = '';
            $html = '';
            $attachments = [];
            foreach ($this->splitMultipart($decoded, $boundary) as $part) {
                [$partHeaders, $partBody] = $this->splitHeaders($part);
                $parsedHeaders = $this->parseHeaders($partHeaders);
                $partType = $parsedHeaders['content-type'] ?? 'text/plain; charset=UTF-8';
                $child = $this->parsePart($parsedHeaders, $partType, $partBody);
                $text .= $child['text'];
                $html .= $child['html'];
                $attachments = array_merge($attachments, $child['attachments']);
            }

            return compact('text', 'html', 'attachments');
        }

        $charset = $params['charset'] ?? 'UTF-8';
        $disposition = strtolower($headers['content-disposition'] ?? '');
        $filename = $this->filename($headers, $params);
        $isAttachment = str_contains($disposition, 'attachment') || ($filename !== null && ! str_starts_with($type, 'text/'));

        if ($isAttachment) {
            return [
                'text' => '',
                'html' => '',
                'attachments' => [new ParsedAttachment($filename ?: 'melleklet.bin', $type, $decoded)],
            ];
        }

        $textContent = $this->toUtf8($decoded, $charset);
        if ($type === 'text/html') {
            return ['text' => '', 'html' => $textContent, 'attachments' => []];
        }

        return ['text' => $textContent, 'html' => '', 'attachments' => []];
    }

    /**
     * @return array<string, string>
     */
    private function contentTypeParams(string $contentType): array
    {
        $params = [];
        if (preg_match_all('/;\s*([a-zA-Z0-9_-]+)\s*=\s*("([^"]*)"|([^;]+))/', $contentType, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[strtolower($match[1])] = trim($match[3] !== '' ? $match[3] : $match[4], " \t\"");
            }
        }

        return $params;
    }

    /**
     * @return list<string>
     */
    private function splitMultipart(string $body, string $boundary): array
    {
        if ($boundary === '') {
            return [];
        }

        $marker = '--'.$boundary;
        $chunks = explode($marker, $body);
        $parts = [];
        foreach ($chunks as $chunk) {
            $chunk = ltrim($chunk, "\r\n");
            if ($chunk === '' || str_starts_with($chunk, '--')) {
                continue;
            }
            $parts[] = $chunk;
        }

        return $parts;
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => base64_decode(preg_replace('/\s+/', '', $body) ?? '', true) ?: '',
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function toUtf8(string $value, string $charset): string
    {
        $charset = strtoupper(trim($charset));
        if ($charset === '' || $charset === 'UTF-8' || $charset === 'UTF8') {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', $charset);

        return $converted === false ? $value : $converted;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $params
     */
    private function filename(array $headers, array $params): ?string
    {
        $disposition = $headers['content-disposition'] ?? '';
        if (preg_match('/filename\*=(?:UTF-8\'\')?([^;]+)/i', $disposition, $matches)) {
            return $this->decodeHeader(rawurldecode(trim($matches[1], "\" ")));
        }
        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches)) {
            return $this->decodeHeader($matches[1]);
        }
        if (! empty($params['name'])) {
            return $this->decodeHeader($params['name']);
        }

        return null;
    }

    private function decodeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? $decoded : $value;
    }

    private function cleanMessageId(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return trim($value, "<> \t");
    }
}
