<?php

use App\Models\Tournament;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.viewer', ['title' => 'Tournaments'])] class extends Component
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        return Tournament::query()
            ->where('status', 'published')
            ->orderByDesc('start_date')
            ->orderBy('tournament_name')
            ->get();
    }
}; ?>

<div class="flex flex-col gap-8" wire:poll.visible.45s>
    <div class="flex flex-col gap-2">
        <flux:heading size="xl" class="text-white" style="font-family: 'Syne', sans-serif;">
            {{ __('Tournaments') }}
        </flux:heading>
        <flux:text class="text-emerald-100/60">
            {{ __('Follow draws and live scores — no account required.') }}
        </flux:text>
    </div>

    @if ($this->tournaments->isEmpty())
        <flux:callout variant="secondary" icon="information-circle">
            {{ __('No published tournaments right now. Check back soon.') }}
        </flux:callout>
    @else
        <ul class="grid gap-4 sm:grid-cols-2" role="list">
            @foreach ($this->tournaments as $tournament)
                <li wire:key="tournament-{{ $tournament->id }}">
                    <a href="{{ route('viewer.tournaments.show', $tournament) }}" wire:navigate
                        class="group flex h-full flex-col rounded-2xl border border-white/10 bg-white/[0.04] p-5 no-underline shadow-lg shadow-black/20 transition hover:border-emerald-500/40 hover:bg-white/[0.07]">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="lg" class="text-white transition group-hover:text-emerald-200"
                                style="font-family: 'Syne', sans-serif;">
                                {{ $tournament->tournament_name }}
                            </flux:heading>
                            <flux:badge color="lime" size="sm">{{ __('Live hub') }}</flux:badge>
                        </div>
                        <flux:text class="mt-2 text-sm text-white/55">
                            {{ $tournament->location }}
                        </flux:text>
                        <flux:text class="mt-1 text-xs text-white/40">
                            {{ $tournament->start_date?->format('M j, Y') }}
                            —
                            {{ $tournament->end_date?->format('M j, Y') }}
                        </flux:text>
                        <span
                            class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-emerald-400 group-hover:text-emerald-300">
                            {{ __('View events') }}
                            <flux:icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
