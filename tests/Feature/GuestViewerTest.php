<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

test('guest sees only published tournaments on viewer index', function (): void {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $published = Tournament::create([
        'tournament_name' => 'Published Open',
        'location' => 'Test City',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $draft = Tournament::create([
        'tournament_name' => 'Secret Draft Cup',
        'location' => 'Hidden',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $response = $this->get(route('viewer.tournaments.index'));

    $response->assertOk()
        ->assertSee('Published Open')
        ->assertDontSee('Secret Draft Cup');

    Livewire::test('pages::viewer.tournaments')
        ->assertOk()
        ->assertSee($published->tournament_name)
        ->assertDontSee($draft->tournament_name);
});

test('guest cannot open draft tournament in viewer', function (): void {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $draft = Tournament::create([
        'tournament_name' => 'Draft Only',
        'location' => 'X',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $this->get(route('viewer.tournaments.show', $draft))->assertNotFound();
});

test('guest can browse to a published match and see players', function (): void {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $tournament = Tournament::create([
        'tournament_name' => 'National Open',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Quarter Final',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'active',
    ]);

    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Player One',
        'side_b_label' => 'Player Two',
        'best_of' => 3,
        'status' => 'completed',
        'winner_side' => 'a',
    ]);

    Livewire::test('pages::viewer.match', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->assertOk()
        ->assertSee('Player One')
        ->assertSee('Player Two');
});
