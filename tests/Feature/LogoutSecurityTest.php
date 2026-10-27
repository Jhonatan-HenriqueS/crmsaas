<?php

use App\Http\CurrentAccount;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
});

function loginLogoutClient(TestCase $test, User $user, bool $remember = false): TestResponse
{
    $initial = $test->browserRequest('GET', route('login'))->assertOk();

    return $test->browserRequest('POST', route('login.store'), [
        '_token' => $initial->getCookie('XSRF-TOKEN')->getValue(),
        'email' => $user->email, 'password' => 'password', 'remember' => $remember,
    ], $test->browserCookies($initial))->assertRedirect(route('dashboard', absolute: false));
}

test('native logout destroys the database session rotates csrf and rejects every old cookie combination', function (bool $remember) {
    $own = Membership::factory()->create();
    $login = loginLogoutClient($this, $own->user, $remember);
    $sessionName = config('session.cookie');
    $oldSession = $login->getCookie($sessionName)->getValue();
    $oldToken = $login->getCookie('XSRF-TOKEN')->getValue();
    $cookies = $this->browserCookies($login);
    $rememberName = Auth::guard()->getRecallerName();
    $this->assertDatabaseHas('sessions', ['id' => $oldSession, 'user_id' => $own->user_id]);
    $this->browserRequest('GET', route('dashboard'), cookies: $cookies)->assertOk();

    $logout = $this->browserRequest('POST', route('logout'), ['_token' => $oldToken], $cookies)
        ->assertRedirect(route('login'));
    $this->assertGuest();
    $this->assertDatabaseMissing('sessions', ['id' => $oldSession]);
    expect($logout->getCookie($sessionName)->getValue())->not->toBe($oldSession);
    expect($logout->getCookie('XSRF-TOKEN')->getValue())->not->toBe($oldToken);
    expect(app('request')->attributes->has(CurrentAccount::class))->toBeFalse();
    $this->browserRequest('GET', route('dashboard'), cookies: $cookies)->assertRedirect(route('login'));
    $this->assertGuest();
    if ($remember) {
        $this->browserRequest('GET', route('dashboard'), cookies: [$rememberName => $cookies[$rememberName]])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }
    $loginPage = $this->browserRequest('GET', route('login'), cookies: $this->browserCookies($logout))
        ->assertOk()->assertInertia(fn ($page) => $page->where('auth.user', null));
    expect($loginPage->viewData('page')['clearHistory'])->toBeTrue();
})->with(['session only' => false, 'with remember me' => true]);

test('old csrf is refused by the new anonymous session and a new login works with its renewed token', function () {
    $own = Membership::factory()->create();
    $login = loginLogoutClient($this, $own->user);
    $oldToken = $login->getCookie('XSRF-TOKEN')->getValue();
    $logout = $this->browserRequest('POST', route('logout'), ['_token' => $oldToken], $this->browserCookies($login))->assertRedirect(route('login'));
    $anonymous = $this->browserCookies($logout);
    $credentials = ['email' => $own->user->email, 'password' => 'password'];
    $this->browserRequest('POST', route('login.store'), [...$credentials, '_token' => $oldToken], $anonymous)->assertStatus(419);
    $this->assertGuest();
    $new = $this->browserRequest('POST', route('login.store'), [
        ...$credentials, '_token' => $logout->getCookie('XSRF-TOKEN')->getValue(),
    ], $anonymous)->assertRedirect(route('dashboard', absolute: false));
    $this->browserRequest('GET', route('dashboard'), cookies: $this->browserCookies($new))->assertOk();
    $this->assertAuthenticatedAs($own->user);
});

test('logout with missing or mismatching csrf is rejected without destroying the authenticated session', function (?string $token) {
    $own = Membership::factory()->create();
    $login = loginLogoutClient($this, $own->user);
    $cookies = $this->browserCookies($login);
    $this->browserRequest('POST', route('logout'), ['_token' => $token], $cookies)->assertStatus(419);
    $this->assertDatabaseHas('sessions', ['id' => $login->getCookie(config('session.cookie'))->getValue(), 'user_id' => $own->user_id]);
    $this->browserRequest('GET', route('dashboard'), cookies: $cookies)->assertOk();
})->with(['missing' => null, 'foreign' => 'not-this-session']);

test('get logout is not a state changing endpoint', function () {
    $own = Membership::factory()->create();
    $login = loginLogoutClient($this, $own->user);
    $cookies = $this->browserCookies($login);
    $this->browserRequest('GET', route('logout'), cookies: $cookies)->assertStatus(405);
    $this->browserRequest('GET', route('dashboard'), cookies: $cookies)->assertOk();
});

test('a valid csrf logout without authentication is handled by the existing auth middleware', function () {
    $initial = $this->browserRequest('GET', route('login'))->assertOk();
    $this->browserRequest('POST', route('logout'), ['_token' => $initial->getCookie('XSRF-TOKEN')->getValue()], $this->browserCookies($initial))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a logged out browser and a replayed session cannot use any account read or mutation endpoint', function (string $method, string $route, string $resource) {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $lead = Lead::factory()->for($own->account)->for($category, 'category')->create();
    $login = loginLogoutClient($this, $own->user);
    $cookies = $this->browserCookies($login);
    $logout = $this->browserRequest('POST', route('logout'), ['_token' => $login->getCookie('XSRF-TOKEN')->getValue()], $cookies)->assertRedirect();
    $parameters = match ($resource) {
        'lead' => ['lead' => $lead->id],
        'category' => ['category' => $category->id],
        'filter' => ['category' => $category->id],
        default => [],
    };
    // No session bypass: each request reloads the original cookies through middleware.
    if ($method === 'GET') {
        $this->browserRequest($method, route($route, $parameters), cookies: $cookies)->assertRedirect(route('login'));
        $this->withServerVariables(['HTTP_ACCEPT' => 'application/json'])->browserRequest($method, route($route, $parameters), cookies: $cookies)->assertUnauthorized();
    } else {
        // The current anonymous session has valid CSRF but still has no authority.
        $this->withServerVariables(['HTTP_ACCEPT' => 'application/json'])->browserRequest($method, route($route, $parameters), [
            '_token' => $logout->getCookie('XSRF-TOKEN')->getValue(),
        ], $this->browserCookies($logout))->assertUnauthorized();
        $this->browserRequest($method, route($route, $parameters), ['_token' => $login->getCookie('XSRF-TOKEN')->getValue()], $cookies)->assertStatus(419);
    }
    $this->assertModelExists($category);
    $this->assertModelExists($lead);
})->with([
    'dashboard' => ['GET', 'dashboard', 'none'],
    'categories' => ['GET', 'categories.index', 'none'],
    'leads filter' => ['GET', 'leads.index', 'filter'],
    'category options' => ['GET', 'leads.category-options', 'none'],
    'create lead' => ['POST', 'leads.store', 'none'],
    'edit or reopen lead' => ['PATCH', 'leads.update', 'lead'],
    'delete lead' => ['DELETE', 'leads.destroy', 'lead'],
    'complete sale' => ['POST', 'leads.complete', 'lead'],
    'create category' => ['POST', 'categories.store', 'none'],
    'edit category' => ['PATCH', 'categories.update', 'category'],
    'delete category' => ['DELETE', 'categories.destroy', 'category'],
]);

test('a second active browser remains authenticated while old persistent credentials stop restoring authentication', function () {
    $own = Membership::factory()->create();
    $a = loginLogoutClient($this, $own->user, true);
    $b = loginLogoutClient($this, $own->user, true);
    $rememberName = Auth::guard()->getRecallerName();
    $this->browserRequest('POST', route('logout'), ['_token' => $a->getCookie('XSRF-TOKEN')->getValue()], $this->browserCookies($a))->assertRedirect();
    $this->assertDatabaseHas('sessions', ['id' => $b->getCookie(config('session.cookie'))->getValue(), 'user_id' => $own->user_id]);
    $this->browserRequest('GET', route('dashboard'), cookies: $this->browserCookies($b))->assertOk();
    $this->assertAuthenticatedAs($own->user);
    $cookies = $this->browserCookies($b);
    $this->browserRequest('GET', route('dashboard'), cookies: [$rememberName => $cookies[$rememberName]])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('logout preserves business data password and other accounts and resolves a different account on the next login', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    Lead::factory()->for($own->account)->for($category, 'category')->create();
    $foreign = Lead::factory()->for($other->account)->create();
    $login = loginLogoutClient($this, $own->user, true);
    $before = collect(['accounts', 'memberships', 'leads', 'lead_categories'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toArray()]);
    $passwordAndProfile = Arr::except($own->user->fresh()->getRawOriginal(), ['remember_token']);
    $this->browserRequest('POST', route('logout'), ['_token' => $login->getCookie('XSRF-TOKEN')->getValue()], $this->browserCookies($login))->assertRedirect();
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->orderBy('id')->get()->toArray())->toEqual($rows);
    }
    expect(Arr::except($own->user->fresh()->getRawOriginal(), ['remember_token']))->toBe($passwordAndProfile);
    $second = loginLogoutClient($this, $other->user);
    $this->browserRequest('GET', route('leads.index'), cookies: $this->browserCookies($second))
        ->assertInertia(fn ($page) => $page->where('auth.user.id', $other->user_id)->where('leads.total', 1)->where('leads.data.0.id', $foreign->id));
    expect(app('request')->attributes->has(CurrentAccount::class))->toBeFalse();
});

test('private pages encrypt history and disable storage while anonymous pages retain their cache policy', function () {
    $own = Membership::factory()->create();
    $login = loginLogoutClient($this, $own->user);
    foreach (['dashboard', 'categories.index', 'leads.index'] as $route) {
        $response = $this->browserRequest('GET', route($route), cookies: $this->browserCookies($login))->assertOk();
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
        expect($response->viewData('page')['encryptHistory'])->toBeTrue();
    }
    $public = $this->browserRequest('GET', route('home'))->assertOk();
    expect($public->headers->get('Cache-Control'))->not->toContain('no-store');
    expect($public->viewData('page')['encryptHistory'] ?? false)->toBeFalse();
});

test('the official json logout contract returns 204 after invalidating the authenticated session', function () {
    $own = Membership::factory()->create();
    $login = loginLogoutClient($this, $own->user);
    $this->withServerVariables(['HTTP_ACCEPT' => 'application/json'])->browserRequest('POST', route('logout'), [
        '_token' => $login->getCookie('XSRF-TOKEN')->getValue(),
    ], $this->browserCookies($login))->assertNoContent();
    $this->assertGuest();
    $this->assertDatabaseMissing('sessions', ['id' => $login->getCookie(config('session.cookie'))->getValue()]);
});
