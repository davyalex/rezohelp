<?php

use Illuminate\Support\Facades\Schedule;

// Heure d'Abidjan (fuseau de l'application).
Schedule::command('alertes:recap-quotidien')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('alertes:recap-sms')->dailyAt('19:00')->withoutOverlapping();
Schedule::command('abonnements:traiter')->hourly()->withoutOverlapping();
Schedule::command('mises-en-avant:compter')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('publications:expirer')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('publications:purger')->weeklyOn(0, '03:00')->withoutOverlapping();
// La veille dépose ses fichiers à 00h51, 08h51 et 16h51 (CONTEXTE §12).
foreach (['01:20', '09:20', '17:20'] as $heure) {
    Schedule::command('veille:importer')->dailyAt($heure)->withoutOverlapping();
}
