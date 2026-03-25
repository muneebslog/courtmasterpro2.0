<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.viewer', ['title' => 'Match'])] class extends Component
{
    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public int $matchId;

    public bool $showFullTimeline = true;

    public function mount(int $tournament, int $event, int $stage, int $match): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;
        $this->stageId = $stage;
        $this->matchId = $match;

        abort_unless($this->tournament()->status === 'published', 404);
        abort_unless((int) $this->event()->tournament_id === $this->tournamentId, 404);
        abort_unless((int) $this->stage()->event_id === $this->eventId, 404);
        abort_unless((int) $this->matchModel()->stage_id === $this->stageId, 404);
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

    private function matchModel(): MatchModel
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
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $match = $this->matchModel();
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

    $backHref = $match->tie_id
        ? route('viewer.ties.show', [$tournament, $event, $stage, $match->tie_id])
        : route('viewer.stages.show', [$tournament, $event, $stage]);
    $backLabel = $match->tie_id ? __('Back to tie') : __('Back to stage');
@endphp

<div @if ($match->status === 'in_progress') wire:poll.4s @endif class="flex w-full flex-col gap-6">
    <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($match->status === 'in_progress')
                        <span class="relative flex size-2">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-60"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-red-500"></span>
                        </span>
                        <flux:badge color="red" size="sm">{{ __('Live') }}</flux:badge>
                    @endif
                    <flux:heading size="lg" class="text-white" style="font-family: 'Syne', sans-serif;">
                        @if ($match->tie_id && $match->match_order)
                            {{ __('Match') }} {{ $match->match_order }}
                        @elseif ($matchTopLevelSequence !== null)
                            {{ __('Match') }} {{ $matchTopLevelSequence }}
                        @else
                            {{ __('Match') }}
                        @endif
                    </flux:heading>
                </div>
                <flux:text class="text-emerald-100/65">
                    {{ $event->event_name }} — {{ $stage->name }}
                </flux:text>
                <div class="text-base text-white/90">
                    {{ $match->side_a_label }} <span class="mx-1 font-semibold text-emerald-400/90">vs</span> {{ $match->side_b_label }}
                </div>
                @if ($match->tie_id && $match->tie)
                    <flux:text class="text-sm text-white/55">
                        {{ __('Tie') }}: {{ $match->tie->teamA?->name }} vs {{ $match->tie->teamB?->name }}
                        @if ($tieWinnerLabel)
                            <span class="font-medium text-emerald-200/90">
                                ({{ $tieWinsA }}-{{ $tieWinsB }}, {{ __('Winner') }}: {{ $tieWinnerLabel }})
                            </span>
                        @endif
                    </flux:text>
                @endif
                <div class="flex flex-wrap items-center gap-3">
                    @if ($match->winner_side)
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-sm font-semibold text-emerald-300 ring-1 ring-emerald-500/30">
                            <flux:icon name="trophy" class="size-4" />
                            {{ __('Winner') }}: {{ $match->winner_side === 'a' ? $match->side_a_label : $match->side_b_label }}
                        </span>
                    @endif
                    <span class="text-sm text-white/50">
                        {{ $matchStatusLabels[$match->status] ?? $match->status }}
                    </span>
                </div>
            </div>

            <flux:button variant="ghost" :href="$backHref" icon="arrow-left" class="text-emerald-200/80" wire:navigate>
                {{ $backLabel }}
            </flux:button>
        </div>

        @if ($match->status === 'pending')
            <div class="mt-6 rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-white/65">
                {{ __('This match has not started yet.') }}
            </div>
        @elseif ($isNotRequired)
            <div class="mt-6 rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-white/65">
                <span class="font-semibold text-white/90">{{ __('Not Required') }}</span>
                <span class="text-white/55">({{ __('tie decided early') }})</span>
            </div>
        @else
            @php
                $mainEventTypes = ['match_started', 'occurrence', 'game_ended', 'match_ended', 'bulk_score_entry', 'score_correction', 'player_edit', 'match_reset'];
                $displayEvents = $this->showFullTimeline
                    ? $match->matchEvents
                    : $match->matchEvents->filter(fn ($e) => in_array($e->event_type, $mainEventTypes, true));
            @endphp

            @if ($match->games->isNotEmpty())
                <div class="mt-8">
                    <flux:heading size="md" class="mb-4 text-white" style="font-family: 'Syne', sans-serif;">
                        {{ __('Round scores') }}
                    </flux:heading>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($match->games as $game)
                            <div @class([
                                'flex flex-col rounded-xl border px-5 py-4 transition-shadow',
                                'border-emerald-500/40 bg-emerald-500/10 shadow-lg shadow-emerald-900/20' => $game->winner_side,
                                'border-white/10 bg-white/[0.03]' => ! $game->winner_side,
                            ]) wire:key="game-{{ $game->id }}">
                                <span class="text-xs font-medium uppercase tracking-wider text-white/45">
                                    {{ __('Round') }} {{ $game->game_number }}
                                </span>
                                <span class="mt-1 font-mono text-3xl font-bold tabular-nums text-white">
                                    {{ $game->score_a }} – {{ $game->score_b }}
                                </span>
                                @if ($game->winner_side)
                                    <span class="mt-2 text-sm font-medium text-emerald-300">
                                        {{ $game->winner_side === 'a' ? $match->side_a_label : $match->side_b_label }} {{ __('wins') }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($match->umpire_name || $match->court || $match->started_at || $match->ended_at)
                <div class="mt-8 flex flex-wrap gap-6 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white/65">
                    @if ($match->court)
                        <span><span class="font-medium text-white/45">{{ __('Court') }}:</span> {{ $match->court }}</span>
                    @endif
                    @if ($match->umpire_name)
                        <span><span class="font-medium text-white/45">{{ __('Umpire') }}:</span> {{ $match->umpire_name }}</span>
                    @endif
                    @if ($match->started_at)
                        <span><span class="font-medium text-white/45">{{ __('Started') }}:</span> {{ $match->started_at->format('M j, Y H:i') }}</span>
                    @endif
                    @if ($match->ended_at)
                        <span><span class="font-medium text-white/45">{{ __('Ended') }}:</span> {{ $match->ended_at->format('M j, Y H:i') }}</span>
                    @endif
                </div>
            @endif

            @if ($match->matchEvents->isNotEmpty())
                <div class="mt-10">
                    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <flux:heading size="md" class="text-white" style="font-family: 'Syne', sans-serif;">
                            {{ __('Match events') }}
                        </flux:heading>
                        <label class="inline-flex cursor-pointer items-center gap-3 rounded-lg border border-white/10 bg-white/[0.04] px-4 py-2">
                            <flux:switch wire:model.live="showFullTimeline" />
                            <span class="text-sm font-medium text-white/80">{{ __('Show full timeline') }}</span>
                        </label>
                    </div>
                    <div class="max-h-[420px] overflow-y-auto rounded-xl border border-white/10 bg-black/20 shadow-inner">
                        <ul class="divide-y divide-white/5">
                            @forelse ($displayEvents as $evt)
                                <li wire:key="evt-{{ $evt->id }}" @class([
                                    'flex items-start gap-4 border-l-4 py-4 pl-5 pr-4 transition-colors',
                                    'border-l-emerald-500/60' => $evt->event_type === 'point',
                                    'border-l-amber-500/50' => in_array($evt->event_type, ['undo', 'occurrence', 'game_ended'], true),
                                    'border-l-emerald-400' => $evt->event_type === 'match_ended',
                                    'border-l-transparent' => ! in_array($evt->event_type, ['point', 'undo', 'occurrence', 'game_ended', 'match_ended'], true),
                                ])>
                                    @if ($evt->event_type === 'point')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-300">
                                            <flux:icon name="plus" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'undo')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-200">
                                            <flux:icon name="arrow-uturn-left" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'occurrence')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-200">
                                            <flux:icon name="exclamation-triangle" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'game_ended')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-200">
                                            <flux:icon name="trophy" class="size-5" />
                                        </span>
                                    @elseif ($evt->event_type === 'match_ended')
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/25 text-emerald-200">
                                            <flux:icon name="check-circle" class="size-5" />
                                        </span>
                                    @else
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white/50">
                                            <flux:icon name="information-circle" class="size-5" />
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-white/95">
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
                                        <span class="ml-2 font-mono text-sm text-white/45">
                                            {{ $evt->score_a_at_time }}–{{ $evt->score_b_at_time }}
                                            @if ($evt->game)
                                                ({{ __('G') }}{{ $evt->game->game_number }})
                                            @endif
                                        </span>
                                        <div class="mt-0.5 text-xs text-white/40">
                                            {{ $evt->created_at->format('H:i:s') }}
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="flex min-h-[120px] items-center justify-center py-12 text-sm text-white/45">
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
