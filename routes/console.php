<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('comprobantes:extraer --limit=10')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notificaciones:procesar --limit=50')
    ->everyMinute()
    ->withoutOverlapping();
