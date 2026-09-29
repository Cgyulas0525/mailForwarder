<?php

namespace App\Console\Commands;

use App\Models\SourceMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneMessagesCommand extends Command
{
    protected $signature = 'mail:prune-messages';

    protected $description = 'A megőrzési időn túli levéltörzsek és mellékletek törlése. A kézbesítési napló megmarad.';

    public function handle(): int
    {
        $days = max(1, (int) config('forwarding.message_retention_days'));
        $cutoff = now()->subDays($days);
        $count = 0;

        SourceMessage::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) {
                $query->whereNotNull('text_body')->orWhereNotNull('html_body');
            })
            ->with('attachments')
            ->orderBy('id')
            ->chunkById(100, function ($messages) use (&$count) {
                foreach ($messages as $message) {
                    foreach ($message->attachments as $attachment) {
                        if ($attachment->stored_path) {
                            Storage::disk('local')->delete($attachment->stored_path);
                            $attachment->forceFill(['stored_path' => null, 'skipped_reason' => 'Megőrzési idő lejárt'])->save();
                        }
                    }
                    $message->forceFill(['text_body' => null, 'html_body' => null])->save();
                    $count++;
                }
            });

        $this->info('Törzsből kiürített levelek: '.$count);

        return self::SUCCESS;
    }
}
