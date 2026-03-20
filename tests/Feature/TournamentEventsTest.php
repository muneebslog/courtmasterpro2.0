<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\MatchPlayer;
use App\Models\Stage;
use App\Models\Team;
use App\Models\Tie;
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

test('stage route resolves for attached users', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 16',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    $this->get(route('tournaments.events.stages.show', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ]))->assertSuccessful();
});

test('admin can create singles matches from stage setup', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 2',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertOk()
        ->call('openCreationFlow')
        ->set('singleMatches.0.player_a', 'Ali')
        ->set('singleMatches.0.player_b', 'Hassan')
        ->set('singleMatches.1.player_a', 'Bilal')
        ->set('singleMatches.1.player_b', 'Usman')
        ->call('createSinglesMatches')
        ->assertHasNoErrors();

    expect(MatchModel::query()->where('stage_id', $stage->id)->count())->toBe(2);
    expect(MatchPlayer::query()->whereIn('match_id', MatchModel::query()->where('stage_id', $stage->id)->pluck('id'))->count())->toBe(4);
});

test('admin can create doubles matches from stage setup', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Doubles',
        'event_type' => Event::EVENT_TYPE_DOUBLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 2',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertOk()
        ->call('openCreationFlow')
        ->set('doubleMatches.0.player_a_1', 'A1')
        ->set('doubleMatches.0.player_a_2', 'A2')
        ->set('doubleMatches.0.player_b_1', 'B1')
        ->set('doubleMatches.0.player_b_2', 'B2')
        ->set('doubleMatches.1.player_a_1', 'C1')
        ->set('doubleMatches.1.player_a_2', 'C2')
        ->set('doubleMatches.1.player_b_1', 'D1')
        ->set('doubleMatches.1.player_b_2', 'D2')
        ->call('createDoublesMatches')
        ->assertHasNoErrors();

    expect(MatchModel::query()->where('stage_id', $stage->id)->count())->toBe(2);
    expect(MatchPlayer::query()->whereIn('match_id', MatchModel::query()->where('stage_id', $stage->id)->pluck('id'))->count())->toBe(8);
});

test('admin can create team ties and linked teams from stage setup', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Team',
        'event_type' => Event::EVENT_TYPE_TEAM,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 2',
        'best_of' => 5,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertOk()
        ->call('openCreationFlow')
        ->set('teamTies.0.team_a_name', 'Falcons')
        ->set('teamTies.0.team_b_name', 'Eagles')
        ->set('teamTies.1.team_a_name', 'Tigers')
        ->set('teamTies.1.team_b_name', 'Lions')
        ->call('createTeamTies')
        ->assertHasNoErrors();

    expect(Team::query()->where('event_id', $event->id)->count())->toBe(4);
    expect(Tie::query()->where('stage_id', $stage->id)->count())->toBe(2);
});

test('singles bye auto-completes match and sets winner side', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Singles Event',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->call('openCreationFlow')
        ->set('singleMatches.0.player_a', 'Bye')
        ->set('singleMatches.0.player_b', 'Hassan')
        ->call('createSinglesMatches')
        ->assertHasNoErrors();

    $match = MatchModel::query()->where('stage_id', $stage->id)->firstOrFail();
    expect($match->status)->toBe('completed');
    expect($match->winner_side)->toBe('b');
});

test('doubles bye auto-completes match and sets winner side', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Doubles Event',
        'event_type' => Event::EVENT_TYPE_DOUBLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->call('openCreationFlow')
        ->set('doubleMatches.0.player_a_1', 'Bye')
        ->set('doubleMatches.0.player_a_2', 'Bye')
        ->set('doubleMatches.0.player_b_1', 'Bilal')
        ->set('doubleMatches.0.player_b_2', 'Usman')
        ->call('createDoublesMatches')
        ->assertHasNoErrors();

    $match = MatchModel::query()->where('stage_id', $stage->id)->firstOrFail();
    expect($match->status)->toBe('completed');
    expect($match->winner_side)->toBe('b');
});

test('team tie bye auto-completes tie and sets winner team', function (): void {
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

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Team Event',
        'event_type' => Event::EVENT_TYPE_TEAM,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 5,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->call('openCreationFlow')
        ->set('teamTies.0.team_a_name', 'Bye')
        ->set('teamTies.0.team_b_name', 'Falcons')
        ->call('createTeamTies')
        ->assertHasNoErrors();

    $tie = Tie::query()->where('stage_id', $stage->id)->firstOrFail();
    expect($tie->status)->toBe('completed');
    expect($tie->winnerTeam)->not()->toBeNull();
    expect($tie->winnerTeam->name)->toBe('Falcons');
});
