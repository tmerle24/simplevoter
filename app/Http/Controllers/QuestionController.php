<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Einreichen einer Frage auf der Public-Seite (Spec Abschnitt 7.1).
     */
    public function store(Request $request, Poll $poll)
    {
        if ($request->filled('website')) {
            abort(422, 'Invalid submission.');
        }

        abort_unless($poll->questions_enabled, 403, 'Questions are disabled for this poll.');

        $nameRule = match ($poll->question_name_mode) {
            'required' => ['required', 'string', 'max:100'],
            'hidden' => ['prohibited'],
            default => ['nullable', 'string', 'max:100'], // optional
        };

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'author_name' => $nameRule,
            'author_token' => ['required', 'string', 'max:64'],
        ]);

        $question = $poll->questions()->create([
            'content' => $validated['content'],
            'author_name' => $poll->question_name_mode === 'hidden'
                ? null
                : ($validated['author_name'] ?? null),
            'author_token' => $validated['author_token'],
        ]);

        $poll->touchActivity();

        return response()->json(['id' => $question->id], 201);
    }

    /**
     * Fragen-Liste fürs Fragen-Panel auf der Public-Seite (Spec Abschnitt 7.2),
     * neueste zuerst. $poll wird hier über public_token aufgelöst.
     */
    public function indexForPublic(Poll $poll)
    {
        return response()->json(
            $poll->questions()
                ->latest()
                ->get()
                ->map(fn ($question) => [
                    'id' => $question->id,
                    'content' => $question->content,
                    'author_name' => $question->author_name,
                    'created_at' => $question->created_at,
                ])
        );
    }

    /**
     * Fragen-Liste für den Owner (Spec Abschnitt 7.2), neueste zuerst.
     * $poll wird hier über manage_token aufgelöst.
     */
    public function indexForOwner(Poll $poll)
    {
        return response()->json(
            $poll->questions()
                ->latest()
                ->get()
                ->map(fn ($question) => [
                    'id' => $question->id,
                    'content' => $question->content,
                    'author_name' => $question->author_name,
                    'created_at' => $question->created_at,
                ])
        );
    }
}
