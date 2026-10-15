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

test('registration rejects a cross site post without a csrf token', function () {
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->post(route('register.store'), [])->assertStatus(419);
    $this->assertDatabaseCount('users', 0);
});

test('registration accepts a valid csrf token and reaches validation', function () {
    $this->withSession(['_token' => 'test-csrf-token'])->post(route('register.store'), ['_token' => 'test-csrf-token'])
        ->assertSessionHasErrors(['name', 'email', 'password', 'terms', 'plan']);
});

test('registration rejects an unprotected post without fetch metadata', function () {
    $this->post(route('register.store'), [])->assertStatus(419);
    $this->assertDatabaseCount('users', 0);
});

test('registration rejects a mismatched fallback csrf token', function () {
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->withSession(['_token' => 'session-csrf-token'])
        ->post(route('register.store'), ['_token' => 'wrong-token'])->assertStatus(419);
    $this->assertDatabaseCount('users', 0);
});

test('same origin registration reaches validation without token fallback', function () {
    $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('https://localhost'.route('register.store', absolute: false), [])
        ->assertSessionHasErrors(['name', 'email', 'password', 'terms', 'plan']);
});
