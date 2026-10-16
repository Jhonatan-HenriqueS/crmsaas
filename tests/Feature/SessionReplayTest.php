<?php

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function resetSessionClient(): void
{
    Auth::forgetGuards();
    app()->forgetInstance('auth.driver');
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    foreach (app('router')->getRoutes() as $route) {
        $route->flushController();
    }
}

test('logout rejects replay of the previous real session and remember cookie', function (bool $remember) {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    Membership::factory()->for($user)->create();
    $cookieName = config('session.cookie');
    $initialResponse = $this->get(route('login'))->assertOk();
    $initialCookie = $initialResponse->getCookie($cookieName, decrypt: false)->getValue();
    $initialSessionId = $initialResponse->getCookie($cookieName)->getValue();
    resetSessionClient();

    $login = $this->withUnencryptedCookie($cookieName, $initialCookie)->post(route('login.store'), [
        'email' => $user->email, 'password' => 'password', 'remember' => $remember,
    ])->assertRedirect(route('dashboard', absolute: false));

    $authenticatedCookie = $login->getCookie($cookieName, decrypt: false)->getValue();
    $authenticatedSessionId = $login->getCookie($cookieName)->getValue();
    $cookies = [$cookieName => $authenticatedCookie];

    if ($remember) {
        $recallerName = Auth::guard()->getRecallerName();
        $cookies[$recallerName] = $login->getCookie($recallerName, decrypt: false)->getValue();
    }

    $this->assertDatabaseMissing('sessions', ['id' => $initialSessionId]);
    $this->assertDatabaseHas('sessions', ['id' => $authenticatedSessionId, 'user_id' => $user->id]);
    resetSessionClient();
    $this->withUnencryptedCookies($cookies)->get(route('dashboard'))->assertOk();
    resetSessionClient();

    $this->withUnencryptedCookies($cookies)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertDatabaseMissing('sessions', ['id' => $authenticatedSessionId]);
    resetSessionClient();
    $this->withUnencryptedCookies($cookies)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['session only' => false, 'session and remember cookie' => true]);
