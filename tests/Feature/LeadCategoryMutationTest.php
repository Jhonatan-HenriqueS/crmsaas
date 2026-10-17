<?php

use App\Models\LeadCategory;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized edits persist only the selected category and refresh the list', function (string $name, string $color, string $expectedName, string $expectedColor) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Original', 'color' => '#112233']);
    $other = LeadCategory::factory()->for($membership->account)->create(['color' => '#445566']);
    $otherBefore = $other->fresh()->getAttributes();

    $this->actingAs($membership->user)->patch(route('categories.update', $category), ['name' => $name, 'color' => $color])
        ->assertRedirect(route('categories.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'operation' => 'updated', 'message' => 'Categoria editada com sucesso!']);

    $this->assertDatabaseHas('lead_categories', [
        'id' => $category->id, 'account_id' => $membership->account_id, 'name' => $expectedName, 'color' => $expectedColor,
    ]);
    expect($other->fresh()->getAttributes())->toBe($otherBefore);
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('categories.data.1.id', $category->id)
        ->where('categories.data.1.name', $expectedName)->where('categories.data.1.color', $expectedColor));
})->with([
    'name with own color unchanged' => ['Renomeada', '#112233', 'Renomeada', '#112233'],
    'color with name unchanged' => ['Original', '#7c65c1', 'Original', '#7c65c1'],
    'both normalized' => ['  Educação e Serviços  ', ' #7C65C1 ', 'Educação e Serviços', '#7c65c1'],
]);

test('unchanged edits preserve timestamps and emit no mutation notification', function () {
    $this->freezeTime();
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Original', 'color' => '#112233']);
    $before = $category->fresh()->getAttributes();
    $this->travel(1)->hours();

    $this->actingAs($membership->user)->patch(route('categories.update', $category), ['name' => ' Original ', 'color' => '#112233'])
        ->assertRedirect(route('categories.index'))->assertInertiaFlashMissing('toast');

    expect($category->fresh()->getAttributes())->toBe($before);
});

test('editing rejects another local color while allowing a color from another account', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Original', 'color' => '#112233']);
    LeadCategory::factory()->for($membership->account)->create(['color' => '#445566']);
    LeadCategory::factory()->create(['color' => '#7c65c1']);

    $this->actingAs($membership->user)->patchJson(route('categories.update', $category), ['name' => 'Recusada', 'color' => '#445566'])
        ->assertUnprocessable()->assertJsonValidationErrors(['color' => 'Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.'])
        ->assertInertiaFlashMissing('toast');
    $this->assertDatabaseHas('lead_categories', ['id' => $category->id, 'name' => 'Original', 'color' => '#112233']);

    $this->patch(route('categories.update', $category), ['name' => 'Permitida', 'color' => '#7C65C1'])
        ->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('lead_categories', ['id' => $category->id, 'account_id' => $membership->account_id, 'color' => '#7c65c1']);
});

test('invalid edits return 422 and preserve the stored category', function (string $field, mixed $value) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Original', 'color' => '#112233']);
    $before = $category->fresh()->getAttributes();

    $this->actingAs($membership->user)->patchJson(route('categories.update', $category), [
        ...['name' => 'Alterada', 'color' => '#445566'], $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors([$field])->assertInertiaFlashMissing('toast');

    expect($category->fresh()->getAttributes())->toBe($before);
})->with([
    'empty name' => ['name', ''], 'blank name' => ['name', '   '],
    'name array' => ['name', ['a']], 'name object' => ['name', (object) ['a' => 'b']],
    'long name' => ['name', str_repeat('á', 121)], 'control character' => ['name', "Nome\x01"],
    'missing color' => ['color', null], 'malformed hex' => ['color', '#fff'],
    'color array' => ['color', ['#445566']], 'color object' => ['color', (object) ['value' => '#445566']],
]);

test('an edit payload cannot change ownership or select the unique rule exception', function (string $field) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['color' => '#112233']);
    $other = LeadCategory::factory()->for($membership->account)->create(['color' => '#445566']);
    $before = $category->fresh()->getAttributes();

    $this->actingAs($membership->user)->patchJson(route('categories.update', $category), [
        'name' => 'Manipulada', 'color' => '#445566', $field => $other->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['form', 'color']);

    expect($category->fresh()->getAttributes())->toBe($before);
    $this->assertModelExists($other);
})->with(['account_id', 'owner_id', 'id', 'role', 'permissions']);

test('other account categories are private for mutations even with invalid input', function (string $method) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->create(['name' => 'Privada']);
    $before = $category->fresh()->getAttributes();

    $this->actingAs($membership->user)->json($method, route('categories.update', $category), ['name' => [], 'account_id' => $category->account_id])
        ->assertNotFound()->assertInertiaFlashMissing('toast');

    expect($category->fresh()->getAttributes())->toBe($before);
})->with(['PATCH', 'DELETE']);

test('guests cannot mutate categories', function (string $method) {
    $category = LeadCategory::factory()->create();

    $this->call($method, route('categories.update', $category), ['name' => 'Alterada', 'color' => '#112233'])
        ->assertRedirect(route('login'));

    $this->assertModelExists($category);
})->with(['PATCH', 'DELETE']);

test('invalid account contexts cannot mutate categories', function (string $method, int $memberships) {
    $user = User::factory()->create();
    Membership::factory()->count($memberships)->for($user)->create();
    $category = LeadCategory::factory()->create();

    $this->actingAs($user)->json($method, route('categories.update', $category), ['name' => 'Alterada', 'color' => '#112233'])
        ->assertNotFound();

    $this->assertModelExists($category);
})->with(['PATCH', 'DELETE'])->with(['no membership' => 0, 'ambiguous account' => 2]);

test('revoked membership and account policy denial prevent mutations', function (string $method, bool $revoked) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    if ($revoked) {
        $membership->delete();
    } else {
        Gate::before(fn () => Response::denyAsNotFound());
    }

    $this->actingAs($membership->user)->json($method, route('categories.update', $category), ['name' => 'Alterada', 'color' => '#112233'])
        ->assertNotFound();

    $this->assertModelExists($category);
})->with(['PATCH', 'DELETE'])->with(['revoked' => true, 'policy denial' => false]);

test('mutations require csrf from the current session', function (string $method, ?string $token, int $status) {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Original', 'color' => '#112233']);
    $payload = $method === 'PATCH' ? ['name' => 'Alterada', 'color' => '#445566'] : [];

    $this->actingAs($membership->user)->withSession(['_token' => 'category-mutation-csrf'])
        ->withHeader('Sec-Fetch-Site', 'cross-site')->call($method, route('categories.update', $category), [...$payload, '_token' => $token])
        ->assertStatus($status);

    if ($status === 419) {
        $this->assertDatabaseHas('lead_categories', ['id' => $category->id, 'name' => 'Original', 'color' => '#112233']);
    } elseif ($method === 'DELETE') {
        $this->assertModelMissing($category);
    } else {
        $this->assertDatabaseHas('lead_categories', ['id' => $category->id, 'name' => 'Alterada', 'color' => '#445566']);
    }
})->with(['PATCH', 'DELETE'])->with([
    'missing' => [null, 419], 'foreign session' => ['wrong-session', 419], 'valid' => ['category-mutation-csrf', 302],
]);

test('deletion removes only the selected owned category and updates the listing', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $other = LeadCategory::factory()->for($membership->account)->create();
    $foreign = LeadCategory::factory()->create();
    $otherBefore = $other->fresh()->getAttributes();
    $foreignBefore = $foreign->fresh()->getAttributes();

    $this->actingAs($membership->user)->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'operation' => 'deleted', 'message' => 'Categoria excluída com sucesso!']);

    $this->assertModelMissing($category);
    $this->assertDatabaseCount('lead_categories', 2);
    expect($other->fresh()->getAttributes())->toBe($otherBefore);
    expect($foreign->fresh()->getAttributes())->toBe($foreignBefore);
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page
        ->has('categories.data', 1)->where('categories.data.0.id', $other->id));
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    $this->delete(route('categories.destroy', $category))->assertNotFound();
});

test('deletion rejects extra payload instead of using it as authority', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();

    $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $category), ['account_id' => $membership->account_id])
        ->assertUnprocessable()->assertJsonValidationErrors(['form' => 'A exclusão não aceita campos adicionais.']);

    $this->assertModelExists($category);
});

test('a database failure after deletion rolls back the category and emits no success', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $other = LeadCategory::factory()->for($membership->account)->create();
    Exceptions::fake();
    LeadCategory::deleted(fn () => DB::statement('select missing_category_test_function()'));

    try {
        $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $category))
            ->assertServerError()->assertInertiaFlashMissing('toast');

        $this->assertModelExists($category);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('lead_categories', 2);
        Exceptions::assertReported(QueryException::class);
    } finally {
        LeadCategory::flushEventListeners();
    }
});

test('a vetoed deletion preserves its category and emits no success', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    LeadCategory::deleting(fn () => false);

    try {
        $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $category))
            ->assertConflict()->assertInertiaFlashMissing('toast');
        $this->assertModelExists($category);
    } finally {
        LeadCategory::flushEventListeners();
    }
});

test('unrelated unique failures during edits remain reported database errors', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Original', 'color' => '#112233']);
    LeadCategory::factory()->for($membership->account)->create(['name' => 'Outra', 'color' => '#445566']);
    Schema::table('lead_categories', fn (Blueprint $table) => $table->unique('name'));
    Exceptions::fake();

    $this->actingAs($membership->user)->patchJson(route('categories.update', $category), ['name' => 'Outra', 'color' => '#778899'])
        ->assertServerError()->assertInertiaFlashMissing('toast');

    $this->assertDatabaseHas('lead_categories', ['id' => $category->id, 'name' => 'Original', 'color' => '#112233']);
    Exceptions::assertReported(UniqueConstraintViolationException::class);
});

test('nonexistent categories return 404 for either mutation', function (string $method) {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->json($method, route('categories.update', 999999), ['name' => 'Ausente', 'color' => '#112233'])
        ->assertNotFound()->assertInertiaFlashMissing('toast');

    $this->assertDatabaseEmpty('lead_categories');
})->with(['PATCH', 'DELETE']);

// Real Lead cascade and rollback coverage is in LeadIntegrityTest.
