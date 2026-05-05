<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('kilismart:weekly-reminders')->weekdays()->at('09:00');
Schedule::command('kilismart:check-price-expiry')->daily()->at('00:05');
Schedule::command('kilismart:inactive-followup')->weekly()->mondays()->at('08:00');
