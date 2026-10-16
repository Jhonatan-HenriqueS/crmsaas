<?php

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

test('password changes revoke a second browser and persistent cookies across session drivers', function (string $driver, string $operation) {
    config(['session.driver' => $driver, 'session.files' => storage_path('framework/testing/sessions')]);
    if (! is_dir(config('session.files'))) {
        mkdir(config('session.files'), 0700, true);
    }
    $user = User::factory()->create();
    Membership::factory()->for($user)->create();
    $credentials = ['email' => $user->email, 'password' => 'password', 'remember' => true];
    $a = $this->browserCookies($this->browserRequest('POST', route('login.store'), $credentials)->assertRedirect());
    $b = $this->browserCookies($this->browserRequest('POST', route('login.store'), $credentials)->assertRedirect());
    $cookieName = config('session.cookie');
    expect($a[$cookieName])->not->toBe($b[$cookieName]);
    $this->browserRequest('GET', route('dashboard'), cookies: $a)->assertOk();
    $this->browserRequest('GET', route('dashboard'), cookies: $b)->assertOk();
    $rememberName = Auth::guard()->getRecallerName();
    if ($operation === 'change') {
        $response = $this->browserRequest('PUT', route('user-password.update'), [
            'current_password' => 'password', 'password' => 'new password', 'password_confirmation' => 'new password',
        ], $a)->assertSessionHasNoErrors()->assertRedirect();
        $this->browserRequest('GET', route('dashboard'), cookies: $this->browserCookies($response))->assertOk();
    } else {
        $token = Password::broker()->createToken($user);
        $this->browserRequest('POST', route('password.update'), [
            'email' => $user->email, 'token' => $token, 'password' => 'new password', 'password_confirmation' => 'new password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertGuest();
        $this->browserRequest('GET', route('dashboard'), cookies: $a)->assertRedirect(route('login'));
    }
    $this->browserRequest('GET', route('dashboard'), cookies: $b)->assertRedirect(route('login'));
    $this->browserRequest('GET', route('dashboard'), cookies: [$rememberName => $b[$rememberName]])->assertRedirect(route('login'));
    $this->browserRequest('POST', route('login.store'), ['email' => $user->email, 'password' => 'new password'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
})->with(['database', 'file'])->with(['change', 'reset']);
