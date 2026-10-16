<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

afterEach(function () {
    Request::setTrustedHosts([]);
});

test('production host validation rejects arbitrary hosts and subdomains', function (string $host) {
    config(['app.url' => 'https://crm.example.test']);
    $this->app->bind(TrustHosts::class, fn () => new class($this->app) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts(): bool
        {
            return true;
        }
    });
    $this->get('https://'.$host.'/login')->assertStatus(400);
})->with(['attacker.example.test', 'sub.crm.example.test']);

test('untrusted forwarding headers cannot poison recovery notification urls or client origin', function () {
    $user = User::factory()->create();
    Notification::fake();
    $this->withHeaders([
        'X-Forwarded-Host' => 'attacker.example.test', 'X-Forwarded-Proto' => 'https',
        'X-Forwarded-For' => '198.51.100.9',
    ])->get(route('login'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canResetPassword', true));
    expect(request()->getHost())->toBe('localhost');
    expect(request()->getScheme())->toBe('http');
    expect(request()->ip())->toBe('127.0.0.1');

    $this->postJson(route('password.email'), ['email' => $user->email])->assertOk();
    Notification::assertSentTo($user, ResetPassword::class,
        fn (ResetPassword $notification): bool => parse_url($notification->toMail($user)->actionUrl, PHP_URL_HOST) === 'localhost');
});

test('password confirmation rejects external intended redirects and malformed passwords', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['url.intended' => 'https://attacker.example.test'])
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->postJson(route('password.confirm.store'), ['password' => ['password']])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
});

test('production notification urls use the configured scheme host and port', function () {
    $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $request = Illuminate\Http\Request::create('http://crm.example.test:9000/login');
        $app->instance('request', $request);
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $user = App\Models\User::factory()->make(['id' => 1]);
        $reset = (new Illuminate\Auth\Notifications\ResetPassword('synthetic-token'))->toMail($user)->actionUrl;
        $verification = (new Illuminate\Auth\Notifications\VerifyEmail)->toMail($user)->actionUrl;
        echo json_encode([
            'reset_origin' => [parse_url($reset, PHP_URL_SCHEME), parse_url($reset, PHP_URL_HOST), parse_url($reset, PHP_URL_PORT)],
            'verification_origin' => [parse_url($verification, PHP_URL_SCHEME), parse_url($verification, PHP_URL_HOST), parse_url($verification, PHP_URL_PORT)],
            'signature_valid' => Illuminate\Support\Facades\URL::hasValidSignature(Illuminate\Http\Request::create($verification)),
        ]);
        PHP;
    $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
        'APP_ENV' => 'production', 'APP_URL' => 'https://crm.example.test:8443',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    ]);
    $process->mustRun();
    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe([
        'reset_origin' => ['https', 'crm.example.test', 8443],
        'verification_origin' => ['https', 'crm.example.test', 8443],
        'signature_valid' => true,
    ]);
});

test('registration sanitizes external intended redirects before creating the session', function () {
    Http::fake(['https://api.pwnedpasswords.com/range/*' => Http::response('00000:0')]);

    $this->withSession(['url.intended' => 'https://attacker.example.test'])->post(route('register.store'), [
        'name' => 'Synthetic user', 'email' => 'new@example.test', 'password' => 'Strong#42', 'plan' => 'empresa', 'terms' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    Http::assertSentCount(1);
});

test('email verification sanitizes external intended redirects', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
        'id' => $user->id, 'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->withSession(['url.intended' => 'https://attacker.example.test'])->get($url)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
