<?php

use App\Console\Commands\ReconcileNas;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep local NAS records authoritative on RADIUS — runs continuously so the
// two stores can never drift. SRD §4.1 / §4.2.
Schedule::command(ReconcileNas::class)->everyMinute();
