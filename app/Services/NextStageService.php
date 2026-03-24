<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Team;
use App\Models\Tie;
use App\Support\BracketStageNaming;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NextStageService
{
    /** @var array<int, string> */
    private const TERMINAL_MATCH_STATUSES = ['completed', 'walkover', 'retired', 'not_required'];

    public function canShowGenerateButton(Stage $stage): bool
    {
        $event = $stage->event;

        if ($this->hasLaterStage($stage)) {
            return false;
        }

        if ($event->event_type === Event::EVENT_TYPE_TEAM) {
            $ties = Tie::query()->where('stage_id', $stage->id)->orderBy('id')->get();
            if ($ties->count() <= 1) {
                return false;
            }

            if (! $this->bracketUnitCountIsValidPowerOfTwo($ties->count())) {
                return false;
            }

            return $this->teamStageIsComplete($ties);
        }

        $matches = MatchModel::query()
            ->where('stage_id', $stage->id)
            ->whereNull('tie_id')
            ->orderBy('id')
            ->get();

        if ($matches->count() <= 1) {
            return false;
        }

        if (! $this->bracketUnitCountIsValidPowerOfTwo($matches->count())) {
            return false;
        }

        return $this->individualStageIsComplete($matches);
    }

    /**
     * @return array{
     *     next_stage_name: string,
     *     best_of: int,
     *     next_unit_count: int,
     *     pairs: array<int, array{left: string, right: string}>,
     *     event_type: string,
     * }
     */
    public function preview(Stage $stage): array
    {
        if ($this->hasLaterStage($stage)) {
            throw new InvalidArgumentException(__('A later stage already exists for this event.'));
        }

        $event = $stage->event;

        if ($event->event_type === Event::EVENT_TYPE_TEAM) {
            return $this->previewTeam($stage, $event);
        }

        return $this->previewIndividual($stage, $event);
    }

    public function generate(Stage $stage, int $bestOf): Stage
    {
        if (! in_array($bestOf, [1, 3, 5], true)) {
            throw new InvalidArgumentException(__('Best of must be 1, 3, or 5.'));
        }

        if ($this->hasLaterStage($stage)) {
            throw new InvalidArgumentException(__('A later stage already exists for this event.'));
        }

        $event = $stage->event;

        return DB::transaction(function () use ($stage, $event, $bestOf): Stage {
            if ($event->event_type === Event::EVENT_TYPE_TEAM) {
                return $this->generateTeam($stage, $event, $bestOf);
            }

            return $this->generateIndividual($stage, $event, $bestOf);
        });
    }

    /**
     * Bracket rounds must have 2, 4, 8, 16, … units (matches or ties) so winners pair evenly.
     */
    public function bracketUnitCountIsValidPowerOfTwo(int $count): bool
    {
        return $count >= 2 && BracketStageNaming::isPowerOfTwo($count);
    }

    private function hasLaterStage(Stage $stage): bool
    {
        return Stage::query()
            ->where('event_id', $stage->event_id)
            ->where('order_index', '>', $stage->order_index)
            ->exists();
    }

    /**
     * @param  Collection<int, MatchModel>  $matches
     */
    private function individualStageIsComplete($matches): bool
    {
        if ($matches->isEmpty()) {
            return false;
        }

        foreach ($matches as $match) {
            if (! in_array($match->status, self::TERMINAL_MATCH_STATUSES, true)) {
                return false;
            }
            if (! in_array($match->winner_side, ['a', 'b'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, Tie>  $ties
     */
    private function teamStageIsComplete($ties): bool
    {
        if ($ties->isEmpty()) {
            return false;
        }

        foreach ($ties as $tie) {
            if ($tie->status !== 'completed' || $tie->winner_team_id === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     next_stage_name: string,
     *     best_of: int,
     *     next_unit_count: int,
     *     pairs: array<int, array{left: string, right: string}>,
     *     event_type: string,
     * }
     */
    private function previewIndividual(Stage $stage, Event $event): array
    {
        $matches = MatchModel::query()
            ->where('stage_id', $stage->id)
            ->whereNull('tie_id')
            ->with('matchPlayers')
            ->orderBy('id')
            ->get();

        if ($matches->count() <= 1) {
            throw new InvalidArgumentException(__('There is no next stage after the final round.'));
        }

        if (! $this->bracketUnitCountIsValidPowerOfTwo($matches->count())) {
            throw new InvalidArgumentException(
                __('This stage must have a power-of-2 number of matches (2, 4, 8, 16, …) to generate the next round. Remove or add matches until the count is valid.')
            );
        }

        if (! $this->individualStageIsComplete($matches)) {
            throw new InvalidArgumentException(__('Not all matches in this stage are finished.'));
        }

        $winners = [];
        foreach ($matches as $match) {
            $winners[] = $this->individualWinnerPayload($match, $event);
        }

        $pairs = $this->buildPairLabels($winners, $event);

        $nextUnitCount = count($pairs);
        $playersInRound = $nextUnitCount * 2;

        return [
            'next_stage_name' => BracketStageNaming::stageNameForPlayers($playersInRound),
            'best_of' => (int) $stage->best_of,
            'next_unit_count' => $nextUnitCount,
            'pairs' => $pairs,
            'event_type' => $event->event_type,
        ];
    }

    /**
     * @return array{
     *     next_stage_name: string,
     *     best_of: int,
     *     next_unit_count: int,
     *     pairs: array<int, array{left: string, right: string}>,
     *     event_type: string,
     * }
     */
    private function previewTeam(Stage $stage, Event $event): array
    {
        $ties = Tie::query()
            ->where('stage_id', $stage->id)
            ->with(['teamA', 'teamB'])
            ->orderBy('id')
            ->get();

        if ($ties->count() <= 1) {
            throw new InvalidArgumentException(__('There is no next stage after the final round.'));
        }

        if (! $this->bracketUnitCountIsValidPowerOfTwo($ties->count())) {
            throw new InvalidArgumentException(
                __('This stage must have a power-of-2 number of ties (2, 4, 8, 16, …) to generate the next round. Remove or add ties until the count is valid.')
            );
        }

        if (! $this->teamStageIsComplete($ties)) {
            throw new InvalidArgumentException(__('Not all ties in this stage are finished.'));
        }

        $winnerNames = [];
        foreach ($ties as $tie) {
            $winnerNames[] = $tie->winner_team_id === $tie->team_a_id
                ? (string) $tie->teamA?->name
                : (string) $tie->teamB?->name;
        }

        $pairRows = [];
        for ($i = 0; $i < count($winnerNames); $i += 2) {
            $pairRows[] = [
                'left' => $winnerNames[$i],
                'right' => $winnerNames[$i + 1],
            ];
        }

        $nextUnitCount = count($pairRows);
        $playersInRound = $nextUnitCount * 2;

        return [
            'next_stage_name' => BracketStageNaming::stageNameForPlayers($playersInRound),
            'best_of' => (int) $stage->best_of,
            'next_unit_count' => $nextUnitCount,
            'pairs' => $pairRows,
            'event_type' => $event->event_type,
        ];
    }

    private function generateIndividual(Stage $stage, Event $event, int $bestOf): Stage
    {
        $matches = MatchModel::query()
            ->where('stage_id', $stage->id)
            ->whereNull('tie_id')
            ->with('matchPlayers')
            ->orderBy('id')
            ->get();

        if ($matches->count() <= 1) {
            throw new InvalidArgumentException(__('There is no next stage after the final round.'));
        }

        if (! $this->bracketUnitCountIsValidPowerOfTwo($matches->count())) {
            throw new InvalidArgumentException(
                __('This stage must have a power-of-2 number of matches (2, 4, 8, 16, …) to generate the next round.')
            );
        }

        if (! $this->individualStageIsComplete($matches)) {
            throw new InvalidArgumentException(__('Not all matches in this stage are finished.'));
        }

        $winners = [];
        foreach ($matches as $match) {
            $winners[] = $this->individualWinnerPayload($match, $event);
        }

        $nextOrderIndex = $this->nextOrderIndex($stage);
        $nextUnitCount = (int) (count($winners) / 2);
        $newStage = Stage::query()->create([
            'event_id' => $event->id,
            'name' => BracketStageNaming::stageNameForPlayers($nextUnitCount * 2),
            'best_of' => $bestOf,
            'order_index' => $nextOrderIndex,
            'status' => 'pending',
        ]);

        if ($event->event_type === Event::EVENT_TYPE_SINGLES) {
            for ($i = 0; $i < count($winners); $i += 2) {
                $this->createSinglesMatchFromWinners($newStage, $winners[$i], $winners[$i + 1]);
            }
        } else {
            for ($i = 0; $i < count($winners); $i += 2) {
                $this->createDoublesMatchFromWinners($newStage, $winners[$i], $winners[$i + 1]);
            }
        }

        return $newStage;
    }

    private function generateTeam(Stage $stage, Event $event, int $bestOf): Stage
    {
        $ties = Tie::query()
            ->where('stage_id', $stage->id)
            ->orderBy('id')
            ->get();

        if ($ties->count() <= 1) {
            throw new InvalidArgumentException(__('There is no next stage after the final round.'));
        }

        if (! $this->bracketUnitCountIsValidPowerOfTwo($ties->count())) {
            throw new InvalidArgumentException(
                __('This stage must have a power-of-2 number of ties (2, 4, 8, 16, …) to generate the next round.')
            );
        }

        if (! $this->teamStageIsComplete($ties)) {
            throw new InvalidArgumentException(__('Not all ties in this stage are finished.'));
        }

        $winnerTeamIds = [];
        foreach ($ties as $tie) {
            $winnerTeamIds[] = (int) $tie->winner_team_id;
        }

        $nextOrderIndex = $this->nextOrderIndex($stage);
        $nextUnitCount = (int) (count($winnerTeamIds) / 2);

        $newStage = Stage::query()->create([
            'event_id' => $event->id,
            'name' => BracketStageNaming::stageNameForPlayers($nextUnitCount * 2),
            'best_of' => $bestOf,
            'order_index' => $nextOrderIndex,
            'status' => 'pending',
        ]);

        for ($i = 0; $i < count($winnerTeamIds); $i += 2) {
            $this->createTeamTieFromWinnerTeams(
                $newStage,
                $winnerTeamIds[$i],
                $winnerTeamIds[$i + 1]
            );
        }

        return $newStage;
    }

    private function nextOrderIndex(Stage $stage): int
    {
        $max = (int) Stage::query()->where('event_id', $stage->event_id)->max('order_index');

        return $max + 1;
    }

    /**
     * @return array{singles: string}|array{doubles: array{0: string, 1: string}}
     */
    private function individualWinnerPayload(MatchModel $match, Event $event): array
    {
        $side = $match->winner_side;
        if (! in_array($side, ['a', 'b'], true)) {
            throw new InvalidArgumentException(__('Invalid winner for a match in this stage.'));
        }

        if ($event->event_type === Event::EVENT_TYPE_SINGLES) {
            return [
                'singles' => $side === 'a' ? (string) $match->side_a_label : (string) $match->side_b_label,
            ];
        }

        $players = $match->matchPlayers
            ->where('side', $side)
            ->sortBy('position')
            ->values();

        if ($players->count() < 2) {
            throw new InvalidArgumentException(__('Doubles match is missing player rows for the winning side.'));
        }

        return [
            'doubles' => [
                (string) $players[0]->player_name,
                (string) $players[1]->player_name,
            ],
        ];
    }

    /**
     * @param  array<int, array{singles: string}|array{doubles: array{0: string, 1: string}}>  $winners
     * @return array<int, array{left: string, right: string}>
     */
    private function buildPairLabels(array $winners, Event $event): array
    {
        $pairs = [];
        for ($i = 0; $i < count($winners); $i += 2) {
            if ($event->event_type === Event::EVENT_TYPE_SINGLES) {
                $pairs[] = [
                    'left' => $winners[$i]['singles'],
                    'right' => $winners[$i + 1]['singles'],
                ];
            } else {
                $a = $winners[$i]['doubles'];
                $b = $winners[$i + 1]['doubles'];
                $pairs[] = [
                    'left' => $a[0].' / '.$a[1],
                    'right' => $b[0].' / '.$b[1],
                ];
            }
        }

        return $pairs;
    }

    /**
     * @param  array{singles: string}  $left
     * @param  array{singles: string}  $right
     */
    private function createSinglesMatchFromWinners(Stage $stage, array $left, array $right): void
    {
        $playerA = $left['singles'];
        $playerB = $right['singles'];

        $winnerSide = null;
        $status = 'pending';

        if ($this->isBye($playerA) xor $this->isBye($playerB)) {
            $winnerSide = $this->isBye($playerA) ? 'b' : 'a';
            $status = 'completed';
        }

        $match = MatchModel::query()->create([
            'stage_id' => $stage->id,
            'tie_id' => null,
            'side_a_label' => $playerA,
            'side_b_label' => $playerB,
            'match_order' => null,
            'best_of' => $stage->best_of,
            'status' => $status,
            'winner_side' => $winnerSide,
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => null,
            'ended_at' => null,
        ]);

        $match->matchPlayers()->createMany([
            [
                'side' => 'a',
                'player_name' => $playerA,
                'position' => 1,
            ],
            [
                'side' => 'b',
                'player_name' => $playerB,
                'position' => 1,
            ],
        ]);
    }

    /**
     * @param  array{doubles: array{0: string, 1: string}}  $left
     * @param  array{doubles: array{0: string, 1: string}}  $right
     */
    private function createDoublesMatchFromWinners(Stage $stage, array $left, array $right): void
    {
        $dA = $left['doubles'];
        $dB = $right['doubles'];

        $sideALabel = $dA[0].' / '.$dA[1];
        $sideBLabel = $dB[0].' / '.$dB[1];

        $isSideABye = $this->isBye($dA[0]) || $this->isBye($dA[1]);
        $isSideBBye = $this->isBye($dB[0]) || $this->isBye($dB[1]);

        $winnerSide = null;
        $status = 'pending';

        if ($isSideABye xor $isSideBBye) {
            $winnerSide = $isSideABye ? 'b' : 'a';
            $status = 'completed';
        }

        $match = MatchModel::query()->create([
            'stage_id' => $stage->id,
            'tie_id' => null,
            'side_a_label' => $sideALabel,
            'side_b_label' => $sideBLabel,
            'match_order' => null,
            'best_of' => $stage->best_of,
            'status' => $status,
            'winner_side' => $winnerSide,
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => null,
            'ended_at' => null,
        ]);

        $match->matchPlayers()->createMany([
            [
                'side' => 'a',
                'player_name' => $dA[0],
                'position' => 1,
            ],
            [
                'side' => 'a',
                'player_name' => $dA[1],
                'position' => 2,
            ],
            [
                'side' => 'b',
                'player_name' => $dB[0],
                'position' => 1,
            ],
            [
                'side' => 'b',
                'player_name' => $dB[1],
                'position' => 2,
            ],
        ]);
    }

    private function createTeamTieFromWinnerTeams(Stage $stage, int $teamAId, int $teamBId): void
    {
        $teamA = Team::query()->whereKey($teamAId)->firstOrFail();
        $teamB = Team::query()->whereKey($teamBId)->firstOrFail();

        $isTeamABye = $this->isBye($teamA->name);
        $isTeamBBye = $this->isBye($teamB->name);

        $winnerTeamId = null;
        $status = 'pending';

        if ($isTeamABye xor $isTeamBBye) {
            $winnerTeamId = $isTeamABye ? $teamB->id : $teamA->id;
            $status = 'completed';
        }

        $tie = Tie::query()->create([
            'stage_id' => $stage->id,
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamB->id,
            'winner_team_id' => $winnerTeamId,
            'status' => $status,
        ]);

        $matchStatus = $status === 'completed' ? 'not_required' : 'pending';
        $matchOrders = ['S1', 'D1', 'S2', 'D2', 'S3'];

        foreach ($matchOrders as $matchOrder) {
            MatchModel::query()->create([
                'stage_id' => $stage->id,
                'tie_id' => $tie->id,
                'side_a_label' => $teamA->name,
                'side_b_label' => $teamB->name,
                'match_order' => $matchOrder,
                'best_of' => $stage->best_of,
                'status' => $matchStatus,
                'winner_side' => null,
                'umpire_name' => null,
                'service_judge_name' => null,
                'court' => null,
                'started_at' => null,
                'ended_at' => null,
            ]);
        }
    }

    private function isBye(string $value): bool
    {
        return mb_strtolower(trim($value)) === 'bye';
    }
}
