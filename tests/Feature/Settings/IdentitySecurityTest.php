<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

test('changing email requires a password checked in the same request', function (mixed $password) {
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson(route('profile.update'), [
        'name' => 'Changed name', 'email' => 'new@example.test', 'current_password' => $password,
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    expect($user->fresh()->email)->toBe($user->email);
    expect($user->fresh()->name)->toBe($user->name);
})->with(['missing' => null, 'incorrect' => 'incorrect', 'array' => [['password']]]);

test('a confirmed email change is canonical and never grants verification', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Changed name', 'email' => 'NEW@Example.Test', 'current_password' => 'password', 'email_verified_at' => now(),
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    expect($user->fresh())->email->toBe('new@example.test')->name->toBe('Changed name')->email_verified_at->toBeNull();
});

test('name changes preserve canonical identity and verification without password confirmation', function () {
    $user = User::factory()->create();
    $verified = $user->email_verified_at;
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Changed name', 'email' => strtoupper($user->email),
    ])->assertSessionHasNoErrors();
    expect($user->fresh()->name)->toBe('Changed name');
    expect($user->fresh()->email)->toBe($user->email);
    expect($user->fresh()->email_verified_at->equalTo($verified))->toBeTrue();
});

test('canonical profile validation rejects another users address', function () {
    $other = User::factory()->create(['email' => 'existing@example.test']);
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson(route('profile.update'), [
        'name' => $user->name, 'email' => strtoupper($other->email), 'current_password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($user->fresh()->email)->toBe($user->email);
});

test('persisted identity supports mixed case login and recovery', function () {
    $user = User::factory()->create(['email' => 'Mixed@Example.Test']);
    expect($user->email)->toBe('mixed@example.test');
    Notification::fake();
    $this->post(route('password.email'), ['email' => 'MIXED@EXAMPLE.TEST'])->assertSessionHasNoErrors();
    Notification::assertSentTo($user, ResetPassword::class);
    $this->post(route('login.store'), ['email' => 'MIXED@EXAMPLE.TEST', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('email reauthentication is rate limited and recovers after its window', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $this->actingAs($user);
    $data = ['name' => $user->name, 'email' => 'new@example.test', 'current_password' => 'incorrect'];

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->patchJson(route('profile.update'), $data)->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    $this->patchJson(route('profile.update'), $data)->assertTooManyRequests();
    $this->travel(61)->seconds();
    $this->patchJson(route('profile.update'), $data)->assertUnprocessable()->assertJsonValidationErrors('current_password');
    expect($user->fresh()->email)->toBe($user->email);
});

test('an email uniqueness conflict after validation returns a controlled error without partial profile changes', function () {
    $user = User::factory()->create();
    User::updating(function (User $changing) use ($user): void {
        if ($changing->isDirty('email')) {
            DB::table('users')->insert([
                'name' => 'Synthetic competing user', 'email' => $changing->email, 'password' => $user->password,
            ]);
        }
    });

    try {
        $this->actingAs($user)->patchJson(route('profile.update'), [
            'name' => 'Changed name', 'email' => 'conflict@example.test', 'current_password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        expect($user->fresh()->email)->toBe($user->getOriginal('email'));
        expect($user->fresh()->name)->not->toBe('Changed name');
    } finally {
        User::flushEventListeners();
    }
});
