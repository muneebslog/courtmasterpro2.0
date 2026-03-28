<?php

use App\Models\Event;
use App\Models\Game;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;

test('welcome page shows hall screen links for courts 1 through 5', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk();

    foreach (range(1, 5) as $n) {
        $response->assertSee('Screen '.$n, false);
        $response->assertSee(route('live.court', ['court' => $n]), false);
    }
});

test('live court score api returns null match when no in-progress match on court', function (): void {
    $response = $this->getJson(route('api.live.court.score', ['court' => 2]));

    $response->assertOk()
        ->assertJsonPath('court', '2')
        ->assertJsonPath('match', null);
});

test('live court score api returns last completed match on court until next match starts', function (): void {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $tournament = Tournament::create([
        'tournament_name' => 'Hold Result Open',
        'location' => 'Hall',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Women Doubles',
        'event_type' => Event::EVENT_TYPE_DOUBLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 1,
        'order_index' => 1,
        'status' => 'active',
    ]);

    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Winner Side',
        'side_b_label' => 'Runner Up',
        'best_of' => 1,
        'status' => 'completed',
        'winner_side' => 'a',
        'court' => '4',
        'started_at' => now()->subHour(),
        'ended_at' => now()->subMinutes(5),
    ]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 21,
        'score_b' => 17,
        'winner_side' => 'a',
    ]);

    $response = $this->getJson(route('api.live.court.score', ['court' => 4]));

    $response->assertOk()
        ->assertJsonPath('court', '4')
        ->assertJsonPath('match.tournament_name', 'Hold Result Open')
        ->assertJsonPath('match.is_live', false)
        ->assertJsonPath('match.status', 'completed')
        ->assertJsonPath('match.winner_side', 'a')
        ->assertJsonPath('match.side_a_label', 'Winner Side')
        ->assertJsonPath('match.games.0.score_a', 21)
        ->assertJsonPath('match.games.0.score_b', 17);
});

test('live court score api prefers in-progress match over completed on same court', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Dual State Cup',
        'location' => 'Hall',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'R1',
        'best_of' => 1,
        'order_index' => 1,
        'status' => 'active',
    ]);

    MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Old',
        'side_b_label' => 'Done',
        'best_of' => 1,
        'status' => 'completed',
        'winner_side' => 'a',
        'court' => '5',
        'started_at' => now()->subHours(2),
        'ended_at' => now()->subHour(),
    ]);

    MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Live A',
        'side_b_label' => 'Live B',
        'best_of' => 1,
        'status' => 'in_progress',
        'winner_side' => null,
        'court' => '5',
        'started_at' => now(),
    ]);

    $this->getJson(route('api.live.court.score', ['court' => 5]))
        ->assertOk()
        ->assertJsonPath('match.is_live', true)
        ->assertJsonPath('match.status', 'in_progress')
        ->assertJsonPath('match.side_a_label', 'Live A');
});

test('live court score api returns match and games for in-progress match on court', function (): void {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $tournament = Tournament::create([
        'tournament_name' => 'Hall Test Open',
        'location' => 'Hall',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Men Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Semi Final',
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
        'status' => 'in_progress',
        'court' => '1',
        'started_at' => now(),
    ]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 21,
        'score_b' => 18,
        'winner_side' => 'a',
    ]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 2,
        'score_a' => 5,
        'score_b' => 7,
        'winner_side' => null,
    ]);

    $response = $this->getJson(route('api.live.court.score', ['court' => 1]));

    $response->assertOk()
        ->assertJsonPath('court', '1')
        ->assertJsonPath('match.side_a_label', 'Player One')
        ->assertJsonPath('match.side_b_label', 'Player Two')
        ->assertJsonPath('match.side_a_flag', null)
        ->assertJsonPath('match.side_b_flag', null)
        ->assertJsonPath('match.best_of', 3)
        ->assertJsonPath('match.status', 'in_progress')
        ->assertJsonPath('match.is_live', true)
        ->assertJsonPath('match.winner_side', null)
        ->assertJsonPath('match.tournament_name', 'Hall Test Open')
        ->assertJsonPath('match.event_name', 'Men Singles')
        ->assertJsonPath('match.stage_name', 'Semi Final')
        ->assertJsonPath('match.games.0.game_number', 1)
        ->assertJsonPath('match.games.0.score_a', 21)
        ->assertJsonPath('match.games.0.score_b', 18)
        ->assertJsonPath('match.games.0.winner_side', 'a')
        ->assertJsonPath('match.games.1.game_number', 2)
        ->assertJsonPath('match.games.1.score_a', 5)
        ->assertJsonPath('match.games.1.score_b', 7)
        ->assertJsonPath('match.games.1.winner_side', null);
});

test('live court score api returns side flags from match players when no tie', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Flag Cup',
        'location' => 'Hall',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'published',
        'admin_id' => $admin->id,
    ]);

    $event = Event::factory()->create([
        'tournament_id' => $tournament->id,
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'R1',
        'best_of' => 1,
        'order_index' => 1,
        'status' => 'active',
    ]);

    $match = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Ali',
        'side_b_label' => 'Bob',
        'best_of' => 1,
        'status' => 'in_progress',
        'court' => '3',
        'started_at' => now(),
    ]);

    $match->matchPlayers()->createMany([
        ['side' => 'a', 'player_name' => 'Ali', 'flag' => '🇵🇰', 'position' => 1],
        ['side' => 'b', 'player_name' => 'Bob', 'flag' => '🇺🇸', 'position' => 1],
    ]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 0,
        'score_b' => 0,
        'winner_side' => null,
    ]);

    $this->getJson(route('api.live.court.score', ['court' => 3]))
        ->assertOk()
        ->assertJsonPath('match.side_a_flag', '🇵🇰')
        ->assertJsonPath('match.side_b_flag', '🇺🇸');
});

test('live court display page loads standalone scoreboard shell', function (): void {
    $response = $this->get(route('live.court', ['court' => 1]));

    $response->assertOk()
        ->assertSee('Court 1', false)
        ->assertSee(route('api.live.court.score', ['court' => 1]), false)
        ->assertSee('XMLHttpRequest', false)
        ->assertSee('board-inner', false)
        ->assertSee('banner-tournament', false)
        ->assertSee('footer-tournament', false)
        ->assertSee('team1Flag', false)
        ->assertSee('team2Flag', false);
});

test('live court route rejects court numbers outside 1 to 5', function (): void {
    $this->get(route('live.court', ['court' => 9]))->assertNotFound();
    $this->getJson(route('api.live.court.score', ['court' => 0]))->assertNotFound();
});

test('live all page embeds court iframes for courts 1 through 5', function (): void {
    $response = $this->get(route('live.all'));

    $response->assertOk()
        ->assertSee('<iframe', false);

    foreach (range(1, 5) as $n) {
        $response->assertSee(route('live.court', ['court' => $n]), false);
    }
});
