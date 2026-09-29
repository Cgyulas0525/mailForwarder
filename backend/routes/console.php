<?php

use App\Services\Rules\ScheduleWindow;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mail:process-accounts')
    ->everyTenMinutes()
    ->timezone('Europe/Budapest')
    ->withoutOverlapping(15)
    ->onOneServer()
    ->when(fn () => app(ScheduleWindow::class)->isOpen());

Schedule::command('mail:prune-messages')
    ->daily()
    ->timezone('Europe/Budapest')
    ->withoutOverlapping()
    ->onOneServer();
