<?php

use App\Models\Account;
use App\Models\LeadCategory;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot list or create categories', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
    $this->post(route('categories.store'), ['name' => 'Tecnologia', 'color' => '#7c65c1'])
        ->assertRedirect(route('login'));
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
});

test('users without exactly one owned account cannot list or create categories', function (int $accounts) {
    $user = User::factory()->create();
    Membership::factory()->count($accounts)->for($user)->create();

    $this->actingAs($user)->get(route('categories.index'))->assertNotFound();
    $this->postJson(route('categories.store'), ['name' => 'Tecnologia', 'color' => '#7c65c1'])->assertNotFound();
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
})->with(['no membership' => 0, 'ambiguous accounts' => 2]);

test('the current account owns the trimmed name and canonical color', function () {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->post(route('categories.store'), [
        'name' => '  Educação e Serviços  ', 'color' => ' #7C65C1 ',
    ])->assertRedirect(route('categories.index'))
        ->assertSessionMissing('status')
        ->assertInertiaFlash('toast', [
            'type' => 'success', 'operation' => 'created', 'message' => 'Categoria criada com sucesso!',
        ]);

    $this->assertDatabaseHas('lead_categories', [
        'account_id' => $membership->account_id, 'name' => 'Educação e Serviços', 'color' => '#7c65c1',
    ]);
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(1);
    expect($membership->account->leadCategories()->whereNull('system_key')->sole()->account->is($membership->account))->toBeTrue();

    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('status')
            ->hasFlash('toast.message', 'Categoria criada com sucesso!'));
    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('toast'));
});

test('an agency and company each see only their own categories and may reuse a color', function () {
    $company = Membership::factory()->create();
    $agency = Membership::factory()->for(Account::factory()->agency())->create();
    $first = LeadCategory::factory()->for($company->account)->create(['name' => 'Empresa privada', 'color' => '#7c65c1']);

    $this->actingAs($agency->user)->post(route('categories.store'), ['name' => 'Agência privada', 'color' => '#7C65C1'])
        ->assertRedirect(route('categories.index'));
    $second = $agency->account->leadCategories()->whereNull('system_key')->sole();
    $this->get(route('categories.index', ['account_id' => $company->account_id]))
        ->assertInertia(fn (Assert $page) => $page->component('categories/index')
            ->has('categories.data', 1)->where('categories.data.0.id', $second->id)
            ->where('categories.data.0.color', '#7c65c1')
            ->missing('categories.data.0.account_id')->missing('categories.data.0.created_at'));
    $this->actingAs($company->user)->get(route('categories.index', ['account_id' => $agency->account_id]))
        ->assertInertia(fn (Assert $page) => $page->has('categories.data', 1)->where('categories.data.0.id', $first->id));
});

test('the same account cannot repeat a color with different letter case', function () {
    $membership = Membership::factory()->create();
    LeadCategory::factory()->for($membership->account)->create(['color' => '#7c65c1']);

    $this->actingAs($membership->user)->postJson(route('categories.store'), ['name' => 'Outra', 'color' => '#7C65C1'])
        ->assertUnprocessable()->assertJsonValidationErrors([
            'color' => 'Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.',
        ]);
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(1);
});

test('names may repeat when their colors differ', function () {
    $membership = Membership::factory()->create();
    LeadCategory::factory()->for($membership->account)->create(['name' => 'Serviços', 'color' => '#7c65c1']);

    $this->actingAs($membership->user)->post(route('categories.store'), ['name' => 'Serviços', 'color' => '#112233'])
        ->assertRedirect(route('categories.index'));
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(2);
});

test('unexpected payload fields cannot assign ownership or other attributes', function (string $field) {
    $membership = Membership::factory()->create();
    $other = Account::factory()->create();

    $this->actingAs($membership->user)->postJson(route('categories.store'), [
        'name' => 'Manipulada', 'color' => '#112233', $field => $other->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['form' => 'Envie apenas o nome e a cor da categoria.']);
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
})->with(['account_id', 'user_id', 'id', 'lead_count', 'class']);

test('mass assignment cannot redirect relationship creation to another account', function () {
    $own = Account::factory()->create();
    $other = Account::factory()->create();

    $category = $own->leadCategories()->create(['name' => 'Segura', 'color' => '#112233', 'account_id' => $other->id]);

    expect($category->account_id)->toBe($own->id);
    $this->assertDatabaseMissing('lead_categories', ['account_id' => $other->id, 'system_key' => null]);
});

test('revoked membership denies subsequent category requests', function () {
    $membership = Membership::factory()->create();
    $this->actingAs($membership->user)->get(route('categories.index'))->assertOk();
    $membership->delete();

    $this->postJson(route('categories.store'), ['name' => 'Revogada', 'color' => '#112233'])->assertNotFound();
    $this->get(route('categories.index'))->assertNotFound();
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
});

test('category endpoints enforce explicit account authorization', function () {
    $membership = Membership::factory()->create();
    Gate::before(fn () => Response::denyAsNotFound());

    $this->actingAs($membership->user)->get(route('categories.index'))->assertNotFound();
    $this->postJson(route('categories.store'), ['name' => 'Negada', 'color' => '#112233'])->assertNotFound();
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
});

test('missing category fields return Portuguese errors without inserting records', function () {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->postJson(route('categories.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors([
            'name' => 'Informe o nome da categoria.', 'color' => 'Escolha uma cor para a categoria.',
        ])->assertInertiaFlashMissing('toast');
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
});

test('malformed category fields are rejected without internal errors', function (string $field, mixed $value, string $message) {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->postJson(route('categories.store'), [
        ...['name' => 'Válida', 'color' => '#112233'], $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
})->with([
    'name array' => ['name', ['name'], 'Informe um nome válido para a categoria.'],
    'name object' => ['name', (object) ['name' => 'x'], 'Informe um nome válido para a categoria.'],
    'name number' => ['name', 123, 'Informe um nome válido para a categoria.'],
    'blank name' => ['name', " \u{00a0} ", 'Informe o nome da categoria.'],
    'long name' => ['name', str_repeat('á', 121), 'O nome da categoria deve ter no máximo 120 caracteres.'],
    'control character' => ['name', "nome\x01", 'O nome da categoria contém caracteres inválidos.'],
    'color array' => ['color', ['#112233'], 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.'],
    'color number' => ['color', 123456, 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.'],
    'short hex' => ['color', '#fff', 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.'],
    'alpha hex' => ['color', '#11223344', 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.'],
    'css expression' => ['color', 'url(javascript:alert(1))', 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.'],
    'invalid hex' => ['color', '#gggggg', 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.'],
]);

test('category list is paginated in stable order and supplies a free initial color', function () {
    $membership = Membership::factory()->create();
    $records = LeadCategory::factory()->count(14)->for($membership->account)->sequence(
        ...array_map(fn (int $i): array => ['color' => sprintf('#%06x', $i)], range(0, 13)),
    )->create();
    LeadCategory::factory()->for($membership->account)->create(['color' => '#6d54b5']);

    $this->actingAs($membership->user)->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->has('categories.data', 12)
            ->where('categories.currentPage', 1)->where('categories.hasMorePages', true)
            ->where('initialColor', '#00000e'));
    $this->get(route('categories.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('categories.data', 3)
            ->where('categories.data.0.id', $records[2]->id)
            ->where('categories.currentPage', 2)->where('categories.hasMorePages', false));
});

test('the ordinary category empty state coexists with the permanent category', function () {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->has('categories.data', 0)->has('programmedCategories', 1)->where('programmedCategories.0.is_sale_completed', true)->where('initialColor', '#6d54b5'));
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(0);
});

test('the category database constraints reject duplicate colors and orphan ownership', function () {
    $category = LeadCategory::factory()->create(['color' => '#112233']);

    expect(fn () => LeadCategory::factory()->for($category->account)->create(['color' => '#112233']))
        ->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => LeadCategory::factory()->create(['account_id' => 999999]))->toThrow(QueryException::class);
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(1);
});

test('unrelated unique constraint failures are reported instead of becoming color errors', function () {
    $membership = Membership::factory()->create();
    LeadCategory::factory()->for($membership->account)->create(['name' => 'Duplicada', 'color' => '#112233']);
    Schema::table('lead_categories', fn (Blueprint $table) => $table->unique('name'));
    Exceptions::fake();

    $this->actingAs($membership->user)->postJson(route('categories.store'), ['name' => 'Duplicada', 'color' => '#445566'])
        ->assertServerError();

    Exceptions::assertReported(UniqueConstraintViolationException::class);
    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe(1);
});

test('category creation requires a matching csrf token', function (?string $token, int $status) {
    $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->withSession(['_token' => 'category-browser-token'])
        ->withHeader('Sec-Fetch-Site', 'cross-site')->post(route('categories.store'), [
            '_token' => $token, 'name' => 'Protegida', 'color' => '#112233',
        ])->assertStatus($status);

    expect(LeadCategory::query()->whereNull('system_key')->count())->toBe($status === 302 ? 1 : 0);
})->with(['missing' => [null, 419], 'foreign session' => ['other-token', 419], 'valid' => ['category-browser-token', 302]]);

test('category mutation routes remain unavailable', function (string $method) {
    $membership = Membership::factory()->create();
    $category = LeadCategory::factory()->for($membership->account)->create(['name' => 'Intacta']);

    $this->actingAs($membership->user)->call($method, '/categorias', ['name' => 'Modificada'])
        ->assertMethodNotAllowed();
    $this->assertModelExists($category);
    expect($category->fresh()->name)->toBe('Intacta');
})->with(['PATCH', 'PUT', 'DELETE']);
