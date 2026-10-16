<?php

use App\Models\Membership;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = Membership::factory()->create()->user;
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users without an owned account receive 404 on the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertNotFound();
});
