<?php

use App\Models\Event;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('admin can view tournament details and see admin actions', function (): void {
    /** @var TestCase $this */

    /** @var User $admin */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
    /** @var User $admin */
    /** @var User $admin */
    $tournament = Tournament::create([
        'tournament_name' => 'National Open 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('tournament-details', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->assertSee('Edit')
        ->assertSee('+ Add New Event');
});

test('admin can publish tournament', function (): void {
    /** @var TestCase $this */

    /** @var User $admin */
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

    $this->actingAs($admin);

    Livewire::test('tournament-details', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->call('publishTournament')
        ->assertHasNoErrors();

    $tournament->refresh();

    expect($tournament->status)->toBe('published');
});

test('admin can create an event and it appears in the tournament events', function (): void {
    /** @var TestCase $this */

    /** @var User $admin */
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

    $this->actingAs($admin);

    $eventName = 'National Open Singles';

    Livewire::test('tournament-details', ['tournamentId' => $tournament->id])
        ->set('create_event_name', $eventName)
        ->set('create_event_type', Event::EVENT_TYPE_SINGLES)
        ->call('createEvent')
        ->assertHasNoErrors()
        ->assertSee($eventName);

    expect(
        Event::query()
            ->where('tournament_id', $tournament->id)
            ->where('event_name', $eventName)
            ->exists()
    )->toBeTrue();
});

test('attached umpire can view tournament but cannot create an event', function (): void {
    /** @var TestCase $this */

    /** @var User $admin */
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

    /** @var User $umpire */
    $umpire = User::factory()->create([
        'role' => User::ROLE_UMPIRES,
    ]);
    /** @var User $umpire */
    $tournament->users()->attach([$umpire->id]);

    $this->actingAs($umpire);

    Livewire::test('tournament-details', ['tournamentId' => $tournament->id])
        ->assertOk()
        ->assertDontSee('+ Add New Event')
        ->set('create_event_name', 'Umpire Event')
        ->set('create_event_type', Event::EVENT_TYPE_SINGLES)
        ->call('createEvent')
        ->assertStatus(403);

    expect(
        Event::query()
            ->where('tournament_id', $tournament->id)
            ->exists()
    )->toBeFalse();
});

test('admin can view event details and see edit/delete actions', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->assertOk()
        ->assertSee($event->event_name)
        ->assertSee('Edit')
        ->assertSee('Delete');
});

test('attached umpire can view event details but cannot see edit/delete actions', function (): void {
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

    $umpire = User::factory()->create([
        'role' => User::ROLE_UMPIRES,
    ]);
    /** @var User $umpire */
    $tournament->users()->attach([$umpire->id]);

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $this->actingAs($umpire);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->assertOk()
        ->assertSee($event->event_name)
        ->assertDontSee('Edit')
        ->assertDontSee('Delete');
});

test('non-attached user cannot view event details', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $notAttached = User::factory()->create(); // default role: umpires
    /** @var User $notAttached */
    $this->actingAs($notAttached);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->assertStatus(403);
});

test('admin can create stages when an event has no stages', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->assertOk()
        ->assertSee('+ Create Stage')
        ->call('openCreateStageModal')
        ->set('create_stage_matches', 8) // matches in Round 1 (8) => rounds: 4
        ->set('create_stage_best_of', 3)
        ->call('createStages')
        ->assertHasNoErrors();

    $stages = Stage::query()
        ->where('event_id', $event->id)
        ->orderBy('order_index')
        ->get();

    expect($stages)->toHaveCount(1);

    expect($stages[0]->order_index)->toBe(1);
    expect($stages[0]->best_of)->toBe(3);
    expect($stages[0]->name)->toBe('Round of 16');
});
