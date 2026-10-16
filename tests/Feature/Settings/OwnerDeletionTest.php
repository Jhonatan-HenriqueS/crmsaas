<?php

use App\Models\Account;
use App\Models\Membership;
use App\Models\User;

test('deleting the last owner is rejected without logging out or deleting memberships', function () {
    $membership = Membership::factory()->create();
    $this->actingAs($membership->user)->deleteJson(route('profile.destroy'), ['password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertAuthenticatedAs($membership->user);
    $this->assertModelExists($membership);
    $this->assertModelExists($membership->account);
    $this->assertModelExists($membership->user);
});

test('deleting an owner is permitted when every account has another owner', function () {
    $membership = Membership::factory()->create();
    $other = Membership::factory()->for($membership->account)->create();
    $this->actingAs($membership->user)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('home'));
    $this->assertGuest();
    $this->assertModelMissing($membership->user);
    $this->assertModelExists($other);
    $this->assertModelExists($membership->account);
});

test('one sole ownership blocks deletion across all memberships atomically', function () {
    $user = User::factory()->create();
    $shared = Account::factory()->create();
    Membership::factory()->for($user)->for($shared)->create();
    Membership::factory()->for($shared)->create();
    $sole = Membership::factory()->for($user)->create();
    $this->actingAs($user)->deleteJson(route('profile.destroy'), ['password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('accounts', 2);
    $this->assertDatabaseCount('memberships', 3);
    $this->assertModelExists($sole);
});
