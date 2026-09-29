<?php

namespace Database\Seeders;

use App\Services\Import\LegacyGmailAccountImporter;
use App\Services\Mail\MailboxClient;
use App\Models\GmailAccount;
use Illuminate\Database\Seeder;

class GmailAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(LegacyGmailAccountImporter::class)->import();
        $this->command?->info('Fiókimport: '.$result->summary());

        if (app()->runningUnitTests() || ! config('forwarding.test_connections_after_import')) {
            $this->command?->warn('A kapcsolatteszt ebben a futásban kimaradt.');

            return;
        }

        $client = app(MailboxClient::class);
        GmailAccount::query()->orderBy('id')->each(function (GmailAccount $account) use ($client) {
            if ($account->needsCredentials()) {
                $this->command?->warn($account->email.': hitelesítő adat pótlása szükséges, kapcsolatteszt kihagyva.');

                return;
            }

            $test = $client->testConnection($account);
            $account->forceFill([
                'last_attempt_at' => now(),
                'status' => $test['success'] ? 'active' : 'error',
                'last_error' => $test['success'] ? null : $test['message'],
            ])->save();
            $this->command?->line($account->email.': '.$test['message']);
        });
    }
}
