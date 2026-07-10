<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierre automático de cajas que quedaron abiertas del día anterior.
// Corre a medianoche todos los días.
Schedule::command('caja:cierre-automatico')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cierre-automatico-caja.log'));
