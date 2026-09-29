<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeOutboundMailer;
use App\Services\Forward\OutboundMailer;

abstract class TestCase extends BaseTestCase
{
    protected FakeOutboundMailer $mailer;

    public function createApplication()
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array',
            'FORWARD_LIVE_SMTP' => 'false',
        ] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new FakeOutboundMailer();
        $this->app->instance(OutboundMailer::class, $this->mailer);
    }
}
