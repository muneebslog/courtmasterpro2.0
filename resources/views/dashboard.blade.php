<x-layouts::app :title="__('Dashboard')">
    <div class="relative flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        {{-- Hero --}}
        <div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-8 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-amber-200/30 blur-3xl dark:bg-amber-400/10"></div>
            <div class="pointer-events-none absolute -right-28 -bottom-28 h-96 w-96 rounded-full bg-sky-200/25 blur-3xl dark:bg-sky-400/10"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(800px_circle_at_25%_0%,rgba(234,179,8,0.12),transparent_50%),radial-gradient(900px_circle_at_85%_30%,rgba(56,189,248,0.10),transparent_55%)]"></div>

            <div class="relative">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ __('Welcome, :name [:role]', ['name' => auth()->user()->name, 'role' => ucfirst(auth()->user()->role)]) }}
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-neutral-600 dark:text-neutral-300">
                    {{ auth()->user()->role === \App\Models\User::ROLE_ADMIN ? __('Manage your badminton tournament from one place.') : __('You can view your Tournament Details once an admin creates one.') }}
                </p>
            </div>
        </div>

        @php
            $userRole = auth()->user()->role;
        @endphp

        @if ($tournament)
            <div class="grid grid-cols-1 gap-6">
                <div>
                    @if (session('status'))
                        <div class="mb-4">
                            <x-auth-session-status :status="session('status')" />
                        </div>
                    @endif

                    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
                        <a  href="{{ route('tournaments.show', $tournament) }}"
                        wire:navigate class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-2">
                                {{-- <flux:heading class="text-lg font-semibold">{{ __('Your Tournament') }}</flux:heading> --}}
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-xl underline capitalize font-medium text-neutral-900 dark:text-white">
                                        {{ $tournament->tournament_name }}
                                    </span>

                                    @php
                                        $status = (string) $tournament->status;
                                    @endphp

                                    <span
                                        class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        {{ ucfirst($status) }}
                                    </span>
                                </div>
                            </div>
                        </a>

                        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="rounded-xl bg-neutral-50/60 p-4 dark:bg-neutral-800/40">
                                <div class="text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                                    {{ __('Location') }}
                                </div>
                                <div class="mt-1 capitalize text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $tournament->location }}
                                </div>
                            </div>

                            <div class="rounded-xl capitalize bg-neutral-50/60 p-4 dark:bg-neutral-800/40">
                                <div class="text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                                    {{ __('Status') }}
                                </div>
                                <div class="mt-1 text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $tournament->status }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-neutral-50/60 p-4 dark:bg-neutral-800/40">
                                <div class="text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                                    {{ __('Start Date') }}
                                </div>
                                <div class="mt-1 capitalize text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ optional($tournament->start_date)->format('Y-m-d') }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-neutral-50/60 p-4 dark:bg-neutral-800/40">
                                <div class="text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                                    {{ __('End Date') }}
                                </div>
                                <div class="mt-1 text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ optional($tournament->end_date)->format('Y-m-d') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($userRole === \App\Models\User::ROLE_ADMIN)
                        <livewire:users :tournamentId="$tournament->id" />
                    @endif
                </div>
            </div>
        @elseif ($userRole === \App\Models\User::ROLE_ADMIN)
            <div class="grid grid-cols-1 gap-6">
                <div>
                    @if (session('status'))
                        <div class="mb-4">
                            <x-auth-session-status :status="session('status')" />
                        </div>
                    @endif

                    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
                        <div class="space-y-4">
                            <flux:heading class="text-lg font-semibold">{{ __('Create New Tournament') }}</flux:heading>
                            <flux:subheading>{{ __('Set up a new badminton tournament.') }}</flux:subheading>

                            @if ($errors->any())
                                <flux:callout
                                    variant="danger"
                                    icon="x-circle"
                                    heading="{{ __('Please fix the errors below and try again.') }}"
                                    class="mt-2"
                                />
                            @endif

                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <form method="POST" action="{{ route('dashboard.tournaments.store') }}" class="space-y-6 lg:col-span-2">
                                    @csrf

                                    <div class="space-y-2">
                                        <flux:input
                                            name="tournament_name"
                                            :label="__('Tournament Name')"
                                            type="text"
                                            required
                                            placeholder="{{ __('e.g., National Open 2024') }}"
                                            :value="old('tournament_name')"
                                        />
                                        @error('tournament_name')
                                            <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <flux:input
                                            name="location"
                                            :label="__('Location')"
                                            type="text"
                                            required
                                            placeholder="{{ __('e.g., Wembley Arena, London') }}"
                                            :value="old('location')"
                                        />
                                        @error('location')
                                            <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                                        @enderror
                                    </div>

                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <flux:input
                                                name="start_date"
                                                :label="__('Start Date')"
                                                type="date"
                                                required
                                                :value="old('start_date')"
                                            />
                                            @error('start_date')
                                                <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <flux:input
                                                name="end_date"
                                                :label="__('End Date')"
                                                type="date"
                                                required
                                                :value="old('end_date')"
                                            />
                                            @error('end_date')
                                                <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                                        <a
                                            href="{{ route('dashboard') }}"
                                            class="rounded-md border border-neutral-300 px-4 py-2 text-center text-sm text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                        >
                                            {{ __('Cancel') }}
                                        </a>

                                        <flux:button variant="primary" type="submit">
                                            {{ __('Create Tournament') }}
                                        </flux:button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
                <flux:heading class="text-lg font-semibold">{{ __('Dashboard') }}</flux:heading>
                <flux:subheading>{{ __('Your Tournament Details will appear here once an admin creates one.') }}</flux:subheading>
            </div>
        @endif
    </div>
</x-layouts::app>
