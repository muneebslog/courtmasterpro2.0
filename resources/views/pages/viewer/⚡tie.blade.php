<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tie;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.viewer', ['title' => 'Tie'])] class extends Component
{
    public int $tournamentId;

    public int $eventId;

    public int $stageId;

    public int $tieId;

    public function mount(int $tournament, int $event, int $stage, int $tie): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;
        $this->stageId = $stage;
        $this->tieId = $tie;

        abort_unless($this->tournament()->status === 'published', 404);
        abort_unless((int) $this->event()->tournament_id === $this->tournamentId, 404);
        abort_unless($this->event()->event_type === Event::EVENT_TYPE_TEAM, 404);
        abort_unless((int) $this->stage()->event_id === $this->eventId, 404);
        abort_unless((int) $this->tieModel()->stage_id === $this->stageId, 404);
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MatchModel>
     */
    #[Computed]
    public function matches()
    {
        return $this->tieModel()
            ->matches()
            ->orderBy('match_order')
            ->orderBy('id')
            ->get();
    }
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
    $stage = $this->stage();
    $tie = $this->tieModel();
@endphp

<div class="flex flex-col gap-8" wire:poll.visible.20s>
    <div>
        <flux:button variant="ghost" size="sm" :href="route('viewer.stages.show', [$tournament, $event, $stage])" icon="arrow-left" class="mb-4 text-emerald-200/80" wire:navigate>
            {{ __('Stage') }}
        </flux:button>
        <flux:heading size="xl" class="text-white" style="font-family: 'Syne', sans-serif;">
            {{ $tie->teamA?->name ?? __('Team A') }}
            <span class="text-white/35">vs</span>
            {{ $tie->teamB?->name ?? __('Team B') }}
        </flux:heading>
        <flux:text class="mt-1 text-emerald-100/60">
            {{ $stage->name }} · {{ $event->event_name }}
        </flux:text>
    </div>

    <flux:heading size="lg" class="text-white/90">{{ __('Matches') }}</flux:heading>

    @if ($this->matches->isEmpty())
        <flux:callout variant="secondary" icon="information-circle">
            {{ __('No matches in this tie yet.') }}
        </flux:callout>
    @else
        <ul class="grid gap-4" role="list">
            @foreach ($this->matches as $match)
                <li wire:key="tie-match-{{ $match->id }}">
                    <x-viewer.match-card :match="$match" :href="route('viewer.matches.show', [$tournament, $event, $stage, $match])" />
                </li>
            @endforeach
        </ul>
    @endif
</div>
