<?php

use App\Http\Controllers\LeadController;
use App\Http\Middleware\ResolveCurrentAccount;
use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'verified', ResolveCurrentAccount::class])
        ->prefix('/test/lead-foundation')->group(function () {
            Route::get('/', [LeadController::class, 'index'])->name('test.leads.index');
            Route::post('/', [LeadController::class, 'store'])->name('test.leads.store');
            Route::get('/{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('test.leads.show');
        });
    Route::getRoutes()->refreshNameLookups();
});

/** @return array<string, mixed> */
function leadFoundationPayload(LeadCategory $category): array
{
    return [
        'category_id' => $category->id,
        'name' => 'Empresa interessada',
        'description' => null,
        'first_contact_date' => '2020-01-10',
        'next_contact_date' => '2020-01-15',
        'potential_amount' => null,
        'charged_amount' => null,
    ];
}

test('lead interface publishes only list creation and category selection without mutation permissions', function () {
    $membership = Membership::factory()->create();
    $lead = Lead::factory()->for($membership->account)->create();

    expect(Route::has('leads.index'))->toBeTrue();
    expect(Route::has('leads.store'))->toBeTrue();
    $this->actingAs($membership->user)->get('/leads')->assertOk();
    expect(Gate::forUser($membership->user)->allows('update', $lead))->toBeFalse();
    expect(Gate::forUser($membership->user)->allows('delete', $lead))->toBeFalse();
});

test('guests receive 401 without reading or creating leads', function () {
    $lead = Lead::factory()->create();

    $this->getJson(route('test.leads.index'))->assertUnauthorized();
    $this->getJson(route('test.leads.show', $lead))->assertUnauthorized();
    $this->postJson(route('test.leads.store'), leadFoundationPayload($lead->category))->assertUnauthorized();

    $this->assertDatabaseCount('leads', 1);
});

test('a missing or ambiguous account returns 404 for all lead operations', function (int $accounts) {
    $user = User::factory()->create();
    Membership::factory()->count($accounts)->for($user)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->getJson(route('test.leads.index'))->assertNotFound();
    $this->getJson(route('test.leads.show', $lead))->assertNotFound();
    $this->postJson(route('test.leads.store'), leadFoundationPayload($lead->category))->assertNotFound();

    $this->assertDatabaseCount('leads', 1);
})->with(['no account' => 0, 'two owned accounts' => 2]);

test('lead policies reuse account authorization and deny foreign records with 404', function (string $type) {
    $membership = Membership::factory()->for(Account::factory()->state(['type' => $type]))->create();
    $lead = Lead::factory()->for($membership->account)->create();
    $other = Membership::factory()->create();
    $gate = Gate::forUser($membership->user);

    expect($gate->allows('viewAny', [Lead::class, $membership->account]))->toBeTrue();
    expect($gate->allows('create', [Lead::class, $membership->account]))->toBeTrue();
    expect($gate->allows('view', [$lead, $membership->account]))->toBeTrue();
    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($other->user)->inspect($ability, [Lead::class, $membership->account])->status())->toBe(404);
    }
    expect(Gate::forUser($other->user)->inspect('view', [$lead, $membership->account])->status())->toBe(404);
    expect($gate->inspect('view', [$lead, $other->account])->status())->toBe(404);
})->with(['empresa', 'agencia']);

test('creation uses the current account and stores civil dates and normalized text', function (string $type) {
    $membership = Membership::factory()->for(Account::factory()->state(['type' => $type]))->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $response = $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), 'name' => '  Educação e Serviços  ', 'category_id' => (string) $category->id,
        'description' => "  Histórico do contato.\nSegunda linha.  ",
    ])->assertCreated()->assertJsonPath('lead.name', 'Educação e Serviços')
        ->assertJsonPath('lead.first_contact_date', '2020-01-10')
        ->assertJsonPath('lead.next_contact_date', '2020-01-15')
        ->assertJsonPath('lead.category.id', $category->id)
        ->assertJsonMissingPath('lead.account_id')->assertJsonMissingPath('lead.status');

    $this->assertDatabaseHas('leads', [
        'id' => $response->json('lead.id'), 'account_id' => $membership->account_id,
        'category_id' => $category->id, 'name' => 'Educação e Serviços',
        'description' => "Histórico do contato.\nSegunda linha.",
        'first_contact_date' => '2020-01-10', 'next_contact_date' => '2020-01-15',
    ]);
    $this->assertDatabaseCount('leads', 1);
})->with(['empresa', 'agencia']);

test('another account cannot view list count or categorize the created lead', function () {
    $first = Membership::factory()->create();
    $second = Membership::factory()->create();
    $category = LeadCategory::factory()->for($first->account)->create();
    $id = $this->actingAs($first->user)->postJson(route('test.leads.store'), leadFoundationPayload($category))
        ->assertCreated()->json('lead.id');

    $this->actingAs($second->user)->getJson(route('test.leads.show', $id))->assertNotFound();
    $this->getJson(route('test.leads.index'))->assertOk()
        ->assertJsonCount(0, 'leads.data')->assertJsonPath('leads.total', 0);
    $this->postJson(route('test.leads.store'), leadFoundationPayload($category))->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id' => 'Selecione uma categoria válida da sua conta.']);

    $this->assertDatabaseCount('leads', 1);
});

test('forged browser account selectors cannot change lead scope', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $ownLead = Lead::factory()->for($own->account)->create();
    Lead::factory()->count(2)->for($other->account)->create();

    $this->actingAs($own->user)->withSession(['account_id' => $other->account_id])
        ->withHeader('X-Account-ID', (string) $other->account_id)
        ->getJson(route('test.leads.index', ['account_id' => $other->account_id, 'sort' => 'name desc; drop table leads']))
        ->assertOk()->assertJsonCount(1, 'leads.data')->assertJsonPath('leads.total', 1)
        ->assertJsonPath('leads.data.0.id', $ownLead->id);
});

test('unexpected authority and business fields are rejected before persistence', function (string $field) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), $field => 999999,
    ])->assertUnprocessable()->assertJsonValidationErrors(['form' => 'Envie apenas os dados permitidos do lead.']);

    $this->assertDatabaseEmpty('leads');
})->with(['account_id', 'user_id', 'owner_id', 'role', 'permissions', 'agency_id', 'id', 'created_at', 'updated_at', 'status', 'sold', 'campaign_id', 'unexpected']);

test('account ownership cannot be replaced through mass assignment', function () {
    $account = Account::factory()->create();
    $other = Account::factory()->create();
    $category = LeadCategory::factory()->for($account)->create();

    $lead = $account->leads()->create([
        ...leadFoundationPayload($category), 'account_id' => $other->id, 'owner_id' => 123,
    ]);

    expect($lead->account_id)->toBe($account->id);
    expect($lead->account->is($account))->toBeTrue();
    expect($lead->getAttributes())->not->toHaveKey('owner_id');
    $this->assertDatabaseMissing('leads', ['account_id' => $other->id]);
});

test('explicit authorization denial protects list show and creation', function () {
    $membership = Membership::factory()->create();
    $lead = Lead::factory()->for($membership->account)->create();
    Gate::before(fn () => Response::denyAsNotFound());

    $this->actingAs($membership->user)->getJson(route('test.leads.index'))->assertNotFound();
    $this->getJson(route('test.leads.show', $lead))->assertNotFound();
    $this->postJson(route('test.leads.store'), leadFoundationPayload($lead->category))->assertNotFound();
    $this->assertDatabaseCount('leads', 1);
});

test('revoked membership blocks subsequent lead requests', function () {
    $membership = Membership::factory()->create();
    $lead = Lead::factory()->for($membership->account)->create();
    $this->actingAs($membership->user)->getJson(route('test.leads.index'))->assertOk();
    $membership->delete();

    $this->getJson(route('test.leads.show', $lead))->assertNotFound();
    $this->postJson(route('test.leads.store'), leadFoundationPayload($lead->category))->assertNotFound();
    $this->assertDatabaseCount('leads', 1);
});

test('missing fields return Portuguese errors and create no lead', function () {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name' => 'Informe o nome do lead.', 'category_id' => 'Selecione uma categoria para o lead.',
            'first_contact_date' => 'Informe a data do primeiro contato.',
        ]);

    $this->assertDatabaseEmpty('leads');
});

test('optional fields can be omitted without converting absence to zero', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        'category_id' => $category->id, 'name' => 'Sem orçamento', 'first_contact_date' => '2000-02-29',
    ])->assertCreated()->assertJsonStructure(['lead' => ['description', 'next_contact_date', 'potential_amount', 'charged_amount']])
        ->assertJsonPath('lead.description', null)->assertJsonPath('lead.next_contact_date', null)
        ->assertJsonPath('lead.potential_amount', null)->assertJsonPath('lead.charged_amount', null);

    $this->assertDatabaseHas('leads', ['description' => null, 'next_contact_date' => null, 'potential_amount' => null, 'charged_amount' => null]);
});

test('valid Unicode text is accepted at the name and description limits', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $name = str_repeat('á', 120);
    $description = str_repeat('á', 5000);

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), 'name' => $name, 'description' => $description,
    ])->assertCreated()->assertJsonPath('lead.name', $name)->assertJsonPath('lead.description', $description);
});

test('malformed lead fields are rejected with Portuguese messages', function (string $field, mixed $value, string $message) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), $field => $value,
    ], options: JSON_PRESERVE_ZERO_FRACTION)->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);

    $this->assertDatabaseEmpty('leads');
})->with([
    'blank name' => ['name', " \u{00a0} ", 'Informe o nome do lead.'],
    'name array' => ['name', ['x'], 'Informe um nome válido para o lead.'],
    'name object' => ['name', (object) ['name' => 'x'], 'Informe um nome válido para o lead.'],
    'name number' => ['name', 123, 'Informe um nome válido para o lead.'],
    'long name' => ['name', str_repeat('á', 121), 'O nome do lead deve ter no máximo 120 caracteres.'],
    'control in name' => ['name', "nome\x01", 'O nome do lead contém caracteres inválidos.'],
    'description array' => ['description', ['x'], 'Informe uma descrição válida para o lead.'],
    'description object' => ['description', (object) ['note' => 'x'], 'Informe uma descrição válida para o lead.'],
    'long description' => ['description', str_repeat('á', 5001), 'A descrição deve ter no máximo 5000 caracteres.'],
    'null byte in description' => ['description', "nota\x00interna", 'A descrição contém caracteres inválidos.'],
    'category array' => ['category_id', [1], 'Selecione uma categoria válida da sua conta.'],
    'category object' => ['category_id', (object) ['id' => 1], 'Selecione uma categoria válida da sua conta.'],
    'category boolean' => ['category_id', true, 'Selecione uma categoria válida da sua conta.'],
    'category float' => ['category_id', 1.0, 'Selecione uma categoria válida da sua conta.'],
    'nonexistent category' => ['category_id', 999999, 'Selecione uma categoria válida da sua conta.'],
    'invalid first date' => ['first_contact_date', '2025-02-29', 'Informe uma data válida para o primeiro contato no formato AAAA-MM-DD.'],
    'localized first date' => ['first_contact_date', '10/01/2020', 'Informe uma data válida para o primeiro contato no formato AAAA-MM-DD.'],
    'first timestamp' => ['first_contact_date', '2020-01-10T00:00:00Z', 'Informe uma data válida para o primeiro contato no formato AAAA-MM-DD.'],
    'first date array' => ['first_contact_date', ['2020-01-10'], 'Informe a data do primeiro contato no formato AAAA-MM-DD.'],
    'first date object' => ['first_contact_date', (object) ['date' => '2020-01-10'], 'Informe a data do primeiro contato no formato AAAA-MM-DD.'],
    'year zero' => ['first_contact_date', '0000-01-01', 'A data do primeiro contato deve ser igual ou posterior a 0001-01-01.'],
    'invalid next date' => ['next_contact_date', '2020-02-30', 'Informe uma data válida para o próximo contato no formato AAAA-MM-DD.'],
    'next date array' => ['next_contact_date', ['2020-01-15'], 'Informe a data do próximo contato no formato AAAA-MM-DD.'],
    'next date object' => ['next_contact_date', (object) ['date' => '2020-01-15'], 'Informe a data do próximo contato no formato AAAA-MM-DD.'],
    'next before first' => ['next_contact_date', '2020-01-09', 'A data do próximo contato não pode ser anterior à data do primeiro contato.'],
]);

test('historical contacts and a next contact on the first date are accepted', function (?string $nextDate) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), 'next_contact_date' => $nextDate,
    ])->assertCreated()->assertJsonPath('lead.next_contact_date', $nextDate);
})->with(['unscheduled' => null, 'same day' => '2020-01-10', 'overdue' => '2020-01-15']);

test('monetary values persist as two decimal strings while null remains null', function (mixed $value, ?string $expected, string $field) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), $field => $value,
    ])->assertCreated()->assertJsonPath('lead.'.$field, $expected)->assertJsonMissingPath('lead.status');

    expect($membership->account->leads()->sole()->{$field})->toBe($expected);
    $this->assertDatabaseCount('leads', 1);
})->with([
    'null' => [null, null], 'integer zero' => [0, '0.00'], 'string zero' => ['0.00', '0.00'],
    'fraction' => ['0.01', '0.01'], 'integer' => [12, '12.00'], 'single decimal' => ['19.9', '19.90'],
    'maximum decimal' => ['9999999999.99', '9999999999.99'],
])->with(['potential_amount', 'charged_amount']);

test('malformed negative or out of range money cannot be saved', function (mixed $value, string $rule, string $field) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $label = $field === 'potential_amount' ? 'potencial' : 'cobrado';
    $message = match ($rule) {
        'min' => "O valor {$label} não pode ser negativo.",
        'max' => "O valor {$label} não pode exceder 9999999999.99.",
        'decimal' => "O valor {$label} deve ter no máximo duas casas decimais.",
        default => "Informe um valor {$label} numérico válido.",
    };

    $this->actingAs($membership->user)->postJson(route('test.leads.store'), [
        ...leadFoundationPayload($category), $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);

    $this->assertDatabaseEmpty('leads');
})->with([
    'negative' => ['-0.01', 'min'], 'overflow' => ['10000000000.00', 'max'],
    'excess precision' => ['1.005', 'decimal'], 'scientific notation' => ['1e2', 'decimal'],
    'localized value' => ['1.234,56', 'numeric'], 'boolean' => [true, 'numeric'],
    'array' => [['1.00'], 'numeric'], 'object' => [(object) ['amount' => '1.00'], 'numeric'],
])->with(['potential_amount', 'charged_amount']);

test('lead pagination is deterministic scoped and omits unnecessary fields', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $records = Lead::factory()->count(14)->for($membership->account)->for($category, 'category')
        ->create(['description' => 'Notas privadas extensas']);
    $other = Account::factory()->create();
    Lead::factory()->count(3)->for($other)->create();

    $response = $this->actingAs($membership->user)->getJson(route('test.leads.index'))->assertOk()
        ->assertJsonCount(12, 'leads.data')->assertJsonPath('leads.total', 14)
        ->assertJsonPath('leads.currentPage', 1)->assertJsonPath('leads.hasMorePages', true)
        ->assertJsonPath('leads.data.0.id', $records->last()->id)
        ->assertJsonPath('leads.data.0.category.name', $category->name)
        ->assertJsonMissingPath('leads.data.0.account_id')->assertJsonPath('leads.data.0.description', 'Notas privadas extensas')
        ->assertJsonMissingPath('leads.data.0.category.account_id')->assertJsonMissingPath('leads.data.0.created_at');
    expect(array_column($response->json('leads.data'), 'id'))->toBe($records->reverse()->take(12)->pluck('id')->all());
    $this->getJson(route('test.leads.index', ['page' => 2]))->assertOk()->assertJsonCount(2, 'leads.data')
        ->assertJsonPath('leads.total', 14)->assertJsonPath('leads.currentPage', 2)
        ->assertJsonPath('leads.hasMorePages', false)->assertJsonPath('leads.data.0.id', $records[1]->id);
});

test('lead listing uses database count and one eager category query without N plus one', function () {
    $membership = Membership::factory()->create();
    Lead::factory()->count(12)->for($membership->account)->create();
    DB::enableQueryLog();

    try {
        $this->actingAs($membership->user)->getJson(route('test.leads.index'))->assertOk()->assertJsonPath('leads.total', 12);

        $queries = collect(DB::getQueryLog());
        $leadQueries = $queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "leads"'));
        expect($leadQueries)->toHaveCount(2);
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "lead_categories"')))->toHaveCount(1);
        expect($leadQueries->filter(fn (array $query): bool => str_contains($query['query'], 'select count(*)')))->toHaveCount(1);
        foreach ($leadQueries as $query) {
            expect($query['query'])->toContain('"leads"."account_id" = ?');
            expect($query['bindings'])->toContain($membership->account_id);
        }
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('lead writes retain the existing csrf protection', function (?string $token, int $status) {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->withSession(['_token' => 'lead-browser-token'])
        ->withHeader('Sec-Fetch-Site', 'cross-site')->postJson(route('test.leads.store'), [
            ...leadFoundationPayload($category), '_token' => $token,
        ])->assertStatus($status);

    $this->assertDatabaseCount('leads', $status === 201 ? 1 : 0);
})->with(['missing' => [null, 419], 'foreign token' => ['other-browser-token', 419], 'valid' => ['lead-browser-token', 201]]);
