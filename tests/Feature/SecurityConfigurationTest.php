<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** @param array<string, string|false> $environment
 * @return array<string, mixed>
 */
function securityConfiguration(array $environment): array
{
    $script = <<<'PHP'
        require 'vendor/autoload.php';
        $application = new Illuminate\Foundation\Application(getcwd());
        $auth = require 'config/auth.php';
        $session = require 'config/session.php';
        $app = require 'config/app.php';
        $mail = require 'config/mail.php';
        echo json_encode([
            'verification' => $auth['email_verification_required'],
            'secure' => $session['secure'],
            'http_only' => $session['http_only'],
            'same_site' => $session['same_site'],
            'serialization' => $session['serialization'],
            'debug' => $app['debug'],
            'mailer' => $mail['default'],
        ], JSON_THROW_ON_ERROR);
        PHP;
    $process = new Process([PHP_BINARY, '-r', $script], base_path(), $environment);
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

test('only an explicit local false disables email verification', function (string|false $environment, string|false $flag, bool $required) {
    $configuration = securityConfiguration(['APP_ENV' => $environment, 'AUTH_EMAIL_VERIFICATION_REQUIRED' => $flag]);

    expect($configuration['verification'])->toBe($required);
})->with([
    'local opt out' => ['local', 'false', false],
    'local opt in' => ['local', 'true', true],
    'local missing flag' => ['local', false, true],
    'local invalid flag' => ['local', 'invalid', true],
    'local empty flag' => ['local', '', true],
    'production false' => ['production', 'false', true],
    'production true' => ['production', 'true', true],
    'production missing flag' => ['production', false, true],
    'production invalid flag' => ['production', 'invalid', true],
    'missing environment' => [false, 'false', true],
]);

test('session defaults protect production without preventing local http development', function (string|false $environment, ?bool $secure) {
    $configuration = securityConfiguration([
        'APP_ENV' => $environment, 'SESSION_SECURE_COOKIE' => false,
        'SESSION_HTTP_ONLY' => false, 'SESSION_SAME_SITE' => false, 'APP_DEBUG' => false,
    ]);

    expect($configuration)->toMatchArray([
        'secure' => $secure, 'http_only' => true, 'same_site' => 'lax', 'serialization' => 'json', 'debug' => false,
    ]);
})->with(['production' => ['production', true], 'local http' => ['local', null], 'missing environment' => [false, true]]);

test('default mail never logs authentication message contents', function (string|false $mailer) {
    $configuration = securityConfiguration(['MAIL_MAILER' => $mailer]);

    expect($configuration['mailer'])->toBe('array');
})->with(['inherited logging mailer' => 'log', 'in memory mailer' => 'array', 'missing mailer' => false]);

test('native verification and reset messages use memory without writing their contents to logs', function () {
    $configuration = securityConfiguration(['MAIL_MAILER' => 'log']);
    config(['mail.default' => $configuration['mailer'], 'auth.email_verification_required' => true]);
    $user = User::factory()->unverified()->create();
    Log::spy();

    $user->sendEmailVerificationNotification();
    $user->sendPasswordResetNotification('synthetic-reset-token');

    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(2);
    Log::shouldNotHaveReceived('debug');
});

test('verification policy survives real configuration caching', function (string $environment, string $flag, bool $required) {
    $directory = sys_get_temp_dir().'/crm-audit-'.Str::uuid();
    mkdir($directory, 0700);
    $cachePath = $directory.'/config.php';
    $variables = ['APP_CONFIG_CACHE' => $cachePath, 'APP_ENV' => $environment, 'AUTH_EMAIL_VERIFICATION_REQUIRED' => $flag];

    try {
        $cache = new Process([PHP_BINARY, 'artisan', 'config:cache', '--no-interaction'], base_path(), $variables);
        $cache->mustRun();
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            echo json_encode(config('auth.email_verification_required'), JSON_THROW_ON_ERROR);
            PHP;
        $read = new Process([PHP_BINARY, '-r', $script], base_path(), [...$variables, 'AUTH_EMAIL_VERIFICATION_REQUIRED' => $flag === 'true' ? 'false' : 'true']);
        $read->mustRun();

        expect(json_decode($read->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe($required);
    } finally {
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        rmdir($directory);
    }
})->with(['local optional' => ['local', 'false', false], 'production required' => ['production', 'true', true]]);
