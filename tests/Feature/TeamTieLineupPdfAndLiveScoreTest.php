<?php

use App\Models\Event;
use App\Models\Game;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;

test('team tie control panel start persists lineup on match_players', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Cup',
        'location' => 'Hall',
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
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'Falcons', 'flag' => '🇵🇰']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'Eagles', 'flag' => '🇮🇳']);

    $playerA = TeamPlayer::create(['team_id' => $teamA->id, 'player_name' => 'Ali']);
    $playerB = TeamPlayer::create(['team_id' => $teamB->id, 'player_name' => 'Bob']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => $tie->id,
        'side_a_label' => $teamA->name,
        'side_b_label' => $teamB->name,
        'match_order' => 'S1',
        'best_of' => 3,
        'status' => 'pending',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => null,
    ]);

    Livewire::actingAs($admin)->test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->set('umpireName', 'Umpire X')
        ->set('teamLineupFormat', 'singles')
        ->set('sideATeamPlayer1Id', $playerA->id)
        ->set('sideBTeamPlayer1Id', $playerB->id)
        ->call('startMatch')
        ->assertHasNoErrors();

    $match->refresh();

    expect($match->status)->toBe('in_progress');
    expect($match->matchPlayers()->count())->toBe(2);
    expect($match->side_a_label)->toContain('Ali');
    expect($match->side_b_label)->toContain('Bob');
    expect($match->matchPlayers()->whereNotNull('team_player_id')->count())->toBe(2);
});

test('live court score api returns per-side subtitles for team tie with lineup', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Hall Open',
        'location' => 'Hall',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $event = Event::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Teams',
        'event_type' => Event::EVENT_TYPE_TEAM,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round 1',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'active',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'North', 'flag' => '🇵🇰']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'South', 'flag' => '🇺🇸']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'in_progress',
    ]);

    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => $tie->id,
        'side_a_label' => 'North One',
        'side_b_label' => 'South One',
        'match_order' => 'S1',
        'best_of' => 3,
        'status' => 'in_progress',
        'winner_side' => null,
        'court' => '2',
        'started_at' => now(),
    ]);

    $match->matchPlayers()->createMany([
        ['side' => 'a', 'player_name' => 'North One', 'position' => 1, 'team_player_id' => null],
        ['side' => 'b', 'player_name' => 'South One', 'position' => 1, 'team_player_id' => null],
    ]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 5,
        'score_b' => 3,
        'winner_side' => null,
    ]);

    $response = $this->getJson(route('api.live.court.score', ['court' => 2]));

    $response->assertOk()
        ->assertJsonPath('court', '2')
        ->assertJsonPath('match.is_live', true)
        ->assertJsonPath('match.side_a_label', 'North One')
        ->assertJsonPath('match.side_a_flag', '🇵🇰')
        ->assertJsonPath('match.side_b_flag', '🇺🇸');

    $subA = $response->json('match.subtitle_a');
    $subB = $response->json('match.subtitle_b');

    expect($subA)->toContain('North');
    expect($subA)->toContain('North One');
    expect($subB)->toContain('South');
    expect($subB)->toContain('South One');
});

test('admin can download tie pdf for team event', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'PDF Cup',
        'location' => 'Hall',
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
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'Alpha']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'Beta']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => $tie->id,
        'side_a_label' => $teamA->name,
        'side_b_label' => $teamB->name,
        'match_order' => 'S1',
        'best_of' => 3,
        'status' => 'pending',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('tournaments.events.stages.ties.pdf', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'tie' => $tie->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

test('bulk score entry for tie match requires lineup first', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Bulk Cup',
        'location' => 'Hall',
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
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'A']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'B']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => $tie->id,
        'side_a_label' => $teamA->name,
        'side_b_label' => $teamB->name,
        'match_order' => 'S1',
        'best_of' => 3,
        'status' => 'pending',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => null,
    ]);

    foreach (['D1', 'S2', 'D2', 'S3'] as $order) {
        MatchModel::create([
            'stage_id' => $stage->id,
            'tie_id' => $tie->id,
            'side_a_label' => $teamA->name,
            'side_b_label' => $teamB->name,
            'match_order' => $order,
            'best_of' => 3,
            'status' => 'pending',
            'winner_side' => null,
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => null,
            'ended_at' => null,
        ]);
    }

    Livewire::actingAs($admin)->test('pages::event.tie', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'tie' => $tie->id,
    ])
        ->call('openBulkScoreModal', $match->id)
        ->set('bulkScores.1.score_a', '21')
        ->set('bulkScores.1.score_b', '15')
        ->set('bulkScores.2.score_a', '21')
        ->set('bulkScores.2.score_b', '10')
        ->call('submitBulkScores')
        ->assertHasErrors('bulkScores');
});
