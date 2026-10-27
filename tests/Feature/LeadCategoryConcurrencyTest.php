<?php

use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

test('two validated concurrent category writes preserve uniqueness and return one controlled duplicate error', function (string $method) {
    $directory = storage_path('framework/testing/category-race-'.bin2hex(random_bytes(8)));
    mkdir($directory, 0700, true);
    $database = $directory.'/test.sqlite';
    touch($database);
    chmod($database, 0600);
    config(['database.connections.category_race' => [...config('database.connections.sqlite'), 'database' => $database, 'busy_timeout' => 5000]]);
    $workers = [];

    try {
        Artisan::call('migrate', ['--database' => 'category_race', '--force' => true, '--no-interaction' => true]);
        $account = Account::factory()->connection('category_race')->create();
        $user = User::factory()->connection('category_race')->create();
        $membership = Membership::on('category_race')->create([
            'account_id' => $account->id, 'user_id' => $user->id, 'role' => 'owner',
        ]);
        $categoryIds = [];
        if ($method === 'PATCH') {
            foreach (['#112233', '#445566'] as $i => $color) {
                $categoryIds[] = $account->leadCategories()->create(['name' => 'Original '.$i, 'color' => $color])->id;
            }
        }
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            config(['database.connections.sqlite.busy_timeout' => 5000, 'inertia.ssr.enabled' => false]);
            Illuminate\Support\Facades\DB::purge('sqlite');
            Illuminate\Support\Facades\Http::preventStrayRequests();
            $event = getenv('CATEGORY_RACE_METHOD') === 'PATCH' ? 'updating' : 'creating';
            App\Models\LeadCategory::{$event}(function (): void {
                $ready = getenv('CATEGORY_RACE_DIRECTORY').'/ready-'.getenv('CATEGORY_RACE_INDEX');
                touch($ready);
                chmod($ready, 0600);
                $deadline = microtime(true) + 15;
                while (! is_file(getenv('CATEGORY_RACE_DIRECTORY').'/start')) {
                    if (microtime(true) > $deadline) {exit(9);}
                    usleep(1000);
                }
            });
            $path = '/categorias'.(getenv('CATEGORY_RACE_METHOD') === 'PATCH' ? '/'.getenv('CATEGORY_RACE_CATEGORY') : '');
            $request = Illuminate\Http\Request::create($path, getenv('CATEGORY_RACE_METHOD'), [], [], [],
                ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => 'Concorrente', 'color' => getenv('CATEGORY_RACE_INDEX') === '0' ? '#7c65c1' : '#7C65C1']));
            $app->instance('request', $request);
            Illuminate\Support\Facades\Auth::loginUsingId((int) getenv('CATEGORY_RACE_USER'));
            $response = $kernel->handle($request);
            echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]);
            $kernel->terminate($request, $response);
            PHP;

        for ($i = 0; $i < 2; $i++) {
            $worker = new Process([PHP_BINARY, '-r', $script], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
                'LOG_CHANNEL' => '"null"', 'APP_CONFIG_CACHE' => $directory.'/config.php',
                'CATEGORY_RACE_DIRECTORY' => $directory, 'CATEGORY_RACE_INDEX' => (string) $i,
                'CATEGORY_RACE_USER' => (string) $membership->user_id,
                'CATEGORY_RACE_METHOD' => $method, 'CATEGORY_RACE_CATEGORY' => (string) ($categoryIds[$i] ?? 0),
            ]);
            $worker->setTimeout(25);
            $worker->start();
            $workers[] = $worker;
        }

        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning() && ! $worker->isSuccessful()) {
                    $this->fail('Category worker stopped before both validations completed: '.$worker->getOutput().$worker->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Category requests did not reach the insert barrier.');
            }
            usleep(1000);
        }
        touch($directory.'/start');
        $responses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue();
            $responses[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        expect(collect($responses)->pluck('status')->sort()->values()->all())->toBe([302, 422]);
        $duplicate = collect($responses)->firstWhere('status', 422);
        expect($duplicate['body']['errors']['color'][0])->toBe('Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.');
        $records = DB::connection('category_race')->table('lead_categories')->whereNull('system_key')->get();
        expect(DB::connection('category_race')->table('lead_categories')->where('system_key', 'sale_completed')->count())->toBe(1);
        expect($records)->toHaveCount($method === 'PATCH' ? 2 : 1);
        expect($records->where('color', '#7c65c1'))->toHaveCount(1);
        expect($records->pluck('account_id')->unique()->all())->toBe([$membership->account_id]);
        if ($method === 'PATCH') {
            expect($records->where('color', '!=', '#7c65c1')->sole()->name)->toStartWith('Original ');
        }
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        DB::purge('category_race');
        File::deleteDirectory($directory);
    }
})->with(['create' => 'POST', 'update' => 'PATCH']);
