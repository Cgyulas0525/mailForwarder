<?php

namespace Tests\Feature;

use App\Jobs\ForwardDeliveryJob;
use App\Models\Delivery;
use App\Models\EmailSender;
use App\Models\ForwardRecipient;
use App\Models\ForwardingRule;
use App\Models\GmailAccount;
use App\Models\MailboxSyncState;
use App\Models\SourceMessage;
use App\Models\User;
use App\Services\Forward\UncertainDeliveryException;
use App\Services\Mail\MailboxClient;
use App\Services\Mail\ParsedMessage;
use App\Services\Rules\ForwardingRuleMatcher;
use App\Services\Sync\AccountSyncService;
use App\Services\Sync\ForwardPlanner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ArrayMailboxClient;
use Tests\TestCase;

class ForwardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_sender_matches_and_unknown_without_link_does_not(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $rule = $this->rule($account, 'any', false, [$sender], [$recipient]);
        $known = $this->message($account, 'szamla@pelda.hu', []);
        $unknown = $this->message($account, 'idegen@example.com', [], uid: 2);

        $matcher = app(ForwardingRuleMatcher::class);
        $this->assertTrue($matcher->explain($rule, $known)['matched']);
        $this->assertFalse($matcher->explain($rule, $unknown)['matched']);
    }

    public function test_invoice_link_from_unknown_sender_matches_on_any(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $rule = $this->rule($account, 'any', true, [$sender], [$recipient]);
        $message = $this->message($account, 'idegen@example.com', ['https://www.szamlazz.hu/szamla/fiok/1']);

        $explained = app(ForwardingRuleMatcher::class)->explain($rule, $message);

        $this->assertTrue($explained['matched']);
        $this->assertSame(['invoice_link'], $explained['conditions']);
    }

    public function test_foreign_domain_is_not_a_condition_match(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $rule = $this->rule($account, 'any', true, [], [$recipient]);
        $message = $this->message($account, 'idegen@example.com', []);

        $this->assertFalse(app(ForwardingRuleMatcher::class)->explain($rule->fresh()->load('accounts', 'senders', 'recipients'), $message)['matched']);
    }

    public function test_all_requires_sender_and_link_any_accepts_either(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $onlySender = $this->message($account, 'szamla@pelda.hu', []);
        $any = $this->rule($account, 'any', true, [$sender], [$recipient], 'Vagy');
        $all = $this->rule($account, 'all', true, [$sender], [$recipient], 'És');
        $matcher = app(ForwardingRuleMatcher::class);

        $this->assertTrue($matcher->explain($any, $onlySender)['matched']);
        $this->assertFalse($matcher->explain($all, $onlySender)['matched']);

        $both = $this->message($account, 'szamla@pelda.hu', ['https://szamlazz.hu/szamla/fiok/2'], uid: 3);
        $this->assertTrue($matcher->explain($all, $both)['matched']);
    }

    public function test_empty_rule_and_inactive_parts_do_not_match(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $empty = $this->rule($account, 'any', false, [], [$recipient], 'Üres');
        $message = $this->message($account, 'szamla@pelda.hu', ['https://szamlazz.hu/szamla/fiok/3']);
        $matcher = app(ForwardingRuleMatcher::class);
        $this->assertFalse($matcher->explain($empty, $message)['matched']);

        $sender->update(['is_active' => false]);
        $inactiveSender = $this->rule($account, 'any', false, [$sender->fresh()], [$recipient], 'Inaktív feladó');
        $this->assertFalse($matcher->explain($inactiveSender, $message)['matched']);

        $inactiveRule = $this->rule($account, 'any', true, [$sender], [$recipient], 'Inaktív szabály');
        $inactiveRule->update(['is_active' => false]);
        $this->assertFalse($matcher->explain($inactiveRule->fresh()->load('accounts', 'senders', 'recipients'), $message)['matched']);
    }

    public function test_two_rules_create_one_delivery_for_the_same_recipient(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $message = $this->message($account, 'szamla@pelda.hu', ['https://www.szamlazz.hu/szamla/fiok/4']);
        $this->rule($account, 'any', false, [$sender], [$recipient], 'Feladó');
        $this->rule($account, 'any', true, [], [$recipient], 'Link');

        $created = app(ForwardPlanner::class)->plan($message);
        app(ForwardPlanner::class)->plan($message->fresh());

        $this->assertSame(1, $created);
        $this->assertSame(1, Delivery::query()->count());
        $rules = Delivery::query()->first()->matched_rules;
        $this->assertCount(2, $rules);
        $this->assertSame($recipient->email, $this->mailer->sent[0]->to);
        $this->assertStringStartsWith('Fwd:', $this->mailer->sent[0]->subject);
        $this->assertNotSame('szamla@pelda.hu', $this->mailer->sent[0]->account->email);
    }

    public function test_inactive_recipient_is_not_sent(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $recipient->update(['is_active' => false]);
        $message = $this->message($account, 'szamla@pelda.hu', []);
        $this->rule($account, 'any', false, [$sender], [$recipient->fresh()]);

        $this->assertSame(0, app(ForwardPlanner::class)->plan($message));
        $this->assertSame(0, Delivery::query()->count());
        $this->assertSame([], $this->mailer->sent);
    }

    public function test_sync_keeps_more_than_fifty_messages_and_resumes_after_a_missed_run(): void
    {
        $account = $this->account();
        $client = new ArrayMailboxClient();
        $client->messages = $this->batch($account, 80);
        $this->app->instance(MailboxClient::class, $client);
        config([
            'forwarding.sync_batch_size' => 30,
            'forwarding.sync_overlap' => 0,
            'forwarding.sync_max_per_run' => 500,
        ]);

        $first = app(AccountSyncService::class)->sync($account);
        $this->assertSame(80, $first->stored);
        $this->assertSame(80, SourceMessage::query()->count());
        $this->assertSame(80, MailboxSyncState::query()->first()->last_uid);

        SourceMessage::query()->where('uid', '>', 40)->delete();
        MailboxSyncState::query()->update(['last_uid' => 40]);
        $second = app(AccountSyncService::class)->sync($account->fresh());

        $this->assertSame(40, $second->stored);
        $this->assertSame(80, SourceMessage::query()->count());
        $this->assertSame(80, MailboxSyncState::query()->first()->last_uid);
    }

    public function test_parallel_delivery_is_blocked_by_unique_key_and_lock(): void
    {
        $account = $this->account();
        $message = $this->message($account, 'a@b.hu', []);

        try {
            SourceMessage::query()->create([
                'gmail_account_id' => $account->id,
                'folder' => 'INBOX',
                'uidvalidity' => 100,
                'uid' => 1,
                'from_email' => 'masik@b.hu',
            ]);
            $this->fail('A duplikált UID-nek el kell hasalnia.');
        } catch (QueryException) {
            $this->assertSame(1, SourceMessage::query()->count());
        }

        $lock = Cache::lock('delivery-'.$message->id, 10);
        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::lock('delivery-'.$message->id, 10)->get());
        $lock->release();
    }

    public function test_queue_retries_a_failed_delivery_but_not_an_uncertain_one(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $message = $this->message($account, 'szamla@pelda.hu', []);
        $this->rule($account, 'any', false, [$sender], [$recipient]);
        $this->mailer->throw = new \RuntimeException('450 temporary');

        try {
            app(ForwardPlanner::class)->plan($message);
            $this->fail('Az újrapróbálható hiba kivételt dob.');
        } catch (\RuntimeException) {
        }

        $delivery = Delivery::query()->first();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame([15, 60, 180], (new ForwardDeliveryJob($delivery->id))->backoff());

        $this->mailer->throw = null;
        (new ForwardDeliveryJob($delivery->id))->handle($this->mailer, app(\App\Services\Forward\ForwardMessageFactory::class));
        $this->assertSame('sent', $delivery->fresh()->status);

        $unknown = Delivery::query()->create([
            'source_message_id' => $message->id,
            'forward_recipient_id' => null,
            'to_email' => 'masik@example.com',
            'status' => 'pending',
            'matched_rules' => [],
        ]);
        $this->mailer->throw = new UncertainDeliveryException('Connection timed out');
        $this->mailer->sent = [];
        (new ForwardDeliveryJob($unknown->id))->handle($this->mailer, app(\App\Services\Forward\ForwardMessageFactory::class));
        $this->assertSame('delivery_unknown', $unknown->fresh()->status);
        $sentBefore = count($this->mailer->sent);
        (new ForwardDeliveryJob($unknown->id))->handle($this->mailer, app(\App\Services\Forward\ForwardMessageFactory::class));
        $this->assertCount($sentBefore, $this->mailer->sent);
    }

    public function test_rule_preview_does_not_send(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $rule = $this->rule($account, 'any', true, [$sender], [$recipient]);
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/rules/preview', [
            'rule_id' => $rule->id,
            'sample' => [
                'from' => 'Idegen <idegen@example.com>',
                'html' => '<a href="https://www.szamlazz.hu/szamla/fiok/5">Letöltöm a számlát</a>',
                'gmail_account_id' => $account->id,
            ],
        ])->assertOk()
            ->assertJsonPath('would_send', false)
            ->assertJsonPath('data.0.matched', true);

        $this->assertSame(0, Delivery::query()->count());
        $this->assertSame([], $this->mailer->sent);
    }

    public function test_account_api_never_returns_the_password(): void
    {
        $account = $this->account();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $response = $this->getJson('/api/accounts');
        $response->assertOk();
        $this->assertStringNotContainsString('secret-app-password', $response->getContent());
        $this->assertTrue($response->json('data.0.has_password'));
    }

    public function test_crud_show_endpoints_return_one_record(): void
    {
        [$account, $sender, $recipient] = $this->parties();
        $rule = $this->rule($account, 'any', false, [$sender], [$recipient]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $accountShow = $this->getJson('/api/accounts/'.$account->id);
        $accountShow->assertOk()->assertJsonPath('data.email', 'fiok@gmail.com');
        $this->assertStringNotContainsString('secret-app-password', $accountShow->getContent());

        $this->getJson('/api/senders/'.$sender->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'szamla@pelda.hu');

        $this->getJson('/api/recipients/'.$recipient->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'iroda@example.com');

        $this->getJson('/api/rules/'.$rule->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Szabály')
            ->assertJsonPath('data.accounts.0.email', 'fiok@gmail.com');

        $this->getJson('/api/accounts/99999')->assertNotFound();
    }

    public function test_admin_can_update_profile(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'name' => 'Régi Név',
            'email' => 'regi@example.com',
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/me', [
            'name' => 'Új Név',
            'email' => 'uj@example.com',
            'password' => 'ujjelszo8',
            'password_confirmation' => 'ujjelszo8',
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonPath('name', 'Új Név')
            ->assertJsonPath('email', 'uj@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Új Név',
            'email' => 'uj@example.com',
        ]);
        $this->assertTrue(Hash::check('ujjelszo8', $admin->fresh()->password));
    }

    public function test_disabled_account_is_not_checked(): void
    {
        $account = $this->account();
        $account->update(['is_enabled' => false]);
        $client = new ArrayMailboxClient();
        $client->messages = $this->batch($account, 3);
        $this->app->instance(MailboxClient::class, $client);

        $result = app(AccountSyncService::class)->sync($account->fresh());

        $this->assertSame(0, $result->stored);
        $this->assertSame(0, $client->calls);
    }

    public function test_schedule_runs_every_ten_minutes(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'mail:process-accounts'));

        $this->assertNotNull($event);
        $this->assertSame('*/10 * * * *', $event->expression);
        $timezone = $event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : $event->timezone;
        $this->assertSame('Europe/Budapest', $timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_database_seeder_does_not_import_accounts(): void
    {
        $this->seed();

        $this->assertSame(0, GmailAccount::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    /**
     * @return array{0: GmailAccount, 1: EmailSender, 2: ForwardRecipient}
     */
    private function parties(): array
    {
        return [$this->account(), $this->sender(), $this->recipient()];
    }

    private function account(): GmailAccount
    {
        return GmailAccount::query()->create([
            'email' => 'fiok@gmail.com',
            'password' => 'secret-app-password',
            'is_enabled' => true,
            'status' => 'active',
            'provider' => 'gmail',
        ]);
    }

    private function sender(): EmailSender
    {
        return EmailSender::query()->create([
            'name' => 'Számlázó',
            'email' => 'szamla@pelda.hu',
            'is_active' => true,
        ]);
    }

    private function recipient(): ForwardRecipient
    {
        return ForwardRecipient::query()->create([
            'name' => 'Iroda',
            'email' => 'iroda@example.com',
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<EmailSender>  $senders
     * @param  list<ForwardRecipient>  $recipients
     */
    private function rule(GmailAccount $account, string $mode, bool $link, array $senders, array $recipients, string $name = 'Szabály'): ForwardingRule
    {
        $rule = ForwardingRule::query()->create([
            'name' => $name,
            'is_active' => true,
            'match_mode' => $mode,
            'checks_invoice_link' => $link,
        ]);
        $rule->accounts()->sync([$account->id]);
        $rule->senders()->sync(collect($senders)->pluck('id'));
        $rule->recipients()->sync(collect($recipients)->pluck('id'));

        return $rule->fresh()->load('accounts', 'senders', 'recipients');
    }

    /**
     * @param  list<string>  $links
     */
    private function message(GmailAccount $account, string $from, array $links, int $uid = 1): SourceMessage
    {
        return SourceMessage::query()->create([
            'gmail_account_id' => $account->id,
            'folder' => 'INBOX',
            'uidvalidity' => 100,
            'uid' => $uid,
            'from_email' => $from,
            'from_raw' => $from,
            'subject' => 'Teszt '.$uid,
            'text_body' => 'szöveg',
            'invoice_links' => $links,
            'received_at' => now(),
        ]);
    }

    /**
     * @return list<ParsedMessage>
     */
    private function batch(GmailAccount $account, int $count): array
    {
        $messages = [];
        for ($uid = 1; $uid <= $count; $uid++) {
            $messages[] = new ParsedMessage(
                uid: $uid,
                uidValidity: 100,
                folder: 'INBOX',
                messageId: 'm'.$uid.'@local',
                fromRaw: 'Kuldo <kuldo@example.com>',
                fromEmail: 'kuldo@example.com',
                subject: 'Levél '.$uid,
                receivedAt: now(),
                text: 'törzs',
                html: '',
                attachments: [],
                invoiceLinks: [],
            );
        }

        return $messages;
    }
}
