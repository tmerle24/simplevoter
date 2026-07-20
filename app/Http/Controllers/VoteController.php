<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    /**
     * $poll wird via Route-Model-Binding über public_token aufgelöst
     * (Route: /w/{poll:public_token}/vote).
     */
    public function store(Request $request, Poll $poll)
    {
        if ($request->filled('website')) {
            abort(422, 'Invalid submission.');
        }

        abort_unless($poll->is_active, 410, 'Diese Umfrage ist geschlossen.');

        $validated = $request->validate([
            'poll_option_id' => ['required', 'integer'],
            'voter_token' => ['required', 'string', 'max:64'],
        ]);

        $option = $poll->options()->where('id', $validated['poll_option_id'])->firstOrFail();
        $voterToken = $validated['voter_token'];

        DB::transaction(function () use ($poll, $option, $voterToken) {
            if (! $poll->allows_multiple_choice) {
                // Single-Choice (Spec Abschnitt 4): vorherige Stimme dieser
                // Person für DIESE Umfrage entfernen, bevor die neue gesetzt wird
                // ("Auswahl ändern" ersetzt vorherige Stimme).
                Vote::where('voter_token', $voterToken)
                    ->whereIn('poll_option_id', $poll->options()->pluck('id'))
                    ->delete();
            }

            // firstOrCreate respektiert den unique(['poll_option_id','voter_token'])
            // Constraint und verhindert Doppel-Votes auf dieselbe Option
            // (z.B. Doppelklick).
            Vote::firstOrCreate([
                'poll_option_id' => $option->id,
                'voter_token' => $voterToken,
            ]);

            $poll->touchActivity();
        });

        // Frontend fragt nach erfolgreichem Vote direkt /w/{publicToken}/state
        // neu ab (derselbe Endpoint wie beim Live-Polling) statt dass wir hier
        // die Ergebnis-Logik duplizieren.
        return response()->json(['ok' => true]);
    }
}
