<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Component;

new class extends Component {
    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public int $matchId;

    /** Show all events (including points/undo) vs main events only. Default true = full timeline on open. */
    public bool $showFullTimeline = true;

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

    private function match(): MatchModel
    {
        return MatchModel::query()
            ->where('stage_id', $this->stageId)
            ->whereKey($this->matchId)
            ->with([
                'games' => fn ($q) => $q->orderBy('game_number'),
                'matchEvents' => fn ($q) => $q->with('game')->orderBy('created_at'),
                'tie.teamA',
                'tie.teamB',
                'tie.matches',
            ])
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
    $match = $this->match();
    $matchTopLevelSequence = $match->topLevelSequenceInStage();

    $isNotRequired = $match->status === 'not_required';
    $matchStatusLabels = [
        'pending' => __('Pending'),
        'in_progress' => __('In Progress'),
        'completed' => __('Completed'),
        'walkover' => __('Walkover'),
        'retired' => __('Retired'),
        'not_required' => __('Not Required'),
    ];

    $tieWinsA = 0;
    $tieWinsB = 0;
    $tieWinnerLabel = null;
    if ($match->tie_id && $match->tie) {
        $tieWinsA = $match->tie->matches
            ->whereIn('status', ['completed', 'walkover'])
            ->where('winner_side', 'a')
            ->count();
        $tieWinsB = $match->tie->matches
            ->whereIn('status', ['completed', 'walkover'])
            ->where('winner_side', 'b')
            ->count();

        $tieWinnerLabel = $match->tie->winner_team_id
            ? ($match->tie->team_a_id === $match->tie->winner_team_id ? $match->tie->teamA?->name : $match->tie->teamB?->name)
            : null;
    }
@endphp

<div class="flex w-full flex-col gap-6 rounded-xl">
    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading class="text-lg font-semibold">
                        @if ($match->tie_id && $match->match_order)
                            @php
                                $tieOrderNumber = match ((string) $match->match_order) {
                                    'S1' => 1,
                                    'D1' => 2,
                                    'S2' => 3,
                                    'D2' => 4,
                                    'S3' => 5,
                                    default => null,
                                };
                            @endphp
                            {{ __('Match') }} {{ $tieOrderNumber ?? $match->match_order }}
                        @elseif ($matchTopLevelSequence !== null)
                            {{ __('Match') }} {{ $matchTopLevelSequence }}
                        @else
                            {{ __('Match') }}
                        @endif
                    </flux:heading>
                    <span class="text-xs font-normal text-neutral-400 dark:text-neutral-500">id {{ $match->id }}</span>
                </div>
                <flux:subheading>
                    {{ $event->event_name }} — {{ $stage->name }}
                </flux:subheading>
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ $match->side_a_label }} <span class="mx-1 font-semibold">vs</span> {{ $match->side_b_label }}
                </div>
                @if ($match->tie_id && $match->tie)
                    <div class="text-xs text-neutral-600 dark:text-neutral-300">
                        {{ __('Tie') }}: {{ $match->tie->teamA?->name }} vs {{ $match->tie->teamB?->name }}
                        @if ($tieWinnerLabel)
                            <span class="font-medium">
                                ({{ $tieWinsA }}-{{ $tieWinsB }}, {{ __('Winner') }}: {{ $tieWinnerLabel }})
                            </span>
                        @endif
                    </div>
                @endif
                <div class="flex flex-wrap items-center gap-3">
                    @if ($match->winner_side)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <flux:icon name="trophy" class="size-4" />
                            {{ __('Winner') }}: {{ $match->winner_side === 'a' ? $match->side_a_label : $match->side_b_label }}
                        </span>
                    @endif
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ $matchStatusLabels[$match->status] ?? $match->status }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('tournaments.events.stages.matches.pdf', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id, 'match' => $match->id]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    <flux:icon name="printer" class="size-4" />
                    {{ __('Print / Download PDF') }}
                </a>
                <a
                    wire:navigate
                    href="{{ route('tournaments.events.stages.show', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id]) }}"
                    class="inline-flex items-center rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    {{ __('Back to Stage') }}
                </a>
            </div>
        </div>

        @if ($match->status === 'pending')
            <div class="mt-6 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                {{ __('Match scoring interface will be implemented here.') }}
            </div>
        @elseif ($match->status === 'not_required')
            <div class="mt-6 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                <span class="font-semibold">{{ __('Not Required') }}</span>
                <span class="text-neutral-600 dark:text-neutral-300">({{ __('tie decided early') }})</span>
            </div>
        @else
            @php
                $mainEventTypes = ['match_started', 'occurrence', 'game_ended', 'match_ended', 'bulk_score_entry', 'score_correction', 'player_edit', 'match_reset'];
                $displayEvents = $this->showFullTimeline
                    ? $match->matchEvents
                    : $match->matchEvents->filter(fn ($e) => in_array($e->event_type, $mainEventTypes, true));
            @endphp

            {{-- Round scores --}}
            @if ($match->games->isNotEmpty())
                <div class="mt-8">
                    <h2 class="mb-4 font-serif text-lg font-semibold tracking-tight text-neutral-800 dark:text-neutral-100">
                        {{ __('Round Scores') }}
                    </h2>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($match->games as $game)
                            <div @class([
                                'flex flex-col rounded-xl border px-5 py-4 shadow-sm transition-shadow',
                                'border-emerald-200 bg-emerald-50/50 dark:border-emerald-800/50 dark:bg-emerald-900/20' => $game->winner_side,
                                'border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900/50' => ! $game->winner_side,
                            ])>
                                <span class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                    {{ __('Round') }} {{ $game->game_number }}
                                </span>
                                <span class="mt-1 font-mono text-2xl font-bold tabular-nums text-neutral-800 dark:text-neutral-100">
                                    {{ $game->score_a }} – {{ $game->score_b }}
                                </span>
                                @if ($game->winner_side)
                                    <span class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                        {{ $game->winner_side === 'a' ? $match->side_a_label : $match->side_b_label }} {{ __('wins') }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Match details --}}
            @if ($match->umpire_name || $match->court || $match->started_at || $match->ended_at)
                <div class="mt-8 flex flex-wrap gap-6 rounded-xl border border-neutral-200 bg-neutral-50/60 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/40">
                    @if ($match->court)
                        <span class="text-sm text-neutral-600 dark:text-neutral-300">
                            <span class="font-medium text-neutral-500 dark:text-neutral-400">{{ __('Court') }}:</span> {{ $match->court }}
                        </span>
                    @endif
                    @if ($match->umpire_name)
                        <span class="text-sm text-neutral-600 dark:text-neutral-300">
                            <span class="font-medium text-neutral-500 dark:text-neutral-400">{{ __('Umpire') }}:</span> {{ $match->umpire_name }}
                        </span>
                    @endif
                    @if ($match->started_at)
                        <span class="text-sm text-neutral-600 dark:text-neutral-300">
                            <span class="font-medium text-neutral-500 dark:text-neutral-400">{{ __('Started') }}:</span> {{ $match->started_at->format('M j, Y H:i') }}
                        </span>
                    @endif
                    @if ($match->ended_at)
                        <span class="text-sm text-neutral-600 dark:text-neutral-300">
                            <span class="font-medium text-neutral-500 dark:text-neutral-400">{{ __('Ended') }}:</span> {{ $match->ended_at->format('M j, Y H:i') }}
                        </span>
                    @endif
                </div>
            @endif

            {{-- Events / Timeline --}}
            @if ($match->matchEvents->isNotEmpty())
                <div class="mt-10">
                    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="font-serif text-lg font-semibold tracking-tight text-neutral-800 dark:text-neutral-100">
                            {{ __('Match Events') }}
                        </h2>
                        <label class="inline-flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-2 dark:border-neutral-700 dark:bg-zinc-900/50">
                            <flux:switch wire:model.live="showFullTimeline" />
                            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ __('Show full timeline') }}</span>
                        </label>
                    </div>
                    <div class="max-h-[420px] overflow-y-auto rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900/50">
                        <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($displayEvents as $evt)
                                <li @class([
                                    'flex items-start gap-4 border-l-4 py-4 pl-5 pr-4 transition-colors',
                                    'border-l-emerald-500/50' => $evt->event_type === 'point',
                                    'border-l-amber-500/50' => in_array($evt->event_type, ['undo', 'occurrence', 'game_ended'], true),
                                    'border-l-emerald-600' => $evt->event_type === 'match_ended',
                                    'border-l-transparent' => ! in_array($evt->event_type, ['point', 'undo', 'occurrence', 'game_ended', 'match_ended'], true),
                                ])>
                                    @if ($evt->event_type === 'point')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                                            <flux:icon name="plus" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'undo')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                                            <flux:icon name="arrow-uturn-left" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'occurrence')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                                            <flux:icon name="exclamation-triangle" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'game_ended')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                                            <flux:icon name="trophy" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'match_ended')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                                            <flux:icon name="check-circle" class="size-5" />
                                        </span>
                                    @else
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                            <flux:icon name="information-circle" class="size-5" />
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-neutral-800 dark:text-neutral-100">
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
                                            @elseif ($evt->event_type === 'bulk_score_entry')
                                                {{ __('Bulk score entry') }}
                                            @elseif ($evt->event_type === 'score_correction')
                                                {{ __('Score correction') }}
                                            @elseif ($evt->event_type === 'player_edit')
                                                {{ __('Player edit') }}
                                            @elseif ($evt->event_type === 'match_reset')
                                                {{ __('Match reset') }}
                                            @else
                                                {{ $evt->event_type }}
                                            @endif
                                        </span>
                                        <span class="ml-2 font-mono text-sm text-neutral-500 dark:text-neutral-400">
                                            {{ $evt->score_a_at_time }}–{{ $evt->score_b_at_time }}
                                            @if ($evt->game)
                                                ({{ __('G') }}{{ $evt->game->game_number }})
                                            @endif
                                        </span>
                                        <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $evt->created_at->format('H:i:s') }}
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="flex min-h-[120px] items-center justify-center py-12 text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ $this->showFullTimeline ? __('No events.') : __('No main events. Toggle "Show full timeline" to see all events.') }}
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
