<?php

use Illuminate\Support\Facades\Schedule;

// Heure d'Abidjan (fuseau de l'application).
Schedule::command('alertes:recap-quotidien')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('alertes:recap-sms')->dailyAt('19:00')->withoutOverlapping();
Schedule::command('abonnements:traiter')->hourly()->withoutOverlapping();
Schedule::command('mises-en-avant:compter')->everyFiveMinutes()->withoutOverlapping();
