<?php

namespace App\Http\Controllers;

use App\Mail\ManageLinkMail;
use App\Models\Poll;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PollManageController extends Controller
{
    /**
     * Owner-Ansicht. $poll wird via Route-Model-Binding über manage_token
     * aufgelöst (Route: /p/{poll:manage_token}/edit).
     */
    public function show(Poll $poll)
    {
        return Inertia::render('Poll/Manage', [
            'poll' => $this->ownerPayload($poll),
        ]);
    }

    /**
     * Reload nach LocalStorage-Restore (Spec Abschnitt 2, Schritt 4):
     * Wird per axios abgefragt, wenn die Manage-Seite direkt aufgerufen wird
     * und der Token schon im LocalStorage liegt, ohne vollen Inertia-Visit.
     */
    public function data(Poll $poll)
    {
        return response()->json($this->ownerPayload($poll));
    }

    public function update(Request $request, Poll $poll)
    {
        $validated = $request->validate([
            'question' => ['sometimes', 'required', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'result_visibility' => ['sometimes', 'in:live,after_vote,hidden_until_closed'],
            'question_name_mode' => ['sometimes', 'in:hidden,optional,required'],
            'questions_enabled' => ['sometimes', 'boolean'],
            'allows_multiple_choice' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $poll->update($validated);

        return response()->json($this->ownerPayload($poll));
    }

    /**
     * "Zurücksetzen": löscht alle Stimmen und Fragen dieser Umfrage,
     * Frage und Antwortoptionen selbst bleiben unverändert bestehen.
     */
    public function reset(Poll $poll)
    {
        DB::transaction(function () use ($poll) {
            Vote::whereIn('poll_option_id', $poll->options()->pluck('id'))->delete();
            $poll->questions()->delete();
            $poll->touchActivity();
        });

        return response()->json($this->ownerPayload($poll));
    }

    /**
     * "Per E-Mail sichern" (Spec Abschnitt 2 + 8): reine Bequemlichkeit,
     * keine Registrierung. Schickt den Verwaltungs-Link per Mail zu.
     */
    public function sendManageLink(Request $request, Poll $poll)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Mail::to($validated['email'])->send(new ManageLinkMail($poll));

        return response()->json(['ok' => true]);
    }

    private function ownerPayload(Poll $poll): array
    {
        // Owner bekommt alles inkl. beider Tokens (fürs Teilen-Panel / QR-Code).
        return [
            'id' => $poll->id,
            'public_token' => $poll->public_token,
            'manage_token' => $poll->manage_token,
            'question' => $poll->question,
            'description' => $poll->description,
            'result_visibility' => $poll->result_visibility,
            'question_name_mode' => $poll->question_name_mode,
            'questions_enabled' => $poll->questions_enabled,
            'allows_multiple_choice' => $poll->allows_multiple_choice,
            'is_active' => $poll->is_active,
            'options' => $poll->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'sort_order' => $option->sort_order,
                'vote_count' => $option->votes()->count(),
            ]),
            'questions' => $poll->questions->map(fn ($question) => [
                'id' => $question->id,
                'content' => $question->content,
                'author_name' => $question->author_name,
                'created_at' => $question->created_at,
            ]),
        ];
    }
}
