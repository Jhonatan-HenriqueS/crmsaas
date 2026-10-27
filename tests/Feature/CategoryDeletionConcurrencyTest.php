<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

test('a lead inserted after impact validation cannot be silently included in a category deletion', function () {
    $directory = storage_path('framework/testing/category-delete-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($directory, 0700);
    $database = $directory.'/database.sqlite';
    touch($database);
    chmod($database, 0600);
    config(['database.connections.category_delete_race' => [...config('database.connections.sqlite'), 'database' => $database, 'busy_timeout' => 5000]]);
    $worker = null;

    try {
        Artisan::call('migrate', ['--database' => 'category_delete_race', '--force' => true, '--no-interaction' => true]);
        DB::connection('category_delete_race')->statement('PRAGMA journal_mode=WAL');
        $account = Account::factory()->connection('category_delete_race')->create();
        $user = User::factory()->connection('category_delete_race')->create();
        $account->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        $category = $account->leadCategories()->create(['name' => 'Impacto conferido', 'color' => '#112233']);
        $account->leads()->create(['category_id' => $category->id, 'name' => 'Lead apresentado', 'first_contact_date' => '2020-01-01']);
        $this->actingAs($user);
        $payload = $this->categoryDeletionPayload($category);
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            config(['database.connections.sqlite.busy_timeout'=>5000,'inertia.ssr.enabled'=>false,'logging.default'=>'null']);
            Illuminate\Support\Facades\DB::purge('sqlite');
            Illuminate\Support\Facades\Http::preventStrayRequests();
            App\Models\LeadCategory::deleting(function (): void {
                $directory = getenv('DELETION_RACE_DIRECTORY');
                touch($directory.'/validated');
                $deadline = microtime(true) + 15;
                while (!is_file($directory.'/writer-finished')) {
                    if (microtime(true)>$deadline) { exit(9); }
                    usleep(1000);
                }
            });
            $request = Illuminate\Http\Request::create('/categorias/'.getenv('DELETION_RACE_CATEGORY'),'DELETE',server:[
                'HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json',
            ], content:json_encode(['confirmation'=>getenv('DELETION_RACE_CONFIRMATION')]));
            $app->instance('request',$request);
            Illuminate\Support\Facades\Auth::loginUsingId((int)getenv('DELETION_RACE_USER'));
            $response=$kernel->handle($request);
            echo json_encode(['status'=>$response->getStatusCode(),'confirmation_error'=>isset(json_decode($response->getContent(),true)['errors']['confirmation'])]);
            $kernel->terminate($request,$response);
            PHP;
        $worker = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'APP_CONFIG_CACHE' => $directory.'/config.php', 'LOG_CHANNEL' => '"null"',
            'DELETION_RACE_DIRECTORY' => $directory, 'DELETION_RACE_USER' => (string) $user->id,
            'DELETION_RACE_CATEGORY' => (string) $category->id, 'DELETION_RACE_CONFIRMATION' => $payload['confirmation'],
        ]);
        $worker->setTimeout(25);
        $worker->start();
        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/validated')) {
            if (! $worker->isRunning() || microtime(true) > $deadline) {
                $this->fail('The isolated deletion worker did not reach the validation barrier.');
            }
            usleep(1000);
        }

        $account->leads()->create(['category_id' => $category->id, 'name' => 'Lead concorrente', 'first_contact_date' => '2020-01-01']);
        touch($directory.'/writer-finished');
        $worker->wait();

        expect($worker->isSuccessful())->toBeTrue();
        expect(json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'status' => 422, 'confirmation_error' => true,
        ]);
        expect($account->leadCategories()->whereKey($category->id)->exists())->toBeTrue();
        expect($account->leads()->where('category_id', $category->id)->count())->toBe(2);
        expect(DB::connection('category_delete_race')->select('PRAGMA foreign_key_check'))->toBe([]);
    } finally {
        if ($worker?->isRunning()) {
            $worker->stop();
        }
        DB::purge('category_delete_race');
        File::deleteDirectory($directory);
    }
});
