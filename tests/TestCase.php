<?php

namespace Tests;

use App\Models\LeadCategory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();

        if (! $application->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('cache.default') !== 'array' || config('session.driver') !== 'array'
            || config('mail.default') !== 'array' || config('queue.default') !== 'sync') {
            throw new \LogicException('The test suite requires isolated SQLite, cache, session, mail and queue drivers.');
        }

        Http::preventStrayRequests();
        config(['inertia.ssr.enabled' => false, 'logging.default' => 'null']);

        return $application;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies
     */
    public function browserRequest(string $method, string $url, array $data = [], array $cookies = []): TestResponse
    {
        Auth::forgetGuards();
        app()->forgetInstance('auth.driver');
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        app('cookie')->flushQueuedCookies();

        foreach (app('router')->getRoutes() as $route) {
            $route->flushController();
        }

        return $this->call($method, $url, $data, $cookies);
    }

    /** @return array<string, string> */
    public function browserCookies(TestResponse $response): array
    {
        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $cookies;
    }

    /** @return array{confirmation: string} */
    public function categoryDeletionPayload(LeadCategory $category): array
    {
        $page = 1;
        do {
            $response = $this->get(route('categories.index', ['page' => $page]))->assertOk();
            $categories = $response->inertiaProps('categories');
            foreach ($categories['data'] as $item) {
                if ($item['id'] === $category->id) {
                    return ['confirmation' => $item['deletion_confirmation']];
                }
            }
            $page++;
        } while ($categories['hasMorePages']);

        $this->fail('The category is absent from the authenticated account listing.');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
