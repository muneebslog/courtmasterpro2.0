<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Team;
use App\Models\Tie;
use App\Models\Tournament;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('admin can view tie page with inner matches in order', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Regional Cup',
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
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'Falcons']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'Eagles']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    foreach (['S1', 'D1', 'S2', 'D2', 'S3'] as $order) {
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

    Livewire::test('pages::event.tie', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'tie' => $tie->id,
    ])
        ->assertOk()
        ->assertSee(__('Tie progress'))
        ->assertSee('Match 1')
        ->assertSee('Match 2')
        ->assertSee('Match 3')
        ->assertSee('Match 4')
        ->assertSee('Match 5')
        ->assertSee('Falcons')
        ->assertSee('Eagles');
});

test('tie page shows Start only for first non-terminal match when all pending', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Regional Cup',
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
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'Falcons']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'Eagles']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    $orders = ['S1', 'D1', 'S2', 'D2', 'S3'];
    foreach ($orders as $order) {
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

    $html = Livewire::test('pages::event.tie', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'tie' => $tie->id,
    ])->html();

    expect(substr_count($html, __('Start')))->toBe(1);
    expect(substr_count($html, __('Waiting for previous matches')))->toBe(4);
});

test('tie page shows Continue when first match is in progress', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Regional Cup',
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
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'Falcons']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'Eagles']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    $orders = ['S1', 'D1', 'S2', 'D2', 'S3'];
    foreach ($orders as $i => $order) {
        MatchModel::create([
            'stage_id' => $stage->id,
            'tie_id' => $tie->id,
            'side_a_label' => $teamA->name,
            'side_b_label' => $teamB->name,
            'match_order' => $order,
            'best_of' => 3,
            'status' => $i === 0 ? 'in_progress' : 'pending',
            'winner_side' => null,
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::event.tie', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'tie' => $tie->id,
    ])
        ->assertOk()
        ->assertSee(__('Continue'))
        ->assertDontSee(__('Start'));
});

test('non-team event returns 404 on tie page', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $tournament = Tournament::create([
        'tournament_name' => 'Regional Cup',
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

    $teamA = Team::create(['event_id' => $event->id, 'name' => 'A']);
    $teamB = Team::create(['event_id' => $event->id, 'name' => 'B']);

    $tie = Tie::create([
        'stage_id' => $stage->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'winner_team_id' => null,
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.tie', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
        'tie' => $tie->id,
    ])->assertNotFound();
});
