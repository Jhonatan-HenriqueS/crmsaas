<?php

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('a user cannot have duplicate memberships in the same account', function () {
    $membership = Membership::factory()->create();

    expect(fn () => $membership->replicate()->save())->toThrow(QueryException::class);
});

test('memberships require an existing user and account', function () {
    expect(fn () => Membership::create(['user_id' => 9999, 'account_id' => 9999, 'role' => 'owner']))->toThrow(QueryException::class);
});

test('dashboard exposes no account data or credentials from another tenant', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();

    $this->actingAs($own->user)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('auth.user.id', $own->user_id)->missing('accounts')->missing('memberships')
            ->missing('auth.user.password')->missing('auth.user.accounts')->missing('auth.user.memberships'))
        ->assertDontSee($other->account->name);
});

test('unverified users must verify their email when verification is required', function () {
    config(['auth.email_verification_required' => true]);
    $this->actingAs(User::factory()->unverified()->create())->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('unverified users can access the dashboard while email verification is optional', function () {
    config(['auth.email_verification_required' => false]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->email_verified_at)->toBeNull();
});
