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

test('sidebar lists tournament events with links on tournament show page', function (): void {
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

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Mens Singles Championship Division',
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('tournaments.show', $tournament));

    $response->assertOk();
    $response->assertSee('Mens Singles Championship Division');
    $response->assertSee(route('tournaments.events.show', [$tournament, $event]), false);
});

test('sidebar lists events for tournaments attached to umpire user', function (): void {
    /** @var User $admin */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    /** @var User $umpire */
    $umpire = User::factory()->create([
        'role' => User::ROLE_UMPIRES,
    ]);

    $tournament = Tournament::create([
        'tournament_name' => 'Regional Cup 2026',
        'location' => 'Peshawar',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $tournament->users()->attach([$umpire->id]);

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Umpire Sidebar Event',
    ]);

    $this->actingAs($umpire);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Umpire Sidebar Event');
    $response->assertSee(route('tournaments.events.show', [$tournament, $event]), false);
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

test('admin can delete an event that has no stages or matches', function (): void {
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
        'event_name' => 'Deletable Event',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->call('deleteEvent')
        ->assertHasNoErrors()
        ->assertRedirect(route('tournaments.show', $tournament->id));

    expect(Event::query()->whereKey($event->id)->exists())->toBeFalse();
});

test('admin cannot delete an event that has a stage', function (): void {
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

    Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 16',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->call('deleteEvent')
        ->assertHasErrors('delete_event');

    expect(Event::query()->whereKey($event->id)->exists())->toBeTrue();
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

test('event page lists stage match remaining and player counts', function (): void {
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

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 16',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'A',
        'side_b_label' => 'B',
        'match_order' => 1,
        'best_of' => 3,
        'status' => 'pending',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => null,
    ]);

    MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'C',
        'side_b_label' => 'D',
        'match_order' => 2,
        'best_of' => 3,
        'status' => 'completed',
        'winner_side' => 'a',
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => now(),
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event', [
        'tournament' => $tournament->id,
        'event' => $event->id,
    ])
        ->assertOk()
        ->assertSee('Round of 16')
        ->assertSee('data-stage-stat-total="2"', false)
        ->assertSee('data-stage-stat-remaining="1"', false)
        ->assertSee('data-stage-stat-participants="4"', false);
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
        ->set('singleMatches.0.player_a_flag', '🇵🇰')
        ->set('singleMatches.0.player_b', 'Hassan')
        ->set('singleMatches.0.player_b_flag', '🇮🇳')
        ->set('singleMatches.1.player_a', 'Bilal')
        ->set('singleMatches.1.player_a_flag', '🇬🇧')
        ->set('singleMatches.1.player_b', 'Usman')
        ->set('singleMatches.1.player_b_flag', '🇺🇸')
        ->call('createSinglesMatches')
        ->assertHasNoErrors();

    expect(MatchModel::query()->where('stage_id', $stage->id)->count())->toBe(2);
    expect(MatchPlayer::query()->whereIn('match_id', MatchModel::query()->where('stage_id', $stage->id)->pluck('id'))->count())->toBe(4);
    expect(MatchModel::query()->where('stage_id', $stage->id)->orderBy('id')->firstOrFail()->side_a_label)->toBe('🇵🇰 Ali');
    expect(MatchModel::query()->where('stage_id', $stage->id)->orderBy('id')->firstOrFail()->side_b_label)->toBe('🇮🇳 Hassan');
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
        ->set('doubleMatches.0.player_a_1_flag', '🇵🇰')
        ->set('doubleMatches.0.player_a_2', 'A2')
        ->set('doubleMatches.0.player_a_2_flag', '🇬🇧')
        ->set('doubleMatches.0.player_b_1', 'B1')
        ->set('doubleMatches.0.player_b_1_flag', '🇺🇸')
        ->set('doubleMatches.0.player_b_2', 'B2')
        ->set('doubleMatches.0.player_b_2_flag', '🇩🇪')
        ->set('doubleMatches.1.player_a_1', 'C1')
        ->set('doubleMatches.1.player_a_1_flag', '🇮🇩')
        ->set('doubleMatches.1.player_a_2', 'C2')
        ->set('doubleMatches.1.player_a_2_flag', '🇲🇾')
        ->set('doubleMatches.1.player_b_1', 'D1')
        ->set('doubleMatches.1.player_b_1_flag', '🇯🇵')
        ->set('doubleMatches.1.player_b_2', 'D2')
        ->set('doubleMatches.1.player_b_2_flag', '🇨🇳')
        ->call('createDoublesMatches')
        ->assertHasNoErrors();

    expect(MatchModel::query()->where('stage_id', $stage->id)->count())->toBe(2);
    expect(MatchPlayer::query()->whereIn('match_id', MatchModel::query()->where('stage_id', $stage->id)->pluck('id'))->count())->toBe(8);
    expect(MatchModel::query()->where('stage_id', $stage->id)->orderBy('id')->firstOrFail()->side_a_label)->toContain('🇵🇰 A1');
    expect(MatchModel::query()->where('stage_id', $stage->id)->orderBy('id')->firstOrFail()->side_b_label)->toContain('🇺🇸 B1');
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
        ->set('teamTies.0.team_a_flag', '🇵🇰')
        ->set('teamTies.0.team_b_name', 'Eagles')
        ->set('teamTies.0.team_b_flag', '🇺🇸')
        ->set('teamTies.1.team_a_name', 'Tigers')
        ->set('teamTies.1.team_a_flag', '🇬🇧')
        ->set('teamTies.1.team_b_name', 'Lions')
        ->set('teamTies.1.team_b_flag', '🇩🇪')
        ->call('createTeamTies')
        ->assertHasNoErrors();

    expect(Team::query()->where('event_id', $event->id)->count())->toBe(4);
    expect(Team::query()->where('event_id', $event->id)->where('name', 'Falcons')->firstOrFail()->flag)->toBe('🇵🇰');
    expect(Team::query()->where('event_id', $event->id)->where('name', 'Eagles')->firstOrFail()->flag)->toBe('🇺🇸');
    expect(Tie::query()->where('stage_id', $stage->id)->count())->toBe(2);
    expect(MatchModel::query()->where('stage_id', $stage->id)->count())->toBe(10);
});

test('admin can create team ties in countries mode storing country names when custom labels are empty', function (): void {
    /** @var TestCase $this */
    /** @var User $admin */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $tournament = Tournament::create([
        'tournament_name' => 'International Team 2026',
        'location' => 'Test',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
        'admin_id' => $admin->id,
    ]);

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'International Team',
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
        ->assertOk()
        ->call('openCreationFlow')
        ->set('teamTieSideLabelMode', 'countries')
        ->set('teamTies.0.team_a_flag', '🇵🇰')
        ->set('teamTies.0.team_b_flag', '🇺🇸')
        ->call('createTeamTies')
        ->assertHasNoErrors();

    $pakistan = Team::query()->where('event_id', $event->id)->where('name', 'Pakistan')->firstOrFail();
    expect($pakistan->flag)->toBe('🇵🇰');

    $unitedStates = Team::query()->where('event_id', $event->id)->where('name', 'United States')->firstOrFail();
    expect($unitedStates->flag)->toBe('🇺🇸');

    expect(Tie::query()->where('stage_id', $stage->id)->count())->toBe(1);
});

test('admin can add team player from team players tab', function (): void {
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

    $team = Team::create([
        'event_id' => $event->id,
        'name' => 'Falcons',
        'flag' => '🇵🇰',
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
        ->assertOk()
        ->call('openTeamPlayersTab')
        ->set('teamPlayerInputs.'.$team->id, 'Ali')
        ->call('addTeamPlayer', $team->id)
        ->assertHasNoErrors()
        ->assertSee('Ali');

    $team->refresh();
    $player = $team->teamPlayers()->firstOrFail();
    expect($player->player_name)->toBe('Ali');
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

    $matches = MatchModel::query()->where('tie_id', $tie->id)->get();
    expect($matches)->toHaveCount(5);
    $statuses = $matches->pluck('status')->unique()->values();
    expect($statuses)->toHaveCount(1);
    expect($statuses->first())->toBe('not_required');
});

test('admin can bulk enter scores for pending match', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);
    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);
    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Alice',
        'side_b_label' => 'Bob',
        'match_order' => null,
        'best_of' => 3,
        'status' => 'pending',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => null,
    ]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'a', 'player_name' => 'Alice', 'position' => 1]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'b', 'player_name' => 'Bob', 'position' => 1]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertOk()
        ->call('openBulkScoreModal', $match->id)
        ->assertSet('showBulkScoreModal', true)
        ->assertSet('bulkScoreMatchId', $match->id)
        ->set('bulkScores.1.score_a', '21')
        ->set('bulkScores.1.score_b', '15')
        ->set('bulkScores.2.score_a', '15')
        ->set('bulkScores.2.score_b', '21')
        ->set('bulkScores.3.score_a', '21')
        ->set('bulkScores.3.score_b', '18')
        ->call('submitBulkScores')
        ->assertHasNoErrors()
        ->assertSet('showBulkScoreModal', false);

    $match->refresh();
    expect($match->status)->toBe('completed');
    expect($match->winner_side)->toBe('a');

    $games = $match->games()->orderBy('game_number')->get();
    expect($games)->toHaveCount(3);
    expect($games[0]->entry_mode)->toBe('bulk');
    expect($games[0]->score_a)->toBe(21);
    expect($games[0]->score_b)->toBe(15);
    expect($games[0]->winner_side)->toBe('a');

    $bulkEvents = $match->matchEvents()->where('event_type', 'bulk_score_entry')->get();
    expect($bulkEvents)->toHaveCount(3);
    expect($bulkEvents[0]->notes)->toContain('offline_entry');
});
