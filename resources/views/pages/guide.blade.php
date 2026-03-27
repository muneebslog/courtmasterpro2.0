<x-layouts::app :title="__('Admin & Umpires Guide')">
    @php
        $role = auth()->user()->role;
        $isAdmin = $role === \App\Models\User::ROLE_ADMIN;
        $isUmpires = $role === \App\Models\User::ROLE_UMPIRES;
    @endphp

    <div class="flex w-full flex-col gap-6">
        <div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
            <div
                class="pointer-events-none absolute inset-0 opacity-80"
                style="background:
                    radial-gradient(900px_circle_at_0%_0%,rgba(16,185,129,0.20),transparent_55%),
                    radial-gradient(900px_circle_at_100%_20%,rgba(56,189,248,0.16),transparent_60%),
                    radial-gradient(900px_circle_at_50%_120%,rgba(245,158,11,0.10),transparent_50%);"
            ></div>

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <flux:heading class="text-2xl font-semibold tracking-tight">
                        {{ __('Admin & Umpires Guide') }}
                    </flux:heading>
                    <flux:subheading class="text-sm text-neutral-600 dark:text-neutral-300">
                        {{ __('Create tournaments, set up brackets, and record match results correctly.') }}
                    </flux:subheading>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($isAdmin)
                        <span
                            class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:ring-emerald-900/40"
                        >
                            {{ __('Admin view') }}
                        </span>
                    @elseif ($isUmpires)
                        <span
                            class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800 ring-1 ring-sky-200 dark:bg-sky-900/20 dark:text-sky-300 dark:ring-sky-900/40"
                        >
                            {{ __('Umpires view') }}
                        </span>
                    @endif

                    <span
                        class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700 ring-1 ring-neutral-200 dark:bg-zinc-800/40 dark:text-neutral-200 dark:ring-zinc-700"
                    >
                        {{ __('Quick reference for common actions') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <flux:heading class="text-lg font-semibold">{{ __('Quick navigation') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Use the same path for setup and scoring.') }}</flux:subheading>

                    <div class="mt-5 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:ring-emerald-900/40">
                                1
                            </div>
                            <div>
                                <div class="font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Dashboard -> your tournament') }}
                                </div>
                                <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    {{ __('Admin creates the tournament. Umpires open the attached tournament.') }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-8 items-center justify-center rounded-lg bg-sky-50 text-sm font-semibold text-sky-700 ring-1 ring-sky-200 dark:bg-sky-900/20 dark:text-sky-300 dark:ring-sky-900/40">
                                2
                            </div>
                            <div>
                                <div class="font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Event -> Stage') }}
                                </div>
                                <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    {{ __('Pick the event category, then the stage (rounds / bracket level).') }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-8 items-center justify-center rounded-lg bg-amber-50 text-sm font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:ring-amber-900/40">
                                3
                            </div>
                            <div>
                                <div class="font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Stage -> Match / Tie') }}
                                </div>
                                <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    {{ __('Singles/Doubles use Matches. Team events use Ties, then inner matches.') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <flux:heading class="text-lg font-semibold">{{ __('During scoring (Umpires)') }}</flux:heading>
                    <flux:subheading class="mt-2">
                        {{ __('Score points, log events, and end games from the control panel.') }}
                    </flux:subheading>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-zinc-800/30">
                            <div class="flex items-center gap-2">
                                <flux:icon name="arrow-right" class="size-4 text-neutral-700 dark:text-neutral-200" />
                                <div class="font-semibold">{{ __('Start / Continue') }}</div>
                            </div>
                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('On a match card, use `Start` for the first round, then `Continue` while it is in progress.') }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-zinc-800/30">
                            <div class="flex items-center gap-2">
                                <flux:icon name="plus" class="size-4 text-emerald-700 dark:text-emerald-300" />
                                <div class="font-semibold">{{ __('Points + Undo') }}</div>
                            </div>
                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('Use `+` to add a point to the current game, and `-` (undo) to remove the last point for that side.') }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-zinc-800/30">
                            <div class="flex items-center gap-2">
                                <flux:icon name="exclamation-triangle" class="size-4 text-amber-700 dark:text-amber-300" />
                                <div class="font-semibold">{{ __('Log cards / walkovers') }}</div>
                            </div>
                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('Choose an action (Card / Injury / Walkover), pick Side A/B, optionally pick a player, then click `Log Action`.') }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-zinc-800/30">
                            <div class="flex items-center gap-2">
                                <flux:icon name="flag" class="size-4 text-sky-700 dark:text-sky-300" />
                                <div class="font-semibold">{{ __('End the match') }}</div>
                            </div>
                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('When a game ends, the modal lets you start the next round or finish with `Done`. Walkovers require a confirmation step.') }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-200">
                        {{ __('Team events: only the current inner match can be started. Others show “Waiting for previous matches”.') }}
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <flux:heading class="text-lg font-semibold">{{ __('Common match states') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('These labels match what you see on match cards.') }}</flux:subheading>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-zinc-800/40 dark:text-neutral-200 dark:ring-zinc-700">
                            {{ __('Pending') }}
                        </span>
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800 ring-1 ring-sky-200 dark:bg-sky-900/20 dark:text-sky-300 dark:ring-sky-900/40">
                            {{ __('In Progress') }}
                        </span>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:ring-emerald-900/40">
                            {{ __('Completed') }}
                        </span>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:ring-amber-900/40">
                            {{ __('Walkover') }}
                        </span>
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-zinc-800/40 dark:text-neutral-200 dark:ring-zinc-700">
                            {{ __('Retired') }}
                        </span>
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-zinc-800/40 dark:text-neutral-200 dark:ring-zinc-700">
                            {{ __('Not Required') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <flux:heading class="text-lg font-semibold">{{ __('Admin checklist') }}</flux:heading>
                    <flux:subheading class="mt-2">
                        {{ __('Tournament setup and bracket generation steps.') }}
                    </flux:subheading>

                    @if ($isAdmin)
                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                            {{ __('You are viewing the Admin checklist.') }}
                        </div>
                    @endif

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
                                1
                            </span>
                            <span>{{ __('From `Dashboard`, create your tournament (name, location, start/end dates).') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
                                2
                            </span>
                            <span>{{ __('Attach umpires in “Tournament Users” (Add Empire user).') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
                                3
                            </span>
                            <span>{{ __('Open your event, then create stages using “+ Create Stage” (round 1 must be a power of 2).') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
                                4
                            </span>
                            <span>{{ __('On a stage page, use “Create Ties/Matches” and (optionally) “Generate next stage”.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <flux:heading class="text-lg font-semibold">{{ __('Umpires checklist') }}</flux:heading>
                    <flux:subheading class="mt-2">
                        {{ __('Run matches and record results accurately.') }}
                    </flux:subheading>

                    @if ($isUmpires)
                        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-200">
                            {{ __('You are viewing the Umpire checklist.') }}
                        </div>
                    @endif

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-sky-100 text-sky-800 dark:bg-sky-900/20 dark:text-sky-200">
                                1
                            </span>
                            <span>{{ __('Open your attached tournament from `Dashboard`.') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-sky-100 text-sky-800 dark:bg-sky-900/20 dark:text-sky-200">
                                2
                            </span>
                            <span>{{ __('Go to the right stage and open the match (or tie -> inner match).') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-sky-100 text-sky-800 dark:bg-sky-900/20 dark:text-sky-200">
                                3
                            </span>
                            <span>{{ __('Use the control panel to score points and log events (cards / injuries / walkovers).') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-6 items-center justify-center rounded-md bg-sky-100 text-sky-800 dark:bg-sky-900/20 dark:text-sky-200">
                                4
                            </span>
                            <span>{{ __('When games end, confirm next rounds in the modal; finish with `Done` when the match is over.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-6 dark:border-neutral-700 dark:bg-zinc-800/30">
                    <flux:callout variant="secondary" icon="information-circle" heading="{{ __('Tip') }}">
                        {{ __('If a match is not ready, it will show `Pending` or (for team events) “Waiting for previous matches”. Keep an eye on the current-status badges.') }}
                    </flux:callout>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>

