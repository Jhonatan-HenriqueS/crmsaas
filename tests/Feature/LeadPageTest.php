<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open leads or its category selector', function () {
    $this->get(route('leads.index'))->assertRedirect(route('login'));
    $this->getJson(route('leads.category-options'))->assertUnauthorized();
    $this->post(route('leads.store'), [])->assertRedirect(route('login'));
});

test('the leads page returns only current account records with a real paginated total', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $leads = Lead::factory()->count(13)->for($own->account)->for($category, 'category')->create([
        'description' => 'Descrição completa para os cards.', 'potential_amount' => '1500.10', 'charged_amount' => '0.00',
    ]);
    Lead::factory()->count(2)->for($other->account)->create();

    $this->actingAs($own->user)->withSession(['account_id' => $other->account_id])
        ->withHeader('X-Account-ID', (string) $other->account_id)
        ->get(route('leads.index', ['account_id' => $other->account_id]))
        ->assertInertia(fn (Assert $page) => $page->component('leads/index')
            ->where('leads.total', 13)->where('leads.currentPage', 1)->where('leads.hasMorePages', true)
            ->has('leads.data', 12)->where('leads.data.0.id', $leads->last()->id)
            ->where('leads.data.0.description', 'Descrição completa para os cards.')
            ->where('leads.data.0.potential_amount', '1500.10')->where('leads.data.0.charged_amount', '0.00')
            ->where('leads.data.0.category.id', $category->id)
            ->missing('leads.data.0.account_id')->missing('leads.data.0.created_at')
            ->missing('leads.data.0.category.account_id')->missing('categories'));
    $this->get(route('leads.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('leads.total', 13)->has('leads.data', 1)
            ->where('leads.data.0.id', $leads->first()->id)->where('leads.hasMorePages', false));
});

test('an empty account has an empty real leads page', function () {
    $own = Membership::factory()->create();
    Lead::factory()->create();

    $this->actingAs($own->user)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page->component('leads/index')->where('leads.total', 0)->has('leads.data', 0));
});

test('browser creation redirects with the existing success toast and survives a new visit', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $description = str_repeat('Histórico completo. ', 30);

    $this->actingAs($own->user)->post(route('leads.store'), [
        'name' => 'Oportunidade real', 'description' => $description, 'category_id' => $category->id,
        'first_contact_date' => '2026-10-10', 'next_contact_date' => null,
        'potential_amount' => '1500.10', 'charged_amount' => '0.00',
    ])->assertRedirect(route('leads.index'))->assertInertiaFlash('toast', [
        'type' => 'success', 'operation' => 'created', 'message' => 'Lead criado com sucesso!',
    ]);

    $this->assertDatabaseHas('leads', ['account_id' => $own->account_id, 'name' => 'Oportunidade real', 'description' => trim($description)]);
    $this->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page->where('leads.total', 1)
        ->where('leads.data.0.name', 'Oportunidade real')->where('leads.data.0.next_contact_date', null)
        ->where('leads.data.0.charged_amount', '0.00'));
});

test('browser validation keeps errors in Portuguese and rejects foreign ownership fields', function () {
    $own = Membership::factory()->create();
    $foreign = LeadCategory::factory()->create();

    $this->actingAs($own->user)->from(route('leads.index'))->post(route('leads.store'), [
        'name' => 'Preservar formulário', 'category_id' => $foreign->id,
        'first_contact_date' => '2026-10-10', 'account_id' => $foreign->account_id,
    ])->assertRedirect(route('leads.index'))->assertSessionHasErrors([
        'category_id' => 'Selecione uma categoria válida da sua conta.',
        'form' => 'Envie apenas os dados permitidos do lead.',
    ]);
    $this->assertDatabaseEmpty('leads');
});

test('category options use bounded cursor pages and expose every own category only', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $categories = LeadCategory::factory()->count(32)->for($own->account)->create();
    LeadCategory::factory()->count(2)->for($other->account)->create();

    $response = $this->actingAs($own->user)->getJson(route('leads.category-options', ['account_id' => $other->account_id]))
        ->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('data.0.id', $categories->last()->id)
        ->assertJsonMissingPath('data.0.account_id')->assertJsonMissingPath('data.0.created_at');
    expect(array_keys($response->json('data.0')))->toBe(['id', 'name', 'color']);
    $this->getJson(route('leads.category-options', ['cursor' => $response->json('nextCursor')]))
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.id', $categories->first()->id)
        ->assertJsonPath('nextCursor', null);
});

test('the category selector enforces policy authorization and current account resolution', function () {
    $own = Membership::factory()->create();
    Gate::before(fn () => Response::denyAsNotFound());

    $this->actingAs($own->user)->getJson(route('leads.category-options'))->assertNotFound();
    $this->get(route('leads.index'))->assertNotFound();
});

test('invalid category cursor types are rejected without loading foreign data', function (mixed $cursor) {
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->getJson(route('leads.category-options', ['cursor' => $cursor]))
        ->assertUnprocessable()->assertJsonValidationErrors('cursor');
})->with(['array' => [['id' => 1]], 'oversized' => str_repeat('x', 1025), 'invalid encoding' => 'broken', 'missing id' => base64_encode('{"_pointsToNextItems":true}'), 'array direction' => base64_encode('{"id":1,"_pointsToNextItems":[]}')]);

test('production lead creation retains csrf protection', function () {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();

    $this->actingAs($own->user)->post(route('leads.store'), [
        'name' => 'Sem CSRF', 'category_id' => $category->id, 'first_contact_date' => '2026-10-10',
    ])->assertStatus(419);
    $this->assertDatabaseEmpty('leads');
});

test('lead edit delete and completion are not published', function (string $method) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();

    $this->actingAs($own->user)->json($method, '/leads/'.$lead->id, ['name' => 'Alterado'])->assertNotFound();
    $this->assertModelExists($lead);
    $this->assertDatabaseMissing('leads', ['name' => 'Alterado']);
})->with(['PATCH', 'DELETE', 'POST']);

test('the Inertia list uses a count a bounded page and a single category query', function () {
    $own = Membership::factory()->create();
    Lead::factory()->count(12)->for($own->account)->create();
    DB::enableQueryLog();

    try {
        $this->actingAs($own->user)->get(route('leads.index'))->assertOk();
        $queries = collect(DB::getQueryLog());
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "leads"')))->toHaveCount(2);
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "lead_categories"')))->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});
