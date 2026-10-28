<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;

/** @return array<string, mixed> */
function saleLeadPayload(Lead $lead): array
{
    return ['name' => $lead->name, 'description' => $lead->description, 'category_id' => $lead->category_id,
        'first_contact_date' => $lead->first_contact_date->toDateString(), 'next_contact_date' => $lead->next_contact_date?->toDateString(),
        'potential_amount' => $lead->potential_amount, 'charged_amount' => $lead->charged_amount];
}

test('creating directly in the permanent category requires explicit confirmation', function (mixed $confirmation) {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $payload = ['name' => 'Venda', 'category_id' => $category->id, 'first_contact_date' => '2026-10-01'];
    if ($confirmation !== null) {
        $payload['confirm_completion'] = $confirmation;
    }
    $this->actingAs($own->user)->postJson(route('leads.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('confirm_completion');
    $this->assertDatabaseEmpty('leads');
})->with(['missing' => null, 'false' => false, 'invalid' => 'accepted']);

test('the three completion paths preserve exact charged amounts', function (string $path, ?string $amount) {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $this->actingAs($own->user);
    if ($path === 'create') {
        $response = $this->postJson(route('leads.store'), ['name' => 'Venda', 'category_id' => $category->id,
            'first_contact_date' => '2026-10-01', 'potential_amount' => '9000.00', 'charged_amount' => $amount, 'confirm_completion' => true]);
        $response->assertCreated()->assertJsonPath('lead.category.is_sale_completed', true);
        $lead = Lead::sole();
    } else {
        $lead = Lead::factory()->for($own->account)->create(['potential_amount' => '9000.00', 'charged_amount' => $amount]);
        $before = $lead->getRawOriginal('created_at');
        if ($path === 'check') {
            $this->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertRedirect()
                ->assertInertiaFlash('toast.message', 'Venda finalizada com sucesso!');
        } else {
            $this->patch(route('leads.update', $lead), [...saleLeadPayload($lead), 'category_id' => $category->id, 'confirm_completion' => true])->assertRedirect();
        }
        expect($lead->fresh()->getRawOriginal('created_at'))->toBe($before);
    }
    expect($lead->fresh()->category_id)->toBe($category->id);
    expect($lead->fresh()->charged_amount)->toBe($amount);
    expect($lead->fresh()->potential_amount)->toBe('9000.00');
})->with(['create', 'edit', 'check'])->with(['0.01', '1500.37']);

test('every completion path rejects a lead without a positive charged amount', function (string $path, mixed $amount) {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    $this->actingAs($own->user);
    $response = match ($path) {
        'create' => $this->postJson(route('leads.store'), ['name' => 'Venda', 'category_id' => $category->id,
            'first_contact_date' => '2026-10-01', 'charged_amount' => $amount, 'confirm_completion' => true]),
        'edit' => $this->patchJson(route('leads.update', $lead), [...saleLeadPayload($lead), 'category_id' => $category->id,
            'charged_amount' => $amount, 'confirm_completion' => true]),
        'check' => $this->postJson(route('leads.complete', $lead), ['confirm_completion' => true, ...($amount === null ? [] : ['charged_amount' => $amount])]),
    };
    $response->assertUnprocessable()->assertJsonValidationErrors('charged_amount');
    expect(Lead::count())->toBe(1);
    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with(['create', 'edit', 'check'])->with(['missing' => null, 'zero' => '0.00', 'negative' => '-10.00', 'text' => 'mil reais', 'three decimals' => '10.001']);

test('the check path stores the charged amount together with the completion', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $this->actingAs($own->user)->post(route('leads.complete', $lead), ['confirm_completion' => true, 'charged_amount' => '1500.37'])
        ->assertRedirect()->assertInertiaFlash('toast.message', 'Venda finalizada com sucesso!');
    expect($lead->fresh()->category->is_sale_completed)->toBeTrue();
    expect($lead->fresh()->charged_amount)->toBe('1500.37');
});

test('a completed lead keeps a positive charged amount until it is reopened', function (?string $amount) {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $lead = Lead::factory()->for($own->account)->for($category, 'category')->create(['charged_amount' => '123.45']);
    $before = $lead->fresh()->getRawOriginal();
    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), [...saleLeadPayload($lead), 'charged_amount' => $amount])
        ->assertUnprocessable()->assertJsonValidationErrors('charged_amount');
    expect($lead->fresh()->getRawOriginal())->toBe($before);
    $this->patch(route('leads.update', $lead), [...saleLeadPayload($lead), 'charged_amount' => '200.00'])->assertRedirect();
    expect($lead->fresh()->charged_amount)->toBe('200.00');
    $ordinary = LeadCategory::factory()->for($own->account)->create();
    $this->patch(route('leads.update', $lead), [...saleLeadPayload($lead), 'category_id' => $ordinary->id, 'charged_amount' => $amount, 'confirm_reopening' => true])->assertRedirect();
    expect($lead->fresh()->category_id)->toBe($ordinary->id);
    expect($lead->fresh()->charged_amount)->toBe($amount);
})->with([null, '0.00']);

test('moving into the permanent category without confirmation leaves every stored field untouched', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    $category = $own->account->leadCategories()->where('system_key', 'sale_completed')->sole();
    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), [...saleLeadPayload($lead), 'name' => 'Tentativa', 'category_id' => $category->id])
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_completion');
    expect($lead->fresh()->getRawOriginal())->toBe($before);
});

test('the completion endpoint refuses missing confirmation and every authority field', function (array $extra, string $error) {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    $this->actingAs($own->user)->postJson(route('leads.complete', $lead), $extra)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with([
    'missing consent' => [[], 'confirm_completion'],
    'false consent' => [['confirm_completion' => false], 'confirm_completion'],
    'forged category' => [['confirm_completion' => true, 'category_id' => 999], 'form'],
    'forged account' => [['confirm_completion' => true, 'account_id' => 999], 'form'],
    'forged identity' => [['confirm_completion' => true, 'system_key' => 'sale_completed'], 'form'],
]);

test('completion is idempotent after renaming and does not modify unrelated leads', function () {
    $this->freezeTime();
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['charged_amount' => '100.00']);
    $other = Lead::factory()->create();
    $otherBefore = $other->fresh()->getRawOriginal();
    $category = $own->account->leadCategories()->where('system_key', 'sale_completed')->sole();
    $category->update(['name' => 'Recebido', 'color' => '#abcdef']);
    $this->actingAs($own->user)->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertRedirect();
    $completed = $lead->fresh()->getRawOriginal();
    $this->get(route('leads.index'))->assertOk();
    $this->travel(1)->hours();
    $this->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertRedirect()->assertInertiaFlashMissing('toast');
    expect($lead->fresh()->getRawOriginal())->toBe($completed);
    expect($other->fresh()->getRawOriginal())->toBe($otherBefore);
    expect($own->account->leads()->where('category_id', $category->id)->count())->toBe(1);
    $this->patch(route('leads.update', $lead), [...saleLeadPayload($lead->fresh()), 'name' => 'Nome corrigido'])->assertRedirect();
    expect($lead->fresh()->category_id)->toBe($category->id);
});

test('reopening requires explicit consent and preserves commercial values and creation date', function () {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $lead = Lead::factory()->for($own->account)->for($category, 'category')->create(['potential_amount' => '5000.37', 'charged_amount' => '123.45']);
    $ordinary = LeadCategory::factory()->for($own->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    $payload = [...saleLeadPayload($lead), 'category_id' => $ordinary->id];
    $this->actingAs($own->user)->patchJson(route('leads.update', $lead), $payload)->assertUnprocessable()->assertJsonValidationErrors('confirm_reopening');
    expect($lead->fresh()->getRawOriginal())->toBe($before);
    $this->patch(route('leads.update', $lead), [...$payload, 'confirm_reopening' => true])->assertRedirect();
    expect($lead->fresh()->category_id)->toBe($ordinary->id);
    expect($lead->fresh()->potential_amount)->toBe('5000.37');
    expect($lead->fresh()->charged_amount)->toBe('123.45');
    expect($lead->fresh()->getRawOriginal('created_at'))->toBe($before['created_at']);
    $this->assertModelExists($category);
});

test('completion remains protected by login current account lead policy and csrf', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $foreign = Lead::factory()->create();
    $this->postJson(route('leads.complete', $lead), ['confirm_completion' => true])->assertUnauthorized();
    $this->actingAs($own->user)->postJson(route('leads.complete', $foreign), ['confirm_completion' => true])->assertNotFound();
    Gate::before(fn () => Response::denyAsNotFound());
    $this->postJson(route('leads.complete', $lead), ['confirm_completion' => true])->assertNotFound();
    expect($lead->fresh()->category_id)->toBe($lead->category_id);
});

test('the new state changing endpoint requires a csrf token', function () {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create();
    $this->actingAs($own->user)->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertStatus(419);
    expect($lead->fresh()->category_id)->toBe($lead->category_id);
});

test('an exception after a transition write rolls the lead back', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['charged_amount' => '100.00']);
    $before = $lead->fresh()->getRawOriginal();
    Exceptions::fake();
    Event::listen('eloquent.updated: '.Lead::class, fn () => throw new RuntimeException('Write failed.'));
    try {
        $this->actingAs($own->user)->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertServerError();
        expect($lead->fresh()->getRawOriginal())->toBe($before);
    } finally {
        Event::forget('eloquent.updated: '.Lead::class);
    }
});

test('a stale ordinary edit cannot silently reopen a concurrently completed lead', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['charged_amount' => '100.00']);
    $stale = saleLeadPayload($lead);
    $this->actingAs($own->user)->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertRedirect();
    $this->patchJson(route('leads.update', $lead), $stale)->assertUnprocessable()->assertJsonValidationErrors('confirm_reopening');
    expect($lead->fresh()->category->is_sale_completed)->toBeTrue();
});
