<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['reporting.timezone' => 'America/Porto_Velho']);
    $this->travelTo(CarbonImmutable::parse('2026-10-16 16:00:00', 'UTC'));
});

function metricLead(Account $account, string $createdAt, ?LeadCategory $category = null): Lead
{
    $category ??= LeadCategory::factory()->for($account)->create();

    return Lead::factory()->for($account)->for($category, 'category')->create([
        'created_at' => $createdAt, 'first_contact_date' => '2020-01-01',
    ]);
}

test('each account sees only its own dashboard counts colors and category percentages', function () {
    $company = Membership::factory()->create();
    $agency = Membership::factory()->for(Account::factory()->agency())->create();
    $first = LeadCategory::factory()->for($company->account)->create(['name' => 'Empresa', 'color' => '#112233']);
    $second = LeadCategory::factory()->for($agency->account)->create(['name' => 'Agência', 'color' => '#aabbcc']);
    metricLead($company->account, '2026-10-16 04:00:00', $first);
    metricLead($agency->account, '2026-10-16 04:00:00', $second);
    metricLead($agency->account, '2026-10-15 04:00:00', $second);

    $this->actingAs($company->user)->withSession(['account_id' => $agency->account_id])
        ->withHeader('X-Account-ID', (string) $agency->account_id)
        ->get(route('dashboard', ['account_id' => $agency->account_id]))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('overview.totalLeads', 1)->where('newLeads.count', 1)->where('newLeads.period', 'week')
            ->where('overview.source', 'account')->where('overview.weeklyTotal', 1)
            ->has('overview.categories', 2)->where('overview.categories.0.id', $first->id)
            ->where('overview.categories.0.color', '#112233')->where('overview.categories.0.count', 1)
            ->where('overview.categories.0.percentage', 100)
            ->missing('overview.categories.0.account_id')->missing('overview.recentRecords'));
    $this->actingAs($agency->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 2)->where('newLeads.count', 2)
            ->where('overview.categories.0.id', $second->id)->where('overview.categories.0.count', 2)
            ->where('overview.weeklyIntake.3.count', 1)->where('overview.weeklyIntake.4.count', 1));
    $this->get(route('categories.index', ['account_id' => $company->account_id]))
        ->assertInertia(fn (Assert $page) => $page->where('totalLeads', 2)->has('categories.data', 1)
            ->where('categories.data.0.id', $second->id)->where('categories.data.0.leads_count', 2)
            ->where('categories.data.0.percentage', 100));
    $this->actingAs($company->user)->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->where('totalLeads', 1)->has('categories.data', 1)
            ->where('categories.data.0.id', $first->id)->where('categories.data.0.leads_count', 1));
});

test('dashboard metrics reject invalid current accounts', function (int $accounts) {
    $user = User::factory()->create();
    Membership::factory()->count($accounts)->for($user)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertNotFound();
})->with([0, 2]);

test('dashboard enforces the lead account authorization policy', function () {
    $own = Membership::factory()->create();
    Gate::before(fn () => Response::denyAsNotFound());

    $this->actingAs($own->user)->get(route('dashboard'))->assertNotFound();
});

test('calendar periods include the starting instant and exclude the next midnight', function (string $period, string $start) {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $boundary = CarbonImmutable::parse($start.' 04:00:00', 'UTC');
    foreach ([$boundary->subSecond()->toDateTimeString(), $boundary->toDateTimeString(), '2026-10-17 03:59:59', '2026-10-17 04:00:00'] as $created) {
        metricLead($own->account, $created, $category);
    }

    $this->actingAs($own->user)->get(route('dashboard', ['period' => $period]))
        ->assertInertia(fn (Assert $page) => $page->where('newLeads.period', $period)->where('newLeads.count', 2)
            ->where('overview.totalLeads', 4));
})->with([
    '7 days' => ['week', '2026-10-10'],
    '14 days' => ['fortnight', '2026-10-03'],
    '30 days' => ['month', '2026-09-17'],
]);

test('changing period changes only the new leads count', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    foreach (['2026-10-16 04:00:00', '2026-10-09 04:00:00', '2026-09-20 04:00:00', '2025-01-01 00:00:00'] as $created) {
        metricLead($own->account, $created, $category);
    }

    $this->actingAs($own->user);
    foreach (['week' => 1, 'fortnight' => 2, 'month' => 3] as $period => $count) {
        $this->get(route('dashboard', ['period' => $period]))
            ->assertInertia(fn (Assert $page) => $page->where('newLeads.count', $count)
                ->where('overview.totalLeads', 4)->where('overview.weeklyTotal', 1)
                ->where('overview.categories.0.count', 4)->where('overview.categories.0.percentage', 100));
    }
});

test('the period allowlist rejects arbitrary input', function (mixed $period) {
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->getJson(route('dashboard', ['period' => $period]))
        ->assertUnprocessable()->assertJsonValidationErrors('period');
})->with(['unsupported' => 'year', 'arbitrary days' => '7', 'arbitrary date' => '2026-01-01', 'array' => [['week']], 'injection' => "week' OR 1=1"]);

test('the weekly chart fills seven civil days using creation time and half open bounds', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    foreach (['2026-10-12 03:59:59', '2026-10-12 04:00:00', '2026-10-13 03:59:59', '2026-10-16 04:00:00', '2026-10-17 03:59:59', '2026-10-19 03:59:59', '2026-10-19 04:00:00'] as $created) {
        metricLead($own->account, $created, $category);
    }

    $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.weeklyTotal', 5)
            ->where('overview.reportingTimezone', 'America/Porto_Velho')->has('overview.weeklyIntake', 7)
            ->where('overview.weeklyIntake', [
                ['day' => 'Seg', 'date' => '2026-10-12', 'count' => 2],
                ['day' => 'Ter', 'date' => '2026-10-13', 'count' => 0],
                ['day' => 'Qua', 'date' => '2026-10-14', 'count' => 0],
                ['day' => 'Qui', 'date' => '2026-10-15', 'count' => 0],
                ['day' => 'Sex', 'date' => '2026-10-16', 'count' => 2],
                ['day' => 'Sáb', 'date' => '2026-10-17', 'count' => 0],
                ['day' => 'Dom', 'date' => '2026-10-18', 'count' => 1],
            ]));
});

test('calendar weeks cross month and year boundaries without losing days', function (string $now, string $monday) {
    $this->travelTo(CarbonImmutable::parse($now.' 16:00:00', 'UTC'));
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $start = CarbonImmutable::parse($monday.' 04:00:00', 'UTC');
    metricLead($own->account, $start->subSecond()->toDateTimeString(), $category);
    metricLead($own->account, $start->toDateTimeString(), $category);

    $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.weeklyIntake.0.date', $monday)
            ->where('overview.weeklyIntake.0.count', 1)->where('overview.weeklyTotal', 1));
})->with([
    'month end' => ['2026-11-01', '2026-10-26'],
    'year end' => ['2027-01-01', '2026-12-28'],
    'next week' => ['2027-01-04', '2027-01-04'],
]);

test('report timezone is configurable and daylight saving uses civil day boundaries', function () {
    config(['reporting.timezone' => 'America/New_York']);
    $this->travelTo(CarbonImmutable::parse('2026-03-08 16:00:00', 'UTC'));
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    foreach (['2026-03-02 04:59:59', '2026-03-02 05:00:00', '2026-03-08 05:00:00', '2026-03-09 03:59:59', '2026-03-09 04:00:00'] as $created) {
        metricLead($own->account, $created, $category);
    }

    $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('newLeads.count', 3)->where('overview.weeklyTotal', 3)
            ->where('overview.reportingTimezone', 'America/New_York')->where('overview.weeklyIntake.6.count', 2));
    expect(config('app.timezone'))->toBe('UTC');
});

test('empty accounts and empty categories have zero counts and percentages', function () {
    $own = Membership::factory()->create();
    LeadCategory::factory()->for($own->account)->create();
    Lead::factory()->create();

    $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 0)->where('newLeads.count', 0)
            ->where('overview.weeklyTotal', 0)->where('overview.categories.0.count', 0)
            ->where('overview.categories.0.percentage', 0)->where('overview.weeklyIntake.0.count', 0));
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->where('totalLeads', 0)
        ->where('categories.data.0.leads_count', 0)->where('categories.data.0.percentage', 0));
});

test('the donut includes categories beyond pagination and paginated percentages use the account total', function () {
    $own = Membership::factory()->create();
    $categories = LeadCategory::factory()->count(14)->for($own->account)->create();
    metricLead($own->account, '2026-10-16 12:00:00', $categories->first());
    metricLead($own->account, '2026-10-16 12:00:00', $categories->last());
    metricLead($own->account, '2026-10-16 12:00:00', $categories->last());

    $response = $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('overview.categories', 15)
            ->where('overview.categories.0.id', $categories->last()->id)->where('overview.categories.0.count', 2)
            ->where('overview.categories.0.percentage', 66.7)->where('overview.categories.13.id', $categories->first()->id)
            ->where('overview.categories.13.percentage', 33.3));
    expect(array_sum(array_column($response->inertiaProps('overview.categories'), 'count')))->toBe(3);
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->has('categories.data', 12)
        ->where('totalLeads', 3)->where('categories.data.0.percentage', 66.7));
    $this->get(route('categories.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('categories.data', 2)
        ->where('totalLeads', 3)->where('categories.data.1.id', $categories->first()->id)
        ->where('categories.data.1.leads_count', 1)->where('categories.data.1.percentage', 33.3));
});

test('percentage rounding is independent and does not fabricate counts to force a hundred percent', function () {
    $own = Membership::factory()->create();
    foreach (range(1, 3) as $ignored) {
        metricLead($own->account, '2026-10-16 12:00:00');
    }

    $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.categories.0.percentage', 33.3)
            ->where('overview.categories.1.percentage', 33.3)->where('overview.categories.2.percentage', 33.3));
});

test('a sold named category charged amount and next contact do not invent commercial statuses', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create(['name' => 'Vendido']);
    Lead::factory()->for($own->account)->for($category, 'category')->create([
        'charged_amount' => '1500.00', 'next_contact_date' => '2026-10-17',
    ]);

    $this->actingAs($own->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 1)
            ->where('overview.inProgressLeads', 0)->where('overview.soldLeads', 0)
            ->where('overview.categories.0.name', 'Vendido')->where('overview.categories.0.percentage', 100)
            ->missing('overview.recentRecords')->where('overview.soldPercentage', 0));
});

test('creating a lead and revisiting pages updates persisted metrics', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $this->actingAs($own->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 0));

    $this->post(route('leads.store'), ['name' => 'Nova oportunidade', 'category_id' => $category->id, 'first_contact_date' => '2020-01-01'])
        ->assertRedirect(route('leads.index'));

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 1)
        ->where('newLeads.count', 1)->where('overview.weeklyTotal', 1)->where('overview.categories.0.count', 1));
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->where('totalLeads', 1)
        ->where('categories.data.0.leads_count', 1)->where('categories.data.0.percentage', 100));
});

test('category deletion cascades and recalculates all metrics without touching another account', function () {
    $own = Membership::factory()->create();
    $remove = LeadCategory::factory()->for($own->account)->create();
    $keep = LeadCategory::factory()->for($own->account)->create();
    metricLead($own->account, '2026-10-16 12:00:00', $remove);
    metricLead($own->account, '2026-10-16 12:00:00', $remove);
    metricLead($own->account, '2026-10-16 12:00:00', $keep);
    $foreign = Lead::factory()->create();
    $foreignBefore = $foreign->fresh()->getRawOriginal();
    $this->actingAs($own->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 3));

    $this->delete(route('categories.destroy', $remove), $this->categoryDeletionPayload($remove))->assertRedirect(route('categories.index'));

    $this->assertModelMissing($remove);
    $this->assertDatabaseCount('leads', 2);
    expect($foreign->fresh()->getRawOriginal())->toBe($foreignBefore);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 1)
        ->where('newLeads.count', 1)->where('overview.weeklyTotal', 1)->has('overview.categories', 2)
        ->where('overview.categories.0.id', $keep->id)->where('overview.categories.0.percentage', 100));
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->where('totalLeads', 1)
        ->where('categories.data.0.leads_count', 1)->where('categories.data.0.percentage', 100));
});

test('a partial period reload queries only the requested metric', function () {
    $own = Membership::factory()->create();
    metricLead($own->account, '2026-10-16 12:00:00');
    $version = app(HandleInertiaRequests::class)->version(Request::create('/dashboard'));
    DB::enableQueryLog();

    try {
        $this->actingAs($own->user)->withHeaders([
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'dashboard', 'X-Inertia-Partial-Data' => 'newLeads',
        ])->get(route('dashboard', ['period' => 'month']))->assertOk()
            ->assertJsonPath('props.newLeads.period', 'month')->assertJsonPath('props.newLeads.count', 1)
            ->assertJsonMissingPath('props.overview');
        $queries = collect(DB::getQueryLog());
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], '"leads"')))->toHaveCount(1);
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], '"lead_categories"')))->toHaveCount(0);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('metrics query counts remain bounded as category and lead counts grow', function (int $records) {
    $own = Membership::factory()->create();
    $categories = LeadCategory::factory()->count($records)->for($own->account)
        ->sequence(fn (Sequence $sequence): array => ['color' => sprintf('#%06x', $sequence->index + 1)])
        ->create();
    foreach ($categories as $category) {
        metricLead($own->account, '2026-10-16 12:00:00', $category);
    }
    DB::enableQueryLog();

    try {
        $this->actingAs($own->user)->get(route('dashboard'))->assertOk();
        $dashboardQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"leads"'));
        expect($dashboardQueries)->toHaveCount(5);
        foreach ($dashboardQueries as $query) {
            expect($query['query'])->toContain('account_id');
            expect($query['bindings'])->toContain($own->account_id);
        }
        DB::flushQueryLog();
        $this->get(route('categories.index'))->assertOk();
        $categoryQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"leads"') || str_contains($query['query'], 'from "lead_categories"'));
        expect($categoryQueries)->toHaveCount(4);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
})->with(['one category' => 1, 'twenty categories' => 20]);
