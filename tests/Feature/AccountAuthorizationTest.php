<?php

use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth'])->get('/test/accounts/{account}', fn (Account $account) => ['account_id' => $account->id, 'type' => $account->type])
        ->can('view', 'account');
});

test('account context is authorized and cannot cross users', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $this->actingAs($own->user)->getJson('/test/accounts/'.$own->account_id)->assertOk()->assertJsonPath('account_id', $own->account_id);
    $this->getJson('/test/accounts/'.$other->account_id.'?role=owner&account_id='.$own->account_id)->assertNotFound();
    $this->actingAs($other->user)->getJson('/test/accounts/'.$own->account_id)->assertNotFound();
    $this->getJson('/test/accounts/'.$other->account_id)->assertOk()->assertJsonPath('account_id', $other->account_id);
    expect($own->fresh()->role)->toBe('owner');
});

test('account membership is checked after revocation even with a loaded relationship', function () {
    $membership = Membership::factory()->create();
    $user = $membership->user->load('memberships');
    $account = $membership->account;
    expect(Gate::forUser($user)->allows('update', $account))->toBeTrue();
    $this->actingAs($user)->getJson('/test/accounts/'.$account->id)->assertOk();
    $membership->delete();
    $this->getJson('/test/accounts/'.$account->id)->assertNotFound();
    expect(Gate::forUser($user)->allows('update', $account))->toBeFalse();
});

test('an account without membership cannot be assumed by browser supplied id', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $this->actingAs($user)->getJson('/test/accounts/'.$account->id)->assertNotFound();
    expect(Gate::forUser($user)->allows('update', $account))->toBeFalse();
    expect($account->fresh()->type)->toBe($account->type);
});
