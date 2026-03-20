<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Team;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public bool $canManageTournament = false;

    public bool $showTeamTiesModal = false;

    public bool $showMatchesForm = false;

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

                Tie::query()->create([
                    'stage_id' => $stage->id,
                    'team_a_id' => $teamA->id,
                    'team_b_id' => $teamB->id,
                    'winner_team_id' => $winnerTeamId,
                    'status' => $status,
                ]);
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
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $ties = $stage->ties()->with(['teamA', 'teamB'])->get();
    $matches = $stage->matches()->with('matchPlayers')->get();
    $teams = $event->teams()->with('teamPlayers')->orderBy('name')->get();
@endphp

<div class="flex w-full flex-col gap-6 rounded-xl">
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
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="font-semibold">{{ __('Tie') }} #{{ $tie->id }}</div>
                                    <div class="mt-1 text-neutral-700 dark:text-neutral-300">
                                        {{ $tie->teamA?->name }} <span class="mx-1 font-semibold">vs</span> {{ $tie->teamB?->name }}
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

                    @if ($matches->isNotEmpty())
                        <div class="space-y-3">
                            @foreach ($matches as $match)
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="font-semibold">{{ __('Match') }} #{{ $match->id }}</div>
                                    <div class="mt-1 text-neutral-700 dark:text-neutral-300">
                                        {{ $match->side_a_label }} <span class="mx-1 font-semibold">vs</span> {{ $match->side_b_label }}
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
</div>