@props([
    'match',
    'href' => '#',
])

@php
    /** @var \App\Models\MatchModel $match */
    $live = $match->status === 'in_progress';
    $done = in_array($match->status, ['completed', 'walkover', 'retired', 'not_required'], true);
@endphp

<a href="{{ $href }}" wire:navigate {{ $attributes->class(['group block rounded-2xl border border-white/10 bg-white/[0.04] p-5 no-underline transition hover:border-emerald-500/40 hover:bg-white/[0.07]']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                @if ($live)
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-60"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-red-500"></span>
                    </span>
                    <flux:badge color="red" size="sm">{{ __('Live') }}</flux:badge>
                @elseif ($done)
                    <flux:badge color="zinc" size="sm" class="capitalize">{{ $match->status }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm" class="capitalize">{{ $match->status }}</flux:badge>
                @endif
                @if ($match->court)
                    <flux:text class="text-xs text-white/45">{{ __('Court') }} {{ $match->court }}</flux:text>
                @endif
            </div>
            <flux:heading size="md" class="mt-2 text-white" style="font-family: 'Syne', sans-serif;">
                <span class="text-emerald-100/90">{{ $match->side_a_label }}</span>
                <span class="mx-2 text-white/35">vs</span>
                <span class="text-emerald-100/90">{{ $match->side_b_label }}</span>
            </flux:heading>
            @if ($match->match_order)
                <flux:text class="mt-1 text-xs text-white/40">{{ $match->match_order }}</flux:text>
            @endif
        </div>
        <flux:icon name="chevron-right" class="size-5 shrink-0 text-white/30 transition group-hover:text-emerald-400/80" />
    </div>
</a>
