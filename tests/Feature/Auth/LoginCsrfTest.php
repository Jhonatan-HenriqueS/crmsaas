<?php

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
