<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Support\PlayerNameFormatter;
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
        return view('live.all', ['courts' => range(1, 4)]);
    }

    /**
     * Live match on this court, or the most recently finished match until the next one is started.
     * Court field must match control panel, e.g. "1".."4".
     */
    public function courtScore(string $court): JsonResponse
    {
        $match = $this->resolveCourtScoreboardMatch($court);

        if ($match === null) {
            return response()->json([
                'court' => $court,
                'match' => null,
            ]);
        }

        $isLive = $match->status === 'in_progress';

        $stageName = $match->stage?->name;
        $subtitleA = $stageName;
        $subtitleB = $stageName;

        if ($match->tie_id && $match->relationLoaded('tie') && $match->tie) {
            $teamAName = (string) ($match->tie->teamA?->name ?? '');
            $teamBName = (string) ($match->tie->teamB?->name ?? '');
            $playersA = $match->matchPlayers
                ->where('side', 'a')
                ->sortBy('position')
                ->pluck('player_name')
                ->filter()
                ->implode(' / ');
            $playersB = $match->matchPlayers
                ->where('side', 'b')
                ->sortBy('position')
                ->pluck('player_name')
                ->filter()
                ->implode(' / ');

            if ($teamAName !== '' && $playersA !== '') {
                $subtitleA = $teamAName.' — '.$playersA;
            }
            if ($teamBName !== '' && $playersB !== '') {
                $subtitleB = $teamBName.' — '.$playersB;
            }
        }

        [$sideAFlag, $sideBFlag] = $this->resolveHallSideFlags($match);

        $labelA = PlayerNameFormatter::stripRegionalIndicatorFlags((string) $match->side_a_label);
        $labelB = PlayerNameFormatter::stripRegionalIndicatorFlags((string) $match->side_b_label);
        $subA = PlayerNameFormatter::stripRegionalIndicatorFlags((string) ($subtitleA ?? ''));
        $subB = PlayerNameFormatter::stripRegionalIndicatorFlags((string) ($subtitleB ?? ''));

        return response()->json([
            'court' => $court,
            'match' => [
                'is_live' => $isLive,
                'side_a_label' => $labelA,
                'side_b_label' => $labelB,
                'side_a_flag' => $sideAFlag,
                'side_b_flag' => $sideBFlag,
                'best_of' => $match->best_of,
                'status' => $match->status,
                'winner_side' => $match->winner_side,
                'tournament_name' => $match->stage?->event?->tournament?->tournament_name,
                'event_name' => $match->stage?->event?->event_name,
                'stage_name' => $stageName,
                'subtitle_a' => $subA,
                'subtitle_b' => $subB,
                'games' => $match->games->map(fn ($game): array => [
                    'game_number' => $game->game_number,
                    'score_a' => $game->score_a,
                    'score_b' => $game->score_b,
                    'winner_side' => $game->winner_side,
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Prefer the in-progress match; otherwise the latest completed or walkover match still assigned to this court.
     */
    private function resolveCourtScoreboardMatch(string $court): ?MatchModel
    {
        $with = [
            'games' => fn ($query) => $query->orderBy('game_number'),
            'stage.event.tournament',
            'matchPlayers',
            'tie.teamA',
            'tie.teamB',
        ];

        $live = MatchModel::query()
            ->where('court', $court)
            ->where('status', 'in_progress')
            ->with($with)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($live !== null) {
            return $live;
        }

        return MatchModel::query()
            ->where('court', $court)
            ->whereIn('status', ['completed', 'walkover'])
            ->with($with)
            ->orderByDesc('ended_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Country / region flag emoji for the hall scoreboard (left column). Team tie → team flag;
     * otherwise first match player on each side (by position).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveHallSideFlags(MatchModel $match): array
    {
        $flagA = null;
        $flagB = null;

        if ($match->tie_id && $match->relationLoaded('tie') && $match->tie !== null) {
            $flagA = PlayerNameFormatter::normalizeFlag($match->tie->teamA?->flag);
            $flagB = PlayerNameFormatter::normalizeFlag($match->tie->teamB?->flag);
        }

        if ($flagA === null) {
            $firstA = $match->matchPlayers->where('side', 'a')->sortBy('position')->first();
            $flagA = PlayerNameFormatter::normalizeFlag($firstA?->flag);
        }

        if ($flagB === null) {
            $firstB = $match->matchPlayers->where('side', 'b')->sortBy('position')->first();
            $flagB = PlayerNameFormatter::normalizeFlag($firstB?->flag);
        }

        return [$flagA, $flagB];
    }
}
