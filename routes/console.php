<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('contracts:expire')->everyTenMinutes()->withoutOverlapping();
Schedule::command('contracts:remind')->hourly()->withoutOverlapping();
Schedule::command('pec:import-receipts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('evidence:purge-expired')->dailyAt('02:20')->withoutOverlapping();
