<?php

use App\Models\User;
use Tests\TestCase;

test('guests are redirected to the login page', function () {
    /** @var TestCase $this */
    $response = $this->get(route('guide'));

    $response->assertRedirect(route('login'));
});

test('admin can view the guide', function () {
    /** @var TestCase $this */
    /** @var User $admin */
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('guide'));

    $response->assertOk();
    $response->assertSee('Admin checklist');
    $response->assertSee('You are viewing the Admin checklist.');
});

test('umpires can view the guide', function () {
    /** @var TestCase $this */
    /** @var User $umpire */
    $umpire = User::factory()->create([
        'role' => User::ROLE_UMPIRES,
    ]);

    $this->actingAs($umpire);

    $response = $this->get(route('guide'));

    $response->assertOk();
    $response->assertSee('Umpires checklist');
    $response->assertSee('You are viewing the Umpire checklist.');
});
