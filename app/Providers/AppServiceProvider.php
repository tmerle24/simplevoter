<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Poll;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Resolve {poll} route parameter: check Event tokens first, fall back to Poll tokens.
        // In manage routes (poll.*): check events.manage_token → polls.manage_token
        // In public routes (public.*): check events.public_token → polls.public_token
        // The resolved Event is stored in request attributes as '_event' for controllers.
        Route::bind('poll', function ($value, $route) {
            $name = $route->getName() ?? '';

            if (str_starts_with($name, 'public.')) {
                $event = Event::where('public_token', $value)->first();
                if ($event) {
                    request()->attributes->set('_event', $event);
                    return $event->activePoll ?? abort(404);
                }
                return Poll::where('public_token', $value)->firstOrFail();
            }

            $event = Event::where('manage_token', $value)->first();
            if ($event) {
                request()->attributes->set('_event', $event);
                return $event->activePoll ?? abort(404);
            }
            return Poll::where('manage_token', $value)->firstOrFail();
        });
    }
}
