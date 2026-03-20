<?php

use App\Models\Event as TournamentEvent;
use App\Models\Game;
use App\Models\MatchEvent;
use App\Models\MatchModel;
use App\Models\MatchPlayer;
use App\Models\Stage;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;

test('bracket schema relationships link end-to-end', function (): void {
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

    $event = TournamentEvent::create([
        'tournament_id' => $tournament->id,
        'event_name' => 'National Open Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 64',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    // Teams / ties are relevant for team events, but we still validate schema linkages.
    $teamA = Team::create([
        'event_id' => $event->id,
        'name' => 'Team A',
    ]);

    $teamB = Team::create([
        'event_id' => $event->id,
        'name' => 'Team B',
    ]);

    $teamPlayerA = TeamPlayer::create([
        'team_id' => $teamA->id,
        'player_name' => 'Alice',
    ]);

    $teamPlayerB = TeamPlayer::create([
        'team_id' => $teamB->id,
        'player_name' => 'Bob',
    ]);

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
        'side_a_label' => $teamPlayerA->player_name,
        'side_b_label' => $teamPlayerB->player_name,
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

    $matchPlayerA = MatchPlayer::create([
        'match_id' => $match->id,
        'side' => 'a',
        'player_name' => $teamPlayerA->player_name,
        'position' => 1,
    ]);

    $matchPlayerB = MatchPlayer::create([
        'match_id' => $match->id,
        'side' => 'b',
        'player_name' => $teamPlayerB->player_name,
        'position' => 1,
    ]);

    $game = Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 0,
        'score_b' => 0,
        'winner_side' => null,
        'entry_mode' => 'live',
        'started_at' => null,
        'ended_at' => null,
    ]);

    $matchEvent = MatchEvent::create([
        'match_id' => $match->id,
        'game_id' => $game->id,
        'event_type' => 'match_started',
        'side' => null,
        'player_name' => null,
        'score_a_at_time' => 0,
        'score_b_at_time' => 0,
        'notes' => null,
        'created_by' => 'umpire',
    ]);

    expect($event->stages()->count())->toBe(1);
    expect($event->teams()->count())->toBe(2);
    expect($stage->ties()->count())->toBe(1);
    expect($tie->matches()->count())->toBe(1);
    expect($match->matchPlayers()->count())->toBe(2);
    expect($match->games()->count())->toBe(1);
    expect($match->matchEvents()->count())->toBe(1);
    expect($game->matchEvents()->count())->toBe(1);

    expect($tie->teamA->id)->toBe($teamA->id);
    expect($tie->teamB->id)->toBe($teamB->id);
    expect($matchEvent->game->id)->toBe($game->id);

    expect($matchPlayerA->match->id)->toBe($match->id);
    expect($matchPlayerB->match->id)->toBe($match->id);
});
