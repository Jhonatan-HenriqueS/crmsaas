<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
});

test('authenticated mutations reject missing and foreign csrf tokens', function (string $method, string $route, ?string $token) {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'current-browser-token'])
        ->withHeader('Sec-Fetch-Site', 'cross-site')->call($method, route($route), [
            '_token' => $token, 'name' => 'Changed name', 'email' => 'new@example.test',
            'current_password' => 'password', 'password' => 'new password', 'password_confirmation' => 'new password',
        ])->assertStatus(419);
    $this->assertModelExists($user);
    expect($user->fresh()->email)->toBe($user->email);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
})->with([
    'logout' => ['POST', 'logout'], 'password' => ['PUT', 'user-password.update'],
    'email' => ['PATCH', 'profile.update'], 'deletion' => ['DELETE', 'profile.destroy'],
])->with(['missing' => null, 'other session' => 'another-browser-token']);

test('a valid csrf token permits an email change', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'current-browser-token'])
        ->patch(route('profile.update'), [
            '_token' => 'current-browser-token', 'name' => $user->name,
            'email' => 'new@example.test', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('new@example.test');
});

test('a valid csrf token permits a password change', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'current-browser-token']);
    $this->put(route('user-password.update'), [
        '_token' => 'current-browser-token', 'current_password' => 'password',
        'password' => 'new password', 'password_confirmation' => 'new password',
    ])->assertSessionHasNoErrors();
    expect(Hash::check('new password', $user->fresh()->password))->toBeTrue();
});

test('csrf tokens from an independent session cannot mutate an authenticated browser', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $first = $this->browserRequest('GET', route('login'))->assertOk();
    $token = $first->getCookie('XSRF-TOKEN')->getValue();
    $login = $this->browserRequest('POST', route('login.store'), [
        '_token' => $token, 'email' => $user->email, 'password' => 'password',
    ], $this->browserCookies($first))->assertRedirect();
    $second = $this->browserRequest('GET', route('login'))->assertOk();
    $foreignToken = $second->getCookie('XSRF-TOKEN')->getValue();
    $this->browserRequest('PATCH', route('profile.update'), [
        '_token' => $foreignToken, 'name' => 'Changed name', 'email' => 'new@example.test', 'current_password' => 'password',
    ], $this->browserCookies($login))->assertStatus(419);
    expect($user->fresh()->email)->toBe($user->email);
});
