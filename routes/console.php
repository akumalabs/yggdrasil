<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule bandwidth tracking daily at midnight
Schedule::command('bandwidth:track')->daily();

// Schedule backups weekly (Sunday 2 AM) with 7-day retention
Schedule::command('backups:scheduled --retain=7')->weekly()->sundays()->at('02:00');
