<?php
use App\Models\Event;
use App\Models\Tournament;
use App\Models\User;
use App\Support\BracketStageNaming;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public int $tournamentId;

    public int $eventId;

    public bool $showEditEventModal = false;

    public bool $showDeleteEventModal = false;

    public bool $showCreateStageModal = false;

    public bool $canManageTournament = false;

    public string $edit_event_name = '';

    public string $edit_event_type = Event::EVENT_TYPE_SINGLES;

    public string $create_stage_matches = '8';

    public int $create_stage_best_of = 3;

    public function mount(int $tournament, int $event): void
    {
        $this->tournamentId = $tournament;
        $this->eventId = $event;

        $tournamentModel = $this->tournament();
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->userCanViewTournament($user, $tournamentModel),
            403
        );

        $this->canManageTournament = $user->role === User::ROLE_ADMIN
            && (int) $tournamentModel->admin_id === (int) $user->id;
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

    private function canAdminister(Tournament $tournament): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->role === User::ROLE_ADMIN
            && (int) $tournament->admin_id === (int) $user->id;
    }

    #[Computed]
    public function stagePreview(): array
    {
        $matches = (int) $this->create_stage_matches;

        if ($matches < 1) {
            return [
                'valid' => false,
                'error' => (string) __('Enter a valid number of matches.'),
            ];
        }

        if (! BracketStageNaming::isPowerOfTwo($matches)) {
            return [
                'valid' => false,
                'error' => (string) __('Number of matches must be a power of 2.'),
            ];
        }

        $players = $matches * 2;

        return [
            'valid' => true,
            'name' => BracketStageNaming::stageNameForPlayers($players),
            'matches' => $matches,
            'players' => $players,
        ];
    }

    public function openCreateStageModal(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $event = $this->event();
        abort_unless(! $event->stages()->exists(), 409);

        $this->resetErrorBag();
        $this->showCreateStageModal = true;
    }

    public function closeCreateStageModal(): void
    {
        $this->showCreateStageModal = false;
        $this->resetErrorBag();
    }

    public function openEditEventModal(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $event = $this->event();

        $this->edit_event_name = $event->event_name;
        $this->edit_event_type = $event->event_type;

        $this->resetErrorBag();
        $this->showEditEventModal = true;
    }

    public function closeEditEventModal(): void
    {
        $this->showEditEventModal = false;

        $this->reset('edit_event_name', 'edit_event_type');
        $this->resetErrorBag();
    }

    public function updateEvent(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $validated = $this->validate([
            'edit_event_name' => ['required', 'string', 'max:255'],
            'edit_event_type' => ['required', 'string', Rule::in(Event::eventTypes())],
        ]);

        $this->event()->update([
            'event_name' => $validated['edit_event_name'],
            'event_type' => $validated['edit_event_type'],
        ]);

        $this->closeEditEventModal();
    }

    public function openDeleteEventModal(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $this->resetErrorBag();
        $this->showDeleteEventModal = true;
    }

    public function closeDeleteEventModal(): void
    {
        $this->showDeleteEventModal = false;
        $this->resetErrorBag();
    }

    public function deleteEvent(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $event = $this->event();

        if (! $event->isDeletable()) {
            $this->addError(
                'delete_event',
                (string) __('This event cannot be deleted while it has stages or matches. Remove those first.')
            );

            return;
        }

        $event->delete();

        $this->closeDeleteEventModal();

        $this->redirect(route('tournaments.show', $this->tournamentId), navigate: true);
    }

    public function createStages(): void
    {
        $tournament = $this->tournament();
        abort_unless($this->canAdminister($tournament), 403);

        $event = $this->event();
        abort_unless(! $event->stages()->exists(), 409);

        $validated = $this->validate([
            'create_stage_matches' => ['required', 'integer', 'min:1', 'max:1024'],
            'create_stage_best_of' => ['required', 'integer', Rule::in([1, 3, 5])],
        ]);

        $matches = (int) $validated['create_stage_matches'];
        if (! BracketStageNaming::isPowerOfTwo($matches)) {
            $this->addError('create_stage_matches', (string) __('Number of matches must be a power of 2.'));

            return;
        }

        $bestOf = (int) $validated['create_stage_best_of'];
        $stageName = BracketStageNaming::stageNameForPlayers($matches * 2);

        DB::transaction(function () use ($event, $bestOf, $stageName): void {
            $event->stages()->create([
                'name' => $stageName,
                'best_of' => $bestOf,
                'order_index' => 1,
                'status' => 'pending',
            ]);
        });

        $this->closeCreateStageModal();
    }
}; ?>

@php
    $tournament = $this->tournament();
    $event = $this->event();

    $finishedMatchStatuses = ['completed', 'walkover', 'retired', 'not_required'];

    $stages = $event->stages()
        ->orderBy('order_index')
        ->withCount([
            'matches',
            'ties',
            'matches as remaining_matches_count' => fn ($q) => $q->whereNotIn('status', $finishedMatchStatuses),
            'ties as remaining_ties_count' => fn ($q) => $q->whereNotIn('status', ['completed']),
        ])
        ->get();
@endphp

<div class="flex w-full flex-col gap-6 rounded-xl">
    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading class="text-lg font-semibold">{{ __('Event Details') }}</flux:heading>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium capitalize text-neutral-900 dark:text-white">
                        {{ $event->event_name }}
                    </span>

                    <span
                        class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        {{ ucfirst((string) $event->event_type) }}
                    </span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <span class="text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Tournament') }}:
                        <a class="font-semibold underline underline-offset-4" href="{{ route('tournaments.show', $tournament->id) }}">
                            {{ $tournament->tournament_name }}
                        </a>
                    </span>

                    <span class="text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Location') }}: <span class="font-semibold capitalize">{{ $tournament->location }}</span>
                    </span>
                </div>
            </div>

            @if ($canManageTournament)
                <div class="flex items-center gap-3">
                    <flux:button variant="outline" wire:click="openEditEventModal">
                        {{ __('Edit') }}
                    </flux:button>

                    @if ($event->isDeletable())
                        <flux:button variant="danger" wire:click="openDeleteEventModal">
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-neutral-200 bg-white p-7 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Matches Categories') }}</flux:heading>
        <flux:subheading>{{ __('Stages for this event will appear here.') }}</flux:subheading>

        @if ($stages->isEmpty())
            @if ($canManageTournament)
                <div class="mt-4 flex justify-end">
                    <flux:button variant="primary" wire:click="openCreateStageModal">
                        {{ __('+ Create Stage') }}
                    </flux:button>
                </div>
            @else
                <div class="mt-4 text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('No stages created for this event yet.') }}
                </div>
            @endif
        @else
            <div class="mt-6 space-y-3">
                @foreach ($stages as $stage)
                    @php
                        if ($event->event_type === Event::EVENT_TYPE_TEAM) {
                            $totalUnits = (int) $stage->ties_count;
                            $remainingUnits = (int) $stage->remaining_ties_count;
                            $unitLabel = __('Ties');
                            $participantLabel = __('Teams');
                            $participantCount = $totalUnits * 2;
                        } else {
                            $totalUnits = (int) $stage->matches_count;
                            $remainingUnits = (int) $stage->remaining_matches_count;
                            $unitLabel = __('Matches');
                            $participantLabel = __('Players');
                            $participantCount = $event->event_type === Event::EVENT_TYPE_DOUBLES
                                ? $totalUnits * 4
                                : $totalUnits * 2;
                        }
                    @endphp
                    <a
                        wire:navigate
                        href="{{ route('tournaments.events.stages.show', ['tournament' => $tournament->id, 'event' => $event->id, 'stage' => $stage->id]) }}"
                        class="block rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm transition hover:border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600 dark:hover:bg-neutral-700/80"
                    >
                        <div class="font-semibold">{{ $stage->name }}</div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-600 dark:text-neutral-400">
                            <span>
                                {{ $unitLabel }}:
                                <span
                                    class="font-medium text-neutral-800 dark:text-neutral-200"
                                    data-stage-stat-total="{{ $totalUnits }}"
                                >{{ $totalUnits }}</span>
                            </span>
                            <span>
                                {{ __('Remaining') }}:
                                <span
                                    class="font-medium text-neutral-800 dark:text-neutral-200"
                                    data-stage-stat-remaining="{{ $remainingUnits }}"
                                >{{ $remainingUnits }}</span>
                            </span>
                            <span>
                                {{ $participantLabel }}:
                                <span
                                    class="font-medium text-neutral-800 dark:text-neutral-200"
                                    data-stage-stat-participants="{{ $participantCount }}"
                                >{{ $participantCount }}</span>
                            </span>
                        </div>
                        <div class="mt-1 text-neutral-600 dark:text-neutral-400">
                            {{ __('Best of') }}: {{ $stage->best_of }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if ($canManageTournament)
        <flux:modal
            name="edit-event-modal"
            wire:model.self="showEditEventModal"
            focusable
            class="max-w-lg"
            @close="closeEditEventModal"
        >
            <form wire:submit="updateEvent" class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Edit Event') }}</flux:heading>
                    <flux:subheading>{{ __('Update the event name and type.') }}</flux:subheading>
                </div>

                <div class="space-y-2">
                    <flux:input
                        wire:model="edit_event_name"
                        :label="__('Event Name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="off"
                        viewable
                    />
                    @error('edit_event_name')
                        <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">
                        {{ __('Event Type') }}
                    </label>

                    <select
                        wire:model="edit_event_type"
                        class="w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-neutral-400 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                    >
                        <option value="{{ Event::EVENT_TYPE_SINGLES }}">{{ __('Singles') }}</option>
                        <option value="{{ Event::EVENT_TYPE_DOUBLES }}">{{ __('Doubles') }}</option>
                        <option value="{{ Event::EVENT_TYPE_TEAM }}">{{ __('Team') }}</option>
                    </select>

                    @error('edit_event_type')
                        <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="outline" wire:click="closeEditEventModal">
                        {{ __('Cancel') }}
                    </flux:button>

                    <flux:button type="submit" variant="primary">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal
            name="delete-event-modal"
            wire:model.self="showDeleteEventModal"
            focusable
            class="max-w-lg"
            @close="closeDeleteEventModal"
        >
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete Event?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-800 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                    <span class="font-semibold">{{ $event->event_name }}</span>
                    <span class="text-neutral-600 dark:text-neutral-400">({{ ucfirst((string) $event->event_type) }})</span>
                </div>

                @error('delete_event')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="outline" wire:click="closeDeleteEventModal">
                        {{ __('Cancel') }}
                    </flux:button>

                    <flux:button type="button" variant="danger" wire:click="deleteEvent">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal
            name="create-stage-modal"
            wire:model.self="showCreateStageModal"
            focusable
            class="max-w-lg"
            @close="closeCreateStageModal"
        >
            <form wire:submit="createStages" class="space-y-6">
                @php
                    $preview = $this->stagePreview;
                @endphp

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Create First Round') }}</flux:heading>
                    <flux:subheading>{{ __('Create only the first round. You can add next rounds later with different best-of values.') }}</flux:subheading>
                </div>

                <div class="space-y-2">
                    <flux:input
                        wire:model.live.debounce.350ms="create_stage_matches"
                        :label="__('Number of matches (Round 1)')"
                        type="number"
                        requiredf
                        min="1"
                        step="1"
                        autofocus
                        viewable
                    />
                    @error('create_stage_matches')
                        <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                    @enderror

                    @if (isset($preview['error']) && $preview['valid'] === false)
                        <flux:text color="red" class="text-sm">{{ $preview['error'] }}</flux:text>
                    @endif
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">
                        {{ __('Best of') }}
                    </label>

                    <select
                        wire:model="create_stage_best_of"
                        class="w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-neutral-400 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                    >
                        <option value="1">{{ __('Best of 1') }}</option>
                        <option value="3">{{ __('Best of 3') }}</option>
                        <option value="5">{{ __('Best of 5') }}</option>
                    </select>

                    @error('create_stage_best_of')
                        <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                    @enderror
                </div>

                @if (($preview['valid'] ?? false) === true)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-800 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        <div class="font-semibold">{{ __('First round preview') }}</div>
                        <div class="mt-2">
                            {{ __('Stage') }}: <span class="font-semibold">{{ $preview['name'] }}</span>
                        </div>
                        <div class="mt-1">
                            {{ __('Matches') }}: <span class="font-semibold">{{ $preview['matches'] }}</span>
                        </div>
                        <div class="mt-1">
                            {{ __('Players') }}: <span class="font-semibold">{{ $preview['players'] }}</span>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="outline" wire:click="closeCreateStageModal">
                        {{ __('Cancel') }}
                    </flux:button>

                    <flux:button type="submit" variant="primary">
                        {{ __('Create') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>