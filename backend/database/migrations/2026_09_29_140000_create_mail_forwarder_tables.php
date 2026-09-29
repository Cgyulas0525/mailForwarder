<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('gmail_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->text('password')->nullable();
            $table->string('provider')->default('gmail');
            $table->string('imap_username')->nullable();
            $table->string('imap_host')->default('imap.gmail.com');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');
            $table->string('imap_mailbox')->default('INBOX');
            $table->string('smtp_host')->default('smtp.gmail.com');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption')->default('tls');
            $table->boolean('is_enabled')->default(false);
            $table->string('status')->default('pending');
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('email_senders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('forward_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('forwarding_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('match_mode')->default('any');
            $table->boolean('checks_invoice_link')->default(false);
            $table->timestamps();
        });

        Schema::create('forwarding_rule_account', function (Blueprint $table) {
            $table->foreignId('forwarding_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gmail_account_id')->constrained()->cascadeOnDelete();
            $table->primary(['forwarding_rule_id', 'gmail_account_id']);
        });

        Schema::create('forwarding_rule_sender', function (Blueprint $table) {
            $table->foreignId('forwarding_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_sender_id')->constrained()->cascadeOnDelete();
            $table->primary(['forwarding_rule_id', 'email_sender_id']);
        });

        Schema::create('forwarding_rule_recipient', function (Blueprint $table) {
            $table->foreignId('forwarding_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forward_recipient_id')->constrained()->cascadeOnDelete();
            $table->primary(['forwarding_rule_id', 'forward_recipient_id']);
        });

        Schema::create('mailbox_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder');
            $table->unsignedBigInteger('uidvalidity')->nullable();
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->timestamps();
            $table->unique(['gmail_account_id', 'folder']);
        });

        Schema::create('source_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder');
            $table->unsignedBigInteger('uidvalidity');
            $table->unsignedBigInteger('uid');
            $table->string('message_id')->nullable();
            $table->string('from_raw')->nullable();
            $table->string('from_email')->nullable()->index();
            $table->string('subject')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->json('invoice_links')->nullable();
            $table->timestamps();
            $table->unique(['gmail_account_id', 'folder', 'uidvalidity', 'uid'], 'source_messages_imap_uid_unique');
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_message_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->string('stored_path')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forward_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->json('matched_rules')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['source_message_id', 'forward_recipient_id']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('source_messages');
        Schema::dropIfExists('mailbox_sync_states');
        Schema::dropIfExists('forwarding_rule_recipient');
        Schema::dropIfExists('forwarding_rule_sender');
        Schema::dropIfExists('forwarding_rule_account');
        Schema::dropIfExists('forwarding_rules');
        Schema::dropIfExists('forward_recipients');
        Schema::dropIfExists('email_senders');
        Schema::dropIfExists('gmail_accounts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
