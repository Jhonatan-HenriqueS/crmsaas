<?php

namespace App\Http\Controllers;

use App\Actions\FlashCrudSuccess;
use App\Http\CurrentAccount;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request, CurrentAccount $currentAccount): Response|JsonResponse
    {
        $account = $currentAccount->get();
        Gate::authorize('viewAny', [Lead::class, $account]);

        $leads = $account->leads()->with('category:id,name,color')->orderByDesc('id')
            ->paginate(12, ['id', 'category_id', 'name', 'description', 'first_contact_date', 'next_contact_date', 'potential_amount', 'charged_amount']);

        $props = [
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
            ->cursorPaginate(30, ['id', 'name', 'color']);

        return response()->json([
            'data' => $categories->items(),
            'nextCursor' => $categories->nextCursor()?->encode(),
        ]);
    }

    public function show(CurrentAccount $currentAccount, string $lead): JsonResponse
    {
        $account = $currentAccount->get();
        Gate::authorize('viewAny', [Lead::class, $account]);
        $record = $account->leads()->with('category:id,name,color')->findOrFail($lead);
        Gate::authorize('view', [$record, $account]);

        return response()->json(['lead' => $record]);
    }

    public function store(StoreLeadRequest $request, CurrentAccount $currentAccount): RedirectResponse|JsonResponse
    {
        $lead = $currentAccount->get()->leads()->make($request->validated());
        abort_unless($lead->saveOrFail(), 409);

        if ($request->wantsJson()) {
            return response()->json(['lead' => $lead->load('category:id,name,color')], 201);
        }

        FlashCrudSuccess::created('Lead');

        return to_route('leads.index');
    }
}
