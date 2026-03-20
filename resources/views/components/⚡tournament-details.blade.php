<?php

use App\Models\Event;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $tournamentId;

    public bool $showEditTournamentModal = false;
    public bool $showCreateEventModal = false;

    public bool $canManageTournament = false;

    // Tournament edit state
    public string $edit_tournament_name = '';
    public string $edit_location = '';
    public string $edit_start_date = '';
    public string $edit_end_date = '';

    // Event create state
    public string $create_event_name = '';
    public string $create_event_type = Event::EVENT_TYPE_SINGLES;

    public function mount(int $tournamentId): void
    {
        $this->tournamentId = $tournamentId;

        $tournament = $this->tournament();
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournament),
            403
        );

        $this->canManageTournament = $user->role === User::ROLE_ADMIN
            && (int) $tournament->admin_id === (int) $user->id;
    }

    private function tournament(): Tournament
    {
        return Tournament::query()->whereKey($this->tournamentId)->firstOrFail();
    }

    private function userCanViewTournament(User $user, Tournament $tournament): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return (int) $tournament->admin_id === (int) $user->id;
        }

        if ($user->role === User::ROLE_UMPIRES) {
            return $tournament->users()
                ->whereKey($user->id)
                ->where('role', User::ROLE_UMPIRES)
                ->exists();
        }

        return false;
    }

    #[Computed]
    public function events(): Collection
    {
        return $this->tournament()
            ->events()
            ->latest()
            ->get();
    }

    public function openEditTournamentModal(): void
    {
        $tournament = $this->tournament();

        abort_unless($this->canAdminister($tournament), 403);

        $this->reset(
            'edit_tournament_name',
            'edit_location',
            'edit_start_date',
            'edit_end_date',
        );

        $this->edit_tournament_name = $tournament->tournament_name;
        $this->edit_location = $tournament->location;
        $this->edit_start_date = $tournament->start_date?->format('Y-m-d') ?? '';
        $this->edit_end_date = $tournament->end_date?->format('Y-m-d') ?? '';

        $this->resetErrorBag();
        $this->showEditTournamentModal = true;
    }

    public function closeEditTournamentModal(): void
    {
        $this->showEditTournamentModal = false;
        $this->reset(
            'edit_tournament_name',
            'edit_location',
            'edit_start_date',
            'edit_end_date',
        );
        $this->resetErrorBag();
    }

    public function publishTournament(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $tournament->update([
            'status' => 'published',
        ]);

        $this->resetErrorBag();
    }

    public function openCreateEventModal(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $this->reset(
            'create_event_name',
        );

        $this->create_event_type = Event::EVENT_TYPE_SINGLES;

        $this->resetErrorBag();
        $this->showCreateEventModal = true;
    }

    public function closeCreateEventModal(): void
    {
        $this->showCreateEventModal = false;
        $this->reset('create_event_name', 'create_event_type');
        $this->create_event_type = Event::EVENT_TYPE_SINGLES;
        $this->resetErrorBag();
    }

    public function updateTournament(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $validated = $this->validate([
            'edit_tournament_name' => ['required', 'string', 'max:255'],
            'edit_location' => ['required', 'string', 'max:255'],
            'edit_start_date' => ['required', 'date'],
            'edit_end_date' => ['required', 'date', 'after_or_equal:edit_start_date'],
        ]);

        $tournament->update([
            'tournament_name' => $validated['edit_tournament_name'],
            'location' => $validated['edit_location'],
            'start_date' => $validated['edit_start_date'],
            'end_date' => $validated['edit_end_date'],
        ]);

        $this->closeEditTournamentModal();
    }

    public function createEvent(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $validated = $this->validate([
            'create_event_name' => ['required', 'string', 'max:255'],
            'create_event_type' => ['required', 'string', Rule::in(Event::eventTypes())],
        ]);

        $tournament->events()->create([
            'event_name' => $validated['create_event_name'],
            'event_type' => $validated['create_event_type'],
        ]);

        $this->closeCreateEventModal();
    }

    private function canAdminister(Tournament $tournament): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->role === User::ROLE_ADMIN
            && (int) $tournament->admin_id === (int) $user->id;
    }
}; ?>

@php
    /** @var \App\Models\Tournament $tournament */
    $tournament = $this->tournament();
    $status = (string) $tournament->status;
@endphp

<div class="flex w-full flex-1 flex-col gap-6 rounded-xl">
    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading class="text-lg font-semibold">{{ __('Tournament Details') }}</flux:heading>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium capitalize text-neutral-900 dark:text-white">
                        {{ $tournament->tournament_name }}
                    </span>

                    <span
                        class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        {{ ucfirst($status) }}
                    </span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <span class="text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Location') }}: <span class="font-semibold capitalize">{{ $tournament->location }}</span>
                    </span>
                    <span class="text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Dates') }}:
                        <span class="font-semibold">
                            {{ $tournament->start_date?->format('Y-m-d') }} - {{ $tournament->end_date?->format('Y-m-d') }}
                        </span>
                    </span>
                </div>
            </div>

            @if ($canManageTournament)
                <div class="flex items-center gap-3">
                    <flux:button variant="outline" wire:click="openEditTournamentModal">
                        {{ __('Edit') }}
                    </flux:button>

                    @if ($tournament->status === 'published')
                        <flux:button variant="primary" disabled>
                            {{ __('Published') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" wire:click="publishTournament">
                            {{ __('Publish') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <flux:heading class="text-lg font-semibold">{{ __('Events') }}</flux:heading>
                <flux:subheading class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('Add matches categories for this tournament.') }}
                </flux:subheading>
            </div>

            @if ($canManageTournament)
                <div class="flex items-center">
                    <flux:button variant="outline" wire:click="openCreateEventModal">
                        {{ __('+ Add New Event') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->events as $event)
                <a
                    href="{{ route('tournaments.events.show', ['tournament' => $tournament->id, 'event' => $event->id]) }}"
                    class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 transition-colors hover:bg-neutral-50 hover:shadow-sm dark:border-neutral-700 dark:bg-neutral-800/40 dark:hover:bg-neutral-800"
                    wire:key="event-{{ $event->id }}"
                >
                    <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ $event->event_name }}
                    </div>
                    <div class="mt-2 inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        {{ ucfirst((string) $event->event_type) }}
                    </div>
                </a>
            @endforeach

            @if ($this->events->isEmpty())
                <div class="col-span-full rounded-xl border border-neutral-200 bg-neutral-50/60 p-6 text-center text-sm text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800/40 dark:text-neutral-300">
                    {{ __('No events yet.') }}
                </div>
            @endif
        </div>
    </div>

    <flux:modal
        name="edit-tournament-modal"
        wire:model.self="showEditTournamentModal"
        focusable
        class="max-w-lg"
        @close="closeEditTournamentModal"
    >
        <form wire:submit="updateTournament" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Edit Tournament') }}</flux:heading>
                <flux:subheading>{{ __('Update the tournament details.') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="edit_tournament_name"
                    :label="__('Tournament Name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="organization"
                    viewable
                />
                @error('edit_tournament_name')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="edit_location"
                    :label="__('Location')"
                    type="text"
                    required
                    autocomplete="address-level1"
                    viewable
                />
                @error('edit_location')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <flux:input
                        wire:model="edit_start_date"
                        :label="__('Start Date')"
                        type="date"
                        required
                        viewable
                    />
                    @error('edit_start_date')
                        <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="space-y-2">
                    <flux:input
                        wire:model="edit_end_date"
                        :label="__('End Date')"
                        type="date"
                        required
                        viewable
                    />
                    @error('edit_end_date')
                        <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="outline" wire:click="closeEditTournamentModal">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="create-tournament-event-modal"
        wire:model.self="showCreateEventModal"
        focusable
        class="max-w-lg"
        @close="closeCreateEventModal"
    >
        <form wire:submit="createEvent" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Create New Event') }}</flux:heading>
                <flux:subheading>{{ __('Enter event name and type.') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="create_event_name"
                    :label="__('Event Name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="off"
                    viewable
                />
                @error('create_event_name')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">
                    {{ __('Event Type') }}
                </label>

                <select
                    wire:model="create_event_type"
                    class="w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-neutral-400 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                >
                    <option value="{{ Event::EVENT_TYPE_SINGLES }}">{{ __('Singles') }}</option>
                    <option value="{{ Event::EVENT_TYPE_DOUBLES }}">{{ __('Doubles') }}</option>
                    <option value="{{ Event::EVENT_TYPE_TEAM }}">{{ __('Team') }}</option>
                </select>

                @error('create_event_type')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="outline" wire:click="closeCreateEventModal">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Create Event') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

