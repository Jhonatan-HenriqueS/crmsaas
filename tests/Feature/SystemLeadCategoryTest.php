<?php

use App\Actions\EnsureSaleCompletedCategory;
use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Database\Seeders\AccountSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('every official account creation path provisions its own permanent category', function (string $path) {
    if ($path === 'factory') {
        Account::factory()->count(2)->create();
    } elseif ($path === 'model') {
        Account::create(['type' => 'empresa']);
        Account::create(['type' => 'agencia']);
    } else {
        $this->seed(AccountSeeder::class);
        $this->seed(AccountSeeder::class);
    }

    foreach (Account::all() as $account) {
        $category = $account->leadCategories()->sole();
        expect($category->name)->toBe('Finalizado');
        expect($category->color)->toBe('#00ff0a');
        expect($category->system_key)->toBe('sale_completed');
        expect($category->account_id)->toBe($account->id);
    }
    $this->assertDatabaseCount('accounts', 2);
    $this->assertDatabaseCount('lead_categories', 2);
})->with(['factory', 'model', 'seeder']);

test('registration commits user account membership and system category together', function () {
    Http::fake(['https://api.pwnedpasswords.com/range/*' => Http::response('00000:0')]);
    $this->post(route('register.store'), ['name' => 'Nova conta', 'email' => 'account@example.com',
        'password' => 'Strong#42', 'terms' => true, 'plan' => 'empresa'])->assertRedirect();

    $this->assertAuthenticated();
    $membership = Membership::sole();
    expect($membership->role)->toBe('owner');
    expect($membership->account->leadCategories()->sole()->system_key)->toBe('sale_completed');
});

test('category creation failure rolls back the complete account creation operation', function (string $path) {
    Event::listen('eloquent.creating: '.LeadCategory::class, fn () => throw new RuntimeException('Category creation failed.'));
    try {
        if ($path === 'registration') {
            Http::fake(['https://api.pwnedpasswords.com/range/*' => Http::response('00000:0')]);
            Exceptions::fake();
            $this->post(route('register.store'), ['name' => 'Rollback', 'email' => 'rollback@example.com',
                'password' => 'Strong#42', 'terms' => true, 'plan' => 'empresa'])->assertServerError();
            $this->assertDatabaseEmpty('users');
            $this->assertDatabaseEmpty('memberships');
        } else {
            expect(fn () => Account::factory()->create())->toThrow(RuntimeException::class);
        }
        $this->assertDatabaseEmpty('accounts');
        $this->assertDatabaseEmpty('lead_categories');
    } finally {
        Event::forget('eloquent.creating: '.LeadCategory::class);
    }
})->with(['registration', 'factory']);

test('provisioning is idempotent and preserves a customized permanent category', function () {
    $account = Account::factory()->create();
    $category = $account->leadCategories()->sole();
    $category->update(['name' => 'Entregue', 'color' => '#123456']);

    expect(app(EnsureSaleCompletedCategory::class)->handle($account)->id)->toBe($category->id);
    expect(app(EnsureSaleCompletedCategory::class)->handle($account)->name)->toBe('Entregue');
    $this->assertDatabaseCount('lead_categories', 1);
});

test('the unique system identity permits ordinary categories but rejects a second protected category', function () {
    $account = Account::factory()->create();
    LeadCategory::factory()->count(2)->for($account)->create();
    expect(fn () => DB::table('lead_categories')->insert(['account_id' => $account->id, 'name' => 'Duplicate',
        'color' => '#123456', 'system_key' => 'sale_completed']))->toThrow(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('lead_categories', 3);
});

test('the protected category can be renamed and recolored through the existing form', function () {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $lead = Lead::factory()->for($own->account)->for($category, 'category')->create();

    $this->actingAs($own->user)->patch(route('categories.update', $category), ['name' => 'Entregue e pago', 'color' => '#abcdef'])->assertRedirect();
    expect($category->fresh()->system_key)->toBe('sale_completed');
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->has('programmedCategories', 1)
        ->has('categories.data', 0)->where('programmedCategories.0.name', 'Entregue e pago')
        ->where('programmedCategories.0.color', '#abcdef')->where('programmedCategories.0.leads_count', 1)
        ->where('programmedCategories.0.percentage', 100)->where('programmedCategories.0.deletion_confirmation', null));
    $this->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page->where('leads.data.0.id', $lead->id)
        ->where('leads.data.0.category.name', 'Entregue e pago')->where('leads.data.0.category.is_sale_completed', true));
});

test('protected editing keeps the existing per account color uniqueness', function () {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    LeadCategory::factory()->for($own->account)->create(['color' => '#abcdef']);
    $this->actingAs($own->user)->patchJson(route('categories.update', $category), ['name' => 'Finalizado', 'color' => '#abcdef'])
        ->assertUnprocessable()->assertJsonValidationErrors('color');
    expect($category->fresh()->color)->toBe('#00ff0a');
    $this->patchJson(route('categories.update', $category), ['name' => 'Entregue', 'color' => '#00ff0a'])->assertRedirect();
});

test('individual deletion of the protected category is forbidden even with a manual request', function () {
    $own = Membership::factory()->create();
    $category = $own->account->leadCategories()->sole();
    $lead = Lead::factory()->for($own->account)->for($category, 'category')->create();
    $this->actingAs($own->user)->deleteJson(route('categories.destroy', $category), ['confirmation' => 'manual'])->assertForbidden();
    expect($category->delete())->toBeFalse();
    $this->assertModelExists($category);
    $this->assertModelExists($lead);
});

test('http payloads cannot assign remove or change system identity', function (bool $protected, mixed $key) {
    $own = Membership::factory()->create();
    $category = $protected ? $own->account->leadCategories()->sole() : LeadCategory::factory()->for($own->account)->create();
    $before = $category->fresh()->getRawOriginal();
    $this->actingAs($own->user)->patchJson(route('categories.update', $category), ['name' => 'Alterado', 'color' => $category->color, 'system_key' => $key])
        ->assertUnprocessable()->assertJsonValidationErrors('form');
    expect($category->fresh()->getRawOriginal())->toBe($before);
    $this->postJson(route('categories.store'), ['name' => 'Outra', 'color' => '#778899', 'system_key' => $key])
        ->assertUnprocessable()->assertJsonValidationErrors('form');
})->with([[true, null], [true, 'ordinary'], [false, 'sale_completed']]);

test('mass assignment cannot control system identity and direct model mutation is rejected', function (bool $protected) {
    $account = Account::factory()->create();
    $category = $protected ? $account->leadCategories()->sole() : LeadCategory::factory()->for($account)->create();
    $before = $category->system_key;
    $category->fill(['system_key' => $protected ? null : 'sale_completed']);
    expect($category->system_key)->toBe($before);
    $category->system_key = $protected ? null : 'sale_completed';
    expect(fn () => $category->save())->toThrow(LogicException::class);
    expect($category->fresh()->system_key)->toBe($before);
})->with([true, false]);

test('a foreign protected category stays hidden from editing and deletion', function () {
    $own = Membership::factory()->create();
    $foreign = Account::factory()->create()->leadCategories()->sole();
    $this->actingAs($own->user)->patchJson(route('categories.update', $foreign), ['name' => 'Foreign', 'color' => '#abcdef'])->assertNotFound();
    $this->deleteJson(route('categories.destroy', $foreign))->assertNotFound();
    expect($foreign->fresh()->name)->toBe('Finalizado');
});

test('explicit legacy provisioning is non destructive and can be repeated', function () {
    $id = DB::table('accounts')->insertGetId(['type' => 'empresa']);
    $this->artisan('accounts:ensure-sale-category')->assertSuccessful();
    $this->artisan('accounts:ensure-sale-category')->assertSuccessful();
    expect(Account::findOrFail($id)->leadCategories()->sole()->system_key)->toBe('sale_completed');
});

test('legacy provisioning reports occupied green without adopting or recoloring an ordinary category', function () {
    $id = DB::table('accounts')->insertGetId(['type' => 'empresa']);
    $ordinary = LeadCategory::factory()->create(['account_id' => $id, 'color' => '#00ff0a']);
    $before = $ordinary->fresh()->getRawOriginal();
    $this->artisan('accounts:ensure-sale-category')->assertFailed();
    expect($ordinary->fresh()->getRawOriginal())->toBe($before);
    expect(Account::findOrFail($id)->leadCategories()->whereNotNull('system_key')->count())->toBe(0);
});
