<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    public function store(Request $request)
    {
        if ($request->filled('website')) {
            abort(422, 'Invalid submission.');
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array', 'max:20', function ($attribute, $value, $fail) {
                if (count($value) === 1) {
                    $fail('Bitte mindestens 2 Antwortoptionen angeben oder gar keine.');
                }
            }],
            'options.*' => ['required', 'string', 'max:200'],
        ]);

        [$event, $poll] = DB::transaction(function () use ($validated, $request) {
            $event = Event::create([]);

            $poll = Poll::create([
                'event_id' => $event->id,
                'question' => $validated['question'],
                'description' => $validated['description'] ?? null,
                'creator_ip' => $request->ip(),
            ]);

            foreach (array_values($validated['options'] ?? []) as $index => $label) {
                $poll->options()->create([
                    'label' => $label,
                    'sort_order' => $index,
                ]);
            }

            $event->update(['active_poll_id' => $poll->id]);

            return [$event, $poll];
        });

        return response()->json([
            'public_token' => $event->public_token,
            'manage_token' => $event->manage_token,
        ], 201);
    }
}
