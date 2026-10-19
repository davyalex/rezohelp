<?php

use Illuminate\Support\Facades\Schedule;

// Heure d'Abidjan (fuseau de l'application).
Schedule::command('alertes:recap-quotidien')->dailyAt('07:00')->withoutOverlapping();
