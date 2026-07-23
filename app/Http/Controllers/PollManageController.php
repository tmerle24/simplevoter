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
    public function show(Poll $poll)
    {
        return Inertia::render('Poll/Manage', [
            'poll' => $this->ownerPayload($poll),
        ]);
    }

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

    public function reset(Poll $poll)
    {
        DB::transaction(function () use ($poll) {
            Vote::whereIn('poll_option_id', $poll->options()->pluck('id'))->delete();
            $poll->questions()->delete();
            $poll->touchActivity();
        });

        return response()->json($this->ownerPayload($poll));
    }

    public function sendManageLink(Request $request, Poll $poll)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $event = request()->attributes->get('_event');
        $manageToken = $event ? $event->manage_token : $poll->manage_token;

        try {
            Mail::to($validated['email'])->send(new ManageLinkMail($poll, $manageToken));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ManageLinkMail fehlgeschlagen', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'E-Mail konnte nicht gesendet werden.'], 500);
        }

        return response()->json(['ok' => true]);
    }

    public function addPoll(Request $request, Poll $poll)
    {
        $event = request()->attributes->get('_event');
        abort_unless($event, 403, 'Not an event context.');

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*' => ['required', 'string', 'max:200'],
        ]);

        $newPoll = DB::transaction(function () use ($event, $validated, $request) {
            $newPoll = Poll::create([
                'event_id' => $event->id,
                'question' => $validated['question'],
                'description' => $validated['description'] ?? null,
                'creator_ip' => $request->ip(),
            ]);

            foreach (array_values($validated['options'] ?? []) as $index => $label) {
                $newPoll->options()->create(['label' => $label, 'sort_order' => $index]);
            }

            $event->update(['active_poll_id' => $newPoll->id]);

            return $newPoll;
        });

        return response()->json($this->ownerPayload($newPoll));
    }

    public function activatePoll(Request $request, Poll $poll, int $pid)
    {
        $event = request()->attributes->get('_event');
        abort_unless($event, 403, 'Not an event context.');

        $target = Poll::where('id', $pid)->where('event_id', $event->id)->firstOrFail();
        $event->update(['active_poll_id' => $target->id]);

        return response()->json($this->ownerPayload($target));
    }

    public function detachPoll(Request $request, Poll $poll, int $pid)
    {
        $event = request()->attributes->get('_event');
        abort_unless($event, 403, 'Not an event context.');

        $target = Poll::where('id', $pid)->where('event_id', $event->id)->firstOrFail();
        abort_if($event->polls()->count() <= 1, 422, 'Cannot detach the only poll from an event.');

        if ($event->active_poll_id === $target->id) {
            $next = $event->polls()->where('id', '!=', $target->id)->first();
            $event->update(['active_poll_id' => $next->id]);
        }

        $target->update(['event_id' => null]);
        $event->refresh();

        $activePoll = Poll::find($event->active_poll_id);
        return response()->json($this->ownerPayload($activePoll));
    }

    public function destroyPoll(Request $request, Poll $poll, int $pid)
    {
        $event = request()->attributes->get('_event');
        abort_unless($event, 403, 'Not an event context.');

        $target = Poll::where('id', $pid)->where('event_id', $event->id)->firstOrFail();
        abort_if($event->polls()->count() <= 1, 422, 'Cannot delete the only poll in an event.');

        if ($event->active_poll_id === $target->id) {
            $next = $event->polls()->where('id', '!=', $target->id)->first();
            $event->update(['active_poll_id' => $next->id]);
        }

        $target->delete();
        $event->refresh();

        $activePoll = Poll::find($event->active_poll_id);
        return response()->json($this->ownerPayload($activePoll));
    }

    public function updateEventName(Request $request, Poll $poll)
    {
        $event = request()->attributes->get('_event');
        abort_unless($event, 403, 'Not an event context.');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
        ]);

        $event->update(['name' => $validated['name'] ?? null]);

        return response()->json($this->ownerPayload($poll));
    }

    private function ownerPayload(Poll $poll): array
    {
        $event = request()->attributes->get('_event');

        // Reload event to get fresh polls list and active_poll_id after mutations
        if ($event) {
            $event->refresh();
            $event->load('polls');
        }

        return [
            'id' => $poll->id,
            // In event context: use event tokens for all frontend API calls
            'public_token' => $event ? $event->public_token : $poll->public_token,
            'manage_token' => $event ? $event->manage_token : $poll->manage_token,
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
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'polls' => $event->polls->map(fn ($p) => [
                    'id' => $p->id,
                    'question' => $p->question,
                    'is_active' => $p->id === $event->active_poll_id,
                ]),
            ] : null,
        ];
    }
}
