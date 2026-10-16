<?php

use App\Http\CurrentAccount;
use App\Http\Middleware\ResolveCurrentAccount;
use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Route::middleware(['web', 'auth', ResolveCurrentAccount::class])
        ->get('/test/current-account', function (CurrentAccount $currentAccount) {
            $account = $currentAccount->get();

            return ['account_id' => $account->id, 'type' => $account->type];
        });
});

test('the current account is resolved from its persisted owner membership', function (string $type) {
    $membership = Membership::factory()->for(Account::factory()->state(['type' => $type]))->create();

    $this->actingAs($membership->user)->getJson('/test/current-account')
        ->assertOk()->assertExactJson(['account_id' => $membership->account_id, 'type' => $type]);
})->with(['empresa', 'agencia']);

test('browser supplied account selectors and roles cannot replace the current account', function () {
    $own = Membership::factory()->for(Account::factory()->state(['type' => 'empresa']))->create();
    $other = Membership::factory()->for(Account::factory()->state(['type' => 'agencia']))->create();

    $this->actingAs($own->user)->withSession(['account_id' => $other->account_id, 'active_account_id' => $other->account_id])
        ->withHeader('X-Account-ID', (string) $other->account_id)
        ->getJson('/test/current-account?'.http_build_query([
            'account_id' => $other->account_id, 'active_account_id' => $other->account_id,
            'role' => 'owner', 'type' => 'agencia', CurrentAccount::class => ['id' => $other->account_id],
        ]))->assertOk()->assertExactJson(['account_id' => $own->account_id, 'type' => 'empresa']);
});

test('a missing membership returns 404 even with forged loaded account relationships', function () {
    $other = Membership::factory()->create();
    $user = User::factory()->create();
    $user->setRelation('accounts', collect([$other->account]));
    $user->setRelation('ownedAccounts', collect([$other->account]));
    $user->setRelation('memberships', collect([$other]));

    $this->actingAs($user)->getJson('/test/current-account?account_id='.$other->account_id)->assertNotFound();
});

test('revoking membership blocks the next request despite loaded relationships', function () {
    $membership = Membership::factory()->create();
    $user = $membership->user->load('ownedAccounts', 'memberships');

    $this->actingAs($user)->getJson('/test/current-account')->assertOk()->assertJsonPath('account_id', $membership->account_id);
    $membership->delete();

    $this->getJson('/test/current-account')->assertNotFound();
});

test('multiple owned accounts return 404 instead of selecting an arbitrary account', function () {
    $user = User::factory()->create();
    $memberships = Membership::factory()->count(2)->for($user)->create();

    $this->actingAs($user)->getJson('/test/current-account?account_id='.$memberships->first()->account_id)->assertNotFound();
});

test('independent authenticated browsers keep their account context isolated', function () {
    config(['session.driver' => 'database']);
    $first = Membership::factory()->create();
    $second = Membership::factory()->create();
    $a = $this->browserCookies($this->browserRequest('POST', route('login.store'), [
        'email' => $first->user->email, 'password' => 'password',
    ])->assertRedirect());
    $b = $this->browserCookies($this->browserRequest('POST', route('login.store'), [
        'email' => $second->user->email, 'password' => 'password',
    ])->assertRedirect());

    $this->browserRequest('GET', '/test/current-account', cookies: $a)->assertOk()->assertJsonPath('account_id', $first->account_id);
    $this->browserRequest('GET', '/test/current-account', cookies: $b)->assertOk()->assertJsonPath('account_id', $second->account_id);
    $this->browserRequest('GET', '/test/current-account', cookies: $a)->assertOk()->assertJsonPath('account_id', $first->account_id);
    $this->browserRequest('GET', '/test/current-account')->assertRedirect(route('login'));
});

test('account context is unavailable on routes without the account middleware', function () {
    $membership = Membership::factory()->create();
    Route::middleware(['web', 'auth'])->get('/test/no-account-context', fn (CurrentAccount $currentAccount) => ['account_id' => $currentAccount->get()->id]);

    $this->actingAs($membership->user)->getJson('/test/current-account')->assertOk();
    $this->getJson('/test/no-account-context')->assertNotFound();
    $this->get(route('profile.edit'))->assertOk();
});

test('retained account context is cleared after both successful and failed requests', function (bool $fail) {
    $membership = Membership::factory()->create();
    $retained = null;
    Route::middleware(['web', 'auth', ResolveCurrentAccount::class])
        ->get('/test/retain-account-context', function (CurrentAccount $currentAccount) use (&$retained, $fail) {
            $retained = $currentAccount;

            if ($fail) {
                throw ValidationException::withMessages(['context' => 'Synthetic failure.']);
            }

            return ['account_id' => $currentAccount->get()->id];
        });

    $this->actingAs($membership->user)->getJson('/test/retain-account-context')->assertStatus($fail ? 422 : 200);
    expect($retained)->toBeInstanceOf(CurrentAccount::class);

    Route::middleware(['web', 'auth'])->get('/test/retained-account-context', fn () => ['account_id' => $retained->get()->id]);
    $this->getJson('/test/retained-account-context')->assertNotFound();
})->with(['success' => false, 'exception' => true]);

test('account scoped relationships reject another accounts record id with 404', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    Route::middleware(['web', 'auth', ResolveCurrentAccount::class])
        ->get('/test/current-account/memberships/{membership}', function (CurrentAccount $currentAccount, string $membership) {
            $record = $currentAccount->get()->memberships()->findOrFail($membership);

            return ['id' => $record->id, 'account_id' => $record->account_id];
        });

    $this->actingAs($own->user)->getJson('/test/current-account/memberships/'.$own->id)
        ->assertOk()->assertExactJson(['id' => $own->id, 'account_id' => $own->account_id]);
    $this->getJson('/test/current-account/memberships/'.$other->id.'?account_id='.$other->account_id)->assertNotFound();
});
