<?php

namespace App\Services\Mail;

use App\Models\GmailAccount;
use App\Services\Mime\MimeParser;
use App\Services\Rules\InvoiceLinkDetector;
use App\Support\ErrorSanitizer;
use RuntimeException;

/**
 * IMAP kliens. A protokoll UIDVALIDITY + UID párost használ, kötegenként lapoz.
 * A kapcsolat később cserélhető egy Gmail API megvalósításra.
 */
class ImapMailboxClient implements MailboxClient
{
    private $socket;

    private int $tag = 1;

    public function __construct(
        private MimeParser $parser,
        private InvoiceLinkDetector $links,
    ) {}

    public function testConnection(GmailAccount $account, ?string $password = null): array
    {
        try {
            $this->connect($account);
            $this->login($account->authUsername(), $password ?? (string) $account->password);
            $this->disconnect();

            return ['success' => true, 'message' => 'IMAP kapcsolat sikeres.'];
        } catch (\Throwable $e) {
            $this->disconnect();

            return ['success' => false, 'message' => ErrorSanitizer::sanitize($e->getMessage())];
        }
    }

    public function fetchBatch(GmailAccount $account, int $minUid, int $batchSize): FetchBatch
    {
        $this->connect($account);
        try {
            $this->login($account->authUsername(), (string) $account->password);
            $uidValidity = $this->select($account);
            $uids = $this->searchUids(max(1, $minUid));
            $slice = array_slice($uids, 0, max(1, $batchSize));
            $messages = [];
            $failures = [];

            foreach ($slice as $uid) {
                try {
                    $raw = $this->fetchRfc822($uid);
                    $parsed = $this->parser->parse($raw);
                    $messages[] = new ParsedMessage(
                        uid: $uid,
                        uidValidity: $uidValidity,
                        folder: $account->imapMailbox(),
                        messageId: $parsed->messageId,
                        fromRaw: $parsed->fromRaw,
                        fromEmail: $parsed->fromEmail,
                        subject: $parsed->subject,
                        receivedAt: $parsed->receivedAt,
                        text: $parsed->text,
                        html: $parsed->html,
                        attachments: $parsed->attachments,
                        invoiceLinks: $this->links->detect($parsed->html, $parsed->text),
                    );
                } catch (\Throwable $e) {
                    $failures[] = ['uid' => $uid, 'error' => ErrorSanitizer::sanitize($e->getMessage())];
                }
            }

            return new FetchBatch($uidValidity, $messages, $failures, count($uids) <= count($slice));
        } finally {
            $this->disconnect();
        }
    }

    private function connect(GmailAccount $account): void
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($account->imapStreamHost(), $account->imapPort(), $errno, $errstr, 15);
        if (! $socket) {
            throw new RuntimeException('IMAP kapcsolódás sikertelen ('.$account->imap_host.':'.$account->imapPort().').');
        }

        stream_set_timeout($socket, 20);
        $this->socket = $socket;
        $greeting = $this->readLine();
        if (! str_contains(strtolower($greeting), '* ok')) {
            throw new RuntimeException('Érvénytelen IMAP üdvözlés.');
        }

        if (($account->imap_encryption ?: 'ssl') === 'tls') {
            $this->command('STARTTLS');
            $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                throw new RuntimeException('IMAP STARTTLS sikertelen.');
            }
        }
    }

    private function login(string $username, string $password): void
    {
        if ($password === '') {
            throw new RuntimeException('Hiányzó IMAP hitelesítő adat.');
        }

        $username = str_replace(["\r", "\n"], '', $username);
        $response = $this->command('LOGIN '.$this->quote($username), $password);
        if (! $this->ok($response['lines'])) {
            throw new RuntimeException('IMAP bejelentkezés sikertelen. Ellenőrizd a jelszót és az IMAP beállításokat.');
        }
    }

    private function select(GmailAccount $account): int
    {
        $response = $this->command($account->imapSelectCommand());
        if (! $this->ok($response['lines'])) {
            throw new RuntimeException('Az IMAP mappa nem nyitható meg.');
        }

        foreach ($response['lines'] as $line) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        throw new RuntimeException('Az IMAP szerver nem adott UIDVALIDITY értéket.');
    }

    /**
     * @return list<int>
     */
    private function searchUids(int $minUid): array
    {
        $response = $this->command('UID SEARCH UID '.$minUid.':*');
        $uids = [];
        foreach ($response['lines'] as $line) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', $line, $matches)) {
                foreach (preg_split('/\s+/', trim($matches[1])) ?: [] as $uid) {
                    if ($uid !== '' && ctype_digit($uid)) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }

        $uids = array_values(array_unique($uids));
        sort($uids);

        return $uids;
    }

    private function fetchRfc822(int $uid): string
    {
        $response = $this->command('UID FETCH '.$uid.' (BODY.PEEK[])');
        if (! $this->ok($response['lines'])) {
            throw new RuntimeException('A levél nem olvasható (UID '.$uid.').');
        }

        if ($response['literals'] === []) {
            throw new RuntimeException('A levél törzse üres válasz volt (UID '.$uid.').');
        }

        usort($response['literals'], fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $response['literals'][0];
    }

    /**
     * @return array{lines: list<string>, literals: list<string>}
     */
    private function command(string $command, ?string $literal = null): array
    {
        $tag = 'A'.str_pad((string) $this->tag++, 4, '0', STR_PAD_LEFT);
        if ($literal !== null) {
            fwrite($this->socket, $tag.' '.$command.' {'.strlen($literal)."}\r\n");
            $continuation = $this->readLine();
            if (! str_starts_with($continuation, '+')) {
                throw new RuntimeException('Az IMAP szerver elutasította a parancsot.');
            }
            fwrite($this->socket, $literal."\r\n");
        } else {
            fwrite($this->socket, $tag.' '.$command."\r\n");
        }

        return $this->readUntilTag($tag);
    }

    /**
     * @return array{lines: list<string>, literals: list<string>}
     */
    private function readUntilTag(string $tag): array
    {
        $lines = [];
        $literals = [];
        while (! feof($this->socket)) {
            $line = $this->readLine();
            if ($line === '') {
                break;
            }
            if (preg_match('/\{(\d+)\}\r\n$/', $line, $matches)) {
                $lines[] = rtrim($line, "\r\n");
                $literals[] = $this->readBytes((int) $matches[1]);
                continue;
            }
            $trimmed = rtrim($line, "\r\n");
            $lines[] = $trimmed;
            if (preg_match('/^'.preg_quote($tag, '/').' (OK|NO|BAD)/i', $trimmed)) {
                break;
            }
        }

        return ['lines' => $lines, 'literals' => $literals];
    }

    private function readLine(): string
    {
        $line = '';
        while (! feof($this->socket)) {
            $chunk = fgets($this->socket, 8192);
            if ($chunk === false) {
                break;
            }
            $line .= $chunk;
            if (str_ends_with($line, "\n")) {
                break;
            }
        }

        return $line;
    }

    private function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length && ! feof($this->socket)) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }

        if (strlen($data) !== $length) {
            throw new RuntimeException('Az IMAP válasz csonka maradt.');
        }

        return $data;
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * @param  list<string>  $lines
     */
    private function ok(array $lines): bool
    {
        $last = end($lines);

        return is_string($last) && preg_match('/^A\d+ OK/i', $last) === 1;
    }

    private function disconnect(): void
    {
        if (! is_resource($this->socket)) {
            $this->socket = null;

            return;
        }

        try {
            fwrite($this->socket, "LOGOUT\r\n");
        } catch (\Throwable) {
            // A kapcsolat bontása nem lehet hibaok.
        }
        fclose($this->socket);
        $this->socket = null;
    }
}
