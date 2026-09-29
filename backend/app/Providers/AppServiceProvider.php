<?php

namespace App\Providers;

use App\Services\Forward\OutboundMailer;
use App\Services\Forward\SmtpOutboundMailer;
use App\Services\Mail\ImapMailboxClient;
use App\Services\Mail\MailboxClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MailboxClient::class, ImapMailboxClient::class);
        $this->app->bind(OutboundMailer::class, SmtpOutboundMailer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
