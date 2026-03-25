<?php

use App\Models\Event;
use App\Models\Game;
use App\Models\MatchEvent;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Team;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use App\Livewire\Concerns\HandlesBulkScoreEntry;
use App\Services\NextStageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    use HandlesBulkScoreEntry;

    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public bool $canManageTournament = false;

    public bool $showTeamTiesModal = false;

    public bool $showMatchesForm = false;

    public bool $showGenerateNextStageModal = false;

    /** @var array<string, mixed>|null */
    public ?array $nextStagePreview = null;

    public int $nextStageBestOf = 3;

    public string $activeTab = 'setup';

    /** @var array<int, array<string, string>> */
    public array $singleMatches = [];

    /** @var array<int, array<string, string>> */
    public array $doubleMatches = [];

    /** @var array<int, array<string, string>> */
    public array $teamTies = [];

    /** @var array<string, string> */
    public array $teamPlayerInputs = [];

    public function mount(int $tournament, int $event, int $stage): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;
        $this->stageId = $stage;

        $tournamentModel = $this->tournament();
        $eventModel = $this->event();
        $stageModel = $this->stage();
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournamentModel),
            403
        );

        abort_unless((int) $eventModel->tournament_id === (int) $tournamentModel->id, 404);
        abort_unless((int) $stageModel->event_id === (int) $eventModel->id, 404);

        $this->canManageTournament = $user->role === User::ROLE_ADMIN
            && (int) $tournamentModel->admin_id === (int) $user->id;
    }

    protected function bulkScoreStageId(): int
    {
        return $this->stageId;
    }

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

    private function canAdminister(Tournament $tournament): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->role === User::ROLE_ADMIN
            && (int) $tournament->admin_id === (int) $user->id;
    }

    private function stageRowsCount(): int
    {
        $name = (string) $this->stage()->name;
        if (preg_match('/(\d+)/', $name, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return match ($name) {
            'Final' => 1,
            'Semi Final' => 2,
            'Quarter Final' => 4,
            default => 1,
        };
    }

    private function isBye(string $value): bool
    {
        return mb_strtolower(trim($value)) === 'bye';
    }

    public function openCreationFlow(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $event = $this->event();

        if ($event->event_type === Event::EVENT_TYPE_TEAM) {
            $this->prepareTeamTieRows();
            $this->showTeamTiesModal = true;

            return;
        }

        if ($event->event_type === Event::EVENT_TYPE_DOUBLES) {
            $this->prepareDoubleMatchRows();
        } else {
            $this->prepareSingleMatchRows();
        }

        $this->showMatchesForm = true;
    }

    public function closeTeamTiesModal(): void
    {
        $this->showTeamTiesModal = false;
        $this->resetErrorBag();
    }

    public function hideMatchesForm(): void
    {
        $this->showMatchesForm = false;
        $this->resetErrorBag();
    }

    private function prepareSingleMatchRows(): void
    {
        $rows = [];
        for ($index = 1; $index <= $this->stageRowsCount(); $index++) {
            $rows[] = [
                'match_label' => 'Match '.$index,
                'player_a' => '',
                'player_b' => '',
            ];
        }

        $this->singleMatches = $rows;
    }

    private function prepareDoubleMatchRows(): void
    {
        $rows = [];
        for ($index = 1; $index <= $this->stageRowsCount(); $index++) {
            $rows[] = [
                'match_label' => 'Match '.$index,
                'player_a_1' => '',
                'player_a_2' => '',
                'player_b_1' => '',
                'player_b_2' => '',
            ];
        }

        $this->doubleMatches = $rows;
    }

    private function prepareTeamTieRows(): void
    {
        $rows = [];
        for ($index = 1; $index <= $this->stageRowsCount(); $index++) {
            $rows[] = [
                'tie_label' => 'Tie '.$index,
                'team_a_name' => '',
                'team_b_name' => '',
            ];
        }

        $this->teamTies = $rows;
    }

    public function createSinglesMatches(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);
        abort_unless($this->event()->event_type === Event::EVENT_TYPE_SINGLES, 422);

        $rules = [
            'singleMatches' => ['required', 'array', 'size:'.$this->stageRowsCount()],
            'singleMatches.*.player_a' => ['required', 'string', 'max:255'],
            'singleMatches.*.player_b' => ['required', 'string', 'max:255'],
        ];

        $validated = $this->validate($rules);
        $stage = $this->stage();

        DB::transaction(function () use ($validated, $stage): void {
            foreach ($validated['singleMatches'] as $matchData) {
                $isSideABye = $this->isBye($matchData['player_a']);
                $isSideBBye = $this->isBye($matchData['player_b']);

                $winnerSide = null;
                $status = 'pending';

                if ($isSideABye xor $isSideBBye) {
                    $winnerSide = $isSideABye ? 'b' : 'a';
                    $status = 'completed';
                }

                $match = MatchModel::query()->create([
                    'stage_id' => $stage->id,
                    'tie_id' => null,
                    'side_a_label' => $matchData['player_a'],
                    'side_b_label' => $matchData['player_b'],
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
                        'player_name' => $matchData['player_a'],
                        'position' => 1,
                    ],
                    [
                        'side' => 'b',
                        'player_name' => $matchData['player_b'],
                        'position' => 1,
                    ],
                ]);
            }
        });

        $this->showMatchesForm = false;
    }

    public function createDoublesMatches(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);
        abort_unless($this->event()->event_type === Event::EVENT_TYPE_DOUBLES, 422);

        $rules = [
            'doubleMatches' => ['required', 'array', 'size:'.$this->stageRowsCount()],
            'doubleMatches.*.player_a_1' => ['required', 'string', 'max:255'],
            'doubleMatches.*.player_a_2' => ['required', 'string', 'max:255'],
            'doubleMatches.*.player_b_1' => ['required', 'string', 'max:255'],
            'doubleMatches.*.player_b_2' => ['required', 'string', 'max:255'],
        ];

        $validated = $this->validate($rules);
        $stage = $this->stage();

        DB::transaction(function () use ($validated, $stage): void {
            foreach ($validated['doubleMatches'] as $matchData) {
                $sideALabel = $matchData['player_a_1'].' / '.$matchData['player_a_2'];
                $sideBLabel = $matchData['player_b_1'].' / '.$matchData['player_b_2'];

                $isSideABye = $this->isBye($matchData['player_a_1']) || $this->isBye($matchData['player_a_2']);
                $isSideBBye = $this->isBye($matchData['player_b_1']) || $this->isBye($matchData['player_b_2']);

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
                        'player_name' => $matchData['player_a_1'],
                        'position' => 1,
                    ],
                    [
                        'side' => 'a',
                        'player_name' => $matchData['player_a_2'],
                        'position' => 2,
                    ],
                    [
                        'side' => 'b',
                        'player_name' => $matchData['player_b_1'],
                        'position' => 1,
                    ],
                    [
                        'side' => 'b',
                        'player_name' => $matchData['player_b_2'],
                        'position' => 2,
                    ],
                ]);
            }
        });

        $this->showMatchesForm = false;
    }

    public function createTeamTies(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);
        abort_unless($this->event()->event_type === Event::EVENT_TYPE_TEAM, 422);

        $rules = [
            'teamTies' => ['required', 'array', 'size:'.$this->stageRowsCount()],
            'teamTies.*.team_a_name' => ['required', 'string', 'max:255'],
            'teamTies.*.team_b_name' => ['required', 'string', 'max:255'],
        ];

        $validated = $this->validate($rules);
        $event = $this->event();
        $stage = $this->stage();

        DB::transaction(function () use ($validated, $event, $stage): void {
            foreach ($validated['teamTies'] as $tieData) {
                $teamA = Team::query()->firstOrCreate([
                    'event_id' => $event->id,
                    'name' => $tieData['team_a_name'],
                ]);

                $teamB = Team::query()->firstOrCreate([
                    'event_id' => $event->id,
                    'name' => $tieData['team_b_name'],
                ]);

                $isTeamABye = $this->isBye($tieData['team_a_name']);
                $isTeamBBye = $this->isBye($tieData['team_b_name']);

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
        });

        $this->showTeamTiesModal = false;
        $this->activeTab = 'team_players';
    }

    public function openTeamPlayersTab(): void
    {
        $this->activeTab = 'team_players';
    }

    public function openSetupTab(): void
    {
        $this->activeTab = 'setup';
    }

    public function addTeamPlayer(int $teamId): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $team = Team::query()
            ->where('event_id', $this->eventId)
            ->whereKey($teamId)
            ->firstOrFail();

        $key = (string) $teamId;
        $this->validate([
            'teamPlayerInputs.'.$key => ['required', 'string', 'max:255'],
        ]);

        $team->teamPlayers()->create([
            'player_name' => $this->teamPlayerInputs[$key],
        ]);

        $this->teamPlayerInputs[$key] = '';
    }

    public function openGenerateNextStageModal(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        try {
            $this->nextStagePreview = app(NextStageService::class)->preview($this->stage());
            $this->nextStageBestOf = (int) $this->stage()->best_of;
            $this->showGenerateNextStageModal = true;
            $this->resetErrorBag();
        } catch (\InvalidArgumentException $e) {
            session()->flash('generate_next_stage_error', $e->getMessage());
        }
    }

    public function closeGenerateNextStageModal(): void
    {
        $this->showGenerateNextStageModal = false;
        $this->nextStagePreview = null;
        $this->nextStageBestOf = 3;
        $this->resetErrorBag();
    }

    public function confirmGenerateNextStage(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $this->validate([
            'nextStageBestOf' => ['required', 'integer', Rule::in([1, 3, 5])],
        ]);

        try {
            $newStage = app(NextStageService::class)->generate($this->stage(), $this->nextStageBestOf);
            $this->showGenerateNextStageModal = false;
            $this->nextStagePreview = null;
            $this->nextStageBestOf = 3;

            session()->flash('generate_next_stage_success', __('Next stage created successfully.'));

            $this->redirect(route('tournaments.events.stages.show', [
                'tournament' => $this->tournamentId,
                'event' => $this->eventId,
                'stage' => $newStage->id,
            ]), navigate: true);
        } catch (\InvalidArgumentException $e) {
            session()->flash('generate_next_stage_error', $e->getMessage());
        }
    }

    public function deletePendingTopLevelMatch(int $matchId): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $match = MatchModel::query()
            ->where('stage_id', $this->stageId)
            ->whereKey($matchId)
            ->firstOrFail();

        abort_unless($match->tie_id === null, 403);
        abort_unless($match->status === 'pending', 403);

        DB::transaction(function () use ($match): void {
            $match->delete();
        });

        session()->flash('match_deleted', __('Match removed.'));
    }

    public function deletePendingTie(int $tieId): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $tie = Tie::query()
            ->where('stage_id', $this->stageId)
            ->whereKey($tieId)
            ->with('matches')
            ->firstOrFail();

        abort_unless($tie->status === 'pending', 403);

        foreach ($tie->matches as $innerMatch) {
            abort_unless($innerMatch->status === 'pending', 403);
        }

        DB::transaction(function () use ($tie): void {
            foreach ($tie->matches as $innerMatch) {
                $innerMatch->delete();
            }
            $tie->delete();
        });

        session()->flash('tie_deleted', __('Tie removed.'));
    }
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $showGenerateNextStageButton = $canManageTournament && app(\App\Services\NextStageService::class)->canShowGenerateButton($stage);
    $ties = $stage->ties()->with(['teamA', 'teamB', 'matches'])->get();
    $matches = $stage->matches()->with('matchPlayers')->get();
    $topLevelMatchesOrdered = $matches->whereNull('tie_id')->sortBy('id')->values();
    /** @var array<int, int> */
    $topLevelMatchNumberById = $topLevelMatchesOrdered
        ->mapWithKeys(fn (MatchModel $m, int $i): array => [$m->id => $i + 1])
        ->all();
    $teams = $event->teams()->with('teamPlayers')->orderBy('name')->get();

    $matchOrderIndex = ['S1' => 0, 'D1' => 1, 'S2' => 2, 'D2' => 3, 'S3' => 4];
    $nextPendingMatchIdByTieId = [];
    if ((int) $event->event_type === (int) \App\Models\Event::EVENT_TYPE_TEAM) {
        $matchesByTie = $matches
            ->whereNotNull('tie_id')
            ->groupBy('tie_id');

        foreach ($matchesByTie as $tieId => $tieMatches) {
            /** @var \Illuminate\Support\Collection<int, \App\Models\MatchModel> $tieMatches */
            $sorted = $tieMatches->sortBy(fn ($m) => $matchOrderIndex[$m->match_order] ?? 999)->values();
            $nextPending = $sorted->first(fn ($m) => (string) $m->status === 'pending');
            $nextPendingMatchIdByTieId[$tieId] = $nextPending?->id;
        }
    }

    $bulkScoreMatch = $showBulkScoreModal && $bulkScoreMatchId
        ? \App\Models\MatchModel::query()->where('stage_id', $stage->id)->whereKey($bulkScoreMatchId)->with(['games' => fn ($q) => $q->orderBy('game_number')])->first()
        : null;
@endphp

<div class="flex w-full flex-col gap-6 rounded-xl">
    @if (session('bulk_score_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            {{ session('bulk_score_success') }}
        </div>
    @endif

    @if (session('generate_next_stage_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            {{ session('generate_next_stage_success') }}
        </div>
    @endif

    @if (session('generate_next_stage_error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ session('generate_next_stage_error') }}
        </div>
    @endif

    @if (session('match_deleted'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            {{ session('match_deleted') }}
        </div>
    @endif

    @if (session('tie_deleted'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            {{ session('tie_deleted') }}
        </div>
    @endif

    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading class="text-lg font-semibold">{{ $stage->name }}</flux:heading>
                <flux:subheading>
                    {{ __('Event') }}: {{ $event->event_name }} ({{ ucfirst((string) $event->event_type) }})
                </flux:subheading>
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('Best of') }}: <span class="font-semibold">{{ $stage->best_of }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    wire:navigate
                    href="{{ route('tournaments.events.show', ['tournament' => $tournament->id, 'event' => $event->id]) }}"
                    class="inline-flex items-center rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    {{ __('Back to Event') }}
                </a>

                @if ($canManageTournament)
                    @if ($showGenerateNextStageButton)
                        <flux:button variant="primary" wire:click="openGenerateNextStageModal">
                            {{ __('Generate next stage') }}
                        </flux:button>
                    @endif

                    <flux:button variant="primary" wire:click="openCreationFlow">
                        {{ __('Create Ties/Matches') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="openSetupTab"
                class="rounded-md px-3 py-2 text-sm font-medium {{ $activeTab === 'setup' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}"
            >
                {{ __('Setup') }}
            </button>
            <button
                type="button"
                wire:click="openTeamPlayersTab"
                class="rounded-md px-3 py-2 text-sm font-medium {{ $activeTab === 'team_players' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}"
            >
                {{ __('Team Players') }}
            </button>
        </div>

        @if ($activeTab === 'setup')
            <div class="mt-5 space-y-4">
                @if ($event->event_type === Event::EVENT_TYPE_TEAM)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        {{ __('Use "Create Ties/Matches" to open tie creation modal for this team event.') }}
                    </div>

                    @if ($ties->isNotEmpty())
                        <div class="space-y-3">
                            @foreach ($ties as $tie)
                                @php
                                    $tieWinner = $tie->winner_team_id ? ($tie->team_a_id === $tie->winner_team_id ? $tie->teamA?->name : $tie->teamB?->name) : null;
                                    $winsA = $tie->matches
                                        ->whereIn('status', ['completed', 'walkover'])
                                        ->where('winner_side', 'a')
                                        ->count();
                                    $winsB = $tie->matches
                                        ->whereIn('status', ['completed', 'walkover'])
                                        ->where('winner_side', 'b')
                                        ->count();
                                @endphp
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold">{{ __('Tie') }} #{{ $tie->id }}</span>
                                            <flux:button
                                                variant="outline"
                                                size="sm"
                                                wire:navigate
                                                href="{{ route('tournaments.events.stages.ties.show', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'tie' => $tie->id]) }}"
                                            >
                                                {{ __('View tie') }}
                                            </flux:button>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span @class([
                                                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' => $tie->status === 'completed',
                                                'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400' => $tie->status === 'in_progress',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' => $tie->status === 'pending',
                                            ])>
                                                {{ $tie->status === 'completed' ? __('Completed') : ($tie->status === 'in_progress' ? __('In Progress') : __('Pending')) }}
                                            </span>
                                            @if ($canManageTournament && $tie->status === 'pending' && $tie->matches->every(fn ($m) => $m->status === 'pending'))
                                                <flux:button
                                                    type="button"
                                                    variant="danger"
                                                    size="sm"
                                                    wire:click="deletePendingTie({{ $tie->id }})"
                                                    wire:confirm="{{ __('Delete this pending tie and its matches?') }}"
                                                >
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-1 gap-y-1 text-neutral-700 dark:text-neutral-300">
                                        <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $tie->winner_team_id === $tie->team_a_id])>{{ $tie->teamA?->name }}</span>
                                        <span class="font-semibold text-neutral-500 mx-2">vs</span>
                                        <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $tie->winner_team_id === $tie->team_b_id])>{{ $tie->teamB?->name }}</span>
                                        @if ($tieWinner)
                                            <span class="ml-2 text-xs text-neutral-500 dark:text-neutral-400">({{ __('Winner') }}: {{ $tieWinner }} · {{ $winsA }}-{{ $winsB }})</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    @if ($showMatchesForm && $event->event_type === Event::EVENT_TYPE_SINGLES)
                        <form wire:submit="createSinglesMatches" class="space-y-4">
                            @foreach ($singleMatches as $index => $row)
                                <div wire:key="single-match-row-{{ $index }}" class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="mb-3 text-sm font-semibold text-neutral-900 dark:text-white">{{ $row['match_label'] }}</div>
                                    <div class="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-center">
                                        <flux:input wire:model.live="singleMatches.{{ $index }}.player_a" :label="__('Player')" type="text" />
                                        <div class="text-center text-sm font-semibold text-neutral-500">VS</div>
                                        <flux:input wire:model.live="singleMatches.{{ $index }}.player_b" :label="__('Player')" type="text" />
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex justify-end gap-3">
                                <flux:button type="button" variant="outline" wire:click="hideMatchesForm">{{ __('Cancel') }}</flux:button>
                                <flux:button type="submit" variant="primary">{{ __('Create Matches') }}</flux:button>
                            </div>
                        </form>
                    @elseif ($showMatchesForm && $event->event_type === Event::EVENT_TYPE_DOUBLES)
                        <form wire:submit="createDoublesMatches" class="space-y-4">
                            @foreach ($doubleMatches as $index => $row)
                                <div wire:key="double-match-row-{{ $index }}" class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="mb-3 text-sm font-semibold text-neutral-900 dark:text-white">{{ $row['match_label'] }}</div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <flux:input wire:model.live="doubleMatches.{{ $index }}.player_a_1" :label="__('Team A - Player 1')" type="text" />
                                        <flux:input wire:model.live="doubleMatches.{{ $index }}.player_a_2" :label="__('Team A - Player 2')" type="text" />
                                        <flux:input wire:model.live="doubleMatches.{{ $index }}.player_b_1" :label="__('Team B - Player 1')" type="text" />
                                        <flux:input wire:model.live="doubleMatches.{{ $index }}.player_b_2" :label="__('Team B - Player 2')" type="text" />
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex justify-end gap-3">
                                <flux:button type="button" variant="outline" wire:click="hideMatchesForm">{{ __('Cancel') }}</flux:button>
                                <flux:button type="submit" variant="primary">{{ __('Create Matches') }}</flux:button>
                            </div>
                        </form>
                    @endif

                    @if ($topLevelMatchesOrdered->isNotEmpty())
                        <div class="space-y-3">
                            @foreach ($topLevelMatchesOrdered as $match)
                                @php
                                    $winnerLabel = $match->winner_side === 'a' ? $match->side_a_label : ($match->winner_side === 'b' ? $match->side_b_label : null);
                                    $matchEnded = in_array($match->status, ['completed', 'walkover', 'retired', 'not_required'], true);
                                    $matchInProgress = $match->status === 'in_progress';
                                    $statusLabels = [
                                        'completed' => __('Completed'),
                                        'walkover' => __('Walkover'),
                                        'retired' => __('Retired'),
                                        'not_required' => __('NOT REQUIRED'),
                                        'in_progress' => __('In Progress'),
                                    ];
                                @endphp
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-semibold">{{ __('Match') }} {{ $loop->iteration }}</span>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span @class([
                                                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' => $matchEnded,
                                                'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400' => $matchInProgress,
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' => ! $matchEnded && ! $matchInProgress,
                                            ])>
                                                {{ $matchEnded ? ($statusLabels[$match->status] ?? __('Completed')) : ($matchInProgress ? ($statusLabels['in_progress'] ?? __('In Progress')) : __('Pending')) }}
                                            </span>
                                            <span class="text-xs font-normal text-neutral-400 dark:text-neutral-500">id {{ $match->id }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-1 gap-y-1 text-neutral-700 dark:text-neutral-300">
                                        <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $match->winner_side === 'a'])>{{ $match->side_a_label }}</span>
                                        <span class="font-semibold text-neutral-500">vs</span>
                                        <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $match->winner_side === 'b'])>{{ $match->side_b_label }}</span>
                                        @if ($winnerLabel)
                                            <span class="ml-2 text-xs text-neutral-500 dark:text-neutral-400">({{ __('Winner') }}: {{ $winnerLabel }})</span>
                                        @endif
                                    </div>
                                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                                        @if (! $matchEnded)
                                            @php
                                                $isTeamInnerMatch = (string) $match->tie_id !== '' && (int) $event->event_type === (int) \App\Models\Event::EVENT_TYPE_TEAM;
                                                $isNextTieMatch = $isTeamInnerMatch && $nextPendingMatchIdByTieId[$match->tie_id] === $match->id;
                                            @endphp
                                            @if (! $isTeamInnerMatch || $isNextTieMatch)
                                                <flux:button
                                                    variant="primary"
                                                    size="sm"
                                                    wire:navigate
                                                    href="{{ route('tournaments.events.stages.matches.controlpanel', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'match' => $match->id]) }}"
                                                >
                                                    {{ __('Start') }}
                                                </flux:button>
                                                @if (auth()->user()?->role === User::ROLE_ADMIN)
                                                    <flux:button
                                                        variant="outline"
                                                        size="sm"
                                                        wire:click="openBulkScoreModal({{ $match->id }})"
                                                    >
                                                        {{ __('Enter Scores') }}
                                                    </flux:button>
                                                @endif
                                                @if ($canManageTournament && ! $isTeamInnerMatch && $match->status === 'pending')
                                                    <flux:button
                                                        type="button"
                                                        variant="danger"
                                                        size="sm"
                                                        wire:click="deletePendingTopLevelMatch({{ $match->id }})"
                                                        wire:confirm="{{ __('Delete this pending match?') }}"
                                                    >
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center rounded-md bg-neutral-100 px-3 py-2 text-sm font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                                    {{ __('Waiting for previous matches') }}
                                                </span>
                                            @endif
                                        @else
                                            <flux:button
                                                variant="outline"
                                                size="sm"
                                                wire:navigate
                                                href="{{ route('tournaments.events.stages.matches.show', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'match' => $match->id]) }}"
                                            >
                                                {{ __('Show results') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @else
            <div class="mt-5 space-y-4">
                @if ($event->event_type !== Event::EVENT_TYPE_TEAM)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        {{ __('Team players are available only for team events.') }}
                    </div>
                @elseif ($teams->isEmpty())
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        {{ __('No teams yet. Create ties first to generate teams.') }}
                    </div>
                @else
                    @foreach ($teams as $team)
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                            <div class="mb-3 text-sm font-semibold text-neutral-900 dark:text-white">{{ $team->name }}</div>

                            <div class="space-y-2">
                                @foreach ($team->teamPlayers as $teamPlayer)
                                    <div class="text-sm text-neutral-700 dark:text-neutral-200">{{ $teamPlayer->player_name }}</div>
                                @endforeach
                            </div>

                            @if ($canManageTournament)
                                <div class="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                    <flux:input wire:model.live="teamPlayerInputs.{{ $team->id }}" :label="__('Player Name')" type="text" />
                                    <flux:button variant="primary" wire:click="addTeamPlayer({{ $team->id }})">
                                        {{ __('Add Player') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endif
    </div>

    <flux:modal
        name="team-ties-modal"
        wire:model.self="showTeamTiesModal"
        focusable
        class=" max-w-7xl"
        @close="closeTeamTiesModal"
    >
        <form wire:submit="createTeamTies" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Create Ties') }}</flux:heading>
                <flux:subheading>{{ __('Enter Team A vs Team B for each tie row.') }}</flux:subheading>
            </div>

            <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                @foreach ($teamTies as $index => $tieRow)
                    <div wire:key="team-tie-row-{{ $index }}" class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <div class="mb-3 text-sm font-semibold text-neutral-900 dark:text-white">{{ $tieRow['tie_label'] }}</div>
                        <div class="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-center">
                            <flux:input size="sm" wire:model.live="teamTies.{{ $index }}.team_a_name" :label="__('Team A')" type="text" />
                            <div class="text-center text-sm font-semibold text-neutral-500">VS</div>
                            <flux:input size="sm" wire:model.live="teamTies.{{ $index }}.team_b_name" :label="__('Team B')" type="text" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="outline" wire:click="closeTeamTiesModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Create Ties') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($canManageTournament)
        <flux:modal
            name="generate-next-stage-modal"
            wire:model.self="showGenerateNextStageModal"
            focusable
            class="max-w-lg"
            @close="closeGenerateNextStageModal"
        >
            @if ($nextStagePreview)
                <div class="space-y-6">
                    <div class="space-y-2">
                        <flux:heading size="lg">{{ __('Generate next stage') }}</flux:heading>
                        <flux:subheading>
                            {{ $nextStagePreview['next_stage_name'] }}
                            —
                            {{ $nextStagePreview['next_unit_count'] }}
                            {{ $nextStagePreview['next_unit_count'] === 1 ? __('match') : __('matches') }}
                        </flux:subheading>
                        <flux:field class="mt-3">
                            <flux:label>{{ __('Best of (next stage)') }}</flux:label>
                            <flux:select wire:model.live="nextStageBestOf">
                                <flux:select.option value="1">{{ __('1 game') }}</flux:select.option>
                                <flux:select.option value="3">{{ __('3 games') }}</flux:select.option>
                                <flux:select.option value="5">{{ __('5 games') }}</flux:select.option>
                            </flux:select>
                            <flux:error name="nextStageBestOf" />
                        </flux:field>
                        @if (($nextStagePreview['event_type'] ?? '') === \App\Models\Event::EVENT_TYPE_TEAM)
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Preview of tie pairings from tie winners.') }}</p>
                        @else
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Preview of match pairings from winners.') }}</p>
                        @endif
                    </div>

                    <div class="max-h-[50vh] space-y-3 overflow-y-auto pr-1">
                        @foreach ($nextStagePreview['pairs'] as $index => $pair)
                            <div
                                wire:key="next-stage-pair-{{ $index }}"
                                class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <div class="font-medium text-neutral-500 dark:text-neutral-400">
                                    @if (($nextStagePreview['event_type'] ?? '') === \App\Models\Event::EVENT_TYPE_TEAM)
                                        {{ __('Tie') }} {{ $index + 1 }}
                                    @else
                                        {{ __('Match') }} {{ $index + 1 }}
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-neutral-900 dark:text-white">
                                    <span class="font-semibold">{{ $pair['left'] }}</span>
                                    <span class="text-neutral-500">{{ __('vs') }}</span>
                                    <span class="font-semibold">{{ $pair['right'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" variant="outline" wire:click="closeGenerateNextStageModal">
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button type="button" variant="primary" wire:click="confirmGenerateNextStage">
                            {{ __('Generate') }}
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:modal>
    @endif

    @include('pages.event.partials.bulk-score-modal', [
        'bulkScoreMatch' => $bulkScoreMatch,
        'bulkScores' => $bulkScores,
        'topLevelMatchNumberById' => $topLevelMatchNumberById,
    ])
</div>