<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

test('concurrent confirmed completions preserve one sale and one permanent category in sqlite', function () {
    $directory = storage_path('framework/testing/completion-race-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($directory, 0700);
    $database = $directory.'/database.sqlite';
    touch($database);
    chmod($database, 0600);
    config(['database.connections.completion_race' => [...config('database.connections.sqlite'), 'database' => $database, 'busy_timeout' => 5000]]);
    $workers = [];
    try {
        Artisan::call('migrate', ['--database' => 'completion_race', '--force' => true, '--no-interaction' => true]);
        $account = Account::factory()->connection('completion_race')->create();
        $user = User::factory()->connection('completion_race')->create();
        $account->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        $ordinary = $account->leadCategories()->create(['name' => 'Em contato', 'color' => '#112233']);
        $lead = $account->leads()->create(['category_id' => $ordinary->id, 'name' => 'Concorrente', 'first_contact_date' => '2020-01-01', 'charged_amount' => '100.00']);
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            config(['database.connections.sqlite.busy_timeout' => 5000, 'inertia.ssr.enabled' => false, 'logging.default' => 'null']);
            Illuminate\Support\Facades\DB::purge('sqlite');
            Illuminate\Support\Facades\Http::preventStrayRequests();
            App\Models\Lead::updating(function (): void {
                $directory = getenv('COMPLETION_RACE_DIRECTORY');
                touch($directory.'/ready-'.getenv('COMPLETION_RACE_INDEX'));
                $deadline = microtime(true) + 15;
                while (!is_file($directory.'/start')) {
                    if (microtime(true) > $deadline) { exit(9); }
                    usleep(1000);
                }
            });
            $request = Illuminate\Http\Request::create('/leads/'.getenv('COMPLETION_RACE_LEAD').'/complete', 'POST',
                server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
                content: json_encode(['confirm_completion' => true]));
            $app->instance('request', $request);
            Illuminate\Support\Facades\Auth::loginUsingId((int) getenv('COMPLETION_RACE_USER'));
            $response = $kernel->handle($request);
            echo json_encode(['status' => $response->getStatusCode()]);
            $kernel->terminate($request, $response);
            PHP;
        for ($i = 0; $i < 2; $i++) {
            $worker = new Process([PHP_BINARY, '-r', $script], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)), 'APP_CONFIG_CACHE' => $directory.'/config.php',
                'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
                'LOG_CHANNEL' => '\"null\"', 'COMPLETION_RACE_DIRECTORY' => $directory, 'COMPLETION_RACE_INDEX' => (string) $i,
                'COMPLETION_RACE_LEAD' => (string) $lead->id, 'COMPLETION_RACE_USER' => (string) $user->id,
            ]);
            $worker->setTimeout(25);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning()) {
                    $this->fail('An isolated completion worker stopped before the barrier.');
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Completion workers did not reach the barrier.');
            }
            usleep(1000);
        }
        touch($directory.'/start');
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue();
            expect(json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'])->toBe(302);
        }
        $protected = $account->leadCategories()->where('system_key', 'sale_completed')->sole();
        expect($account->leads()->where('category_id', $protected->id)->count())->toBe(1);
        expect($account->leads()->count())->toBe(1);
        expect($lead->fresh()->getRawOriginal('created_at'))->toBe($lead->getRawOriginal('created_at'));
        expect(DB::connection('completion_race')->select('PRAGMA foreign_key_check'))->toBe([]);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        DB::purge('completion_race');
        File::deleteDirectory($directory);
    }
});
