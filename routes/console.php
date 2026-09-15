<?php

use App\Models\Event;
use App\Models\Poll;
use Illuminate\Support\Facades\Schedule;

// Löschkonzept (Spec Abschnitt 10): Polls, deren last_activity_at älter
// als 90 Tage ist, werden täglich gelöscht. Cascade-Delete über die
// foreign-key-Constraints entfernt poll_options, votes und questions
// automatisch mit.
Schedule::call(function () {
    Poll::where('last_activity_at', '<', now()->subDays(90))->each->delete();

    // Events ohne Polls aufräumen (inkl. Branding-Logo über deleting-Hook)
    Event::doesntHave('polls')->where('created_at', '<', now()->subDay())->each->delete();
})->daily();
