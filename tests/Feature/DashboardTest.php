<?php

use App\Models\Tournament;
use App\Models\User;
use Tests\TestCase;

test('guests are redirected to the login page', function () {
    /** @var TestCase $this */
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admin sees create tournament form when they have no tournament', function () {
    /** @var TestCase $this */
    /** @var User $admin */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Create New Tournament');
    $response->assertDontSee('Your Tournament');
});

test('admin sees their existing tournament on dashboard', function () {
    /** @var TestCase $this */
    /** @var User $admin */
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

    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee($tournament->tournament_name);
    $response->assertSee($tournament->location);
});

test('umpire sees their attached tournament on dashboard', function () {
    /** @var TestCase $this */
    /** @var User $admin */
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

    /** @var User $umpire */
    $umpire = User::factory()->create([
        'role' => User::ROLE_UMPIRES,
    ]);

    $tournament->users()->attach([$umpire->id]);

    $this->actingAs($umpire);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Your Tournament');
    $response->assertSee($tournament->tournament_name);
    $response->assertSee($tournament->location);
    $response->assertDontSee('Create New Tournament');
});

test('umpires see an empty state when they have no tournament', function () {
    /** @var TestCase $this */
    /** @var User $umpire */
    $umpire = User::factory()->create([
        'role' => User::ROLE_UMPIRES,
    ]);

    $this->actingAs($umpire);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Your Tournament Details will appear here once an admin creates one.');
    $response->assertDontSee('Create New Tournament');
});
