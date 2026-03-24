<?php

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\User;
use App\Services\NextStageService;
use Livewire\Livewire;

test('admin can preview and generate next singles stage from two completed matches', function (): void {
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
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 4',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $m1 = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Ali',
        'side_b_label' => 'Hassan',
        'match_order' => null,
        'best_of' => 3,
        'status' => 'completed',
        'winner_side' => 'a',
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => now(),
    ]);
    $m1->matchPlayers()->createMany([
        ['side' => 'a', 'player_name' => 'Ali', 'position' => 1],
        ['side' => 'b', 'player_name' => 'Hassan', 'position' => 1],
    ]);

    $m2 = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Bilal',
        'side_b_label' => 'Usman',
        'match_order' => null,
        'best_of' => 3,
        'status' => 'completed',
        'winner_side' => 'b',
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => now(),
    ]);
    $m2->matchPlayers()->createMany([
        ['side' => 'a', 'player_name' => 'Bilal', 'position' => 1],
        ['side' => 'b', 'player_name' => 'Usman', 'position' => 1],
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertSee('Generate next stage')
        ->call('openGenerateNextStageModal')
        ->assertSet('showGenerateNextStageModal', true)
        ->assertSet('nextStagePreview.next_stage_name', 'Final')
        ->set('nextStageBestOf', 1)
        ->call('confirmGenerateNextStage')
        ->assertRedirect(route('tournaments.events.stages.show', [
            'tournament' => $tournament->id,
            'event' => $event->id,
            'stage' => Stage::query()->where('event_id', $event->id)->where('order_index', 2)->value('id'),
        ]));

    $stages = Stage::query()->where('event_id', $event->id)->orderBy('order_index')->get();
    expect($stages)->toHaveCount(2);

    $next = $stages[1];
    expect($next->order_index)->toBe(2);
    expect($next->name)->toBe('Final');
    expect($next->best_of)->toBe(1);

    $matches = MatchModel::query()->where('stage_id', $next->id)->whereNull('tie_id')->get();
    expect($matches)->toHaveCount(1);
    expect($matches->first()->side_a_label)->toBe('Ali');
    expect($matches->first()->side_b_label)->toBe('Usman');
});

test('generate next stage button is hidden when only one match exists in stage', function (): void {
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

    $m1 = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Ali',
        'side_b_label' => 'Hassan',
        'match_order' => null,
        'best_of' => 3,
        'status' => 'completed',
        'winner_side' => 'a',
        'umpire_name' => null,
        'service_judge_name' => null,
        'court' => null,
        'started_at' => null,
        'ended_at' => now(),
    ]);
    $m1->matchPlayers()->createMany([
        ['side' => 'a', 'player_name' => 'Ali', 'position' => 1],
        ['side' => 'b', 'player_name' => 'Hassan', 'position' => 1],
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertDontSee('Generate next stage');
});

test('next stage service refuses duplicate generation when a later stage already exists', function (): void {
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
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage1 = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 4',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    Stage::create([
        'event_id' => $event->id,
        'name' => 'Final',
        'best_of' => 3,
        'order_index' => 2,
        'status' => 'pending',
    ]);

    foreach ([['Ali', 'Hassan'], ['Bilal', 'Usman']] as [$a, $b]) {
        $m = MatchModel::create([
            'stage_id' => $stage1->id,
            'tie_id' => null,
            'side_a_label' => $a,
            'side_b_label' => $b,
            'match_order' => null,
            'best_of' => 3,
            'status' => 'completed',
            'winner_side' => 'a',
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => null,
            'ended_at' => now(),
        ]);
        $m->matchPlayers()->createMany([
            ['side' => 'a', 'player_name' => $a, 'position' => 1],
            ['side' => 'b', 'player_name' => $b, 'position' => 1],
        ]);
    }

    $this->actingAs($admin);

    expect(app(NextStageService::class)->canShowGenerateButton($stage1))->toBeFalse();

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage1->id,
    ])
        ->assertDontSee('Generate next stage');
});

test('generate next stage is not offered when bracket match count is not a power of two', function (): void {
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
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    foreach (['A', 'B', 'C'] as $suffix) {
        $m = MatchModel::create([
            'stage_id' => $stage->id,
            'tie_id' => null,
            'side_a_label' => 'P'.$suffix.'1',
            'side_b_label' => 'P'.$suffix.'2',
            'match_order' => null,
            'best_of' => 3,
            'status' => 'completed',
            'winner_side' => 'a',
            'umpire_name' => null,
            'service_judge_name' => null,
            'court' => null,
            'started_at' => null,
            'ended_at' => now(),
        ]);
        $m->matchPlayers()->createMany([
            ['side' => 'a', 'player_name' => 'X', 'position' => 1],
            ['side' => 'b', 'player_name' => 'Y', 'position' => 1],
        ]);
    }

    $this->actingAs($admin);

    expect(app(NextStageService::class)->canShowGenerateButton($stage))->toBeFalse();

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->assertDontSee('Generate next stage');
});

test('admin can delete a pending top-level match', function (): void {
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
        'event_name' => 'Singles',
        'event_type' => Event::EVENT_TYPE_SINGLES,
    ]);

    $stage = Stage::create([
        'event_id' => $event->id,
        'name' => 'Round of 4',
        'best_of' => 3,
        'order_index' => 1,
        'status' => 'pending',
    ]);

    $m = MatchModel::create([
        'stage_id' => $stage->id,
        'tie_id' => null,
        'side_a_label' => 'Ali',
        'side_b_label' => 'Hassan',
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
    $m->matchPlayers()->createMany([
        ['side' => 'a', 'player_name' => 'Ali', 'position' => 1],
        ['side' => 'b', 'player_name' => 'Hassan', 'position' => 1],
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::event.stage', [
        'tournament' => $tournament->id,
        'event' => $event->id,
        'stage' => $stage->id,
    ])
        ->call('deletePendingTopLevelMatch', $m->id);

    expect(MatchModel::query()->whereKey($m->id)->exists())->toBeFalse();
});
