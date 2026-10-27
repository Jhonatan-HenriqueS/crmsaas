<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function leadMutationPayload(Lead $lead): array
{
    return $lead->only(['name', 'description', 'category_id', 'first_contact_date', 'next_contact_date', 'potential_amount', 'charged_amount']);
}

test('updates persist every editable field without touching creation or unrelated records', function () {
    $this->freezeTime();
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['created_at' => '2020-01-01 12:00:00']);
    $target = LeadCategory::factory()->for($own->account)->create();
    $other = Lead::factory()->create();
    $otherBefore = $other->fresh()->getRawOriginal();
    $createdAt = $lead->getRawOriginal('created_at');
    $payload = ['name' => '  Empresa atualizada  ', 'description' => str_repeat("Informação completa\n", 20),
        'category_id' => $target->id, 'first_contact_date' => '2024-02-29', 'next_contact_date' => '2024-03-01',
        'potential_amount' => '1500.50', 'charged_amount' => '0.00'];

    $this->actingAs($own->user)->patch(route('leads.update', $lead), $payload)
        ->assertRedirect(route('leads.index', ['page' => 1]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'operation' => 'updated', 'message' => 'Lead atualizado com sucesso!']);

    $this->assertDatabaseHas('leads', [...$payload, 'name' => 'Empresa atualizada', 'description' => trim($payload['description']),
        'account_id' => $own->account_id, 'id' => $lead->id, 'created_at' => $createdAt]);
    expect($other->fresh()->getRawOriginal())->toBe($otherBefore);
    $this->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('leads.data.0.name', 'Empresa atualizada')->where('leads.data.0.description', trim($payload['description']))
        ->where('leads.data.0.category.id', $target->id)->where('leads.data.0.category.name', $target->name)
        ->where('leads.data.0.first_contact_date', '2024-02-29')->where('leads.data.0.next_contact_date', '2024-03-01')
        ->where('leads.data.0.potential_amount', '1500.50')->where('leads.data.0.charged_amount', '0.00')
        ->missing('leads.data.0.account_id')->missing('leads.data.0.created_at'));
});

test('editing can clear all optional values while preserving a monetary zero', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['description' => 'Contexto', 'next_contact_date' => '2030-01-01', 'charged_amount' => '23.50']);
    $payload = ['name' => $lead->name, 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-01',
        'description' => '   ', 'next_contact_date' => null, 'potential_amount' => null, 'charged_amount' => '0'];

    $this->actingAs($own->user)->patch(route('leads.update', $lead), $payload)->assertRedirect();

    $saved = $lead->fresh();
    expect($saved->description)->toBeNull();
    expect($saved->next_contact_date)->toBeNull();
    expect($saved->potential_amount)->toBeNull();
    expect($saved->charged_amount)->toBe('0.00');
});

test('saving identical values preserves timestamps and emits no update notification', function () {
    $this->freezeTime();
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['potential_amount' => '1500.50', 'charged_amount' => '0.00']);
    $before = $lead->fresh()->getRawOriginal();
    $payload = [...leadMutationPayload($lead), 'first_contact_date' => $lead->first_contact_date->toDateString(),
        'next_contact_date' => $lead->next_contact_date?->toDateString()];
    $this->travel(1)->hours();

    $this->actingAs($own->user)->patch(route('leads.update', $lead), $payload)
        ->assertRedirect()->assertInertiaFlashMissing('toast');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
});

test('invalid updates return 422 and preserve the complete stored lead', function (string $field, mixed $value) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    $payload = ['name' => 'Mudança rejeitada', 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-10',
        'next_contact_date' => '2020-01-15', $field => $value];

    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field)->assertInertiaFlashMissing('toast');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with([
    'empty name' => ['name', ''], 'array name' => ['name', []], 'long name' => ['name', str_repeat('a', 121)],
    'control character' => ['name', "Empresa\0Inválida"], 'array description' => ['description', []],
    'long description' => ['description', str_repeat('a', 5001)], 'array category' => ['category_id', []],
    'missing category' => ['category_id', 999999], 'invalid date' => ['first_contact_date', '2026-02-30'],
    'date timestamp' => ['first_contact_date', '2020-01-10T00:00:00Z'], 'next before first' => ['next_contact_date', '2020-01-09'],
    'invalid next date' => ['next_contact_date', '2020-02-30'], 'negative potential' => ['potential_amount', '-0.01'],
    'fractional potential' => ['potential_amount', '1.001'], 'large potential' => ['potential_amount', '10000000000'],
    'array money' => ['charged_amount', []], 'negative charged' => ['charged_amount', '-1'],
    'fractional charged' => ['charged_amount', '1.001'], 'large charged' => ['charged_amount', '10000000000'],
]);

test('an empty update cannot erase required lead information', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();

    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), [])
        ->assertUnprocessable()->assertJsonValidationErrors([
            'name' => 'Informe o nome do lead.',
            'category_id' => 'Selecione uma categoria para o lead.',
            'first_contact_date' => 'Informe a data do primeiro contato.',
        ])->assertInertiaFlashMissing('toast');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
});

test('invalid pagination context cannot mutate a lead', function (string $method, mixed $page) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    $payload = $method === 'PATCH'
        ? ['name' => 'Inválida', 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-01']
        : [];

    $this->actingAs($own->user)->json($method, route('leads.update', $lead), [...$payload, 'page' => $page])
        ->assertUnprocessable()->assertJsonValidationErrors('page')->assertInertiaFlashMissing('toast');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with([['PATCH', -1], ['PATCH', []], ['DELETE', -1], ['DELETE', []]]);

test('unexpected fields cannot change lead ownership timestamps or commercial status', function (string $field) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();

    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), [
        'name' => 'Adulterada', 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-01', $field => 123,
    ])->assertUnprocessable()->assertJsonValidationErrors('form')->assertInertiaFlashMissing('toast');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with(['account_id', 'owner_id', 'user_id', 'roles', 'permissions', 'id', 'created_at', 'updated_at', 'status', 'sold']);

test('updates cannot associate a foreign category', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $foreign = LeadCategory::factory()->create();
    $before = $lead->fresh()->getRawOriginal();

    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), [
        'name' => 'Proibido', 'category_id' => $foreign->id, 'first_contact_date' => '2020-01-01',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'category_id' => 'Selecione uma categoria válida da sua conta.',
    ]);

    expect($lead->fresh()->getRawOriginal())->toBe($before);
});

test('foreign leads remain private for mutations even with invalid input', function (string $method) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->create();
    $before = $lead->fresh()->getRawOriginal();

    $this->actingAs($own->user)->withSession(['account_id' => $lead->account_id])->withHeader('X-Account-ID', (string) $lead->account_id)
        ->json($method, route('leads.update', $lead), ['name' => [], 'account_id' => $own->account_id])
        ->assertNotFound()->assertInertiaFlashMissing('toast');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with(['PATCH', 'DELETE']);

test('guests cannot mutate leads', function (string $method) {
    $lead = Lead::factory()->create();

    $this->call($method, route('leads.update', $lead))->assertRedirect(route('login'));
    $this->json($method, route('leads.update', $lead))->assertUnauthorized();

    $this->assertModelExists($lead);
})->with(['PATCH', 'DELETE']);

test('mutations require one owned current account', function (string $method, int $accounts) {
    $user = User::factory()->create();
    Membership::factory()->count($accounts)->for($user)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->json($method, route('leads.update', $lead))->assertNotFound();
    $this->assertModelExists($lead);
})->with([['PATCH', 0], ['PATCH', 2], ['DELETE', 0], ['DELETE', 2]]);

test('the policy permits only an owner with the matching account and lead', function (string $ability) {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $gate = Gate::forUser($own->user);

    expect($gate->allows($ability, [$lead, $own->account]))->toBeTrue();
    expect($gate->inspect($ability, [$lead, $other->account])->status())->toBe(404);
    expect(Gate::forUser($other->user)->inspect($ability, [$lead, $own->account])->status())->toBe(404);
})->with(['update', 'delete']);

test('mutation endpoints enforce explicit policy denial', function (string $method) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    Gate::before(fn () => Response::denyAsNotFound());

    $this->actingAs($own->user)->json($method, route('leads.update', $lead))->assertNotFound();
    $this->assertModelExists($lead);
})->with(['PATCH', 'DELETE']);

test('mutations retain production csrf protection', function (string $method, ?string $token, int $status) {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $payload = $method === 'PATCH' ? ['name' => 'Permitido', 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-01'] : [];

    $this->actingAs($own->user)->withSession(['_token' => 'lead-mutation-csrf'])->withHeader('Sec-Fetch-Site', 'cross-site')
        ->call($method, route('leads.update', $lead), [...$payload, '_token' => $token])->assertStatus($status);

    if ($status === 419) {
        $this->assertModelExists($lead);
        $this->assertDatabaseMissing('leads', ['name' => 'Permitido']);
    }
})->with([['PATCH', null, 419], ['PATCH', 'wrong-session', 419], ['PATCH', 'lead-mutation-csrf', 302],
    ['DELETE', null, 419], ['DELETE', 'wrong-session', 419], ['DELETE', 'lead-mutation-csrf', 302]]);

test('deletion removes only the chosen lead and keeps its category and other accounts', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $sibling = Lead::factory()->for($own->account)->for($lead->category, 'category')->create();
    $foreign = Lead::factory()->create();
    $before = $foreign->fresh()->getRawOriginal();

    $this->actingAs($own->user)->delete(route('leads.destroy', $lead))
        ->assertRedirect(route('leads.index', ['page' => 1]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'operation' => 'deleted', 'message' => 'Lead excluído com sucesso!']);

    $this->assertModelMissing($lead);
    $this->assertModelExists($sibling);
    $this->assertModelExists($lead->category);
    expect($foreign->fresh()->getRawOriginal())->toBe($before);
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    $this->deleteJson(route('leads.destroy', $lead))->assertNotFound()->assertInertiaFlashMissing('toast');
});

test('deleting the only lead keeps its empty category', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();

    $this->actingAs($own->user)->delete(route('leads.destroy', $lead))->assertRedirect();

    $this->assertModelExists($lead->category);
    $this->assertDatabaseEmpty('leads');
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('totalLeads', 0)->where('categories.data.0.leads_count', 0)->where('categories.data.0.percentage', 0));
});

test('deletion rejects unexpected fields without removing the record', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();

    $this->actingAs($own->user)->deleteJson(route('leads.destroy', $lead), ['account_id' => $own->account_id])
        ->assertUnprocessable()->assertJsonValidationErrors('form');
    $this->assertModelExists($lead);
});

test('missing and malformed lead ids cannot be mutated', function (string $method, string $id) {
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->json($method, '/leads/'.$id)->assertNotFound();
})->with([['PATCH', '999999'], ['DELETE', '999999'], ['PATCH', 'invalid'], ['DELETE', 'invalid']]);

test('database errors roll back lead mutations without emitting success', function (string $method) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    Exceptions::fake();
    $listener = fn () => DB::statement('select missing_lead_mutation_test_function()');
    if ($method === 'PATCH') {
        Lead::updated($listener);
    } else {
        Lead::deleted($listener);
    }

    try {
        $this->actingAs($own->user)->json($method, route('leads.update', $lead), $method === 'PATCH'
            ? ['name' => 'Deve reverter', 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-01'] : [])
            ->assertServerError()->assertInertiaFlashMissing('toast');
        expect($lead->fresh()->getRawOriginal())->toBe($before);
        $this->assertModelExists($lead->category);
        Exceptions::assertReported(QueryException::class);
    } finally {
        Lead::flushEventListeners();
    }
})->with(['PATCH', 'DELETE']);

test('vetoed mutations return 409 without claiming success', function (string $method) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    if ($method === 'PATCH') {
        Lead::updating(fn () => false);
    } else {
        Lead::deleting(fn () => false);
    }

    try {
        $this->actingAs($own->user)->json($method, route('leads.update', $lead), $method === 'PATCH'
            ? ['name' => 'Rejeitada', 'category_id' => $lead->category_id, 'first_contact_date' => '2020-01-01'] : [])
            ->assertConflict()->assertInertiaFlashMissing('toast');
        expect($lead->fresh()->getRawOriginal())->toBe($before);
    } finally {
        Lead::flushEventListeners();
    }
})->with(['PATCH', 'DELETE']);

test('mutation redirects retain pagination and an emptied last page returns to the previous page', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $leads = Lead::factory()->count(13)->for($own->account)->for($category, 'category')->create();
    $this->actingAs($own->user)->patch(route('leads.update', [$leads->first(), 'page' => 2]), [
        'name' => 'Página dois', 'category_id' => $category->id, 'first_contact_date' => '2020-01-01',
    ])->assertRedirect(route('leads.index', ['page' => 2]));

    $this->delete(route('leads.destroy', [$leads->first(), 'page' => 2]))->assertRedirect(route('leads.index', ['page' => 2]));
    $this->get(route('leads.index', ['page' => 2]))->assertRedirect(route('leads.index', ['page' => 1]));
    $this->get(route('leads.index', ['page' => 1]))->assertInertia(fn (Assert $page) => $page
        ->has('leads.data', 12)->where('leads.total', 12)->where('leads.currentPage', 1));
});
