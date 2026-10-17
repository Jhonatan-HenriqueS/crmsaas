<?php

use App\Http\Controllers\LeadController;
use App\Http\Middleware\ResolveCurrentAccount;
use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'verified', ResolveCurrentAccount::class])
        ->post('/test/lead-integrity', [LeadController::class, 'store'])->name('test.leads.integrity.store');
    Route::getRoutes()->refreshNameLookups();
});

test('the composite foreign key rejects cross account inserts made directly against SQLite', function () {
    $account = Account::factory()->create();
    $category = LeadCategory::factory()->create();

    expect(DB::selectOne('PRAGMA foreign_keys')->foreign_keys)->toBe(1);
    expect(fn () => DB::table('leads')->insert([
        'account_id' => $account->id, 'category_id' => $category->id,
        'name' => 'Associação indevida', 'first_contact_date' => '2020-01-10',
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseEmpty('leads');
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('direct changes to either lead foreign key cannot cross accounts', function (string $field) {
    $lead = Lead::factory()->create();
    $category = LeadCategory::factory()->create();
    $before = $lead->fresh()->getRawOriginal();

    expect(fn () => DB::table('leads')->where('id', $lead->id)->update([
        $field => $field === 'account_id' ? $category->account_id : $category->id,
    ]))->toThrow(QueryException::class);

    expect($lead->fresh()->getRawOriginal())->toBe($before);
})->with(['account_id', 'category_id']);

test('a category with leads cannot be reassigned to another account directly', function () {
    $lead = Lead::factory()->create();
    $other = Account::factory()->create();

    expect(fn () => DB::table('lead_categories')->where('id', $lead->category_id)
        ->update(['account_id' => $other->id]))->toThrow(QueryException::class);

    expect($lead->category->account_id)->toBe($lead->account_id);
    $this->assertModelExists($lead);
});

test('required foreign keys cannot be bypassed with null or orphan values', function (string $field, mixed $value) {
    $category = LeadCategory::factory()->create();

    expect(fn () => DB::table('leads')->insert([
        ...['account_id' => $category->account_id, 'category_id' => $category->id,
            'name' => 'Sem referência válida', 'first_contact_date' => '2020-01-10'],
        $field => $value,
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseEmpty('leads');
})->with([
    'null account' => ['account_id', null], 'orphan account' => ['account_id', 999999],
    'null category' => ['category_id', null], 'orphan category' => ['category_id', 999999],
]);

test('authorized category deletion cascades only to its linked leads', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    $linked = Lead::factory()->count(2)->for($membership->account)->for($category, 'category')->create();
    $independent = Lead::factory()->for($membership->account)->create();
    $foreign = Lead::factory()->create();
    $independentBefore = $independent->fresh()->getRawOriginal();
    $foreignBefore = $foreign->fresh()->getRawOriginal();
    $otherCategories = LeadCategory::query()->whereKey([$independent->category_id, $foreign->category_id])->get()
        ->mapWithKeys(fn (LeadCategory $record): array => [$record->id => $record->getRawOriginal()])->all();

    $this->actingAs($membership->user)->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'operation' => 'deleted', 'message' => 'Categoria excluída com sucesso!']);

    $this->assertModelMissing($category);
    foreach ($linked as $lead) {
        $this->assertModelMissing($lead);
    }
    expect($independent->fresh()->getRawOriginal())->toBe($independentBefore);
    expect($foreign->fresh()->getRawOriginal())->toBe($foreignBefore);
    foreach ($otherCategories as $id => $attributes) {
        expect(LeadCategory::query()->findOrFail($id)->getRawOriginal())->toBe($attributes);
    }
    $this->assertDatabaseCount('leads', 2);
    $this->assertDatabaseCount('lead_categories', 2);
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('foreign category deletion is denied and cannot cascade to another account', function () {
    $membership = Membership::factory()->create();
    $lead = Lead::factory()->create();
    $before = $lead->fresh()->getRawOriginal();

    $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $lead->category_id))->assertNotFound();

    $this->assertModelExists($lead->category);
    expect($lead->fresh()->getRawOriginal())->toBe($before);
});

test('renaming and recoloring a category preserve existing and new lead associations', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['color' => '#112233']);
    $lead = Lead::factory()->for($membership->account)->for($category, 'category')->create();
    $before = $lead->fresh()->getRawOriginal();

    $this->actingAs($membership->user)->patch(route('categories.update', $category), ['name' => 'Reunião', 'color' => '#445566'])
        ->assertRedirect(route('categories.index'));
    $this->postJson(route('test.leads.integrity.store'), [
        'category_id' => $category->id, 'name' => 'Novo contato', 'first_contact_date' => '2020-01-10',
    ])->assertCreated()->assertJsonPath('lead.category.name', 'Reunião')->assertJsonPath('lead.category.color', '#445566');

    expect($lead->fresh()->getRawOriginal())->toBe($before);
    expect($category->leads()->count())->toBe(2);
    $this->assertDatabaseCount('leads', 2);
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('a database failure after category deletion rolls back its cascaded leads', function () {
    $membership = Membership::factory()->create();
    $lead = Lead::factory()->for($membership->account)->create();
    $before = $lead->fresh()->getRawOriginal();
    Exceptions::fake();
    LeadCategory::deleted(fn () => DB::statement('select missing_lead_cascade_test_function()'));

    try {
        $this->actingAs($membership->user)->deleteJson(route('categories.destroy', $lead->category_id))
            ->assertServerError()->assertInertiaFlashMissing('toast');

        $this->assertModelExists($lead->category);
        expect($lead->fresh()->getRawOriginal())->toBe($before);
        Exceptions::assertReported(QueryException::class);
        expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    } finally {
        LeadCategory::flushEventListeners();
    }
});

test('a post insert database error rolls back lead creation completely', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    Exceptions::fake();
    Lead::created(fn () => DB::statement('select missing_lead_insert_test_function()'));

    try {
        $this->actingAs($membership->user)->postJson(route('test.leads.integrity.store'), [
            'category_id' => $category->id, 'name' => 'Falha transacional', 'first_contact_date' => '2020-01-10',
        ])->assertServerError();

        $this->assertDatabaseEmpty('leads');
        $this->assertModelExists($category);
        Exceptions::assertReported(QueryException::class);
    } finally {
        Lead::flushEventListeners();
    }
});

test('a constraint error during creation restores category changes made in its transaction', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    Exceptions::fake();
    Lead::creating(fn () => $category->delete());

    try {
        $this->actingAs($membership->user)->postJson(route('test.leads.integrity.store'), [
            'category_id' => $category->id, 'name' => 'Categoria indisponível', 'first_contact_date' => '2020-01-10',
        ])->assertServerError();

        $this->assertDatabaseEmpty('leads');
        $this->assertModelExists($category);
        Exceptions::assertReported(QueryException::class);
        expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    } finally {
        Lead::flushEventListeners();
    }
});

test('a vetoed creation returns 409 without claiming a successful insertion', function () {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create();
    Lead::creating(fn () => false);

    try {
        $this->actingAs($membership->user)->postJson(route('test.leads.integrity.store'), [
            'category_id' => $category->id, 'name' => 'Veto', 'first_contact_date' => '2020-01-10',
        ])->assertConflict();

        $this->assertDatabaseEmpty('leads');
    } finally {
        Lead::flushEventListeners();
    }
});

test('transactional foreign key failure leaves no partially inserted leads', function () {
    $category = LeadCategory::factory()->create();
    $foreign = LeadCategory::factory()->create();

    expect(fn () => DB::transaction(function () use ($category, $foreign): void {
        Lead::factory()->for($category->account)->for($category, 'category')->create();
        DB::table('leads')->insert([
            'account_id' => $category->account_id, 'category_id' => $foreign->id,
            'name' => 'Conta errada', 'first_contact_date' => '2020-01-10',
        ]);
    }))->toThrow(QueryException::class);

    $this->assertDatabaseEmpty('leads');
    $this->assertModelExists($category);
    $this->assertModelExists($foreign);
});

test('civil date casts preserve date only storage and normal timestamp precision', function () {
    $lead = Lead::factory()->create([
        'first_contact_date' => Date::parse('2020-01-10 23:54:00', 'America/Sao_Paulo'),
        'next_contact_date' => Date::parse('2020-01-11 15:30:00', 'America/Sao_Paulo'),
        'potential_amount' => '123.40', 'charged_amount' => '0.00',
    ])->fresh();

    expect($lead->first_contact_date)->toBeInstanceOf(CarbonImmutable::class);
    expect($lead->getRawOriginal('first_contact_date'))->toBe('2020-01-10');
    expect($lead->getRawOriginal('next_contact_date'))->toBe('2020-01-11');
    expect($lead->toArray()['first_contact_date'])->toBe('2020-01-10');
    expect($lead->toArray()['next_contact_date'])->toBe('2020-01-11');
    expect($lead->getRawOriginal('created_at'))->toMatch('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/');
    expect($lead->potential_amount)->toBe('123.40');
    expect($lead->charged_amount)->toBe('0.00');
});

test('the account and category factory relationships refer to the same owner', function () {
    $lead = Lead::factory()->create();

    expect($lead->category->account_id)->toBe($lead->account_id);
    expect($lead->account->leads()->sole()->is($lead))->toBeTrue();
    expect($lead->category->leads()->sole()->is($lead))->toBeTrue();
});

test('deleting an account removes only its own categories and leads', function () {
    $lead = Lead::factory()->create();
    $other = Lead::factory()->create();
    $before = $other->fresh()->getRawOriginal();
    $category = $lead->category;

    $lead->account->deleteOrFail();

    $this->assertModelMissing($lead);
    $this->assertModelMissing($category);
    expect($other->fresh()->getRawOriginal())->toBe($before);
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('additive migrations and their rollback preserve preexisting categories and color uniqueness', function () {
    $leads = require database_path('migrations/2026_10_17_012836_create_leads_table.php');
    $unique = require database_path('migrations/2026_10_17_012835_add_account_category_unique_key_to_lead_categories_table.php');
    $leads->down();
    $unique->down();
    $category = LeadCategory::factory()->create(['color' => '#112233']);
    $before = $category->fresh()->getRawOriginal();

    $unique->up();
    $leads->up();
    $lead = Lead::factory()->for($category->account)->for($category, 'category')->create();

    expect($category->fresh()->getRawOriginal())->toBe($before);
    $this->assertModelExists($lead);
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);

    $leads->down();
    $unique->down();

    expect(Schema::hasTable('leads'))->toBeFalse();
    expect($category->fresh()->getRawOriginal())->toBe($before);
    expect(fn () => LeadCategory::factory()->for($category->account)->create(['color' => '#112233']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('new migrations compile PostgreSQL constraints without requiring a PostgreSQL server', function () {
    $schema = Schema::getFacadeRoot();
    $postgres = new PostgresConnection(new PDO('sqlite::memory:'), 'lead_foundation');

    try {
        Schema::swap($postgres->getSchemaBuilder());
        $queries = $postgres->pretend(function (): void {
            (require database_path('migrations/2026_10_17_012835_add_account_category_unique_key_to_lead_categories_table.php'))->up();
            (require database_path('migrations/2026_10_17_012836_create_leads_table.php'))->up();
        });
        $sql = implode("\n", array_column($queries, 'query'));

        expect($sql)->toContain('unique ("account_id", "id")');
        expect($sql)->toContain('foreign key ("account_id", "category_id") references "lead_categories" ("account_id", "id") on delete cascade');
        expect($sql)->toContain('"first_contact_date" date not null', '"next_contact_date" date null');
        expect($sql)->toContain('"potential_amount" decimal(12, 2)', '"charged_amount" decimal(12, 2)');
        expect($sql)->not->toContain('set null');
    } finally {
        Schema::swap($schema);
    }
});

test('SQLite query plans use the account pagination and category relationship indexes', function () {
    $lead = Lead::factory()->create();

    $listing = DB::select('EXPLAIN QUERY PLAN SELECT id FROM leads WHERE account_id = ? ORDER BY id DESC LIMIT 12', [$lead->account_id]);
    $category = DB::select('EXPLAIN QUERY PLAN SELECT id FROM leads WHERE category_id = ? AND account_id = ?', [$lead->category_id, $lead->account_id]);

    expect(implode(' ', array_column($listing, 'detail')))->toContain('leads_account_id_id_index');
    expect(implode(' ', array_column($category, 'detail')))->toContain('leads_category_id_account_id_index');
});
