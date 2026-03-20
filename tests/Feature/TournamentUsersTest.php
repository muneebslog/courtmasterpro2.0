<?php

use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('non-admin cannot manage tournament attached users', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $notAdmin = User::factory()->create(); // default role: umpires
    /** @var User $notAdmin */
    $this->actingAs($notAdmin);

    Livewire::test('users', ['tournamentId' => $tournament->id])
        ->assertStatus(403);
});

test('admin can see attached umpires', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    /** @var User $admin */
    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $umpire1 = User::factory()->create();
    $umpire2 = User::factory()->create();

    $tournament->users()->attach([$umpire1->id, $umpire2->id]);

    $this->actingAs($admin);

    Livewire::test('users', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->assertSee($umpire1->name)
        ->assertSee($umpire1->email)
        ->assertSee($umpire2->name)
        ->assertSee($umpire2->email);
});

test('admin can create an attached empire user (email + password)', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    /** @var User $admin */
    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $email = 'empire1@example.com';
    $name = 'Empire One';

    $this->actingAs($admin);

    Livewire::test('users', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->set('create_name', $name)
        ->set('create_email', $email)
        ->set('create_password', 'password123!')
        ->set('create_password_confirmation', 'password123!')
        ->call('createUser')
        ->assertHasNoErrors();

    $created = User::query()->where('email', $email)->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe(User::ROLE_UMPIRES);
    expect($created->name)->toBe($name);

    expect(Tournament::query()->whereKey($tournament->id)->first()->users()->whereKey($created->id)->exists())->toBeTrue();
});

test('admin can edit an attached empire user (email only)', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    /** @var User $admin */
    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $umpire = User::factory()->create();
    $tournament->users()->attach([$umpire->id]);

    $this->actingAs($admin);

    Livewire::test('users', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->call('startEdit', $umpire->id)
        ->set('edit_email', 'newemail@example.com')
        ->set('edit_password', '')
        ->set('edit_password_confirmation', '')
        ->call('updateUser')
        ->assertHasNoErrors();

    expect($umpire->refresh()->email)->toBe('newemail@example.com');
});

test('admin cannot edit themselves even if attached', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    /** @var User $admin */
    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    // Attach the admin user manually to ensure self-protection is enforced.
    $tournament->users()->attach([$admin->id]);

    $this->actingAs($admin);

    Livewire::test('users', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->call('startEdit', $admin->id)
        ->assertStatus(403);
});

test('admin can delete an attached empire user (detach + delete when no other tournaments)', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    /** @var User $admin */
    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $umpire = User::factory()->create();
    $tournament->users()->attach([$umpire->id]);

    $this->actingAs($admin);

    Livewire::test('users', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->call('startDelete', $umpire->id)
        ->call('deleteUser', $umpire->id)
        ->assertHasNoErrors();

    expect(User::query()->whereKey($umpire->id)->exists())->toBeFalse();
    expect($tournament->users()->whereKey($umpire->id)->exists())->toBeFalse();
});
