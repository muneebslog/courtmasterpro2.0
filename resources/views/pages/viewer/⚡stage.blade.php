<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tie;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.viewer', ['title' => 'Stage'])] class extends Component
{
    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public function mount(int $tournament, int $event, int $stage): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;
        $this->stageId = $stage;

        abort_unless($this->tournament()->status === 'published', 404);
        abort_unless((int) $this->event()->tournament_id === $this->tournamentId, 404);
        abort_unless((int) $this->stage()->event_id === $this->eventId, 404);
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Tie>
     */
    #[Computed]
    public function ties()
    {
        return $this->stage()
            ->ties()
            ->with(['teamA', 'teamB'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MatchModel>
     */
    #[Computed]
    public function singlesMatches()
    {
        return $this->stage()
            ->matches()
            ->whereNull('tie_id')
            ->orderBy('id')
            ->get();
    }
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $isTeam = $event->event_type === Event::EVENT_TYPE_TEAM;
@endphp

<div class="flex flex-col gap-8" wire:poll.visible.20s>
    <div>
        <flux:button variant="ghost" size="sm" :href="route('viewer.events.show', [$tournament, $event])" icon="arrow-left" class="mb-4 text-emerald-200/80" wire:navigate>
            {{ __('Stages') }}
        </flux:button>
        <flux:heading size="xl" class="text-white" style="font-family: 'Syne', sans-serif;">
            {{ $stage->name }}
        </flux:heading>
        <flux:text class="mt-1 text-emerald-100/60">
            {{ $event->event_name }} · {{ __('Best of :n', ['n' => $stage->best_of]) }}
        </flux:text>
    </div>

    @if ($isTeam)
        <flux:heading size="lg" class="text-white/90">{{ __('Ties') }}</flux:heading>
        @if ($this->ties->isEmpty())
            <flux:callout variant="secondary" icon="information-circle">
                {{ __('No ties on this stage yet.') }}
            </flux:callout>
        @else
            <ul class="grid gap-4 sm:grid-cols-2" role="list">
                @foreach ($this->ties as $tie)
                    <li wire:key="tie-{{ $tie->id }}">
                        <a href="{{ route('viewer.ties.show', [$tournament, $event, $stage, $tie]) }}" wire:navigate
                            class="group flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.04] p-5 no-underline transition hover:border-emerald-500/40 hover:bg-white/[0.07]">
                            <flux:heading size="md" class="text-white" style="font-family: 'Syne', sans-serif;">
                                {{ $tie->teamA?->name ?? __('Team A') }}
                                <span class="text-white/40">vs</span>
                                {{ $tie->teamB?->name ?? __('Team B') }}
                            </flux:heading>
                            <flux:badge class="mt-3 w-fit capitalize" color="zinc" size="sm">{{ $tie->status }}</flux:badge>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-emerald-400">
                                {{ __('Line matches') }}
                                <flux:icon name="arrow-right" class="size-4" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        <flux:heading size="lg" class="text-white/90">{{ __('Matches') }}</flux:heading>
        @if ($this->singlesMatches->isEmpty())
            <flux:callout variant="secondary" icon="information-circle">
                {{ __('No matches on this stage yet.') }}
            </flux:callout>
        @else
            <ul class="grid gap-4" role="list">
                @foreach ($this->singlesMatches as $match)
                    <li wire:key="match-row-{{ $match->id }}">
                        <x-viewer.match-card :match="$match" :href="route('viewer.matches.show', [$tournament, $event, $stage, $match])" />
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
