<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('authentication pages use Brazilian Portuguese as the document language', function () {
    $this->get(route('login'))->assertOk()->assertSee('lang="pt-BR"', false);
});

test('invalid login credentials are reported in Brazilian Portuguese', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect-password'])
        ->assertSessionHasErrors(['email' => 'O e-mail ou a senha informados estão incorretos.']);
});

test('password reset validation errors are reported in Brazilian Portuguese', function () {
    $this->post(route('password.email'), ['email' => 'invalid-email'])
        ->assertSessionHasErrors(['email' => 'Informe um endereço de e-mail válido.']);
});

test('verification notices reflect whether the configured mailer can deliver email', function (string $mailer, bool $available) {
    config(['auth.email_verification_required' => true, 'mail.default' => $mailer]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.notice'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/verify-email')->where('emailDeliveryAvailable', $available));
})->with(['log' => ['log', false], 'array' => ['array', false], 'smtp' => ['smtp', true]]);

test('optional verification does not send a notification when a user registers', function () {
    config(['auth.email_verification_required' => false]);
    $user = User::factory()->unverified()->create();
    Notification::fake();

    event(new Registered($user));

    Notification::assertNothingSent();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('required verification sends a notification when a user registers', function () {
    config(['auth.email_verification_required' => true]);
    $user = User::factory()->unverified()->create();
    Notification::fake();

    event(new Registered($user));

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('optional verification redirects an existing confirmation screen to the dashboard', function () {
    config(['auth.email_verification_required' => false]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('dashboard'));
});

test('optional verification suppresses profile confirmation prompts', function () {
    config(['auth.email_verification_required' => false]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('mustVerifyEmail', false));
});
