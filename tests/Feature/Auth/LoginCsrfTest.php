<?php

use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app->make(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
});

test('login rejects a cross site post without a csrf token', function () {
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->post(route('login.store'), [
        'email' => 'login@example.test', 'password' => 'password',
    ])->assertStatus(419);

    $this->assertGuest();
});

test('login with a valid csrf token reaches server validation', function () {
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->withSession(['_token' => 'test-csrf-token'])
        ->post(route('login.store'), ['_token' => 'test-csrf-token'])
        ->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

test('login rejects missing or invalid fallback protection', function (?string $site, ?string $token) {
    $this->withSession(['_token' => 'session-csrf-token']);

    if ($site !== null) {
        $this->withHeader('Sec-Fetch-Site', $site);
    }

    $this->post(route('login.store'), ['_token' => $token])->assertStatus(419);

    $this->assertGuest();
})->with([
    'no origin and no token' => [null, null],
    'same site requires fallback by default' => ['same-site', null],
    'cross site wrong token' => ['cross-site', 'incorrect-token'],
]);

test('https same origin login reaches authentication without needing token fallback', function () {
    $user = User::factory()->create();

    $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('https://localhost'.route('login.store', absolute: false), [
        'email' => $user->email, 'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('login accepts the csrf header when origin verification is unavailable', function () {
    $user = User::factory()->create();

    $this->withSession(['_token' => 'session-csrf-token'])->withHeader('X-CSRF-TOKEN', 'session-csrf-token')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('login accepts the encrypted xsrf cookie header used by inertia', function () {
    $response = $this->get(route('login'))->assertOk();
    $cookie = $response->getCookie('XSRF-TOKEN', decrypt: false);

    $this->withHeader('X-XSRF-TOKEN', $cookie->getValue())->post(route('login.store'), [])
        ->assertSessionHasErrors(['email', 'password']);
});

test('login rejects a corrupted encrypted xsrf header', function () {
    $this->withHeader('X-XSRF-TOKEN', 'invalid-encrypted-token')->post(route('login.store'), [])->assertStatus(419);
});
