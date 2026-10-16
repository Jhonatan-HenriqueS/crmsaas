<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('recovery rejects malformed identity and token inputs without changing credentials', function (array $input, string $field) {
    $user = User::factory()->create();
    $hash = $user->password;
    $this->postJson(route('password.update'), array_replace([
        'email' => $user->email, 'token' => 'invalid-token',
        'password' => 'new password', 'password_confirmation' => 'new password',
    ], $input))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($user->fresh()->password)->toBe($hash);
    $this->assertGuest();
})->with([
    'email array' => [['email' => ['person@example.test']], 'email'],
    'email object' => [['email' => (object) ['value' => 'person@example.test']], 'email'],
    'email null' => [['email' => null], 'email'],
    'email number' => [['email' => 123], 'email'],
    'token array' => [['token' => ['invalid-token']], 'token'],
    'token null' => [['token' => null], 'token'],
    'invalid token' => [['token' => 'invalid-token'], 'email'],
]);

test('recovery responses hide account existence and broker throttling', function (bool $json) {
    Notification::fake();
    $user = User::factory()->create();
    $responses = [];
    foreach ([$user->email, 'unknown@example.test', strtoupper($user->email)] as $email) {
        $response = $json
            ? $this->postJson(route('password.email'), ['email' => $email])->assertOk()
            : $this->from(route('password.request'))->post(route('password.email'), ['email' => $email])
                ->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
        $responses[] = $json ? $response->json() : session('status');
    }
    expect($responses[0])->toBe($responses[1])->toBe($responses[2]);
    expect(json_encode($responses[0]))->not->toContain('enviamos', 'enviado');
    Notification::assertSentToTimes($user, ResetPassword::class, 1);
})->with(['json' => true, 'browser' => false]);

test('recovery limits unknown identities and recovers after the window', function (string $route, int $limit) {
    $this->freezeTime();
    $data = ['email' => 'unknown@example.test', 'token' => 'invalid-token', 'password' => 'password'];
    for ($attempt = 0; $attempt < $limit; $attempt++) {
        expect($this->postJson(route($route), $data)->status())->not->toBe(429);
    }
    $this->postJson(route($route), $data)->assertTooManyRequests()->assertHeader('Retry-After');
    $this->travel(61)->seconds();
    expect($this->postJson(route($route), $data)->status())->not->toBe(429);
})->with(['request' => ['password.email', 5], 'reset' => ['password.update', 10]]);

test('reset consumes its token and requires a new login', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $data = ['email' => strtoupper($user->email), 'token' => $token, 'password' => 'new password', 'password_confirmation' => 'new password'];
    $this->post(route('password.update'), $data)->assertSessionHasNoErrors()->assertRedirect(route('login'));
    $this->assertGuest();
    expect(Hash::check('new password', $user->fresh()->password))->toBeTrue();
    $this->postJson(route('password.update'), $data)->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('expired reset tokens cannot change credentials', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $hash = $user->password;
    $this->travel(61)->minutes();
    $this->postJson(route('password.update'), [
        'email' => $user->email, 'token' => $token, 'password' => 'new password', 'password_confirmation' => 'new password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($user->fresh()->password)->toBe($hash);
});

test('validation failures do not flash reset tokens into the next page', function () {
    $this->post(route('password.update'), ['email' => 'person@example.test', 'token' => str_repeat('a', 64)])
        ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.token');
});

test('invalid reset tokens cannot distinguish known and unknown accounts', function (bool $json) {
    $user = User::factory()->create();
    $responses = [];
    foreach ([$user->email, 'unknown@example.test'] as $email) {
        $data = ['email' => $email, 'token' => 'invalid-token', 'password' => 'new password'];
        $response = $json ? $this->postJson(route('password.update'), $data)->assertUnprocessable()
            : $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
        $responses[] = $json ? $response->json() : session('errors')->get('email');
    }
    expect($responses[0])->toBe($responses[1]);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with(['json' => true, 'browser' => false]);
