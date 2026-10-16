<?php

use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

test('concurrent sqlite requests preserve ownership and unique canonical identity', function (string $operation) {
    $directory = storage_path('framework/testing/concurrency-'.bin2hex(random_bytes(8)));
    mkdir($directory, 0700, true);
    $database = $directory.'/test.sqlite';
    touch($database);
    chmod($database, 0600);
    config(['database.connections.race' => [...config('database.connections.sqlite'), 'database' => $database, 'busy_timeout' => 5000]]);
    $workers = [];

    try {
        Artisan::call('migrate', ['--database' => 'race', '--force' => true, '--no-interaction' => true]);
        $users = [];
        if ($operation === 'delete') {
            $account = Account::factory()->connection('race')->create();
            for ($i = 0; $i < 2; $i++) {
                $user = User::factory()->connection('race')->create();
                Membership::on('race')->create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'owner']);
                $users[] = $user->id;
            }
        }

        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            config(['database.connections.sqlite.busy_timeout' => 5000]);
            Illuminate\Support\Facades\DB::purge('sqlite');
            Illuminate\Support\Facades\Http::preventStrayRequests();
            $mode = getenv('RACE_OPERATION');
            if ($mode === 'delete') {
                $request = Illuminate\Http\Request::create('/settings/profile', 'DELETE', [], [], [],
                    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode(['password' => 'password']));
            } else {
                Illuminate\Support\Facades\Http::fake(['https://api.pwnedpasswords.com/range/*' => Illuminate\Support\Facades\Http::response('00000:0')]);
                $request = Illuminate\Http\Request::create('/register', 'POST', [], [], [],
                    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode([
                        'name' => 'Concurrent user', 'email' => getenv('RACE_USER') === '0' ? 'Race@Example.Test' : 'race@example.test',
                        'password' => 'Strong#42', 'plan' => 'empresa', 'terms' => true,
                    ]));
            }
            $app->instance('request', $request);
            if ($mode === 'delete') {
                Illuminate\Support\Facades\Auth::loginUsingId((int) getenv('RACE_USER'));
            }
            touch(getenv('RACE_DIRECTORY').'/ready-'.getenv('RACE_INDEX'));
            $deadline = microtime(true) + 15;
            while (! is_file(getenv('RACE_DIRECTORY').'/start')) {
                if (microtime(true) > $deadline) {exit(9);}
                usleep(1000);
            }
            $response = $kernel->handle($request);
            echo json_encode(['status' => $response->getStatusCode()]);
            $kernel->terminate($request, $response);
            PHP;

        for ($i = 0; $i < 2; $i++) {
            $worker = new Process([PHP_BINARY, '-r', $script], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
                'LOG_CHANNEL' => '"null"', 'APP_CONFIG_CACHE' => $directory.'/config.php',
                'RACE_DIRECTORY' => $directory, 'RACE_INDEX' => (string) $i,
                'RACE_USER' => (string) ($users[$i] ?? $i), 'RACE_OPERATION' => $operation,
            ]);
            $worker->setTimeout(25);
            $worker->start();
            $workers[] = $worker;
        }

        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning() && ! $worker->isSuccessful()) {
                    $this->fail('Concurrent worker stopped before the barrier: '.$worker->getOutput().$worker->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Concurrent workers did not reach the synchronization barrier.');
            }
            usleep(1000);
        }
        touch($directory.'/start');
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue();
            $statuses[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        expect($statuses)->toBe($operation === 'delete' ? [302, 422] : [201, 422]);
        expect(DB::connection('race')->table('users')->count())->toBe(1);
        expect(DB::connection('race')->table('accounts')->count())->toBe(1);
        expect(DB::connection('race')->table('memberships')->where('role', 'owner')->count())->toBe(1);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        DB::purge('race');
        File::deleteDirectory($directory);
    }
})->with(['delete', 'register']);
