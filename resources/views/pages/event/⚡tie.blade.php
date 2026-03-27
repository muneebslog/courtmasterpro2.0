<?php

use App\Livewire\Concerns\HandlesBulkScoreEntry;
use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Component;

new class extends Component {
    use HandlesBulkScoreEntry;

    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public int $tieId;

    public bool $canManageTournament = false;

    public function mount(int $tournament, int $event, int $stage, int $tie): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;
        $this->stageId = $stage;
        $this->tieId = $tie;

        $tournamentModel = $this->tournament();
        $eventModel = $this->event();
        $stageModel = $this->stage();
        $tieModel = $this->tieModel();
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournamentModel),
            403
        );

        abort_unless((int) $eventModel->tournament_id === (int) $tournamentModel->id, 404);
        abort_unless((int) $stageModel->event_id === (int) $eventModel->id, 404);
        abort_unless((int) $tieModel->stage_id === (int) $stageModel->id, 404);
        abort_unless((string) $eventModel->event_type === Event::EVENT_TYPE_TEAM, 404);

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

    private function tieModel(): Tie
    {
        return Tie::query()
            ->where('stage_id', $this->stageId)
            ->whereKey($this->tieId)
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
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $tie = Tie::query()
        ->whereKey($this->tieId)
        ->with(['teamA', 'teamB', 'matches.matchPlayers'])
        ->firstOrFail();

    $matchOrderIndex = ['S1' => 0, 'D1' => 1, 'S2' => 2, 'D2' => 3, 'S3' => 4];
    $innerMatches = $tie->matches
        ->sortBy(fn ($m) => $matchOrderIndex[$m->match_order] ?? 999)
        ->values();

    $terminalStatuses = ['completed', 'walkover', 'retired', 'not_required'];
    $currentTieMatchId = null;
    foreach ($innerMatches as $m) {
        if (! in_array($m->status, $terminalStatuses, true)) {
            $currentTieMatchId = $m->id;
            break;
        }
    }

    $topLevelMatchNumberById = [];

    $bulkScoreMatch = $this->showBulkScoreModal && $this->bulkScoreMatchId
        ? MatchModel::query()->where('stage_id', $stage->id)->whereKey($this->bulkScoreMatchId)->with(['games' => fn ($q) => $q->orderBy('game_number')])->first()
        : null;

    $winsA = $tie->matches->where('winner_side', 'a')->count();
    $winsB = $tie->matches->where('winner_side', 'b')->count();
    $tieWinner = $tie->winner_team_id ? ($tie->team_a_id === $tie->winner_team_id ? $tie->teamA?->name : $tie->teamB?->name) : null;
@endphp

<div class="flex w-full flex-col gap-6 rounded-xl">
    @if (session('bulk_score_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            {{ session('bulk_score_success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading class="text-lg font-semibold">{{ __('Tie') }} #{{ $tie->id }}</flux:heading>
                <flux:subheading>
                    {{ $event->event_name }} — {{ $stage->name }}
                </flux:subheading>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-700 dark:text-neutral-200">
                    <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $tie->winner_team_id === $tie->team_a_id])>
                        {{ $tie->teamA?->flag ? $tie->teamA->flag.' ' : '' }}{{ $tie->teamA?->name }}
                    </span>
                    <span class="font-semibold text-neutral-500">{{ __('vs') }}</span>
                    <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $tie->winner_team_id === $tie->team_b_id])>
                        {{ $tie->teamB?->flag ? $tie->teamB->flag.' ' : '' }}{{ $tie->teamB?->name }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' => $tie->status === 'completed',
                        'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' => $tie->status === 'pending',
                    ])>
                        {{ $tie->status === 'completed' ? __('Completed') : __('Pending') }}
                    </span>
                    <span>{{ __('Rubber score') }}: {{ $winsA }}–{{ $winsB }}</span>
                    @if ($tieWinner)
                        <span class="text-neutral-500">({{ __('Winner') }}: {{ $tieWinner }})</span>
                    @endif
                </div>
            </div>

            <a
                wire:navigate
                href="{{ route('tournaments.events.stages.show', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id]) }}"
                class="inline-flex items-center rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
            >
                {{ __('Back to Stage') }}
            </a>
        </div>
    </div>

    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:heading class="mb-4 text-base font-semibold">{{ __('Matches') }}</flux:heading>

        <div class="space-y-3">
            @foreach ($innerMatches as $match)
                @php
                    $winnerLabel = $match->winner_side === 'a' ? $match->side_a_label : ($match->winner_side === 'b' ? $match->side_b_label : null);
                    $matchEnded = in_array($match->status, ['completed', 'walkover', 'retired', 'not_required'], true);
                    $statusLabels = [
                        'completed' => __('Completed'),
                        'walkover' => __('Walkover'),
                        'retired' => __('Retired'),
                        'not_required' => __('NOT REQUIRED'),
                    ];
                    $isCurrentTieMatch = $currentTieMatchId !== null && (int) $currentTieMatchId === (int) $match->id;
                @endphp
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold">{{ __('Match') }} {{ $loop->iteration }}</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' => $matchEnded,
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' => ! $matchEnded,
                            ])>
                                {{ $matchEnded ? ($statusLabels[$match->status] ?? __('Completed')) : __('Pending') }}
                            </span>
                            <span class="text-xs font-normal text-neutral-400 dark:text-neutral-500">id {{ $match->id }}</span>
                        </div>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-1 gap-y-1 text-neutral-700 dark:text-neutral-300">
                        <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $match->winner_side === 'a'])>{{ $match->side_a_label }}</span>
                        <span class="font-semibold text-neutral-500">{{ __('vs') }}</span>
                        <span @class(['font-semibold text-emerald-700 dark:text-emerald-400' => $match->winner_side === 'b'])>{{ $match->side_b_label }}</span>
                        @if ($winnerLabel)
                            <span class="ml-2 text-xs text-neutral-500 dark:text-neutral-400">({{ __('Winner') }}: {{ $winnerLabel }})</span>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        @if (! $matchEnded)
                            @if ($isCurrentTieMatch)
                                @if ($match->status === 'pending')
                                    <flux:button
                                        variant="primary"
                                        size="sm"
                                        wire:navigate
                                        href="{{ route('tournaments.events.stages.matches.controlpanel', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'match' => $match->id]) }}"
                                    >
                                        {{ __('Start') }}
                                    </flux:button>
                                @elseif ($match->status === 'in_progress')
                                    <flux:button
                                        variant="primary"
                                        size="sm"
                                        wire:navigate
                                        href="{{ route('tournaments.events.stages.matches.controlpanel', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'match' => $match->id]) }}"
                                    >
                                        {{ __('Continue') }}
                                    </flux:button>
                                @endif
                                @if (in_array($match->status, ['pending', 'in_progress'], true))
                                    <flux:button
                                        variant="outline"
                                        size="sm"
                                        wire:click="openBulkScoreModal({{ $match->id }})"
                                    >
                                        {{ __('Enter Scores') }}
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
    </div>

    @include('pages.event.partials.bulk-score-modal', [
        'bulkScoreMatch' => $bulkScoreMatch,
        'bulkScores' => $bulkScores,
        'topLevelMatchNumberById' => $topLevelMatchNumberById,
    ])
</div>
