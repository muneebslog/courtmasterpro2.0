<?php
use App\Concerns\PasswordValidationRules;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public int $tournamentId;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public string $create_name = '';
    public string $create_email = '';
    public string $create_password = '';
    public string $create_password_confirmation = '';

    public int $editing_user_id = 0;
    public string $edit_name = '';
    public string $edit_email = '';
    public string $edit_password = '';
    public string $edit_password_confirmation = '';

    public int $deleting_user_id = 0;
    public string $deleting_user_name = '';
    public string $deleting_user_email = '';

    public function mount(int $tournamentId): void
    {
        $this->tournamentId = $tournamentId;
        $tournament = Tournament::query()
            ->whereKey($tournamentId)
            ->firstOrFail();

        $user = auth()->user();

        abort_unless(
            $user instanceof User
                && $user->role === User::ROLE_ADMIN
                && (int) $tournament->admin_id === (int) $user->id,
            403
        );
    }

    #[Computed]
    public function attachedUmpires(): Collection
    {
        return $this->tournament()
            ->users()
            ->where('role', User::ROLE_UMPIRES)
            ->orderByDesc('users.id')
            ->get();
    }

    private function tournament(): Tournament
    {
        return Tournament::query()->whereKey($this->tournamentId)->firstOrFail();
    }

    public function openCreateModal(): void
    {
        $this->reset(
            'create_name',
            'create_email',
            'create_password',
            'create_password_confirmation'
        );

        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(
            'create_name',
            'create_email',
            'create_password',
            'create_password_confirmation'
        );
        $this->resetErrorBag();
    }

    public function startEdit(int $userId): void
    {
        if ($userId === auth()->id()) {
            abort(403);
        }

        $user = $this->tournament()
            ->users()
            ->where('users.id', $userId)
            ->where('role', User::ROLE_UMPIRES)
            ->firstOrFail();

        $this->editing_user_id = $user->id;
        $this->edit_name = $user->name;
        $this->edit_email = $user->email;
        $this->edit_password = '';
        $this->edit_password_confirmation = '';

        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editing_user_id = 0;
        $this->edit_name = '';
        $this->edit_email = '';
        $this->edit_password = '';
        $this->edit_password_confirmation = '';
        $this->resetErrorBag();
    }

    public function startDelete(int $userId): void
    {
        if ($userId === auth()->id()) {
            abort(403);
        }

        $user = $this->tournament()
            ->users()
            ->where('users.id', $userId)
            ->where('role', User::ROLE_UMPIRES)
            ->firstOrFail();

        $this->deleting_user_id = $user->id;
        $this->deleting_user_name = $user->name;
        $this->deleting_user_email = $user->email;

        $this->resetErrorBag();
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleting_user_id = 0;
        $this->deleting_user_name = '';
        $this->deleting_user_email = '';
        $this->resetErrorBag();
    }

    public function createUser(): void
    {
        $validated = $this->validate([
            'create_name' => ['required', 'string', 'max:255'],
            'create_email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'create_password' => $this->passwordRules(),
        ]);

        $user = User::create([
            'name' => $validated['create_name'],
            'email' => $validated['create_email'],
            'password' => Hash::make($validated['create_password']),
            'role' => User::ROLE_UMPIRES,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $this->tournament()->users()->attach($user->id);

        $this->closeCreateModal();
    }

    public function updateUser(): void
    {
        $userId = $this->editing_user_id;

        if ($userId === auth()->id()) {
            abort(403);
        }

        $user = $this->tournament()
            ->users()
            ->where('users.id', $userId)
            ->where('role', User::ROLE_UMPIRES)
            ->firstOrFail();

        $baseValidated = $this->validate([
            'edit_name' => ['required', 'string', 'max:255'],
            'edit_email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($userId)],
        ]);

        $data = [
            'name' => $baseValidated['edit_name'],
            'email' => $baseValidated['edit_email'],
            'email_verified_at' => now(),
        ];

        if (trim($this->edit_password) !== '') {
            $passwordValidated = $this->validate([
                'edit_password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
            ]);

            $data['password'] = Hash::make($passwordValidated['edit_password']);
        }

        $user->update($data);
        $this->closeEditModal();
    }

    public function deleteUser(int $userId): void
    {
        if ($userId === auth()->id()) {
            abort(403);
        }

        $user = $this->tournament()
            ->users()
            ->where('users.id', $userId)
            ->where('role', User::ROLE_UMPIRES)
            ->firstOrFail();

        $this->tournament()->users()->detach($userId);

        if (! $user->tournaments()->exists()) {
            $user->delete();
        }

        $this->closeDeleteModal();
    }
};
?>

<div class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="space-y-1">
            <flux:heading class="text-lg font-semibold">{{ __('Tournament Users') }}</flux:heading>
            <flux:subheading class="text-sm text-neutral-600 dark:text-neutral-300">
                {{ __('Manage attached umpires (Empire).') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <div class="rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                {{ $this->attachedUmpires->count() }} {{ __('attached') }}
            </div>

            <flux:button variant="primary" wire:click="openCreateModal">
                {{ __('Add User') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead>
                <tr class="bg-neutral-50 dark:bg-neutral-800">
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                        {{ __('Name') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                        {{ __('Email') }}
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-300">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse($this->attachedUmpires as $user)
                    <tr wire:key="{{ $user->id }}" class="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/40">
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ $user->name }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-300">
                            {{ $user->email }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button variant="outline" wire:click="startEdit({{ $user->id }})">
                                    {{ __('Edit') }}
                                </flux:button>

                                <flux:button variant="danger" wire:click="startDelete({{ $user->id }})">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-sm text-neutral-600 dark:text-neutral-300">
                            {{ __('No attached users yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal
        name="create-tournament-user-modal"
        wire:model.self="showCreateModal"
        focusable
        class="max-w-lg"
        @close="closeCreateModal"
    >
        <form wire:submit="createUser" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Add Empire user') }}</flux:heading>
                <flux:subheading>{{ __('Enter name, email and password for a new user account.') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="create_name"
                    :label="__('Name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    viewable
                />
                @error('create_name')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="create_email"
                    :label="__('Email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    viewable
                />
                @error('create_email')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="create_password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                />
                @error('create_password')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="create_password_confirmation"
                    :label="__('Confirm Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                />
                @error('create_password_confirmation')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="outline" wire:click="closeCreateModal">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="edit-tournament-user-modal"
        wire:model.self="showEditModal"
        focusable
        class="max-w-lg"
        @close="closeEditModal"
    >
        <form wire:submit="updateUser" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Edit Empire user') }}</flux:heading>
                <flux:subheading>{{ __('Update name and email (and optionally password).') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="edit_name"
                    :label="__('Name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    viewable
                />
                @error('edit_name')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="edit_email"
                    :label="__('Email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    viewable
                />
                @error('edit_email')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="edit_password"
                    :label="__('New Password (optional)')"
                    type="password"
                    autocomplete="new-password"
                    viewable
                />
                @error('edit_password')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="space-y-2">
                <flux:input
                    wire:model="edit_password_confirmation"
                    :label="__('Confirm New Password')"
                    type="password"
                    autocomplete="new-password"
                    viewable
                />
                @error('edit_password_confirmation')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="outline" wire:click="closeEditModal">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="delete-tournament-user-modal"
        wire:model.self="showDeleteModal"
        focusable
        class="max-w-lg"
        @close="closeDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete Empire user') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to detach and delete this user if they have no other tournament attachments?') }}
                </flux:subheading>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $deleting_user_name }}
                </div>
                <div class="text-neutral-700 dark:text-neutral-300">
                    {{ $deleting_user_email }}
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="outline" wire:click="closeDeleteModal">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="deleteUser({{ $deleting_user_id }})"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>