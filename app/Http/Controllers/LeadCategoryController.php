<?php

namespace App\Http\Controllers;

use App\Actions\FlashCrudSuccess;
use App\Http\CurrentAccount;
use App\Http\Requests\MutateLeadCategoryRequest;
use App\Http\Requests\StoreLeadCategoryRequest;
use App\Models\Account;
use App\Models\LeadCategory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadCategoryController extends Controller
{
    public function index(CurrentAccount $currentAccount): Response
    {
        $account = $currentAccount->get();
        Gate::authorize('view', $account);

        $total = $account->leads()->count();
        $query = $account->leadCategories()->select(['id', 'name', 'color', 'system_key'])
            ->withCount(['leads' => fn (Builder $query) => $query->where('account_id', $account->id)])
            ->orderByDesc('id');
        $serialize = function (LeadCategory $category) use ($total, $account): array {
            $count = (int) $category->getAttribute('leads_count');

            return [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'is_sale_completed' => $category->is_sale_completed,
                'leads_count' => $count,
                'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                'deletion_confirmation' => $category->is_sale_completed ? null : Crypt::encryptString($this->deletionConfirmationData($account, $category, $count)),
            ];
        };
        $programmed = (clone $query)->whereNotNull('system_key')->get()->map($serialize)->all();
        $categories = (clone $query)->whereNull('system_key')->simplePaginate(12)->through($serialize);

        return Inertia::render('categories/index', [
            'categories' => [
                'data' => $categories->items(),
                'currentPage' => $categories->currentPage(),
                'hasMorePages' => $categories->hasMorePages(),
            ],
            'totalLeads' => $total,
            'programmedCategories' => $programmed,
            'initialColor' => $this->availableColor($account),
        ]);
    }

    public function store(StoreLeadCategoryRequest $request, CurrentAccount $currentAccount): RedirectResponse
    {
        try {
            $currentAccount->get()->leadCategories()->create($request->safe()->only(['name', 'color']));
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->columns !== ['account_id', 'color']) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'color' => 'Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.',
            ]);
        }

        FlashCrudSuccess::created('Categoria', feminine: true);

        return to_route('categories.index');
    }

    public function update(MutateLeadCategoryRequest $request): RedirectResponse
    {
        $category = $request->category();
        $category->fill($request->safe()->only(['name', 'color']));

        if ($category->isDirty()) {
            try {
                $category->save();
            } catch (UniqueConstraintViolationException $exception) {
                if ($exception->columns !== ['account_id', 'color']) {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'color' => 'Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.',
                ]);
            }

            FlashCrudSuccess::updated('Categoria', feminine: true);
        }

        return to_route('categories.index');
    }

    public function destroy(MutateLeadCategoryRequest $request, CurrentAccount $currentAccount): RedirectResponse
    {
        $account = $currentAccount->get();
        $id = $request->category()->id;
        DB::transaction(function () use ($request, $account, $id): void {
            $category = $account->leadCategories()->lockForUpdate()->findOrFail($id);
            abort_if($category->is_sale_completed, 403, 'A categoria de finalização não pode ser excluída.');
            $count = $account->leads()->where('category_id', $category->id)->count();

            try {
                $confirmation = Crypt::decryptString($request->validated('confirmation'));
            } catch (DecryptException) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Confirmação inválida. Atualize a página e revise a exclusão.',
                ]);
            }

            if (! hash_equals($this->deletionConfirmationData($account, $category, $count), $confirmation)) {
                throw ValidationException::withMessages([
                    'confirmation' => 'A categoria ou a quantidade de leads mudou. Revise os dados e confirme novamente.',
                ]);
            }

            abort_unless($category->deleteOrFail(), 409);
        }, attempts: 3);

        FlashCrudSuccess::deleted('Categoria', feminine: true);

        return to_route('categories.index');
    }

    private function deletionConfirmationData(Account $account, LeadCategory $category, int $count): string
    {
        return json_encode([$account->id, $category->id, $category->name, $count], JSON_THROW_ON_ERROR);
    }

    private function availableColor(Account $account): string
    {
        if (! $account->leadCategories()->where('color', '#6d54b5')->exists()) {
            return '#6d54b5';
        }

        $candidate = 0;

        foreach ($account->leadCategories()->select('color')->orderBy('color')->cursor() as $category) {
            if ((int) hexdec(substr($category->color, 1)) !== $candidate) {
                break;
            }

            $candidate++;
        }

        abort_if($candidate > 0xFFFFFF, 422, 'Não há mais cores HEX disponíveis nesta conta.');

        return sprintf('#%06x', $candidate);
    }
}
