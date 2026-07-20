<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\Vote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicPollController extends Controller
{
    /**
     * $poll wird via Route-Model-Binding über public_token aufgelöst
     * (Route: /w/{poll:public_token}). manage_token wird hier NIEMALS
     * mit ausgeliefert.
     */
    public function show(Request $request, Poll $poll)
    {
        return Inertia::render('Poll/Public', [
            'poll' => $this->pollShape($poll),
            'initialState' => $this->stateShape($poll, $request->query('voter_token')),
            'publicToken' => $poll->public_token,
        ]);
    }

    /**
     * Einziger Polling-Endpoint (Spec Abschnitt 5), liefert Vote-Ergebnisse
     * und Fragen-Badge-Info in einem Request. Wird alle 5-8s vom Frontend
     * abgefragt.
     */
    public function state(Request $request, Poll $poll)
    {
        return response()->json(
            $this->stateShape($poll, $request->query('voter_token'))
        );
    }

    private function pollShape(Poll $poll): array
    {
        return [
            'question' => $poll->question,
            'description' => $poll->description,
            'question_name_mode' => $poll->question_name_mode,
            'questions_enabled' => $poll->questions_enabled,
            'allows_multiple_choice' => $poll->allows_multiple_choice,
            'result_visibility' => $poll->result_visibility,
            'is_active' => $poll->is_active,
        ];
    }

    private function stateShape(Poll $poll, ?string $voterToken): array
    {
        $optionIds = $poll->options->pluck('id');

        $hasVoted = $voterToken
            ? Vote::where('voter_token', $voterToken)
                ->whereIn('poll_option_id', $optionIds)
                ->exists()
            : false;

        $showCounts = match ($poll->result_visibility) {
            'live' => true,
            'after_vote' => $hasVoted,
            'hidden_until_closed' => ! $poll->is_active,
        };

        return [
            'poll' => [
                'question' => $poll->question,
                'result_visibility' => $poll->result_visibility,
                'allows_multiple_choice' => $poll->allows_multiple_choice,
                'is_active' => $poll->is_active,
            ],
            'options' => $poll->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'vote_count' => $showCounts ? $option->votes()->count() : null,
            ]),
            'has_voted' => $hasVoted,
            'latest_question_id' => optional($poll->questions->first())->id,
            'question_count_total' => $poll->questions->count(),
        ];
    }
}
