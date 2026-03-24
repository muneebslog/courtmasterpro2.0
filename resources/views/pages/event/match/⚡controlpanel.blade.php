<?php

use App\Models\Event;
use App\Models\Game;
use App\Models\MatchEvent;
use App\Models\MatchModel;
use App\Models\MatchPlayer;
use App\Models\Stage;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TieResultService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public int $matchId;

    public bool $showPreStartModal = false;

    public string $umpireName = '';

    public string $serviceJudgeName = '';

    public string $court = '';

    /** @var array<string, int|null> Player ID per slot: side_a_1, side_a_2, side_b_1, side_b_2 */
    public array $selectedPlayerIds = [
        'side_a_1' => null,
        'side_a_2' => null,
        'side_b_1' => null,
        'side_b_2' => null,
    ];

    public string $occurrenceType = 'card';

    public string $cardType = 'yellow';

    public string $occurrenceSide = 'a';

    public ?int $occurrencePlayerId = null;

    public function mount(int $tournament, int $event, int $stage, int $match): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;
        $this->stageId = $stage;
        $this->matchId = $match;

        $tournamentModel = $this->tournament();
        $eventModel = $this->event();
        $stageModel = $this->stage();
        $matchModel = $this->match();
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournamentModel),
            403
        );

        abort_unless((int) $eventModel->tournament_id === (int) $tournamentModel->id, 404);
        abort_unless((int) $stageModel->event_id === (int) $eventModel->id, 404);
        abort_unless((int) $matchModel->stage_id === (int) $stageModel->id, 404);

        $this->umpireName = $matchModel->umpire_name ?? '';
        $this->serviceJudgeName = $matchModel->service_judge_name ?? '';
        $this->court = $matchModel->court ?? '';

        if ($matchModel->status === 'pending') {
            $this->showPreStartModal = true;
            $this->populateDefaultTeamPlayerSelections();
        }
    }

    public bool $showGameEndModal = false;

    public bool $showWalkoverConfirmModal = false;

    public ?string $gameEndWinnerLabel = null;

    public ?int $gameEndScoreA = null;

    public ?int $gameEndScoreB = null;

    public ?int $gameEndGameNumber = null;

    public ?int $gameEndNextRoundNumber = null;

    public bool $gameEndIsMatchOver = false;

    private function tournament(): Tournament
    {
        return Tournament::query()->whereKey($this->tournamentId)->firstOrFail();
    }

    private function event(): Event
    {
        return Event::query()
            ->where('tournament_id', $this->tournamentId)
            ->whereKey($this->eventId)
            ->firstOrFail();
    }

    private function stage(): Stage
    {
        return Stage::query()
            ->where('event_id', $this->eventId)
            ->whereKey($this->stageId)
            ->firstOrFail();
    }

    private function match(): MatchModel
    {
        return MatchModel::query()
            ->where('stage_id', $this->stageId)
            ->whereKey($this->matchId)
            ->with(['games' => fn ($q) => $q->orderBy('game_number')])
            ->firstOrFail();
    }

    private function userCanViewTournament(User $user, Tournament $tournament): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return (int) $tournament->admin_id === (int) $user->id;
        }

        if ($user->role === User::ROLE_UMPIRES) {
            return $tournament->users()
                ->whereKey($user->id)
                ->where('role', User::ROLE_UMPIRES)
                ->exists();
        }

        return false;
    }

    public function openPreStartModal(): void
    {
        $this->showPreStartModal = true;
        $this->populateDefaultTeamPlayerSelections();
    }

    /**
     * Sync Livewire state with the first eligible roster option per slot (native <select> shows
     * the first option while wire:model stays null until the user changes it).
     */
    private function populateDefaultTeamPlayerSelections(): void
    {
        $match = $this->match();
        $event = $this->event();

        if ($event->event_type !== Event::EVENT_TYPE_TEAM || ! $match->tie_id || $match->status !== 'pending') {
            return;
        }

        $tie = $match->tie()->with(['teamA.teamPlayers', 'teamB.teamPlayers', 'matches.matchPlayers'])->first();
        if (! $tie) {
            return;
        }

        $isDoubles = in_array((string) $match->match_order, ['D1', 'D2'], true);

        $playedSinglesBySide = ['a' => [], 'b' => []];
        $playedDoublesBySide = ['a' => [], 'b' => []];
        foreach ($tie->matches as $innerMatch) {
            $innerOrder = (string) $innerMatch->match_order;
            if ($innerOrder === '') {
                continue;
            }
            $innerIsDoubles = in_array($innerOrder, ['D1', 'D2'], true);
            foreach ($innerMatch->matchPlayers as $innerMp) {
                if ($innerIsDoubles) {
                    $playedDoublesBySide[$innerMp->side][$innerMp->player_name] = true;
                } else {
                    $playedSinglesBySide[$innerMp->side][$innerMp->player_name] = true;
                }
            }
        }

        /** @var Collection<int, \App\Models\TeamPlayer> $playersA */
        $playersA = ($tie->teamA?->teamPlayers ?? collect())->sortBy('id')->values();
        /** @var Collection<int, \App\Models\TeamPlayer> $playersB */
        $playersB = ($tie->teamB?->teamPlayers ?? collect())->sortBy('id')->values();

        $eligible = function (Collection $players, string $side) use ($isDoubles, $playedSinglesBySide, $playedDoublesBySide): Collection {
            return $players->filter(function ($tp) use ($side, $isDoubles, $playedSinglesBySide, $playedDoublesBySide) {
                if ($isDoubles) {
                    return ! isset($playedDoublesBySide[$side][$tp->player_name]);
                }

                return ! isset($playedSinglesBySide[$side][$tp->player_name]);
            })->values();
        };

        $eligibleA = $eligible($playersA, 'a');
        $eligibleB = $eligible($playersB, 'b');

        $firstA = $eligibleA->first();
        $firstB = $eligibleB->first();

        if ($isDoubles) {
            $secondA = $eligibleA->first(fn ($tp) => $firstA && (int) $tp->id !== (int) $firstA->id);
            $secondB = $eligibleB->first(fn ($tp) => $firstB && (int) $tp->id !== (int) $firstB->id);

            $this->selectedPlayerIds = [
                'side_a_1' => $firstA?->id,
                'side_a_2' => $secondA?->id,
                'side_b_1' => $firstB?->id,
                'side_b_2' => $secondB?->id,
            ];
        } else {
            $this->selectedPlayerIds = [
                'side_a_1' => $firstA?->id,
                'side_a_2' => null,
                'side_b_1' => $firstB?->id,
                'side_b_2' => null,
            ];
        }
    }

    public function closePreStartModal(): mixed
    {
        $this->showPreStartModal = false;
        $this->resetErrorBag();

        return $this->redirect(route('tournaments.events.stages.show', [
            'tournament' => $this->tournamentId,
            'event' => $this->eventId,
            'stage' => $this->stageId,
        ]), navigate: true);
    }

    public function closeGameEndModal(): void
    {
        $this->showGameEndModal = false;
        $this->gameEndWinnerLabel = null;
        $this->gameEndScoreA = null;
        $this->gameEndScoreB = null;
        $this->gameEndGameNumber = null;
        $this->gameEndNextRoundNumber = null;
        $this->gameEndIsMatchOver = false;
    }

    public function startNextRound(): void
    {
        if (! $this->showGameEndModal) {
            return;
        }

        $match = $this->match();
        if ($this->gameEndIsMatchOver) {
            $this->closeGameEndModal();

            return;
        }

        $nextNum = $this->gameEndNextRoundNumber ?? 1;
        if ($nextNum <= $match->best_of) {
            $match->games()->create([
                'game_number' => $nextNum,
                'score_a' => 0,
                'score_b' => 0,
                'winner_side' => null,
                'entry_mode' => 'live',
            ]);
        }

        $this->closeGameEndModal();
    }

    public function startMatch(): void
    {
        $match = $this->match();
        if ($match->status !== 'pending') {
            return;
        }

        $event = $this->event();
        $isTeamEvent = $event->event_type === Event::EVENT_TYPE_TEAM;
        $isDoubles = $event->event_type === Event::EVENT_TYPE_DOUBLES || ($isTeamEvent && in_array($match->match_order, ['D1', 'D2'], true));

        // Team ties are played strictly sequentially inside the tie order:
        // S1 -> D1 -> S2 -> D2 -> S3.
        if ($isTeamEvent && $match->tie_id) {
            $tieOrderIndex = ['S1' => 0, 'D1' => 1, 'S2' => 2, 'D2' => 3, 'S3' => 4];

            $tieStatus = $match->tie()->value('status');
            if ($tieStatus === 'completed') {
                $this->addError('startMatch', __('This tie is already completed.'));
                return;
            }

            $tieMatches = MatchModel::query()
                ->where('tie_id', $match->tie_id)
                ->get()
                ->sortBy(fn (MatchModel $m) => $tieOrderIndex[$m->match_order] ?? 999)
                ->values();

            $terminalStatuses = ['completed', 'walkover', 'retired', 'not_required'];
            $nextPlayableMatch = $tieMatches->first(
                fn (MatchModel $m) => ! in_array($m->status, $terminalStatuses, true)
            );
            if (! $nextPlayableMatch || $nextPlayableMatch->id !== $match->id) {
                $this->addError('startMatch', __('You can only start the current match in this tie order.'));

                return;
            }
        }

        if ($isTeamEvent && $match->tie_id) {
            $this->validate([
                'umpireName' => ['nullable', 'string', 'max:255'],
                'serviceJudgeName' => ['nullable', 'string', 'max:255'],
                'court' => ['nullable', 'string', 'max:255'],
                'selectedPlayerIds.side_a_1' => ['required', 'integer', 'exists:team_players,id'],
                'selectedPlayerIds.side_a_2' => $isDoubles ? ['required', 'integer', 'exists:team_players,id'] : ['nullable'],
                'selectedPlayerIds.side_b_1' => ['required', 'integer', 'exists:team_players,id'],
                'selectedPlayerIds.side_b_2' => $isDoubles ? ['required', 'integer', 'exists:team_players,id'] : ['nullable'],
            ]);

            // Eligibility rule per tie (info.md):
            // - A player may play max 1 singles match and max 1 doubles match per tie.
            $selectedIds = array_filter(array_values($this->selectedPlayerIds));
            $playersById = TeamPlayer::query()
                ->whereIn('id', $selectedIds)
                ->get()
                ->keyBy('id');

            $playedSinglesBySide = ['a' => [], 'b' => []]; // [side][player_name] = true
            $playedDoublesBySide = ['a' => [], 'b' => []]; // [side][player_name] = true

            $tieMatches = MatchModel::query()
                ->where('tie_id', $match->tie_id)
                ->with('matchPlayers')
                ->get();

            foreach ($tieMatches as $innerMatch) {
                $innerMatchOrder = (string) $innerMatch->match_order;
                if ($innerMatchOrder === '') {
                    continue;
                }

                $innerIsDoubles = in_array($innerMatchOrder, ['D1', 'D2'], true);

                foreach ($innerMatch->matchPlayers as $innerMp) {
                    if ($innerIsDoubles) {
                        $playedDoublesBySide[$innerMp->side][$innerMp->player_name] = true;
                    } else {
                        $playedSinglesBySide[$innerMp->side][$innerMp->player_name] = true;
                    }
                }
            }

            $positions = [
                ['side' => 'a', 'key' => 'side_a_1'],
                ['side' => 'a', 'key' => 'side_a_2'],
                ['side' => 'b', 'key' => 'side_b_1'],
                ['side' => 'b', 'key' => 'side_b_2'],
            ];

            foreach ($positions as $p) {
                $id = $this->selectedPlayerIds[$p['key']] ?? null;
                if (! $id || ! isset($playersById[$id])) {
                    continue;
                }

                $playerName = $playersById[$id]->player_name;

                if ($isDoubles) {
                    if (isset($playedDoublesBySide[$p['side']][$playerName])) {
                        $this->addError('startMatch', __('A player can only play one doubles match per tie.'));
                        return;
                    }
                } else {
                    if (isset($playedSinglesBySide[$p['side']][$playerName])) {
                        $this->addError('startMatch', __('A player can only play one singles match per tie.'));
                        return;
                    }
                }
            }
        } else {
            $this->validate([
                'umpireName' => ['nullable', 'string', 'max:255'],
                'serviceJudgeName' => ['nullable', 'string', 'max:255'],
                'court' => ['nullable', 'string', 'max:255'],
            ]);
        }

        DB::transaction(function () use ($match, $event, $isTeamEvent, $isDoubles): void {
            $match->update([
                'status' => 'in_progress',
                'umpire_name' => $this->umpireName ?: null,
                'service_judge_name' => $this->serviceJudgeName ?: null,
                'court' => $this->court ?: null,
                'started_at' => now(),
            ]);

            if ($isTeamEvent && $match->tie_id && $match->matchPlayers()->count() === 0) {
                $tie = $match->tie()->with(['teamA', 'teamB'])->first();
                $players = TeamPlayer::query()
                    ->whereIn('id', array_filter(array_values($this->selectedPlayerIds)))
                    ->get()
                    ->keyBy('id');

                $positions = [
                    ['side' => 'a', 'position' => 1, 'key' => 'side_a_1'],
                    ['side' => 'a', 'position' => 2, 'key' => 'side_a_2'],
                    ['side' => 'b', 'position' => 1, 'key' => 'side_b_1'],
                    ['side' => 'b', 'position' => 2, 'key' => 'side_b_2'],
                ];
                foreach ($positions as $p) {
                    $id = $this->selectedPlayerIds[$p['key']] ?? null;
                    if ($id && isset($players[$id])) {
                        $match->matchPlayers()->create([
                            'side' => $p['side'],
                            'player_name' => $players[$id]->player_name,
                            'position' => $p['position'],
                        ]);
                    }
                }
            }

            $game = $match->games()->firstOrCreate(
                ['game_number' => 1],
                [
                    'score_a' => 0,
                    'score_b' => 0,
                    'winner_side' => null,
                    'entry_mode' => 'live',
                ]
            );

            $match->matchEvents()->create([
                'game_id' => $game->id,
                'event_type' => 'match_started',
                'side' => null,
                'player_name' => null,
                'score_a_at_time' => 0,
                'score_b_at_time' => 0,
                'notes' => null,
                'created_by' => 'umpire',
            ]);
        });

        $this->showPreStartModal = false;
        $this->resetErrorBag();
    }

    public function addPoint(string $side): void
    {
        if (! in_array($side, ['a', 'b'], true)) {
            return;
        }

        $match = $this->match();
        if ($match->status === 'completed' || $match->status === 'walkover' || $match->status === 'retired') {
            return;
        }

        DB::transaction(function () use ($match, $side): void {
            if ($match->status === 'pending') {
                $match->update(['status' => 'in_progress', 'started_at' => now()]);
                $game = $match->games()->firstOrCreate(
                    ['game_number' => 1],
                    ['score_a' => 0, 'score_b' => 0, 'winner_side' => null, 'entry_mode' => 'live']
                );
                $match->matchEvents()->create([
                    'game_id' => $game->id,
                    'event_type' => 'match_started',
                    'side' => null,
                    'player_name' => null,
                    'score_a_at_time' => 0,
                    'score_b_at_time' => 0,
                    'notes' => null,
                    'created_by' => 'umpire',
                ]);
            }

            $game = $match->games()->whereNull('winner_side')->orderBy('game_number')->first();
            if (! $game) {
                $lastGame = $match->games()->orderByDesc('game_number')->first();
                $nextNum = ($lastGame?->game_number ?? 0) + 1;
                if ($nextNum > $match->best_of) {
                    return;
                }
                $game = $match->games()->create([
                    'game_number' => $nextNum,
                    'score_a' => 0,
                    'score_b' => 0,
                    'winner_side' => null,
                    'entry_mode' => 'live',
                ]);
            }

            $scoreA = $game->score_a + ($side === 'a' ? 1 : 0);
            $scoreB = $game->score_b + ($side === 'b' ? 1 : 0);

            $game->update(['score_a' => $scoreA, 'score_b' => $scoreB]);

            $match->matchEvents()->create([
                'game_id' => $game->id,
                'event_type' => 'point',
                'side' => $side,
                'player_name' => null,
                'score_a_at_time' => $scoreA,
                'score_b_at_time' => $scoreB,
                'notes' => null,
                'created_by' => 'umpire',
            ]);

            $this->checkGameWinner($match, $game);
        });
    }

    public function undoLastPoint(): void
    {
        $match = $this->match();
        $game = $match->games()->whereNull('winner_side')->orderBy('game_number')->first();
        if (! $game) {
            return;
        }

        $lastPoint = $match->matchEvents()
            ->where('game_id', $game->id)
            ->where('event_type', 'point')
            ->orderByDesc('created_at')
            ->first();

        if (! $lastPoint) {
            return;
        }

        DB::transaction(function () use ($match, $game, $lastPoint): void {
            $side = $lastPoint->side;
            $scoreA = $game->score_a - ($side === 'a' ? 1 : 0);
            $scoreB = $game->score_b - ($side === 'b' ? 1 : 0);

            $game->update([
                'score_a' => max(0, $scoreA),
                'score_b' => max(0, $scoreB),
                'winner_side' => null,
                'ended_at' => null,
            ]);

            $match->matchEvents()->create([
                'game_id' => $game->id,
                'event_type' => 'undo',
                'side' => $side,
                'player_name' => null,
                'score_a_at_time' => max(0, $scoreA),
                'score_b_at_time' => max(0, $scoreB),
                'notes' => null,
                'created_by' => 'umpire',
            ]);
        });
    }

    private function checkGameWinner(MatchModel $match, Game $game): void
    {
        $a = $game->score_a;
        $b = $game->score_b;

        $gameWon = false;
        $winnerSide = null;

        if ($a >= 21 && $a - $b >= 2) {
            $gameWon = true;
            $winnerSide = 'a';
        } elseif ($b >= 21 && $b - $a >= 2) {
            $gameWon = true;
            $winnerSide = 'b';
        } elseif ($a === 30) {
            $gameWon = true;
            $winnerSide = 'a';
        } elseif ($b === 30) {
            $gameWon = true;
            $winnerSide = 'b';
        }

        if ($gameWon && $winnerSide) {
            $game->update(['winner_side' => $winnerSide, 'ended_at' => now()]);

            $match->matchEvents()->create([
                'game_id' => $game->id,
                'event_type' => 'game_ended',
                'side' => $winnerSide,
                'player_name' => null,
                'score_a_at_time' => $game->score_a,
                'score_b_at_time' => $game->score_b,
                'notes' => null,
                'created_by' => 'umpire',
            ]);

            $required = (int) ceil($match->best_of / 2);
            $winsA = $match->games()->where('winner_side', 'a')->count();
            $winsB = $match->games()->where('winner_side', 'b')->count();
            $matchOver = $winsA >= $required || $winsB >= $required;

            if ($matchOver) {
                $this->checkMatchWinner($match);
            }

            $winnerLabel = $winnerSide === 'a' ? $match->side_a_label : $match->side_b_label;
            $nextNum = $game->game_number + 1;

            $this->gameEndWinnerLabel = $winnerLabel;
            $this->gameEndScoreA = $game->score_a;
            $this->gameEndScoreB = $game->score_b;
            $this->gameEndGameNumber = $game->game_number;
            $this->gameEndNextRoundNumber = $nextNum <= $match->best_of ? $nextNum : null;
            $this->gameEndIsMatchOver = $matchOver;
            $this->showGameEndModal = true;
        }
    }

    private function checkMatchWinner(MatchModel $match): void
    {
        $required = (int) ceil($match->best_of / 2);
        $winsA = $match->games()->where('winner_side', 'a')->count();
        $winsB = $match->games()->where('winner_side', 'b')->count();

        if ($winsA >= $required || $winsB >= $required) {
            $winnerSide = $winsA >= $required ? 'a' : 'b';
            $match->update([
                'winner_side' => $winnerSide,
                'status' => 'completed',
                'ended_at' => now(),
            ]);

            $lastGame = $match->games()->orderByDesc('game_number')->first();
            $match->matchEvents()->create([
                'game_id' => $lastGame?->id,
                'event_type' => 'match_ended',
                'side' => $winnerSide,
                'player_name' => null,
                'score_a_at_time' => 0,
                'score_b_at_time' => 0,
                'notes' => null,
                'created_by' => 'umpire',
            ]);

            // If this match is part of a team tie, recompute tie winner + remaining
            // not-required matches.
            if ($match->tie_id) {
                app(TieResultService::class)->updateTieAfterInnerMatch($match);
            }
        }
    }

    public function logOccurrence(): void
    {
        $this->validate([
            'occurrenceType' => ['required', 'in:card,injury,walkover'],
            'occurrenceSide' => ['required', 'in:a,b'],
            'cardType' => ['required_if:occurrenceType,card', 'in:yellow,red'],
        ]);

        $match = $this->match();
        $currentGame = $match->games()->whereNull('winner_side')->orderBy('game_number')->first();

        $subtype = $this->occurrenceType === 'card' ? $this->cardType.'_card' : $this->occurrenceType;
        $playerName = null;
        if ($this->occurrencePlayerId) {
            $mp = MatchPlayer::query()->where('match_id', $match->id)->whereKey($this->occurrencePlayerId)->first();
            $playerName = $mp?->player_name;
        }

        if ($this->occurrenceType === 'walkover') {
            $this->showWalkoverConfirmModal = true;

            return;
        }

        $scoreA = $currentGame?->score_a ?? 0;
        $scoreB = $currentGame?->score_b ?? 0;

        $match->matchEvents()->create([
            'game_id' => $currentGame?->id,
            'event_type' => 'occurrence',
            'side' => $this->occurrenceSide,
            'player_name' => $playerName,
            'score_a_at_time' => $scoreA,
            'score_b_at_time' => $scoreB,
            'notes' => json_encode(['subtype' => $subtype]),
            'created_by' => 'umpire',
        ]);

        $this->occurrencePlayerId = null;
    }

    public function confirmWalkover(): void
    {
        $match = $this->match();
        if (! in_array($match->status, ['pending', 'in_progress'], true)) {
            return;
        }

        $winnerSide = $this->occurrenceSide === 'a' ? 'b' : 'a';
        $subtype = 'walkover';
        $playerName = null;
        if ($this->occurrencePlayerId) {
            $mp = MatchPlayer::query()->where('match_id', $match->id)->whereKey($this->occurrencePlayerId)->first();
            $playerName = $mp?->player_name;
        }

        $currentGame = $match->games()->whereNull('winner_side')->orderBy('game_number')->first();

        DB::transaction(function () use ($match, $winnerSide, $subtype, $playerName, $currentGame): void {
            $match->update([
                'status' => 'walkover',
                'winner_side' => $winnerSide,
                'ended_at' => now(),
            ]);
            $match->matchEvents()->create([
                'game_id' => $currentGame?->id,
                'event_type' => 'occurrence',
                'side' => $this->occurrenceSide,
                'player_name' => $playerName,
                'score_a_at_time' => $currentGame?->score_a ?? 0,
                'score_b_at_time' => $currentGame?->score_b ?? 0,
                'notes' => json_encode(['subtype' => $subtype]),
                'created_by' => 'umpire',
            ]);
        });

        if ($match->tie_id) {
            app(TieResultService::class)->updateTieAfterInnerMatch($match);
        }

        $this->showWalkoverConfirmModal = false;
        $this->occurrencePlayerId = null;
    }

    public function cancelWalkoverConfirm(): void
    {
        $this->showWalkoverConfirmModal = false;
    }
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $match = $this->match()->load(['games' => fn ($q) => $q->orderBy('game_number'), 'matchPlayers', 'matchEvents' => fn ($q) => $q->with('game')->orderByDesc('created_at')]);

    $matchTopLevelSequence = $match->topLevelSequenceInStage();

    $currentGame = $match->games->first(fn ($g) => $g->winner_side === null);
    $currentGameNumber = $currentGame ? $currentGame->game_number : ($match->games->max('game_number') ?? 1);
    $scoreA = $currentGame?->score_a ?? 0;
    $scoreB = $currentGame?->score_b ?? 0;

    $lastPointEvent = $match->matchEvents->first(fn ($e) => $e->event_type === 'point' && $e->game_id === $currentGame?->id);
    $canUndoA = $lastPointEvent && $lastPointEvent->side === 'a';
    $canUndoB = $lastPointEvent && $lastPointEvent->side === 'b';

    $gamesWonA = $match->games->where('winner_side', 'a')->count();
    $gamesWonB = $match->games->where('winner_side', 'b')->count();

    $statusLabels = [
        'pending' => __('NOT STARTED'),
        'in_progress' => __('IN PROGRESS'),
        'completed' => __('COMPLETED'),
        'retired' => __('RETIRED'),
        'walkover' => __('WALKOVER'),
        'not_required' => __('NOT REQUIRED'),
    ];
    $statusLabel = $statusLabels[$match->status] ?? $match->status;

    $badgeColors = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
    ];
    $badgeColor = $badgeColors[$match->status] ?? 'neutral';

    $isTeamEvent = $event->event_type === Event::EVENT_TYPE_TEAM;
    $isDoubles = $event->event_type === Event::EVENT_TYPE_DOUBLES || ($isTeamEvent && $match->match_order && in_array($match->match_order, ['D1', 'D2'], true));
    $tie = $match->tie_id ? $match->tie()->with(['teamA.teamPlayers', 'teamB.teamPlayers', 'matches.matchPlayers'])->first() : null;
    $teamPlayersA = $tie?->teamA?->teamPlayers ?? collect();
    $teamPlayersB = $tie?->teamB?->teamPlayers ?? collect();

    // Player eligibility per tie (info.md):
    // - each player can play at most one singles and at most one doubles match per tie.
    $playedSinglesBySide = ['a' => [], 'b' => []];
    $playedDoublesBySide = ['a' => [], 'b' => []];
    if ($tie) {
        foreach ($tie->matches as $innerMatch) {
            $innerOrder = (string) $innerMatch->match_order;
            if ($innerOrder === '') {
                continue;
            }

            $innerIsDoubles = in_array($innerOrder, ['D1', 'D2'], true);
            foreach ($innerMatch->matchPlayers as $innerMp) {
                if ($innerIsDoubles) {
                    $playedDoublesBySide[$innerMp->side][$innerMp->player_name] = true;
                } else {
                    $playedSinglesBySide[$innerMp->side][$innerMp->player_name] = true;
                }
            }
        }
    }

    $canScore = ! in_array($match->status, ['completed', 'walkover', 'retired', 'not_required'], true);
    $playersForOccurrence = $match->matchPlayers->where('side', $this->occurrenceSide)->values();
@endphp

<div class="control-panel-arena" x-data="{ scorePressed: null }">
{{-- Arena-style control panel: deep slate background, broadcast scoreboard aesthetic --}}
<div class="min-h-[calc(100vh-8rem)] w-full overflow-hidden rounded-2xl bg-slate-950 shadow-2xl ring-1 ring-white/5 dark:bg-slate-950">
    {{-- Ambient gradient orbs --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-emerald-500/5 blur-3xl"></div>
        <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-sky-500/5 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/2 h-48 w-[600px] -translate-x-1/2 rounded-full bg-amber-500/5 blur-3xl"></div>
        <div class="absolute inset-0 bg-[linear-gradient(180deg,transparent_0%,rgba(0,0,0,0.3)_100%)]"></div>
    </div>

    <div class="relative p-6 md:p-8 lg:p-10">
        {{-- Header: minimal, broadcast-style --}}
        <div class="mb-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div class="flex w-full max-w-3xl flex-col items-center gap-1 sm:items-start">
                <div class="flex w-full flex-col items-center gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <span class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-sky-400/90 sm:text-left">
                        @if ($match->tie_id && $match->match_order)
                            {{ __('MATCH') }} {{ $match->match_order }} · {{ $stage->name }}
                        @elseif ($matchTopLevelSequence !== null)
                            {{ __('MATCH') }} {{ $matchTopLevelSequence }} · {{ $stage->name }}
                        @else
                            {{ __('MATCH') }} · {{ $stage->name }}
                        @endif
                    </span>
                    <span class="shrink-0 text-[10px] font-normal uppercase tracking-wider text-slate-500">id {{ $match->id }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    {{ __('Game') }} {{ $currentGameNumber }} <span class="text-slate-500">/</span> {{ $match->best_of }}
                </h1>
                @if ($match->best_of > 1 && ($gamesWonA > 0 || $gamesWonB > 0))
                    <p class="text-sm font-medium tabular-nums text-slate-400">
                        {{ $gamesWonA }} – {{ $gamesWonB }}
                    </p>
                @endif
            </div>

            <div class="flex flex-col items-center gap-3 sm:items-end">
                <div class="text-center text-xl font-bold tracking-tight text-white sm:text-right">
                    <span class="text-slate-300">{{ $match->side_a_label }}</span>
                    <span class="mx-3 inline-block rounded bg-slate-700/80 px-2 py-0.5 text-sm font-bold text-amber-400">VS</span>
                    <span class="text-slate-300">{{ $match->side_b_label }}</span>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-end">
                    <span @class([
                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider',
                        'bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/30' => $match->status === 'pending',
                        'bg-emerald-500/20 text-emerald-400 ring-1 ring-emerald-500/30' => $match->status === 'in_progress',
                        'bg-slate-500/20 text-slate-400 ring-1 ring-slate-500/30' => in_array($match->status, ['completed', 'retired', 'walkover'], true),
                    ])>
                        {{ $statusLabel }}
                    </span>
                    <a
                        href="{{ route('tournaments.events.stages.matches.pdf', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'match' => $match->id]) }}"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-600/60 bg-slate-800/50 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-slate-500 hover:bg-slate-700/50 hover:text-white"
                    >
                        <flux:icon name="printer" class="size-4" />
                        {{ __('Print / Download PDF') }}
                    </a>
                    <a
                        wire:navigate
                        href="{{ route('tournaments.events.stages.show', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id]) }}"
                        class="rounded-lg border border-slate-600/60 bg-slate-800/50 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-slate-500 hover:bg-slate-700/50 hover:text-white"
                    >
                        ← {{ __('Back to Stage') }}
                    </a>
                </div>
            </div>
        </div>

        @if ($canScore)
            {{-- Score cards: broadcast scoreboard style --}}
            <div class="mb-10 grid gap-6 sm:grid-cols-2 lg:gap-10">
                @foreach (['a' => ['score' => $scoreA, 'label' => str_contains($match->side_a_label, '&') ? str_replace(' & ', ' / ', $match->side_a_label) : $match->side_a_label, 'canUndo' => $canUndoA], 'b' => ['score' => $scoreB, 'label' => str_contains($match->side_b_label, '&') ? str_replace(' & ', ' / ', $match->side_b_label) : $match->side_b_label, 'canUndo' => $canUndoB]] as $side => $data)
                    <div class="group relative overflow-hidden rounded-2xl border border-slate-700/60 bg-linear-to-b from-slate-800/90 to-slate-900/95 p-8 shadow-xl ring-1 ring-white/5 transition-all duration-300 hover:ring-slate-500/50 dark:from-slate-800/90 dark:to-slate-900/95">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(255,255,255,0.03),transparent_60%)]"></div>
                        <div class="relative">
                            <p class="mb-4 text-center text-sm font-semibold uppercase tracking-widest text-slate-400">
                                {{ $data['label'] }}
                            </p>
                            <div class="mb-8 text-center font-mono text-8xl font-bold tabular-nums text-white drop-shadow-lg transition-transform duration-150 md:text-9xl" :class="{ 'scale-105': scorePressed === '{{ $side }}' }">
                                {{ $data['score'] }}
                            </div>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    wire:click="undoLastPoint"
                                    @if (! $data['canUndo']) disabled @endif
                                    class="flex min-h-14 flex-1 items-center justify-center rounded-xl border border-rose-500/40 bg-rose-600/20 font-semibold text-rose-400 transition-all hover:bg-rose-600/30 hover:border-rose-500/60 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-rose-600/20"
                                >
                                    <flux:icon name="minus" class="size-7" />
                                </button>
                                <button
                                    type="button"
                                    wire:click="addPoint('{{ $side }}')"
                                    @click="scorePressed = '{{ $side }}'; setTimeout(() => scorePressed = null, 150)"
                                    class="flex min-h-14 flex-1 items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-600 font-semibold text-white shadow-lg shadow-emerald-900/30 transition-all hover:bg-emerald-500 hover:shadow-emerald-800/40 active:scale-[0.98]"
                                >
                                    <flux:icon name="plus" class="size-7" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Match Actions + Event Log --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-700/60 bg-slate-800/50 p-6 ring-1 ring-white/5">
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-[0.15em] text-slate-400">
                        {{ __('Match Actions') }}
                    </h3>
                    <div class="space-y-5">
                        <div>
                            <flux:label class="mb-2 block text-sm font-medium text-slate-300">{{ __('What happened?') }}</flux:label>
                            <flux:radio.group wire:model.live="occurrenceType" variant="segmented" class="flex flex-wrap gap-2">
                                <flux:radio value="card" icon="exclamation-triangle">{{ __('Card') }}</flux:radio>
                                <flux:radio value="injury" icon="heart">{{ __('Injury') }}</flux:radio>
                                <flux:radio value="walkover" icon="flag">{{ __('Walkover') }}</flux:radio>
                            </flux:radio.group>
                        </div>

                        @if ($this->occurrenceType === 'card')
                            <div>
                                <flux:label class="mb-2 block text-sm font-medium text-slate-300">{{ __('Card Type') }}</flux:label>
                                <flux:radio.group wire:model.live="cardType" variant="segmented" class="flex flex-wrap gap-2">
                                    <flux:radio value="yellow" icon="exclamation-triangle">{{ __('Yellow Card') }}</flux:radio>
                                    <flux:radio value="red" icon="x-circle">{{ __('Red Card') }}</flux:radio>
                                </flux:radio.group>
                            </div>
                        @endif

                        <div>
                            <flux:label class="mb-2 block text-sm font-medium text-slate-300">{{ __('Team') }}</flux:label>
                            <flux:radio.group wire:model.live="occurrenceSide" variant="segmented" class="flex flex-wrap gap-2">
                                <flux:radio value="a">{{ $match->side_a_label }}</flux:radio>
                                <flux:radio value="b">{{ $match->side_b_label }}</flux:radio>
                            </flux:radio.group>
                        </div>

                        @if ($playersForOccurrence->isNotEmpty())
                            <div>
                                <flux:label class="mb-2 block text-sm font-medium text-slate-300">{{ __('Player') }} ({{ __('optional') }})</flux:label>
                                <flux:select wire:model="occurrencePlayerId" placeholder="—">
                                    <option value="">{{ __('—') }}</option>
                                    @foreach ($playersForOccurrence as $mp)
                                        <option value="{{ $mp->id }}">{{ $mp->player_name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif

                        <flux:button variant="primary" class="w-full bg-slate-700 font-semibold hover:bg-slate-600" wire:click="logOccurrence">
                            {{ __('Log Action') }}
                        </flux:button>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-700/60 bg-slate-800/50 p-6 ring-1 ring-white/5">
                    <h3 class="mb-4 font-semibold text-white">{{ __('Event Log') }}</h3>
                    <div class="max-h-80 overflow-y-auto rounded-xl border border-slate-600/50 bg-slate-900/50">
                        @if ($match->matchEvents->isEmpty())
                            <div class="flex min-h-[120px] items-center justify-center p-6 text-sm text-slate-500">
                                {{ __('No events yet.') }}
                            </div>
                        @else
                            <ul class="divide-y divide-slate-700/50">
                                @foreach ($match->matchEvents as $evt)
                                    <li class="flex items-start gap-3 border-l-2 border-transparent py-3 pl-4 pr-4 transition-colors hover:border-slate-600 hover:bg-slate-800/30" @class(['border-l-emerald-500/50' => $evt->event_type === 'point', 'border-l-amber-500/50' => in_array($evt->event_type, ['undo', 'occurrence', 'game_ended'], true), 'border-l-emerald-500' => $evt->event_type === 'match_ended'])>
                                        @if ($evt->event_type === 'point')
                                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                                <flux:icon name="plus" class="size-4" />
                                            </span>
                                        @elseif ($evt->event_type === 'undo')
                                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400">
                                                <flux:icon name="arrow-uturn-left" class="size-4" />
                                            </span>
                                        @elseif ($evt->event_type === 'occurrence')
                                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400">
                                                <flux:icon name="exclamation-triangle" class="size-4" />
                                            </span>
                                        @elseif ($evt->event_type === 'game_ended')
                                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400">
                                                <flux:icon name="trophy" class="size-4" />
                                            </span>
                                        @elseif ($evt->event_type === 'match_ended')
                                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                                <flux:icon name="check-circle" class="size-4" />
                                            </span>
                                        @else
                                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-600/50 text-slate-400">
                                                <flux:icon name="information-circle" class="size-4" />
                                            </span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <span class="font-medium text-slate-200">
                                                @if ($evt->event_type === 'point')
                                                    {{ __('Point') }} — {{ $evt->side === 'a' ? $match->side_a_label : $match->side_b_label }}
                                                @elseif ($evt->event_type === 'undo')
                                                    {{ __('Undo') }}
                                                @elseif ($evt->event_type === 'occurrence')
                                                    @php
                                                        $notes = $evt->notes ? json_decode($evt->notes, true) : [];
                                                        $sub = $notes['subtype'] ?? 'occurrence';
                                                    @endphp
                                                    {{ ucfirst(str_replace('_', ' ', $sub)) }}
                                                    @if ($evt->player_name)
                                                        — {{ $evt->player_name }}
                                                    @endif
                                                @elseif ($evt->event_type === 'game_ended')
                                                    {{ __('Game ended') }} — {{ $evt->side === 'a' ? $match->side_a_label : $match->side_b_label }}
                                                @elseif ($evt->event_type === 'match_ended')
                                                    {{ __('Match ended') }} — {{ $evt->side === 'a' ? $match->side_a_label : $match->side_b_label }}
                                                @elseif ($evt->event_type === 'match_started')
                                                    {{ __('Match started') }}
                                                @else
                                                    {{ $evt->event_type }}
                                                @endif
                                            </span>
                                            <span class="ml-2 font-mono text-sm text-slate-500">
                                                {{ $evt->score_a_at_time }}–{{ $evt->score_b_at_time }}
                                                @if ($evt->game)
                                                    ({{ __('G') }}{{ $evt->game->game_number }})
                                                @endif
                                            </span>
                                            <div class="mt-0.5 text-xs text-slate-500">
                                                {{ $evt->created_at->format('H:i:s') }}
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- Completed / terminal: show stored games — not $scoreA/$scoreB (those are the *current* open game, which is null when every game has finished). --}}
            <div class="rounded-2xl border border-slate-700/60 bg-slate-800/50 p-10 ring-1 ring-white/5">
                @if ($match->best_of > 1)
                    <p class="mb-6 text-center text-sm font-semibold uppercase tracking-wider text-slate-400">
                        {{ __('Match score') }}
                        <span class="ms-2 font-mono text-2xl font-bold tabular-nums text-white">{{ $gamesWonA }} – {{ $gamesWonB }}</span>
                    </p>
                @endif
                @if ($match->games->isNotEmpty())
                    <div class="mb-6 flex flex-col items-stretch gap-4 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-6">
                        @foreach ($match->games as $finishedGame)
                            <div class="rounded-xl border border-slate-700/50 bg-slate-900/40 px-6 py-4 text-center">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Round') }} {{ $finishedGame->game_number }}</p>
                                <p class="font-mono text-4xl font-bold tabular-nums text-white md:text-5xl">
                                    {{ $finishedGame->score_a }} <span class="text-slate-500">–</span> {{ $finishedGame->score_b }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-col items-center gap-2 text-center text-sm text-slate-400">
                        <span class="font-semibold text-slate-300">{{ str_contains($match->side_a_label, '&') ? str_replace(' & ', ' / ', $match->side_a_label) : $match->side_a_label }}</span>
                        <span class="text-slate-600">{{ __('vs') }}</span>
                        <span class="font-semibold text-slate-300">{{ str_contains($match->side_b_label, '&') ? str_replace(' & ', ' / ', $match->side_b_label) : $match->side_b_label }}</span>
                    </div>
                @else
                    <div class="flex flex-col items-center gap-8 sm:flex-row sm:justify-center sm:gap-16">
                        <div class="text-center">
                            <p class="mb-2 text-sm font-semibold uppercase tracking-wider text-slate-400">{{ str_contains($match->side_a_label, '&') ? str_replace(' & ', ' / ', $match->side_a_label) : $match->side_a_label }}</p>
                            <p class="font-mono text-6xl font-bold tabular-nums text-white md:text-7xl">—</p>
                        </div>
                        <div class="text-2xl font-bold text-slate-500">—</div>
                        <div class="text-center">
                            <p class="mb-2 text-sm font-semibold uppercase tracking-wider text-slate-400">{{ str_contains($match->side_b_label, '&') ? str_replace(' & ', ' / ', $match->side_b_label) : $match->side_b_label }}</p>
                            <p class="font-mono text-6xl font-bold tabular-nums text-white md:text-7xl">—</p>
                        </div>
                    </div>
                    <p class="mt-6 text-center text-sm text-slate-500">{{ __('No game scores on record for this match.') }}</p>
                @endif
                @if ($match->winner_side)
                    <div class="mt-8 flex justify-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-6 py-2 font-bold text-emerald-400 ring-1 ring-emerald-500/30">
                            <flux:icon name="trophy" class="size-5" />
                            {{ __('Winner') }}: {{ $match->winner_side === 'a' ? $match->side_a_label : $match->side_b_label }}
                        </span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

<flux:modal
    name="pre-start-modal"
    wire:model.self="showPreStartModal"
    focusable
    class="max-w-lg"
    :dismissible="false"
    :closable="false"
>
    <form wire:submit.prevent="startMatch" class="space-y-6">
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('Start Match') }}</flux:heading>
            <flux:subheading>{{ __('Enter umpire, service judge, and court details.') }}</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Umpire Name') }}</flux:label>
                <flux:input wire:model="umpireName" type="text" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Service Judge Name') }}</flux:label>
                <flux:input wire:model="serviceJudgeName" type="text" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Court Number') }}</flux:label>
                <flux:input wire:model="court" type="text" />
            </flux:field>

            @if ($isTeamEvent && $tie)
                <div class="border-t border-neutral-200 pt-4 dark:border-neutral-600">
                    <flux:heading size="sm" class="mb-3">{{ __('Select Players') }}</flux:heading>
                    <div class="space-y-4">
                        <div>
                            <flux:label class="mb-1 block text-sm">{{ $match->side_a_label }} — {{ $isDoubles ? __('Player 1') : __('Player') }}</flux:label>
                            <flux:select wire:model.live="selectedPlayerIds.side_a_1" placeholder="{{ __('Select') }}">
                                @foreach ($teamPlayersA as $tp)
                                    <option value="{{ $tp->id }}" @if(($isDoubles && isset($playedDoublesBySide['a'][$tp->player_name])) || (! $isDoubles && isset($playedSinglesBySide['a'][$tp->player_name]))) disabled @endif>
                                        {{ $tp->player_name }}
                                    </option>
                                @endforeach
                            </flux:select>
                        </div>
                        @if ($isDoubles)
                            <div>
                                <flux:label class="mb-1 block text-sm">{{ $match->side_a_label }} — {{ __('Player 2') }}</flux:label>
                                <flux:select wire:model.live="selectedPlayerIds.side_a_2" placeholder="{{ __('Select') }}">
                                    @foreach ($teamPlayersA as $tp)
                                        <option value="{{ $tp->id }}" @if(($isDoubles && isset($playedDoublesBySide['a'][$tp->player_name])) || (! $isDoubles && isset($playedSinglesBySide['a'][$tp->player_name]))) disabled @endif>
                                            {{ $tp->player_name }}
                                        </option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                        <div>
                            <flux:label class="mb-1 block text-sm">{{ $match->side_b_label }} — {{ $isDoubles ? __('Player 1') : __('Player') }}</flux:label>
                            <flux:select wire:model.live="selectedPlayerIds.side_b_1" placeholder="{{ __('Select') }}">
                                @foreach ($teamPlayersB as $tp)
                                    <option value="{{ $tp->id }}" @if(($isDoubles && isset($playedDoublesBySide['b'][$tp->player_name])) || (! $isDoubles && isset($playedSinglesBySide['b'][$tp->player_name]))) disabled @endif>
                                        {{ $tp->player_name }}
                                    </option>
                                @endforeach
                            </flux:select>
                        </div>
                        @if ($isDoubles)
                            <div>
                                <flux:label class="mb-1 block text-sm">{{ $match->side_b_label }} — {{ __('Player 2') }}</flux:label>
                                <flux:select wire:model.live="selectedPlayerIds.side_b_2" placeholder="{{ __('Select') }}">
                                    @foreach ($teamPlayersB as $tp)
                                        <option value="{{ $tp->id }}" @if(($isDoubles && isset($playedDoublesBySide['b'][$tp->player_name])) || (! $isDoubles && isset($playedSinglesBySide['b'][$tp->player_name]))) disabled @endif>
                                            {{ $tp->player_name }}
                                        </option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3">
            <flux:button id="back-to-stage" key="back-to-stage" type="button" variant="outline" wire:click="closePreStartModal">
                {{ __('Back to Stage') }}
            </flux:button>
            <flux:button id="start-match" key="start-match" type="submit" variant="primary">
                {{ __('Start Match') }}
            </flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal
    name="game-end-modal"
    wire:model.self="showGameEndModal"
    focusable
    class="max-w-md"
    @close="closeGameEndModal"
>
    <div class="space-y-6">
        <div class="space-y-2 text-center">
            <flux:heading size="lg">{{ __('Round') }} {{ $this->gameEndGameNumber }} {{ __('won') }}</flux:heading>
            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">
                {{ $this->gameEndWinnerLabel }}
            </div>
            @if ($this->gameEndScoreA !== null && $this->gameEndScoreB !== null)
                <div class="text-lg text-neutral-600 dark:text-neutral-400">
                    {{ $this->gameEndScoreA }} – {{ $this->gameEndScoreB }}
                </div>
            @endif
            @if ($this->gameEndIsMatchOver)
                <div class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                    {{ __('Match completed!') }}
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3">
            @if ($this->gameEndIsMatchOver)
                <flux:button variant="primary" wire:click="startNextRound">
                    {{ __('Done') }}
                </flux:button>
            @else
                <flux:button variant="primary" wire:click="startNextRound">
                    {{ __('Start') }} {{ __('Round') }} {{ $this->gameEndNextRoundNumber }}
                </flux:button>
            @endif
        </div>
    </div>
</flux:modal>

<flux:modal
    name="walkover-confirm-modal"
    wire:model.self="showWalkoverConfirmModal"
    focusable
    class="max-w-md"
>
    <div class="space-y-6">
        <div class="space-y-2 text-center">
            <flux:heading size="lg">{{ __('Confirm Walkover') }}</flux:heading>
            <flux:subheading>
                {{ __('Does') }}
                <strong>{{ $this->occurrenceSide === 'a' ? $match->side_a_label : $match->side_b_label }}</strong>
                {{ __('really want to leave?') }}
            </flux:subheading>
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('If yes, the match will end and') }}
                <strong>{{ $this->occurrenceSide === 'a' ? $match->side_b_label : $match->side_a_label }}</strong>
                {{ __('will win.') }}
            </p>
        </div>

        <div class="flex justify-end gap-3">
            <flux:button variant="outline" wire:click="cancelWalkoverConfirm">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button variant="primary" wire:click="confirmWalkover">
                {{ __('Yes, End Match') }}
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>
