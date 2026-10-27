<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

test('a category with linked leads cannot be deleted without an explicit impact confirmation', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    Lead::factory()->for($membership->account)->for($category, 'category')->create();

    $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $category))
        ->assertUnprocessable()->assertJsonValidationErrors('confirmation');

    $this->assertModelExists($category);
    $this->assertDatabaseCount('leads', 1);
});

test('the authorized listing supplies the actual impact and an opaque confirmation', function (int $count) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    Lead::factory()->count($count)->for($membership->account)->for($category, 'category')->create();
    Lead::factory()->create();

    $response = $this->actingAs($membership->user)->get(route('categories.index'))->assertOk();
    $item = $response->inertiaProps('categories.data.0');

    expect($item['id'])->toBe($category->id);
    expect($item['leads_count'])->toBe($count);
    expect($item['deletion_confirmation'])->toBeString()->not->toBeEmpty();
    expect($item)->not->toHaveKeys(['account_id', 'created_at']);
    $this->delete(route('categories.destroy', $category), ['confirmation' => $item['deletion_confirmation']])
        ->assertRedirect(route('categories.index'));
    $this->assertModelMissing($category);
    $this->assertDatabaseCount('leads', 1);
})->with(['empty' => 0, 'singular' => 1, 'plural' => 12]);

test('a changed lead count or category name requires another reviewed confirmation', function (bool $rename) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    Lead::factory()->for($membership->account)->for($category, 'category')->create();
    $this->actingAs($membership->user);
    $payload = $this->categoryDeletionPayload($category);
    if ($rename) {
        $category->update(['name' => 'Nome atualizado']);
    } else {
        Lead::factory()->for($membership->account)->for($category, 'category')->create();
    }

    $this->deleteJson(route('categories.destroy', $category), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors([
            'confirmation' => 'A categoria ou a quantidade de leads mudou. Revise os dados e confirme novamente.',
        ])->assertInertiaFlashMissing('toast');

    $this->assertModelExists($category);
    $this->assertDatabaseCount('leads', $rename ? 1 : 2);
    $this->delete(route('categories.destroy', $category), $this->categoryDeletionPayload($category))
        ->assertRedirect(route('categories.index'));
    $this->assertModelMissing($category);
    $this->assertDatabaseEmpty('leads');
})->with(['renamed' => true, 'new linked lead' => false]);

test('invalid deletion confirmations never delete a category', function (mixed $confirmation) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $category), ['confirmation' => $confirmation])
        ->assertUnprocessable()->assertJsonValidationErrors('confirmation')->assertInertiaFlashMissing('toast');

    $this->assertModelExists($category);
})->with(['empty' => '', 'null' => null, 'array' => [['x']], 'forged' => 'not-an-authentic-confirmation', 'oversized' => str_repeat('x', 4097)]);

test('a valid confirmation cannot be reused for a different category', function () {
    $membership = Membership::factory()->create();
    $first = LeadCategory::factory()->for($membership->account)->create(['name' => 'Mesmo nome']);
    $second = LeadCategory::factory()->for($membership->account)->create(['name' => 'Mesmo nome']);
    $this->actingAs($membership->user);
    $payload = $this->categoryDeletionPayload($first);

    $this->deleteJson(route('categories.destroy', $second), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('confirmation');

    $this->assertModelExists($first);
    $this->assertModelExists($second);
});

test('a valid confirmation never replaces account ownership or policy authorization', function (bool $foreign) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $this->actingAs($membership->user);
    $payload = $this->categoryDeletionPayload($category);
    if ($foreign) {
        $this->actingAs(Membership::factory()->create()->user);
    } else {
        Gate::before(fn () => Response::denyAsNotFound());
    }

    $this->deleteJson(route('categories.destroy', $category), $payload)->assertNotFound();

    $this->assertModelExists($category);
})->with(['foreign account' => true, 'policy denied' => false]);

test('client supplied impact counts are rejected even with a valid confirmation', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $this->actingAs($membership->user);
    $payload = $this->categoryDeletionPayload($category);

    $this->deleteJson(route('categories.destroy', $category), [...$payload, 'leads_count' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('form');

    $this->assertModelExists($category);
});
