<?php

namespace App\Livewire\Concerns;

use App\Models\MatchModel;
use App\Models\User;
use App\Services\TieResultService;
use Illuminate\Support\Facades\DB;

trait HandlesBulkScoreEntry
{
    public bool $showBulkScoreModal = false;

    public ?int $bulkScoreMatchId = null;

    /** @var array<int, array{score_a: string, score_b: string}> */
    public array $bulkScores = [];

    /**
     * @return int The stage ID used to scope bulk score entry for matches.
     */
    abstract protected function bulkScoreStageId(): int;

    public function openBulkScoreModal(int $matchId): void
    {
        $match = MatchModel::query()
            ->where('stage_id', $this->bulkScoreStageId())
            ->whereKey($matchId)
            ->with(['games' => fn ($q) => $q->orderBy('game_number')])
            ->firstOrFail();

        if (! in_array($match->status, ['pending', 'in_progress'], true)) {
            return;
        }

        $this->bulkScoreMatchId = $matchId;
        $this->prepareBulkScoresForMatch($match);
        $this->showBulkScoreModal = true;
    }

    public function closeBulkScoreModal(): void
    {
        $this->showBulkScoreModal = false;
        $this->bulkScoreMatchId = null;
        $this->bulkScores = [];
        $this->resetErrorBag();
    }

    protected function prepareBulkScoresForMatch(MatchModel $match): void
    {
        $this->bulkScores = [];
        $existingGameNumbers = $match->games->pluck('game_number')->all();
        for ($gameNum = 1; $gameNum <= $match->best_of; $gameNum++) {
            if (! in_array($gameNum, $existingGameNumbers, true)) {
                $this->bulkScores[$gameNum] = ['score_a' => '', 'score_b' => ''];
            }
        }
    }

    public function submitBulkScores(): void
    {
        if (! $this->bulkScoreMatchId) {
            return;
        }

        $match = MatchModel::query()
            ->where('stage_id', $this->bulkScoreStageId())
            ->whereKey($this->bulkScoreMatchId)
            ->with(['games' => fn ($q) => $q->orderBy('game_number')])
            ->first();

        if (! $match || ! in_array($match->status, ['pending', 'in_progress'], true)) {
            $this->closeBulkScoreModal();

            return;
        }

        if ($match->tie_id !== null && $match->matchPlayers()->count() === 0) {
            $this->addError(
                'bulkScores',
                __('Assign players from the match control panel before entering scores for tie matches.')
            );

            return;
        }

        $gamesToAdd = [];
        foreach ($this->bulkScores as $gameNum => $scores) {
            $scoreA = trim((string) ($scores['score_a'] ?? ''));
            $scoreB = trim((string) ($scores['score_b'] ?? ''));
            if ($scoreA === '' && $scoreB === '') {
                continue;
            }
            $scoreA = (int) $scoreA;
            $scoreB = (int) $scoreB;
            if (! $this->isValidBulkGameScore($scoreA, $scoreB)) {
                $this->addError('bulkScores', __('Invalid game score for Game :gameNum. Must be 21 with 2-point lead, or 30-29.', ['gameNum' => $gameNum]));

                return;
            }
            $winnerSide = $this->bulkGameWinner($scoreA, $scoreB);
            $gamesToAdd[] = ['game_number' => (int) $gameNum, 'score_a' => $scoreA, 'score_b' => $scoreB, 'winner_side' => $winnerSide];
        }

        if (empty($gamesToAdd)) {
            $this->addError('bulkScores', __('Please enter at least one game score to submit.'));

            return;
        }

        $createdBy = auth()->user()?->role === User::ROLE_ADMIN ? 'admin' : 'umpire';

        DB::transaction(function () use ($match, $gamesToAdd, $createdBy): void {
            $wasPending = $match->status === 'pending';
            if ($wasPending) {
                $match->update(['status' => 'in_progress', 'started_at' => now()]);
                $match->matchEvents()->create([
                    'game_id' => null,
                    'event_type' => 'match_started',
                    'side' => null,
                    'player_name' => null,
                    'score_a_at_time' => 0,
                    'score_b_at_time' => 0,
                    'notes' => null,
                    'created_by' => $createdBy,
                ]);
            }

            $existingGameNumbers = $match->games()->pluck('game_number')->all();
            foreach ($gamesToAdd as $gameData) {
                if (in_array($gameData['game_number'], $existingGameNumbers, true)) {
                    continue;
                }
                $game = $match->games()->create([
                    'game_number' => $gameData['game_number'],
                    'score_a' => $gameData['score_a'],
                    'score_b' => $gameData['score_b'],
                    'winner_side' => $gameData['winner_side'],
                    'entry_mode' => 'bulk',
                    'ended_at' => now(),
                ]);
                $match->matchEvents()->create([
                    'game_id' => $game->id,
                    'event_type' => 'bulk_score_entry',
                    'side' => $gameData['winner_side'],
                    'player_name' => null,
                    'score_a_at_time' => $gameData['score_a'],
                    'score_b_at_time' => $gameData['score_b'],
                    'notes' => json_encode(['offline_entry' => true]),
                    'created_by' => $createdBy,
                ]);
                $existingGameNumbers[] = $gameData['game_number'];
            }

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
                    'created_by' => $createdBy,
                ]);

                if ($match->tie_id) {
                    app(TieResultService::class)->updateTieAfterInnerMatch($match);
                }
            }
        });

        $this->showBulkScoreModal = false;
        $this->bulkScoreMatchId = null;
        $this->bulkScores = [];
        session()->flash('bulk_score_success', __('Scores submitted successfully.'));
    }

    protected function isValidBulkGameScore(int $scoreA, int $scoreB): bool
    {
        if ($scoreA >= 21 && $scoreA - $scoreB >= 2) {
            return $scoreA <= 30 && $scoreB <= 29;
        }
        if ($scoreB >= 21 && $scoreB - $scoreA >= 2) {
            return $scoreB <= 30 && $scoreA <= 29;
        }
        if ($scoreA === 30 && $scoreB === 29) {
            return true;
        }
        if ($scoreB === 30 && $scoreA === 29) {
            return true;
        }

        return false;
    }

    protected function bulkGameWinner(int $scoreA, int $scoreB): ?string
    {
        if ($scoreA >= 21 && $scoreA - $scoreB >= 2) {
            return 'a';
        }
        if ($scoreB >= 21 && $scoreB - $scoreA >= 2) {
            return 'b';
        }
        if ($scoreA === 30) {
            return 'a';
        }
        if ($scoreB === 30) {
            return 'b';
        }

        return null;
    }
}
