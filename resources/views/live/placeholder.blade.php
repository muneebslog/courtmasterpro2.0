<x-layouts.viewer title="Live score">
    <div class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center">
        <flux:heading size="lg">{{ __('Live score display') }}</flux:heading>
        <flux:text class="mt-2 text-white/70">
            @if ($court)
                {{ __('Court: :court', ['court' => $court]) }}
            @else
                {{ __('All courts — coming soon.') }}
            @endif
        </flux:text>
        <flux:button class="mt-6" variant="primary" :href="route('viewer.tournaments.index')">
            {{ __('Browse tournaments') }}
        </flux:button>
    </div>
</x-layouts.viewer>
