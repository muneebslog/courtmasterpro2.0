<?php

use App\Models\Event as TournamentEvent;
use App\Models\Game;
use App\Models\MatchModel;
use App\Models\MatchPlayer;
use App\Models\Stage;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('umpire can visit control panel and add points', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
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

    Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->call('addPoint', 'a')
        ->assertHasNoErrors();

    $match->refresh();
    expect($match->status)->toBe('in_progress');

    $game = $match->games()->first();
    expect($game)->not()->toBeNull();
    expect($game->score_a)->toBe(1);
    expect($game->score_b)->toBe(0);

    $pointEvent = $match->matchEvents()->where('event_type', 'point')->first();
    expect($pointEvent)->not()->toBeNull();
    expect($pointEvent->side)->toBe('a');
    expect($pointEvent->score_a_at_time)->toBe(1);
    expect($pointEvent->score_b_at_time)->toBe(0);
});

test('umpire can undo last point', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
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
        'status' => 'in_progress',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => now(),
        'ended_at' => null,
    ]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'a', 'player_name' => 'Alice', 'position' => 1]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'b', 'player_name' => 'Bob', 'position' => 1]);

    $game = Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 5,
        'score_b' => 3,
        'winner_side' => null,
        'entry_mode' => 'live',
        'started_at' => null,
        'ended_at' => null,
    ]);
    $match->matchEvents()->create([
        'game_id' => $game->id,
        'event_type' => 'point',
        'side' => 'a',
        'player_name' => null,
        'score_a_at_time' => 5,
        'score_b_at_time' => 3,
        'notes' => null,
        'created_by' => 'umpire',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->call('undoLastPoint')
        ->assertHasNoErrors();

    $game->refresh();
    expect($game->score_a)->toBe(4);
    expect($game->score_b)->toBe(3);

    $undoEvent = $match->matchEvents()->where('event_type', 'undo')->first();
    expect($undoEvent)->not()->toBeNull();
});

test('umpire can log occurrence', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
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
        'status' => 'in_progress',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => now(),
        'ended_at' => null,
    ]);
    $mp = MatchPlayer::create(['match_id' => $match->id, 'side' => 'a', 'player_name' => 'Alice', 'position' => 1]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'b', 'player_name' => 'Bob', 'position' => 1]);

    $game = Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 10,
        'score_b' => 8,
        'winner_side' => null,
        'entry_mode' => 'live',
        'started_at' => null,
        'ended_at' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->set('occurrenceType', 'card')
        ->set('cardType', 'yellow')
        ->set('occurrenceSide', 'a')
        ->set('occurrencePlayerId', $mp->id)
        ->call('logOccurrence')
        ->assertHasNoErrors();

    $occ = $match->matchEvents()->where('event_type', 'occurrence')->first();
    expect($occ)->not()->toBeNull();
    expect($occ->side)->toBe('a');
    expect($occ->player_name)->toBe('Alice');
    $notes = json_decode($occ->notes, true);
    expect($notes['subtype'] ?? null)->toBe('yellow_card');
});

test('game winner detected at 21 with 2 point lead and next game created', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
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
        'status' => 'in_progress',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => now(),
        'ended_at' => null,
    ]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'a', 'player_name' => 'Alice', 'position' => 1]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'b', 'player_name' => 'Bob', 'position' => 1]);

    $game = Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 20,
        'score_b' => 19,
        'winner_side' => null,
        'entry_mode' => 'live',
        'started_at' => null,
        'ended_at' => null,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->call('addPoint', 'a')
        ->assertHasNoErrors();

    $game->refresh();
    expect($game->winner_side)->toBe('a');
    expect($game->score_a)->toBe(21);
    expect($game->score_b)->toBe(19);

    $gameEnded = $match->matchEvents()->where('event_type', 'game_ended')->first();
    expect($gameEnded)->not()->toBeNull();

    $component->call('startNextRound');

    $game2 = $match->games()->where('game_number', 2)->first();
    expect($game2)->not()->toBeNull();
    expect($game2->score_a)->toBe(0);
    expect($game2->score_b)->toBe(0);
});

test('match winner detected when required games won', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
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
        'status' => 'in_progress',
        'winner_side' => null,
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => now(),
        'ended_at' => null,
    ]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'a', 'player_name' => 'Alice', 'position' => 1]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'b', 'player_name' => 'Bob', 'position' => 1]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 21,
        'score_b' => 15,
        'winner_side' => 'a',
        'entry_mode' => 'live',
        'started_at' => null,
        'ended_at' => now(),
    ]);
    $game2 = Game::create([
        'match_id' => $match->id,
        'game_number' => 2,
        'score_a' => 20,
        'score_b' => 19,
        'winner_side' => null,
        'entry_mode' => 'live',
        'started_at' => null,
        'ended_at' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->call('addPoint', 'a')
        ->assertHasNoErrors();

    $match->refresh();
    expect($match->status)->toBe('completed');
    expect($match->winner_side)->toBe('a');

    $matchEnded = $match->matchEvents()->where('event_type', 'match_ended')->first();
    expect($matchEnded)->not()->toBeNull();
});

test('team tie pre-start modal defaults first eligible players so start match works without extra selects', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

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
        'event_name' => 'Team Event',
        'event_type' => TournamentEvent::EVENT_TYPE_TEAM,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'Falcons']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'Eagles']);

    $pA1 = TeamPlayer::create(['team_id' => $teamA->id, 'player_name' => 'Alice']);
    TeamPlayer::create(['team_id' => $teamA->id, 'player_name' => 'Bob']);
    $pB1 = TeamPlayer::create(['team_id' => $teamB->id, 'player_name' => 'X']);
    TeamPlayer::create(['team_id' => $teamB->id, 'player_name' => 'Y']);

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

    $this->actingAs($admin);

    $component = Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ]);

    $ids = $component->get('selectedPlayerIds');
    expect($ids['side_a_1'])->toBe($pA1->id);
    expect($ids['side_b_1'])->toBe($pB1->id);

    $component->call('startMatch')->assertHasNoErrors();

    $match->refresh();
    expect($match->status)->toBe('in_progress');
    expect($match->matchPlayers()->count())->toBe(2);
});

test('team tie winner is set after 3 inner matches and remaining matches become not required', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

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
        'event_name' => 'National Open Team',
        'event_type' => TournamentEvent::EVENT_TYPE_TEAM,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
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

    $matchOrders = ['S1', 'D1', 'S2', 'D2', 'S3'];
    $innerMatches = [];

    foreach ($matchOrders as $idx => $order) {
        $status = $idx < 3 ? 'in_progress' : 'pending';

        $innerMatch = MatchModel::create([
            'stage_id' => $stage->id,
            'tie_id' => $tie->id,
            'side_a_label' => $teamA->name,
            'side_b_label' => $teamB->name,
            'match_order' => $order,
            'best_of' => 3,
            'status' => $status,
            'winner_side' => null,
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => $status === 'in_progress' ? now() : null,
            'ended_at' => null,
        ]);

        if ($status === 'in_progress') {
            Game::create([
                'match_id' => $innerMatch->id,
                'game_number' => 1,
                'score_a' => 21,
                'score_b' => 15,
                'winner_side' => 'a',
                'entry_mode' => 'live',
                'started_at' => null,
                'ended_at' => now(),
            ]);

            Game::create([
                'match_id' => $innerMatch->id,
                'game_number' => 2,
                'score_a' => 20,
                'score_b' => 19,
                'winner_side' => null,
                'entry_mode' => 'live',
                'started_at' => null,
                'ended_at' => null,
            ]);
        }

        $innerMatches[$order] = $innerMatch;
    }

    $this->actingAs($admin);

    foreach (['S1', 'D1', 'S2'] as $order) {
        $m = $innerMatches[$order]->fresh();

        Livewire::test('pages::event.match.controlpanel', [
            'tournament' => $tournament->id,
            'event' => $event->id,
            'stage' => $stage->id,
            'match' => $m->id,
        ])
            ->call('addPoint', 'a')
            ->assertHasNoErrors();
    }

    $tie->refresh();
    expect($tie->status)->toBe('completed');
    expect($tie->winner_team_id)->toBe($teamA->id);

    $innerMatches['D2']->refresh();
    $innerMatches['S3']->refresh();
    expect($innerMatches['D2']->status)->toBe('not_required');
    expect($innerMatches['S3']->status)->toBe('not_required');
});

test('completed match control panel shows per-game scores not placeholder zeros', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        'event_name' => 'Singles',
        'event_type' => TournamentEvent::EVENT_TYPE_SINGLES,
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
        'side_a_label' => 'Olympia Roy',
        'side_b_label' => 'Drake Hyde',
        'match_order' => null,
        'best_of' => 3,
        'status' => 'completed',
        'winner_side' => 'a',
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => now(),
        'ended_at' => now(),
    ]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'a', 'player_name' => 'Olympia Roy', 'position' => 1]);
    MatchPlayer::create(['match_id' => $match->id, 'side' => 'b', 'player_name' => 'Drake Hyde', 'position' => 1]);

    Game::create([
        'match_id' => $match->id,
        'game_number' => 1,
        'score_a' => 21,
        'score_b' => 0,
        'winner_side' => 'a',
        'entry_mode' => 'bulk',
        'started_at' => null,
        'ended_at' => now(),
    ]);
    Game::create([
        'match_id' => $match->id,
        'game_number' => 2,
        'score_a' => 21,
        'score_b' => 0,
        'winner_side' => 'a',
        'entry_mode' => 'bulk',
        'started_at' => null,
        'ended_at' => now(),
    ]);

    $this->actingAs($admin);

    $html = Livewire::test('pages::event.match.controlpanel', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'match' => $match->id,
    ])
        ->assertOk()
        ->assertSee(__('Match score'))
        ->assertSee('Olympia Roy')
        ->assertSee('Drake Hyde')
        ->html();

    expect(preg_match_all('/\b21\b/', $html))->toBeGreaterThanOrEqual(2);
});
