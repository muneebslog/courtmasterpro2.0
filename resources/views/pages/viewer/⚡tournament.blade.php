<?php

use App\Models\Event;
use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.viewer', ['title' => 'Events'])] class extends Component
{
    public int $tournamentId;

    public function mount(int $tournament): void
    {
        $this->tournamentId = $tournament;
        abort_unless($this->tournament()->status === 'published', 404);
    }

    private function tournament(): Tournament
    {
        return Tournament::query()->whereKey($this->tournamentId)->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Event>
     */
    #[Computed]
    public function events()
    {
        return $this->tournament()
            ->events()
            ->orderBy('event_name')
            ->get();
    }
}; ?>

@php
    $tournament = $this->tournament();
@endphp

<div class="flex flex-col gap-8" wire:poll.visible.45s>
    <div>
        <flux:button variant="ghost" size="sm" :href="route('viewer.tournaments.index')" icon="arrow-left" class="mb-4 text-emerald-200/80" wire:navigate>
            {{ __('All tournaments') }}
        </flux:button>
        <flux:heading size="xl" class="text-white" style="font-family: 'Syne', sans-serif;">
            {{ $tournament->tournament_name }}
        </flux:heading>
        <flux:text class="mt-1 text-emerald-100/60">
            {{ $tournament->location }} · {{ $tournament->start_date?->format('M j') }} – {{ $tournament->end_date?->format('M j, Y') }}
        </flux:text>
    </div>

    <flux:heading size="lg" class="text-white/90">{{ __('Events') }}</flux:heading>

    @if ($this->events->isEmpty())
        <flux:callout variant="secondary" icon="information-circle">
            {{ __('No events in this tournament yet.') }}
        </flux:callout>
    @else
        <ul class="grid gap-4 sm:grid-cols-2" role="list">
            @foreach ($this->events as $event)
                <li wire:key="event-{{ $event->id }}">
                    <a href="{{ route('viewer.events.show', [$tournament, $event]) }}" wire:navigate
                        class="group flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.04] p-5 no-underline transition hover:border-emerald-500/40 hover:bg-white/[0.07]">
                        <flux:heading size="lg" class="text-white" style="font-family: 'Syne', sans-serif;">
                            {{ $event->event_name }}
                        </flux:heading>
                        <flux:badge class="mt-3 w-fit capitalize" color="zinc" size="sm">{{ $event->event_type }}</flux:badge>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-emerald-400">
                            {{ __('Stages') }}
                            <flux:icon name="arrow-right" class="size-4" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
