<?php

namespace App\Http\Controllers;

use App\Actions\FlashCrudSuccess;
use App\Actions\SaveLead;
use App\Http\CurrentAccount;
use App\Http\Requests\CompleteLeadRequest;
use App\Http\Requests\MutateLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadLocationRequest;
use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request, CurrentAccount $currentAccount): Response|JsonResponse|RedirectResponse
    {
        $account = $currentAccount->get();
        Gate::authorize('viewAny', [Lead::class, $account]);

        $selectedCategory = $this->selectedCategory($request, $account);
        $query = $account->leads()->with('category:id,name,color,system_key')->orderByDesc('id');

        if ($selectedCategory !== null) {
            $query->where('category_id', $selectedCategory->id);
        }

        $leads = $query
            ->paginate(12, ['id', 'category_id', 'name', 'description', 'first_contact_date', 'next_contact_date', 'potential_amount', 'charged_amount', 'maps_url', 'latitude', 'longitude']);

        if ($leads->currentPage() > $leads->lastPage()) {
            return to_route('leads.index', array_filter(['page' => $leads->lastPage(), 'category' => $selectedCategory?->id]));
        }

        $props = [
            'selectedCategory' => $selectedCategory,
            'completionCategory' => $account->leadCategories()->where('system_key', LeadCategory::SALE_COMPLETED)
                ->first(['id', 'name', 'color', 'system_key']),
            'leads' => [
                'data' => $leads->items(),
                'currentPage' => $leads->currentPage(),
                'hasMorePages' => $leads->hasMorePages(),
                'total' => $leads->total(),
            ],
        ];

        return $request->wantsJson() ? response()->json($props) : Inertia::render('leads/index', $props);
    }

    public function categoryOptions(Request $request, CurrentAccount $currentAccount): JsonResponse
    {
        $account = $currentAccount->get();
        Gate::authorize('create', [Lead::class, $account]);
        $request->validate(['cursor' => [
            'bail', 'nullable', 'string', 'max:1024',
            static function (string $attribute, mixed $value, Closure $fail): void {
                $parameters = json_decode(base64_decode(strtr($value, '-_', '+/'), true) ?: '', true);
                if (! is_array($parameters) || count($parameters) !== 2
                    || ! isset($parameters['id'], $parameters['_pointsToNextItems'])
                    || ! is_int($parameters['id']) || $parameters['id'] < 1
                    || ! is_bool($parameters['_pointsToNextItems'])) {
                    $fail('A paginação das etapas é inválida. Abra o seletor novamente.');
                }
            },
        ]]);

        $categories = $account->leadCategories()->orderByDesc('id')
            ->cursorPaginate(30, ['id', 'name', 'color', 'system_key']);

        return response()->json([
            'data' => $categories->items(),
            'nextCursor' => $categories->nextCursor()?->encode(),
        ]);
    }

    public function show(CurrentAccount $currentAccount, string $lead): JsonResponse
    {
        $account = $currentAccount->get();
        Gate::authorize('viewAny', [Lead::class, $account]);
        $record = $account->leads()->with('category:id,name,color,system_key')->findOrFail($lead);
        Gate::authorize('view', [$record, $account]);

        return response()->json(['lead' => $record]);
    }

    public function store(StoreLeadRequest $request, CurrentAccount $currentAccount, SaveLead $saveLead): RedirectResponse|JsonResponse
    {
        $selectedCategory = $this->selectedCategory($request, $currentAccount->get());
        $lead = $saveLead->handle($currentAccount->get(), [...$request->safe()->except(['confirm_completion', 'confirm_reopening', 'category']), ...$request->mapsLocation()],
            confirmCompletion: $request->boolean('confirm_completion'));

        if ($request->wantsJson()) {
            return response()->json(['lead' => $lead->load('category:id,name,color,system_key')], 201);
        }

        FlashCrudSuccess::created('Lead');

        return to_route('leads.index', array_filter(['category' => $selectedCategory?->id]));
    }

    public function update(MutateLeadRequest $request, CurrentAccount $currentAccount, SaveLead $saveLead): RedirectResponse
    {
        $selectedCategory = $this->selectedCategory($request, $currentAccount->get());
        $lead = $saveLead->handle($currentAccount->get(), [...$request->safe()->except(['page', 'confirm_completion', 'confirm_reopening', 'category']), ...$request->mapsLocation()],
            $request->lead(), $request->boolean('confirm_completion'), $request->boolean('confirm_reopening'));

        if ($lead->wasChanged()) {
            FlashCrudSuccess::updated('Lead', verb: 'atualizado');
        }

        return to_route('leads.index', array_filter(['page' => $request->integer('page', 1), 'category' => $selectedCategory?->id]));
    }

    public function complete(CompleteLeadRequest $request, CurrentAccount $currentAccount, SaveLead $saveLead): RedirectResponse
    {
        $account = $currentAccount->get();
        $selectedCategory = $this->selectedCategory($request, $account);
        $category = $account->leadCategories()->where('system_key', LeadCategory::SALE_COMPLETED)->firstOrFail();
        $lead = $saveLead->handle($account, ['category_id' => $category->id, ...$request->safe()->only('charged_amount')], $request->lead(), $request->boolean('confirm_completion'));
        if ($lead->wasChanged()) {
            FlashCrudSuccess::updated('Venda', feminine: true, verb: 'finalizada');
        }

        return to_route('leads.index', array_filter(['page' => $request->integer('page', 1), 'category' => $selectedCategory?->id]));
    }

    public function location(UpdateLeadLocationRequest $request, CurrentAccount $currentAccount, SaveLead $saveLead): RedirectResponse
    {
        $selectedCategory = $this->selectedCategory($request, $currentAccount->get());
        $lead = $saveLead->handle($currentAccount->get(), ['category_id' => $request->lead()->category_id, ...$request->safe()->only('maps_url'), ...$request->mapsLocation()], $request->lead());
        if ($lead->wasChanged()) {
            FlashCrudSuccess::updated('Localização', feminine: true, verb: 'salva');
        }

        return to_route('leads.index', array_filter(['page' => $request->integer('page', 1), 'category' => $selectedCategory?->id]));
    }

    public function destroy(MutateLeadRequest $request, CurrentAccount $currentAccount): RedirectResponse
    {
        $selectedCategory = $this->selectedCategory($request, $currentAccount->get());
        abort_unless($request->lead()->deleteOrFail(), 409);
        FlashCrudSuccess::deleted('Lead');

        return to_route('leads.index', array_filter(['page' => $request->integer('page', 1), 'category' => $selectedCategory?->id]));
    }

    private function selectedCategory(Request $request, Account $account): ?LeadCategory
    {
        $validated = Validator::make($request->query(), [
            'category' => ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ], ['category.*' => 'Selecione uma categoria válida para filtrar os leads.'])->validate();

        return isset($validated['category'])
            ? $account->leadCategories()->whereKey($validated['category'])->firstOrFail(['id', 'name', 'color', 'system_key'])
            : null;
    }
}
