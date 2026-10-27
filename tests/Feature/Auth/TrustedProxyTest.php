<?php

use App\Models\Lead;
use App\Models\Membership;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

afterEach(function () {
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_PROTO);
    Request::setTrustedHosts([]);
});

test('the local tunnel opt in applies only to explicit local configuration and loopback callers', function (string $environment, ?string $enabled, string $remote, string $scheme, bool $trusted) {
    $directory = storage_path('framework/testing/proxy-env-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($directory, 0700);
    $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->useEnvironmentPath(getenv('PROXY_TEST_ENV_DIRECTORY'));
        $request = Illuminate\Http\Request::create('http://localhost/login', server: [
            'REMOTE_ADDR' => getenv('PROXY_TEST_REMOTE_ADDR'),
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'attacker.example.test',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
        ]);
        $app->instance('request', $request);
        $app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
        $response = app(Illuminate\Http\Middleware\TrustProxies::class)->handle($request, fn ($request) => response()->json([
            'scheme' => $request->getScheme(), 'host' => $request->getHost(),
            'ip' => $request->ip(), 'proxies' => config('trustedproxy.proxies'),
            'headers' => Illuminate\Http\Request::getTrustedHeaderSet(),
        ]));
        echo $response->getContent();
        PHP;

    try {
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'APP_ENV' => $environment, 'APP_URL' => 'http://localhost', 'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)),
            'TRUST_LOCAL_TUNNEL_PROXY' => $enabled ?? false,
            'APP_CONFIG_CACHE' => $directory.'/config.php', 'PROXY_TEST_ENV_DIRECTORY' => $directory,
            'PROXY_TEST_REMOTE_ADDR' => $remote, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => '"null"',
        ]);
        $process->mustRun();
        expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'scheme' => $scheme, 'host' => 'localhost', 'ip' => $remote,
            'proxies' => $trusted ? ['127.0.0.1', '::1'] : [],
            'headers' => Request::HEADER_X_FORWARDED_PROTO,
        ]);
    } finally {
        File::deleteDirectory($directory);
    }
})->with([
    'local default is off' => ['local', null, '127.0.0.1', 'http', false],
    'local explicitly disabled' => ['local', 'false', '127.0.0.1', 'http', false],
    'local malformed flag is off' => ['local', 'unexpected', '127.0.0.1', 'http', false],
    'local authorized IPv4 connector' => ['local', 'true', '127.0.0.1', 'https', true],
    'local authorized IPv6 connector' => ['local', 'true', '::1', 'https', true],
    'local remote client stays untrusted' => ['local', 'true', '198.51.100.10', 'http', true],
    'testing ignores the opt in' => ['testing', 'true', '127.0.0.1', 'http', false],
    'staging ignores the opt in' => ['staging', 'true', '127.0.0.1', 'http', false],
    'production ignores the opt in' => ['production', 'true', '127.0.0.1', 'http', false],
]);

test('an authorized connector changes only the effective protocol', function (string $remote, string $scheme) {
    config(['trustedproxy.proxies' => ['127.0.0.1', '::1']]);

    $this->withServerVariables(['REMOTE_ADDR' => $remote])->withHeaders([
        'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example.test',
        'X-Forwarded-For' => '198.51.100.99', 'X-Forwarded-Port' => '9443',
        'X-Forwarded-Prefix' => '/attacker', 'Forwarded' => 'for=198.51.100.99;host=attacker.example.test;proto=https',
    ])->get('http://localhost/login')->assertOk();

    expect(request()->getScheme())->toBe($scheme);
    expect(request()->getHost())->toBe('localhost');
    expect(request()->ip())->toBe($remote);
    expect(request()->getBaseUrl())->toBe('');
    expect(request()->getPort())->not->toBe(9443);
})->with([
    'IPv4 connector' => ['127.0.0.1', 'https'], 'IPv6 connector' => ['::1', 'https'],
    'external caller' => ['198.51.100.10', 'http'], 'private network caller' => ['10.0.0.10', 'http'],
]);

test('trusted forwarding cannot bypass production host validation', function () {
    config(['trustedproxy.proxies' => ['127.0.0.1', '::1'], 'app.url' => 'https://crm.example.test']);
    $this->app->bind(TrustHosts::class, fn () => new class($this->app) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts(): bool
        {
            return true;
        }
    });

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'crm.example.test'])
        ->get('http://attacker.example.test/login')->assertStatus(400);
    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example.test'])
        ->get('http://crm.example.test/login')->assertOk();
    expect(request()->getHost())->toBe('crm.example.test');
});

test('proxied authentication keeps secure session csrf and account isolation', function () {
    config(['trustedproxy.proxies' => ['127.0.0.1', '::1']]);
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $own = Membership::factory()->create();
    Lead::factory()->for($own->account)->create();
    Lead::factory()->count(2)->create();
    $headers = ['X-Forwarded-Proto' => 'https', 'Origin' => 'https://localhost', 'Sec-Fetch-Site' => 'cross-site'];
    $credentials = ['email' => $own->user->email, 'password' => 'password'];

    $this->withHeaders($headers)->post('http://localhost/login', $credentials)->assertStatus(419);
    $response = $this->withSession(['_token' => 'proxy-session-csrf'])->post('http://localhost/login', [
        ...$credentials, '_token' => 'proxy-session-csrf',
    ])->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($own->user);
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
    expect($cookie)->not->toBeNull();
    expect($cookie->isSecure())->toBeTrue();
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->getSameSite())->toBe('lax');
    $this->get('http://localhost/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 1));
});

test('tunnel html uses https assets without relying on a quick tunnel hostname', function () {
    config(['trustedproxy.proxies' => ['127.0.0.1', '::1']]);
    $directory = storage_path('framework/testing/proxy-assets-'.bin2hex(random_bytes(8)));
    $build = 'proxy-build-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($directory.'/'.$build, 0700);
    $manifest = [];
    foreach (['resources/css/app.css' => 'app.css', 'resources/js/app.tsx' => 'app.js', 'resources/js/pages/auth/login.tsx' => 'login.js'] as $source => $file) {
        $manifest[$source] = ['file' => 'assets/'.$file, 'src' => $source, 'isEntry' => true];
    }
    File::put($directory.'/'.$build.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    chmod($directory.'/'.$build.'/manifest.json', 0600);
    $public = public_path();
    $vite = app(Vite::class);
    $hot = $vite->hotFile();

    try {
        $this->app->usePublicPath($directory);
        $vite->useHotFile($directory.'/absent.hot')->useBuildDirectory($build);
        $response = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example.test'])
            ->get('http://localhost/login')->assertOk();
        foreach (['app.css', 'app.js', 'login.js'] as $file) {
            $response->assertSee('https://localhost/'.$build.'/assets/'.$file, escape: false);
        }
        $response->assertDontSee('http://', escape: false)->assertDontSee('attacker.example.test', escape: false);
        expect(request()->isSecure())->toBeTrue();
    } finally {
        $this->app->usePublicPath($public);
        $vite->useHotFile($hot)->useBuildDirectory('build');
        File::deleteDirectory($directory);
    }
});
