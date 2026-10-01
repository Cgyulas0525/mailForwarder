<?php

namespace App\Services\Sync;

use App\Models\GmailAccount;
use App\Models\MailboxSyncState;
use App\Models\MessageAttachment;
use App\Models\SourceMessage;
use App\Services\Mail\MailboxClient;
use App\Services\Mail\ParsedMessage;
use App\Support\ErrorSanitizer;
use App\Support\Utf8Text;
use Illuminate\Support\Facades\Storage;

class AccountSyncService
{
    public function __construct(
        private MailboxClient $client,
        private ForwardPlanner $planner,
    ) {}

    public function sync(GmailAccount $account): SyncResult
    {
        $result = new SyncResult();
        if (! $account->is_enabled || $account->needsCredentials()) {
            return $result;
        }

        $account->forceFill(['last_attempt_at' => now()])->save();
        $folder = $account->imapMailbox();
        $state = MailboxSyncState::query()->firstOrCreate(
            ['gmail_account_id' => $account->id, 'folder' => $folder],
            ['last_uid' => 0]
        );

        $batchSize = max(1, (int) config('forwarding.sync_batch_size'));
        $max = max(1, (int) config('forwarding.sync_max_per_run'));
        $overlap = max(0, (int) config('forwarding.sync_overlap'));
        $seen = 0;
        $forceMin = null;

        try {
            while ($seen < $max) {
                $state->refresh();
                $minUid = $forceMin ?? ($state->last_uid > 0
                    ? max(1, $state->last_uid - $overlap + 1)
                    : 1);
                $batch = $this->client->fetchBatch($account, $minUid, $batchSize);

                if ($state->uidvalidity && $state->uidvalidity !== $batch->uidValidity) {
                    $state->forceFill(['uidvalidity' => $batch->uidValidity, 'last_uid' => 0])->save();

                    continue;
                }

                if (! $state->uidvalidity) {
                    $state->forceFill(['uidvalidity' => $batch->uidValidity])->save();
                }

                $events = [];
                foreach ($batch->messages as $message) {
                    $events[$message->uid] = ['ok' => $message];
                }
                foreach ($batch->failures as $failure) {
                    $events[$failure['uid']] = ['fail' => $failure['error']];
                }
                ksort($events);

                $cursor = (int) $state->last_uid;
                $blocked = false;
                $advanced = false;
                foreach ($events as $uid => $event) {
                    if (isset($event['fail'])) {
                        $blocked = true;
                        $result->failed++;
                        continue;
                    }

                    /** @var ParsedMessage $parsed */
                    $parsed = $event['ok'];
                    try {
                        $stored = $this->store($account, $parsed);
                    } catch (\Throwable) {
                        $result->failed++;
                        if ($uid > $cursor) {
                            $cursor = $uid;
                            $advanced = true;
                        }
                        $seen++;
                        continue;
                    }
                    if ($stored['created']) {
                        $result->stored++;
                        $result->planned += $this->planner->plan($stored['message']);
                    }
                    if (! $blocked && $uid > $cursor) {
                        $cursor = $uid;
                        $advanced = true;
                    }
                    $seen++;
                }

                if ($advanced) {
                    $state->forceFill(['last_uid' => $cursor, 'uidvalidity' => $batch->uidValidity])->save();
                    $forceMin = null;
                } elseif (! $batch->exhausted) {
                    $next = max($minUid + 1, (int) $state->last_uid + 1);
                    if ($next <= $minUid) {
                        break;
                    }
                    $forceMin = $next;
                }

                if ($batch->exhausted || $events === []) {
                    break;
                }
            }

            $account->forceFill([
                'status' => 'active',
                'last_fetched_at' => now(),
                'last_error' => $result->failed > 0 ? 'Néhány levél feldolgozása sikertelen, a kurzor a hibánál maradt.' : null,
            ])->save();
        } catch (\Throwable $e) {
            $result->error = ErrorSanitizer::sanitize($e->getMessage());
            $account->forceFill([
                'status' => 'error',
                'last_error' => $result->error,
            ])->save();
        }

        return $result;
    }

    /**
     * @return array{created: bool, message: SourceMessage}
     */
    private function store(GmailAccount $account, ParsedMessage $parsed): array
    {
        $message = SourceMessage::query()->firstOrCreate(
            [
                'gmail_account_id' => $account->id,
                'folder' => $parsed->folder,
                'uidvalidity' => $parsed->uidValidity,
                'uid' => $parsed->uid,
            ],
            [
                'message_id' => Utf8Text::sanitize($parsed->messageId),
                'from_raw' => Utf8Text::sanitize($parsed->fromRaw),
                'from_email' => Utf8Text::sanitize($parsed->fromEmail),
                'subject' => Utf8Text::sanitize($parsed->subject),
                'received_at' => $parsed->receivedAt,
                'text_body' => Utf8Text::sanitize($parsed->text) ?: null,
                'html_body' => Utf8Text::sanitize($parsed->html) ?: null,
                'invoice_links' => $parsed->invoiceLinks,
            ]
        );

        if ($message->wasRecentlyCreated) {
            $this->storeAttachments($message, $parsed);
        }

        return ['created' => $message->wasRecentlyCreated, 'message' => $message];
    }

    private function storeAttachments(SourceMessage $message, ParsedMessage $parsed): void
    {
        $limit = (int) config('forwarding.max_attachment_bytes');
        foreach ($parsed->attachments as $attachment) {
            $size = $attachment->size();
            if ($size > $limit) {
                MessageAttachment::query()->create([
                    'source_message_id' => $message->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mimeType,
                    'size_bytes' => $size,
                    'skipped_reason' => 'Méretkorlát felett',
                ]);

                continue;
            }

            $path = 'messages/'.$message->id.'/'.$attachment->filename;
            Storage::disk('local')->put($path, $attachment->content);
            MessageAttachment::query()->create([
                'source_message_id' => $message->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mimeType,
                'size_bytes' => $size,
                'stored_path' => $path,
            ]);
        }
    }
}
