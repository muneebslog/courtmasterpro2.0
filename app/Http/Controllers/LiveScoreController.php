<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class LiveScoreController extends Controller
{
    public function courtView(string $court): View
    {
        return view('live.court', ['court' => $court]);
    }

    public function allView(): View
    {
        return view('live.all', ['courts' => range(1, 5)]);
    }

    /**
     * Current in-progress match on this court (court field must match control panel, e.g. "1".."5").
     */
    public function courtScore(string $court): JsonResponse
    {
        $match = MatchModel::query()
            ->where('court', $court)
            ->where('status', 'in_progress')
            ->with([
                'games' => fn ($query) => $query->orderBy('game_number'),
                'stage.event',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($match === null) {
            return response()->json([
                'court' => $court,
                'match' => null,
            ]);
        }

        return response()->json([
            'court' => $court,
            'match' => [
                'side_a_label' => $match->side_a_label,
                'side_b_label' => $match->side_b_label,
                'best_of' => $match->best_of,
                'status' => $match->status,
                'winner_side' => $match->winner_side,
                'event_name' => $match->stage?->event?->event_name,
                'stage_name' => $match->stage?->name,
                'games' => $match->games->map(fn ($game): array => [
                    'game_number' => $game->game_number,
                    'score_a' => $game->score_a,
                    'score_b' => $game->score_b,
                    'winner_side' => $game->winner_side,
                ])->values()->all(),
            ],
        ]);
    }
}
