<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    /**
     * Zero-Friction-Erstellung (Spec Abschnitt 2):
     * Erster Submit auf der Landing-Page erzeugt implizit Poll + PollOptions.
     * Response liefert manage_token + public_token; das Frontend speichert
     * manage_token in LocalStorage und leitet zur Manage-Seite weiter.
     */
    public function store(Request $request)
    {
        // Honeypot-Feld (Spec Abschnitt 10, Anti-Spam). Menschen sehen/befüllen
        // dieses Feld nicht, Bots häufig schon.
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

        $poll = DB::transaction(function () use ($validated, $request) {
            $poll = Poll::create([
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

            return $poll;
        });

        return response()->json([
            'public_token' => $poll->public_token,
            'manage_token' => $poll->manage_token,
        ], 201);
    }
}
