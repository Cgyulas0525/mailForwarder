<?php

namespace App\Console\Commands;

use App\Models\GmailAccount;
use App\Services\Rules\ScheduleWindow;
use App\Services\Sync\AccountSyncService;
use Illuminate\Console\Command;

class ProcessAccountsCommand extends Command
{
    protected $signature = 'mail:process-accounts {--force : Időablakon kívül is fusson}';

    protected $description = 'Engedélyezett postafiókok új leveleinek ellenőrzése és továbbítása';

    public function handle(AccountSyncService $sync, ScheduleWindow $window): int
    {
        if (! $this->option('force') && ! $window->isOpen()) {
            $this->info('Az ellenőrzési időablak zárva van.');

            return self::SUCCESS;
        }

        $accounts = GmailAccount::query()->where('is_enabled', true)->orderBy('id')->get();
        foreach ($accounts as $account) {
            if ($account->needsCredentials()) {
                $this->warn($account->email.': hitelesítő adat hiányzik, kihagyva.');

                continue;
            }

            try {
                $result = $sync->sync($account);
                $this->info(sprintf(
                    '%s: tárolva %d, hiba %d, továbbítás %d%s',
                    $account->email,
                    $result->stored,
                    $result->failed,
                    $result->planned,
                    $result->error ? ' ('.$result->error.')' : ''
                ));
            } catch (\Throwable $e) {
                $this->error($account->email.': a fiók feldolgozása megállt, a többi folytatódik.');
            }
        }

        return self::SUCCESS;
    }
}
