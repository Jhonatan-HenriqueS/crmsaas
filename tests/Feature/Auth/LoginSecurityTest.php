<?php

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

test('invalid credentials do not reveal whether an email exists', function (bool $existing) {
    $email = 'login@example.test';

    if ($existing) {
        User::factory()->create(['email' => $email]);
    }

    $this->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => 'incorrect-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'O e-mail ou a senha informados estão incorretos.'])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
})->with(['registered email' => true, 'unknown email' => false]);

test('successful json login returns only the native authentication result', function () {
    $user = User::factory()->create();

    $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertExactJson(['two_factor' => false]);

    $this->assertAuthenticatedAs($user);
});

test('login validates malformed inputs without attempting authentication', function (array $input, string $field, string $message) {
    $this->post(route('login.store'), $input)->assertSessionHasErrors([$field => $message]);

    $this->assertGuest();
})->with([
    'missing email' => [['password' => 'password'], 'email', 'O campo e-mail é obrigatório.'],
    'missing password' => [['email' => 'login@example.test'], 'password', 'O campo senha é obrigatório.'],
    'array email' => [['email' => ['login@example.test'], 'password' => 'password'], 'email', 'O campo e-mail deve ser um texto.'],
    'invalid email' => [['email' => 'invalid-email', 'password' => 'password'], 'email', 'Informe um endereço de e-mail válido.'],
    'array password' => [['email' => 'login@example.test', 'password' => ['password']], 'password', 'O campo senha deve ser um texto.'],
    'oversized password' => [['email' => 'login@example.test', 'password' => str_repeat('a', 129)], 'password', 'Esta senha é muito longa. Escolha uma senha menor.'],
    'invalid remember' => [['email' => 'login@example.test', 'password' => 'password', 'remember' => ['1']], 'remember', 'A opção de lembrar o acesso é inválida.'],
]);

test('login exposes the configured availability of registration and password recovery', function () {
    config(['fortify.features' => []]);

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login')
        ->where('canRegister', false)->where('canResetPassword', false)->where('auth.user', null));
});

test('https login uses a secure http only session cookie with same site protection', function () {
    $response = $this->get('https://localhost/login')->assertOk()->assertCookie(config('session.cookie'));
    $cookie = $response->getCookie(config('session.cookie'));

    expect($cookie->isSecure())->toBeTrue();
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->getSameSite())->toBe('lax');
});

test('successful login rotates the session and csrf token', function () {
    $user = User::factory()->create();
    $this->withSession(['_token' => 'old-csrf-token', 'session-marker' => 'kept']);
    $previousSession = session()->getId();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('session-marker', 'kept');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($previousSession);
    expect(session()->token())->not->toBe('old-csrf-token');
});

test('logout clears the session and rotates its identifier and csrf token', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'old-csrf-token', 'session-marker' => 'removed']);
    $previousSession = session()->getId();

    $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('session-marker');

    $this->assertGuest();
    expect(session()->getId())->not->toBe($previousSession);
    expect(session()->token())->not->toBe('old-csrf-token');
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the configured login limiter blocks the sixth attempt even with changed email casing', function () {
    $this->freezeTime();
    $user = User::factory()->create(['email' => 'throttled@example.test']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect-password'])
            ->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => strtoupper($user->email), 'password' => 'password'])
        ->assertTooManyRequests()->assertHeader('Retry-After');

    $this->assertGuest();
});

test('login discards untrusted intended redirects', function (mixed $destination) {
    $user = User::factory()->create();

    $this->withSession(['url.intended' => $destination])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => 'https://untrusted.example/',
        'return_to' => 'https://untrusted.example/',
    ])->assertRedirect(route('dashboard', absolute: false))->assertSessionMissing('url.intended');

    $this->assertAuthenticatedAs($user);
})->with([
    'external URL' => 'https://untrusted.example/',
    'protocol relative' => '//untrusted.example/',
    'backslash' => '/\\untrusted.example/',
    'javascript' => 'javascript:alert(1)',
    'different port' => 'http://localhost:9001/settings/profile',
    'control character' => "/\nuntrusted.example/",
    'malformed session value' => [['url' => 'https://untrusted.example/']],
    'data scheme' => 'data:text/html,untrusted',
    'encoded absolute URL' => 'https%3A%2F%2Funtrusted.example%2F',
    'double encoded absolute URL' => 'https%253A%252F%252Funtrusted.example%252F',
    'encoded protocol relative path' => '/%2funtrusted.example/',
    'encoded backslash' => '/%5cuntrusted.example/',
    'encoded control character' => '/%0auntrusted.example/',
    'deceptive host suffix' => 'http://localhost.untrusted.example/settings/profile',
    'untrusted subdomain' => 'http://admin.localhost/settings/profile',
    'userinfo on trusted host' => 'http://untrusted@localhost/settings/profile',
    'trusted host as userinfo' => 'http://localhost@untrusted.example/settings/profile',
    'different scheme' => 'https://localhost/settings/profile',
    'invalid port' => 'http://localhost:99999/settings/profile',
]);

test('login preserves a destination on the same origin', function (bool $absolute) {
    $user = User::factory()->create();
    $destination = route('profile.edit', absolute: $absolute);

    $this->withSession(['url.intended' => $destination])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('profile.edit'));
})->with(['absolute URL' => true, 'relative URL' => false]);

test('login preserves internal query and fragment after validating a normalized origin', function () {
    $user = User::factory()->create();

    $destination = str_replace('localhost', 'LOCALHOST', route('profile.edit')).'?section=contact#email';

    $this->withSession(['url.intended' => $destination])
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('profile.edit').'?section=contact#email');
});

test('inertia shares only public user fields even when private attributes and relations are loaded', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $user = $own->user;
    $user->setAttribute('internal_credential', 'private-credential-placeholder');
    $user->load('accounts', 'memberships');

    $this->actingAs($user)->get(route('dashboard', ['account_id' => $other->account_id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->has('auth.user', 6)->where('auth.user.id', $user->id)
        ->missing('auth.user.password')->missing('auth.user.remember_token')
        ->missing('auth.user.two_factor_secret')->missing('auth.user.two_factor_recovery_codes')
        ->missing('auth.user.internal_credential')->missing('auth.user.accounts')->missing('auth.user.memberships'))
        ->assertDontSee('private-credential-placeholder')->assertDontSee($user->password)
        ->assertDontSee($other->account->name);
});

test('login ignores tenant and privilege fields from the browser', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();

    $this->post(route('login.store'), [
        'email' => $own->user->email,
        'password' => 'password',
        'tenant_id' => $other->account_id,
        'account_id' => $other->account_id,
        'company_id' => $other->account_id,
        'agency_id' => $other->account_id,
        'role' => 'admin',
    ])->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionMissing('tenant_id')->assertSessionMissing('account_id')->assertSessionMissing('role');

    $this->assertAuthenticatedAs($own->user);
    $this->assertDatabaseCount('memberships', 2);
    $this->assertDatabaseMissing('memberships', ['user_id' => $own->user_id, 'account_id' => $other->account_id]);
});

test('two factor recovery authenticates once and rejects external redirects', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    $user = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->withSession(['url.intended' => 'https://untrusted.example/'])->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertRedirect(route('dashboard', absolute: false))->assertSessionMissing('login.id');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');

    $this->post(route('logout'));
    Auth::forgetGuards();
    $this->withSession(['login.id' => $user->id])->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('two factor otp rejects an invalid code and accepts a valid authenticator code', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    $authenticator = app(Google2FA::class);
    $secret = $authenticator->generateSecretKey();
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt($secret)]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['code' => 'invalid-otp'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => $authenticator->getCurrentOtp($secret)])
        ->assertRedirect(route('dashboard', absolute: false))->assertSessionMissing('login.id');

    $this->assertAuthenticatedAs($user);
});
