<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Team;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;

test('top level match sequence is 1-based order by id within stage', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $tournament = Tournament::create([
        'tournament_name' => 'Test Open',
        'location' => 'Test',
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
        'name' => 'Round 1',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $first = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'A1',
        'side_b_label' => 'B1',
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

    $second = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'A2',
        'side_b_label' => 'B2',
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

    expect($first->topLevelSequenceInStage())->toBe(1);
    expect($second->topLevelSequenceInStage())->toBe(2);
});

test('tie inner match has no top level sequence', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $tournament = Tournament::create([
        'tournament_name' => 'Test Open',
        'location' => 'Test',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'draft',
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
        'status' => 'pending',
    ]);

    $teamA = Team::create([
        'event_id' => $event->id,
        'name' => 'Falcons',
    ]);
    $teamB = Team::create([
        'event_id' => $event->id,
        'name' => 'Eagles',
    ]);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    $inner = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => $tie->id,
        'side_a_label' => 'P1',
        'side_b_label' => 'P2',
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

    expect($inner->topLevelSequenceInStage())->toBeNull();
});
