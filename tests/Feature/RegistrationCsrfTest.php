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
