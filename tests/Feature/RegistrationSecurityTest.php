<?php

use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function registrationData(array $overrides = []): array
{
    return array_replace([
        'name' => 'Maria Silva',
        'email' => 'maria@example.test',
        'password' => 'Senha#42',
        'plan' => 'empresa',
        'terms' => true,
    ], $overrides);
}

function fakePasswordCheck(): void
{
    Http::preventStrayRequests();
    Http::fake(['https://api.pwnedpasswords.com/range/*' => Http::response('00000:0')]);
}

test('registration renders the selected plan without exposing credentials', function (string $plan) {
    $this->get(route('register', ['plan' => $plan]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/register')
            ->where('plan', $plan)->missing('password')->missing('auth.user.password'));
})->with(['empresa', 'agencia']);

test('registration requires an explicit valid plan', function (mixed $plan) {
    $this->get(route('register', ['plan' => $plan]))->assertRedirect(route('home').'#planos');
})->with(['missing' => [null], 'unknown' => ['admin'], 'array' => [['empresa']]]);

test('registration creates one user account and owner membership atomically', function (string $plan) {
    fakePasswordCheck();
    Event::fake([Registered::class]);
    $this->withSession(['registration_marker' => 'preserved']);
    $previousSessionId = session()->getId();

    $this->post(route('register.store'), registrationData(['plan' => $plan]))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('accounts', 1);
    $this->assertDatabaseCount('memberships', 1);
    $user = User::sole();
    $account = $user->accounts()->sole();
    expect($account->type)->toBe($plan);
    $this->assertDatabaseHas('memberships', ['user_id' => $user->id, 'account_id' => $account->id, 'role' => 'owner']);
    expect($user->password)->not->toBe(registrationData()['password']);
    expect(Hash::check(registrationData()['password'], $user->password))->toBeTrue();
    expect(session()->getId())->not->toBe($previousSessionId);
    expect(session('registration_marker'))->toBe('preserved');
    Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($user));
    Http::assertSentCount(1);
})->with(['empresa', 'agencia']);

test('registration rejects invalid fields with useful errors', function (array $overrides, string $field, string $message, int $passwordChecks) {
    fakePasswordCheck();

    $this->postJson(route('register.store'), registrationData($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors([$field])
        ->assertJsonPath('errors.'.$field.'.0', $message);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('accounts', 0);
    $this->assertDatabaseCount('memberships', 0);
    Http::assertSentCount($passwordChecks);
})->with([
    'missing name' => [['name' => ''], 'name', 'Informe seu nome.', 1],
    'invalid email' => [['email' => 'not-an-email'], 'email', 'Informe um e-mail válido.', 1],
    'short password' => [['password' => 'Abc123!'], 'password', 'Use pelo menos 8 caracteres na senha.', 0],
    'long password' => [['password' => str_repeat('a', 129)], 'password', 'Esta senha é muito longa. Escolha uma senha menor.', 0],
    'missing terms' => [['terms' => null], 'terms', 'Aceite os Termos e Condições para continuar.', 1],
    'false terms' => [['terms' => false], 'terms', 'Aceite os Termos e Condições para continuar.', 1],
    'invalid plan' => [['plan' => 'admin'], 'plan', 'Selecione um plano válido na página inicial.', 1],
    'array plan' => [['plan' => ['empresa']], 'plan', 'Selecione um plano válido na página inicial.', 1],
]);

test('registration rejects an already registered email', function () {
    $user = User::factory()->create(['email' => 'maria@example.test']);
    fakePasswordCheck();

    $this->postJson(route('register.store'), registrationData())->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado.');

    $this->assertDatabaseCount('users', 1);
    $this->assertModelExists($user);
    $this->assertDatabaseCount('accounts', 0);
    Http::assertSentCount(1);
});

test('registration rejects a password found in breach data', function () {
    $passwordHash = strtoupper(sha1(registrationData()['password']));
    Http::preventStrayRequests();
    Http::fake(['https://api.pwnedpasswords.com/range/'.substr($passwordHash, 0, 5) => Http::response(substr($passwordHash, 5).':100')]);

    $this->postJson(route('register.store'), registrationData())->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->assertDatabaseCount('users', 0);
    Http::assertSentCount(1);
});

test('registration ignores browser supplied ownership and privilege fields', function () {
    $existing = Membership::factory()->create();
    fakePasswordCheck();

    $this->post(route('register.store'), registrationData([
        'account_id' => $existing->account_id, 'user_id' => $existing->user_id,
        'owner_id' => $existing->user_id, 'role' => 'admin', 'is_admin' => true,
        'permissions' => ['*'], 'subscription_active' => true, 'email_verified_at' => now(),
    ]))->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'maria@example.test')->sole();
    $membership = $user->memberships()->sole();
    expect($membership->account_id)->not->toBe($existing->account_id);
    expect($membership->role)->toBe('owner');
    expect($user->email_verified_at)->toBeNull();
    expect($user->getAttributes())->not->toHaveKeys(['is_admin', 'permissions', 'subscription_active']);
    $this->assertDatabaseCount('accounts', 2);
    $this->assertDatabaseCount('memberships', 2);
    Http::assertSentCount(1);
});

test('registration rolls back all records if account or membership creation fails', function (string $resource) {
    $model = $resource === 'account' ? Account::class : Membership::class;
    fakePasswordCheck();
    Event::fake([Registered::class]);
    $model::creating(function (): void {
        throw new RuntimeException('Simulated persistence failure');
    });

    try {
        $this->postJson(route('register.store'), registrationData())->assertInternalServerError();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertGuest();
        Event::assertNotDispatched(Registered::class);
        Http::assertSentCount(1);
    } finally {
        $model::flushEventListeners();
    }
})->with(['account', 'membership']);

test('registration preserves every character of long unicode passwords', function () {
    fakePasswordCheck();
    $password = str_repeat('ação ', 24).'fim';

    $this->post(route('register.store'), registrationData(['password' => $password]))->assertRedirect(route('dashboard', absolute: false));

    $hash = User::sole()->password;
    expect(Hash::info($hash)['algoName'])->toBe('argon2id');
    expect(Hash::check($password, $hash))->toBeTrue();
    expect(Hash::check(substr($password, 0, -3).'outro', $hash))->toBeFalse();
    Http::assertSentCount(1);
});

test('legacy bcrypt passwords can still authenticate and are upgraded', function () {
    $user = User::factory()->create();
    $user->setRawAttributes([...$user->getAttributes(), 'password' => Hash::driver('bcrypt')->make('legacy password')]);
    $user->save();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'legacy password'])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(Hash::info($user->fresh()->password)['algoName'])->toBe('argon2id');
});

test('registration is rate limited before repeated requests reach password verification', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('register.store'), [])->assertUnprocessable();
    }

    $this->postJson(route('register.store'), [])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertDatabaseCount('users', 0);
});

test('registration accepts the exact password length limits', function (int $length) {
    fakePasswordCheck();
    $password = str_repeat('a', $length);

    $this->post(route('register.store'), registrationData(['password' => $password]))->assertRedirect(route('dashboard', absolute: false));

    expect(Hash::check($password, User::sole()->password))->toBeTrue();
    Http::assertSentCount(1);
})->with([8, 128]);

test('registration hashes a password even when its input resembles an encoded hash', function () {
    fakePasswordCheck();
    $password = Hash::make('a long original phrase');

    $this->post(route('register.store'), registrationData(['password' => $password]))->assertRedirect(route('dashboard', absolute: false));

    $stored = User::sole()->password;
    expect($stored)->not->toBe($password);
    expect(Hash::check($password, $stored))->toBeTrue();
    Http::assertSentCount(1);
});
