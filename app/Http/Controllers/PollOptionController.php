<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Http\Request;

class PollOptionController extends Controller
{
    public function store(Request $request, Poll $poll)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:200'],
        ]);

        $nextSortOrder = ($poll->options()->max('sort_order') ?? -1) + 1;

        $option = $poll->options()->create([
            'label' => $validated['label'],
            'sort_order' => $nextSortOrder,
        ]);

        return response()->json([
            'id' => $option->id,
            'label' => $option->label,
            'sort_order' => $option->sort_order,
            'vote_count' => 0,
        ], 201);
    }

    public function update(Request $request, Poll $poll, PollOption $option)
    {
        $this->authorizeOptionBelongsToPoll($poll, $option);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:200'],
        ]);

        $option->update($validated);

        return response()->json([
            'id' => $option->id,
            'label' => $option->label,
            'sort_order' => $option->sort_order,
        ]);
    }

    public function destroy(Poll $poll, PollOption $option)
    {
        $this->authorizeOptionBelongsToPoll($poll, $option);

        $option->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Drag&Drop-Reorder: erwartet ein Array von Option-IDs in der neuen
     * Reihenfolge, z.B. { "order": [3, 1, 2] }.
     */
    public function reorder(Request $request, Poll $poll)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:poll_options,id'],
        ]);

        $optionIds = $poll->options()->pluck('id')->all();
        foreach ($validated['order'] as $id) {
            abort_unless(in_array($id, $optionIds, true), 403);
        }

        foreach ($validated['order'] as $index => $id) {
            PollOption::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }

    private function authorizeOptionBelongsToPoll(Poll $poll, PollOption $option): void
    {
        abort_unless($option->poll_id === $poll->id, 403);
    }
}
