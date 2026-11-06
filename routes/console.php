<?php

use Illuminate\Support\Facades\Schedule;

// Durée de conservation : données faciales puis photos des événements expirés.
Schedule::command('events:purge-expired')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
