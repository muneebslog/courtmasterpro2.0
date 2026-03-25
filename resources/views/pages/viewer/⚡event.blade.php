<?php

use App\Models\Event;
use App\Models\Stage;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.viewer', ['title' => 'Stages'])] class extends Component
{
    public int $tournamentId;

    public int $eventId;

    public function mount(int $tournament, int $event): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;

        abort_unless($this->tournament()->status === 'published', 404);
        abort_unless((int) $this->event()->tournament_id === $this->tournamentId, 404);
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Stage>
     */
    #[Computed]
    public function stages()
    {
        return $this->event()
            ->stages()
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();
    }
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();
@endphp

<div class="flex flex-col gap-8">
    <div>
        <flux:button variant="ghost" size="sm" :href="route('viewer.tournaments.show', $tournament)" icon="arrow-left" class="mb-4 text-emerald-200/80" wire:navigate>
            {{ __('Events') }}
        </flux:button>
        <flux:heading size="xl" class="text-white" style="font-family: 'Syne', sans-serif;">
            {{ $event->event_name }}
        </flux:heading>
        <flux:text class="mt-1 text-emerald-100/60">
            {{ $tournament->tournament_name }}
        </flux:text>
    </div>

    <flux:heading size="lg" class="text-white/90">{{ __('Stages') }}</flux:heading>

    @if ($this->stages->isEmpty())
        <flux:callout variant="secondary" icon="information-circle">
            {{ __('No stages for this event yet.') }}
        </flux:callout>
    @else
        <ul class="grid gap-4 sm:grid-cols-2" role="list">
            @foreach ($this->stages as $stage)
                <li wire:key="stage-{{ $stage->id }}">
                    <a href="{{ route('viewer.stages.show', [$tournament, $event, $stage]) }}" wire:navigate
                        class="group flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.04] p-5 no-underline transition hover:border-emerald-500/40 hover:bg-white/[0.07]">
                        <flux:heading size="lg" class="text-white" style="font-family: 'Syne', sans-serif;">
                            {{ $stage->name }}
                        </flux:heading>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <flux:badge color="zinc" size="sm">{{ __('Best of :n', ['n' => $stage->best_of]) }}</flux:badge>
                            <flux:badge color="emerald" size="sm" class="capitalize">{{ $stage->status }}</flux:badge>
                        </div>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-emerald-400">
                            @if ($event->event_type === \App\Models\Event::EVENT_TYPE_TEAM)
                                {{ __('Ties & matches') }}
                            @else
                                {{ __('Matches') }}
                            @endif
                            <flux:icon name="arrow-right" class="size-4" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
