<?php

namespace Tests\Feature;

use App\Models\GmailAccount;
use App\Models\User;
use App\Services\Import\LegacyGmailAccountImporter;
use App\Services\Import\LegacyUserImporter;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_legacy_database_fails_without_secrets(): void
    {
        config(['forwarding.legacy_app_key' => 'base64:'.base64_encode(random_bytes(32))]);
        $connections = config('database.connections');
        $connections['legacy_gmail_eval'] = [
            'driver' => 'mysql',
            'host' => '',
            'database' => 'gmail_eval',
            'username' => null,
            'password' => null,
        ];
        config(['database.connections' => $connections]);
        DB::purge('legacy_gmail_eval');

        try {
            app(LegacyGmailAccountImporter::class)->import();
            $this->fail('Hiányzó forrásnál kivételt kell dobni.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('LEGACY_DB', $e->getMessage());
            $this->assertStringNotContainsString('base64:', $e->getMessage());
        }
    }

    public function test_import_is_idempotent_and_keeps_a_working_password_when_the_source_key_fails(): void
    {
        $legacyKey = 'base64:'.base64_encode(random_bytes(32));
        $path = $this->legacyDatabase();
        $plain = 'regi-alkalmazas-jelszo';
        $cipher = $this->encrypt($plain, $legacyKey);
        DB::connection('legacy_gmail_eval')->table('gmail_accounts')->insert([
            'email' => 'iroda@gmail.com',
            'password' => $cipher,
            'provider' => 'gmail',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_mailbox' => 'INBOX',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'status' => 'error',
        ]);
        config(['forwarding.legacy_app_key' => $legacyKey]);

        $first = app(LegacyGmailAccountImporter::class)->import();
        $account = GmailAccount::query()->first();

        $this->assertSame(1, $first->created);
        $this->assertSame(1, GmailAccount::query()->count());
        $this->assertSame($plain, $account->password);
        $this->assertTrue($account->is_enabled);
        $this->assertSame('error', $account->status);
        $this->assertNotSame('credentials_required', $account->status);

        $second = app(LegacyGmailAccountImporter::class)->import();
        $this->assertSame(0, $second->created);
        $this->assertSame(1, $second->unchanged);
        $this->assertSame(1, GmailAccount::query()->count());

        DB::connection('legacy_gmail_eval')->table('gmail_accounts')->update(['password' => 'nem-fejtheto-vissza']);
        config(['forwarding.legacy_app_key' => 'base64:'.base64_encode(random_bytes(32))]);
        app(LegacyGmailAccountImporter::class)->import();

        $this->assertSame($plain, $account->fresh()->password);
        $this->assertTrue($account->fresh()->is_enabled);
    }

    public function test_missing_or_invalid_legacy_key_imports_public_fields_and_marks_credentials_required(): void
    {
        $this->legacyDatabase();
        DB::connection('legacy_gmail_eval')->table('gmail_accounts')->insert([
            'email' => 'hiany@gmail.com',
            'password' => 'titkos-de-nem-a-mi-kulcsunkkal',
            'provider' => 'gmail',
            'imap_mailbox' => 'INBOX',
            'status' => 'active',
        ]);
        config(['forwarding.legacy_app_key' => null]);

        $result = app(LegacyGmailAccountImporter::class)->import();
        $account = GmailAccount::query()->where('email', 'hiany@gmail.com')->first();

        $this->assertSame(1, $result->credentialsRequired);
        $this->assertFalse($account->is_enabled);
        $this->assertSame('credentials_required', $account->status);
        $this->assertFalse($account->hasStoredPassword());
        $this->assertSame('gmail', $account->provider);

        config(['forwarding.legacy_app_key' => 'rossz-kulcs']);
        DB::connection('legacy_gmail_eval')->table('gmail_accounts')->update(['email' => 'masik@gmail.com']);
        $again = app(LegacyGmailAccountImporter::class)->import();
        $other = GmailAccount::query()->where('email', 'masik@gmail.com')->first();

        $this->assertGreaterThanOrEqual(1, $again->credentialsRequired);
        $this->assertSame('credentials_required', $other->status);
        $this->assertFalse($other->hasStoredPassword());
    }

    public function test_users_are_imported_with_existing_password_hash_and_are_idempotent(): void
    {
        $this->legacyDatabase();
        $hash = Hash::make('regi-jelszo');
        DB::connection('legacy_gmail_eval')->table('users')->insert([
            'name' => 'Iroda',
            'email' => 'iroda@example.com',
            'password' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = app(LegacyUserImporter::class)->import();
        $user = User::query()->where('email', 'iroda@example.com')->first();

        $this->assertSame(1, $first->created);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('regi-jelszo', $user->password));

        $second = app(LegacyUserImporter::class)->import();
        $this->assertSame(0, $second->created);
        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('regi-jelszo', $user->fresh()->password));
    }

    public function test_user_import_requires_legacy_connection(): void
    {
        $connections = config('database.connections');
        $connections['legacy_gmail_eval'] = [
            'driver' => 'mysql',
            'host' => '',
            'database' => 'gmail_eval',
            'username' => null,
            'password' => null,
        ];
        config(['database.connections' => $connections]);
        DB::purge('legacy_gmail_eval');

        try {
            app(LegacyUserImporter::class)->import();
            $this->fail('Hiányzó forrásnál kivételt kell dobni.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('LEGACY_DB', $e->getMessage());
        }
    }

    private function legacyDatabase(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-mail');
        $connections = config('database.connections');
        $connections['legacy_gmail_eval'] = [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
        config(['database.connections' => $connections]);
        DB::purge('legacy_gmail_eval');
        Schema::connection('legacy_gmail_eval')->create('gmail_accounts', function ($table) {
            $table->id();
            $table->string('email');
            $table->text('password')->nullable();
            $table->string('provider')->nullable();
            $table->string('imap_username')->nullable();
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption')->nullable();
            $table->string('imap_mailbox')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('status')->nullable();
        });

        Schema::connection('legacy_gmail_eval')->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        return $path;
    }

    private function encrypt(string $plain, string $key): string
    {
        $raw = base64_decode(substr($key, 7), true);

        return (new Encrypter($raw, 'AES-256-CBC'))->encryptString($plain);
    }
}
