<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('all and category links use real account totals and filter before pagination', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $another = LeadCategory::factory()->for($own->account)->create();
    $matching = Lead::factory()->count(27)->for($own->account)->for($category, 'category')->create();
    Lead::factory()->count(2)->for($own->account)->for($another, 'category')->create();
    Lead::factory()->count(20)->for($other->account)->create();
    $this->actingAs($own->user);

    $this->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('selectedCategory', null)->where('leads.total', 29)->has('leads.data', 12));
    $url = route('leads.index', ['category' => $category->id]);
    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('selectedCategory.id', $category->id)->where('selectedCategory.name', $category->name)
        ->where('leads.total', 27)->where('leads.currentPage', 1)->where('leads.hasMorePages', true)
        ->has('leads.data', 12)->where('leads.data.0.id', $matching->last()->id)
        ->missing('selectedCategory.account_id')->missing('selectedCategory.system_key')
        ->missing('leads.data.0.account_id')->missing('leads.data.0.category.account_id'));
    $this->getJson(route('leads.index', ['category' => $category->id, 'page' => 3]))
        ->assertOk()->assertJsonPath('leads.total', 27)->assertJsonPath('leads.currentPage', 3)
        ->assertJsonCount(3, 'leads.data')->assertJsonPath('leads.hasMorePages', false)
        ->assertJsonPath('leads.data.2.id', $matching->first()->id);
    // A new request to the same URL represents reload or a direct/history visit.
    $this->getJson($url)->assertOk()->assertJsonPath('selectedCategory.id', $category->id)
        ->assertJsonPath('leads.currentPage', 1)->assertJsonPath('leads.total', 27);
    $this->getJson(route('leads.index', ['category' => $another->id]))
        ->assertOk()->assertJsonPath('leads.currentPage', 1)->assertJsonPath('leads.total', 2);
    $this->getJson(route('leads.index'))->assertOk()->assertJsonPath('selectedCategory', null)->assertJsonPath('leads.total', 29);
});

test('an empty category is valid and a renamed recolored permanent category remains selectable', function () {
    $own = Membership::factory()->create();
    $empty = LeadCategory::factory()->for($own->account)->create();
    $completed = $own->account->leadCategories()->where('system_key', LeadCategory::SALE_COMPLETED)->sole();
    $completed->update(['name' => 'Entrega recebida', 'color' => '#123abc']);
    $lead = Lead::factory()->for($own->account)->for($completed, 'category')->create();
    $this->actingAs($own->user);

    $this->getJson(route('leads.index', ['category' => $empty->id]))->assertOk()
        ->assertJsonPath('selectedCategory.id', $empty->id)->assertJsonPath('leads.total', 0)->assertJsonCount(0, 'leads.data');
    $this->getJson(route('leads.index', ['category' => $completed->id]))->assertOk()
        ->assertJsonPath('selectedCategory.name', 'Entrega recebida')->assertJsonPath('selectedCategory.color', '#123abc')
        ->assertJsonPath('selectedCategory.is_sale_completed', true)->assertJsonPath('leads.total', 1)
        ->assertJsonPath('leads.data.0.id', $lead->id);
});

test('foreign and absent category ids both return 404', function (bool $foreign) {
    $own = Membership::factory()->create();
    $other = LeadCategory::factory()->create();
    Lead::factory()->for($other->account)->for($other, 'category')->create(['name' => 'Private foreign lead']);

    $this->actingAs($own->user)->getJson(route('leads.index', ['category' => $foreign ? $other->id : 2147483647]))
        ->assertNotFound()->assertDontSee('Private foreign lead')->assertJsonMissingPath('leads');
})->with(['foreign' => true, 'absent' => false]);

test('structurally invalid category parameters return a controlled Portuguese validation error', function (mixed $category) {
    $own = Membership::factory()->create();
    $this->actingAs($own->user)->getJson(route('leads.index', ['category' => $category]))
        ->assertUnprocessable()->assertJsonValidationErrors(['category' => 'Selecione uma categoria válida para filtrar os leads.']);
})->with([
    'empty' => '', 'zero' => 0, 'negative' => -1, 'fractional' => '1.5',
    'name' => 'Finalizado', 'multiple ids' => '1,2', 'list' => [[1, 2]],
    'object' => [['id' => 1]], 'SQL input' => '1 OR 1=1', 'overflow' => str_repeat('9', 50),
]);

test('forged account authority cannot change a category filter scope', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    Lead::factory()->for($own->account)->for($category, 'category')->create();
    Lead::factory()->count(2)->for($other->account)->create();

    $this->actingAs($own->user)->withSession(['account_id' => $other->account_id])
        ->withHeader('X-Account-ID', (string) $other->account_id)
        ->getJson(route('leads.index', ['category' => $category->id, 'account_id' => $other->account_id]))
        ->assertOk()->assertJsonPath('leads.total', 1)->assertJsonPath('selectedCategory.id', $category->id)
        ->assertJsonMissingPath('leads.data.0.account_id')->assertJsonMissingPath('selectedCategory.account_id');
});

test('creation while filtered preserves the filter and only matching leads appear', function (bool $matches) {
    $own = Membership::factory()->create();
    $filter = LeadCategory::factory()->for($own->account)->create();
    $target = $matches ? $filter : LeadCategory::factory()->for($own->account)->create();
    $this->actingAs($own->user)->post(route('leads.store', ['category' => $filter->id]), [
        'name' => 'New filtered opportunity', 'category_id' => $target->id, 'first_contact_date' => '2026-10-01',
    ])->assertRedirect(route('leads.index', ['category' => $filter->id]))
        ->assertInertiaFlash('toast.message', 'Lead criado com sucesso!');
    $this->assertDatabaseHas('leads', ['account_id' => $own->account_id, 'category_id' => $target->id, 'name' => 'New filtered opportunity']);
    $this->getJson(route('leads.index', ['category' => $filter->id]))->assertOk()
        ->assertJsonPath('leads.total', $matches ? 1 : 0);
})->with(['matching' => true, 'different' => false]);

test('moving a lead to another category updates both filtered sets without changing the global total', function () {
    $own = Membership::factory()->create();
    $source = LeadCategory::factory()->for($own->account)->create();
    $target = LeadCategory::factory()->for($own->account)->create();
    $lead = Lead::factory()->for($own->account)->for($source, 'category')->create();
    $this->actingAs($own->user)->patch(route('leads.update', ['lead' => $lead->id, 'category' => $source->id, 'page' => 1]), [
        'name' => $lead->name, 'first_contact_date' => $lead->first_contact_date->toDateString(), 'category_id' => $target->id,
    ])->assertRedirect(route('leads.index', ['page' => 1, 'category' => $source->id]));
    $this->getJson(route('leads.index', ['category' => $source->id]))->assertOk()->assertJsonPath('leads.total', 0);
    $this->getJson(route('leads.index', ['category' => $target->id]))->assertOk()->assertJsonPath('leads.total', 1);
    $this->getJson(route('leads.index'))->assertOk()->assertJsonPath('leads.total', 1);
    expect($lead->fresh()->category_id)->toBe($target->id);
});

test('deleting or moving the final lead on a filtered page preserves its category on the corrected page', function (string $operation) {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    $target = LeadCategory::factory()->for($own->account)->create();
    $leads = Lead::factory()->count(13)->for($own->account)->for($category, 'category')->create();
    $lead = $leads->first();
    $parameters = ['lead' => $lead->id, 'category' => $category->id, 'page' => 2];
    $this->actingAs($own->user);
    if ($operation === 'delete') {
        $this->delete(route('leads.destroy', $parameters))->assertRedirect(route('leads.index', ['page' => 2, 'category' => $category->id]));
        $this->assertModelMissing($lead);
    } else {
        $this->patch(route('leads.update', $parameters), ['name' => $lead->name, 'category_id' => $target->id,
            'first_contact_date' => $lead->first_contact_date->toDateString()])->assertRedirect();
        expect($lead->fresh()->category_id)->toBe($target->id);
    }
    $this->get(route('leads.index', ['page' => 2, 'category' => $category->id]))
        ->assertRedirect(route('leads.index', ['page' => 1, 'category' => $category->id]));
    $this->getJson(route('leads.index', ['page' => 1, 'category' => $category->id]))
        ->assertOk()->assertJsonPath('leads.total', 12)->assertJsonCount(12, 'leads.data');
    $this->assertModelExists($category);
})->with(['delete', 'move']);

test('completion and reopening preserve the list context and require the original confirmations', function () {
    $own = Membership::factory()->create();
    $source = LeadCategory::factory()->for($own->account)->create();
    $completed = $own->account->leadCategories()->where('system_key', LeadCategory::SALE_COMPLETED)->sole();
    $lead = Lead::factory()->for($own->account)->for($source, 'category')->create();
    $this->actingAs($own->user);
    $this->postJson(route('leads.complete', ['lead' => $lead->id, 'category' => $source->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_completion');
    $this->post(route('leads.complete', ['lead' => $lead->id, 'category' => $source->id]), ['confirm_completion' => true])
        ->assertRedirect(route('leads.index', ['page' => 1, 'category' => $source->id]));
    $this->getJson(route('leads.index', ['category' => $source->id]))->assertOk()->assertJsonPath('leads.total', 0);
    $this->getJson(route('leads.index', ['category' => $completed->id]))->assertOk()->assertJsonPath('leads.total', 1);
    $payload = ['name' => $lead->name, 'category_id' => $source->id, 'first_contact_date' => $lead->first_contact_date->toDateString()];
    $this->patchJson(route('leads.update', ['lead' => $lead->id, 'category' => $completed->id]), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_reopening');
    $this->patch(route('leads.update', ['lead' => $lead->id, 'category' => $completed->id]), [...$payload, 'confirm_reopening' => true])
        ->assertRedirect(route('leads.index', ['page' => 1, 'category' => $completed->id]));
    $this->getJson(route('leads.index', ['category' => $completed->id]))->assertOk()->assertJsonPath('leads.total', 0);
    $this->getJson(route('leads.index', ['category' => $source->id]))->assertOk()->assertJsonPath('leads.total', 1);
    expect($lead->fresh()->category_id)->toBe($source->id);
});

test('invalid filter context cannot cause a write before returning 404', function (string $operation) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $foreign = LeadCategory::factory()->create();
    $before = $lead->fresh()->getRawOriginal();
    $parameters = ['lead' => $lead->id, 'category' => $foreign->id];
    $this->actingAs($own->user);
    $payload = ['name' => 'Changed', 'category_id' => $lead->category_id, 'first_contact_date' => '2026-10-01'];
    $response = match ($operation) {
        'create' => $this->post(route('leads.store', ['category' => $foreign->id]), $payload),
        'update' => $this->patch(route('leads.update', $parameters), $payload),
        'complete' => $this->post(route('leads.complete', $parameters), ['confirm_completion' => true]),
        'delete' => $this->delete(route('leads.destroy', $parameters)),
    };
    $response->assertNotFound();
    expect($lead->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('leads', 1);
})->with(['create', 'update', 'complete', 'delete']);

test('filtering uses bounded queries without writes or changes to dashboard metrics', function () {
    $own = Membership::factory()->create();
    $category = LeadCategory::factory()->for($own->account)->create();
    Lead::factory()->count(13)->for($own->account)->for($category, 'category')->create();
    $this->actingAs($own->user);
    $overview = $this->get(route('dashboard'))->assertOk()->inertiaProps('overview');
    DB::enableQueryLog();
    try {
        $this->getJson(route('leads.index', ['category' => $category->id]))->assertOk()
            ->assertJsonPath('leads.total', 13)->assertJsonCount(12, 'leads.data');
        $queries = collect(DB::getQueryLog());
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "leads"')))->toHaveCount(2);
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "lead_categories"')))->toHaveCount(3);
        expect($queries->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query['query']) === 1))->toBeEmpty();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('overview', $overview));
});
