<?php

use App\Console\Commands\GenerateTagihanHarian;
use App\Console\Commands\KirimDueReminderTagihan;
use App\Console\Commands\KirimReminderSesi;
use App\Console\Commands\TandaiTugasTerlewat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(KirimReminderSesi::class)->dailyAt('07:00');
Schedule::command('finance:reconcile-payments')->hourly()->withoutOverlapping();
Schedule::command(TandaiTugasTerlewat::class)->dailyAt('01:00');
Schedule::command(GenerateTagihanHarian::class)->dailyAt('00:01');
Schedule::command(KirimDueReminderTagihan::class)->dailyAt('08:00');
