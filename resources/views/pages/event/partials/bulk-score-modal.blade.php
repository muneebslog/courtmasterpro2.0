@if ($bulkScoreMatch)
    <flux:modal
        name="bulk-score-entry-modal"
        wire:model.self="showBulkScoreModal"
        focusable
        class="max-w-md"
        @close="closeBulkScoreModal"
    >
        <form wire:submit="submitBulkScores" class="space-y-6">
            @php
                $bulkMatchPrimaryLabel = $bulkScoreMatch->match_order
                    ? __('Match').' '.$bulkScoreMatch->match_order
                    : __('Match').' '.($topLevelMatchNumberById[$bulkScoreMatch->id] ?? $bulkScoreMatch->id);
                $gameRows = $bulkScores;
                ksort($gameRows);
            @endphp
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Enter Scores') }}</flux:heading>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                    <span>{{ $bulkMatchPrimaryLabel }} — {{ $bulkScoreMatch->side_a_label }} vs {{ $bulkScoreMatch->side_b_label }}</span>
                    <span class="shrink-0 text-xs font-normal text-neutral-400 dark:text-neutral-500">id {{ $bulkScoreMatch->id }}</span>
                </div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Enter final score for each game (e.g. 21-15). Best of :bestOf.', ['bestOf' => $bulkScoreMatch->best_of]) }}</p>
                <flux:error name="bulkScores" class="mt-2 text-sm text-red-600 dark:text-red-400" />
            </div>

            @if (empty($gameRows))
                <div class="space-y-4">
                    <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('All games already recorded.') }}</div>
                    <div class="flex justify-end">
                        <flux:button type="button" variant="outline" wire:click="closeBulkScoreModal">
                            {{ __('Close') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($gameRows as $gameNum => $scores)
                        <div wire:key="bulk-game-{{ $gameNum }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800">
                            <span class="w-20 text-sm font-medium text-neutral-600 dark:text-neutral-300">{{ __('Game') }} {{ $gameNum }}:</span>
                            <div class="flex items-center gap-2">
                                <flux:input
                                    type="number"
                                    min="0"
                                    max="30"
                                    size="sm"
                                    wire:model.live.debounce.350ms="bulkScores.{{ $gameNum }}.score_a"
                                    :label="__('Score A')"
                                    class="w-20"
                                />
                                <span class="text-neutral-500">-</span>
                                <flux:input
                                    type="number"
                                    min="0"
                                    max="30"
                                    size="sm"
                                    wire:model.live.debounce.350ms="bulkScores.{{ $gameNum }}.score_b"
                                    :label="__('Score B')"
                                    class="w-20"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="outline" wire:click="closeBulkScoreModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Submit Scores') }}
                    </flux:button>
                </div>
            @endif
        </form>
    </flux:modal>
@endif
