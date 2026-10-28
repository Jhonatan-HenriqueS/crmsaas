<?php

namespace App\Http\Controllers;

use App\Http\CurrentAccount;
use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const PERIOD_DAYS = ['week' => 7, 'fortnight' => 14, 'month' => 30];

    public function index(Request $request, CurrentAccount $currentAccount): Response
    {
        $account = $currentAccount->get();
        Gate::authorize('viewAny', [Lead::class, $account]);
        $validated = $request->validate([
            'period' => ['sometimes', 'string', Rule::in(array_keys(self::PERIOD_DAYS))],
        ], ['period.in' => 'Selecione um período válido: última semana, últimas 2 semanas ou último mês.']);
        $period = $validated['period'] ?? 'week';
        $now = CarbonImmutable::now(config('reporting.timezone'));

        return Inertia::render('dashboard', [
            'overview' => fn (): array => $this->overview($account, $now),
            'newLeads' => fn (): array => [
                'period' => $period,
                'count' => $account->leads()
                    ->where('created_at', '>=', $this->storageBoundary($now->startOfDay()->subDays(self::PERIOD_DAYS[$period] - 1)))
                    ->where('created_at', '<', $this->storageBoundary($now->startOfDay()->addDay()))
                    ->count(),
            ],
        ]);
    }

    /**
     * @return array{
     *     source: string, totalLeads: int, inProgressLeads: int, soldLeads: int, soldPercentage: float, soldRevenue: string,
     *     reportingTimezone: string, weeklyTotal: int,
     *     categories: list<array{id: int, name: string, color: string, count: int, percentage: float}>,
     *     weeklyIntake: list<array{day: string, date: string, count: int}>
     * }
     */
    private function overview(Account $account, CarbonImmutable $now): array
    {
        $total = $account->leads()->count();
        $records = $account->leadCategories()->select(['id', 'name', 'color', 'system_key'])
            ->withCount(['leads' => fn (Builder $query) => $query->where('account_id', $account->id)])
            ->orderByDesc('leads_count')->orderByDesc('id')->get();
        $soldCategory = $records->firstWhere('system_key', LeadCategory::SALE_COMPLETED);
        $sold = (int) ($soldCategory?->getAttribute('leads_count') ?? 0);
        $soldCents = $soldCategory === null ? 0 : (int) $account->leads()->toBase()->where('category_id', $soldCategory->id)
            ->selectRaw('COALESCE(SUM(CAST(ROUND(charged_amount * 100) AS BIGINT)), 0) AS cents')->value('cents');
        $categories = $records
            ->map(static function (LeadCategory $category) use ($total): array {
                $count = (int) $category->getAttribute('leads_count');

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'count' => $count,
                    'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                ];
            })->all();
        $weekly = $this->weeklyIntake($account, $now);

        return [
            'source' => 'account',
            'totalLeads' => $total,
            'inProgressLeads' => 0,
            'soldLeads' => $sold,
            'soldPercentage' => $total > 0 ? round($sold / $total * 100, 1) : 0.0,
            'soldRevenue' => sprintf('%d.%02d', intdiv($soldCents, 100), $soldCents % 100),
            'reportingTimezone' => $now->getTimezone()->getName(),
            'weeklyTotal' => $weekly['total'],
            'categories' => array_values($categories),
            'weeklyIntake' => $weekly['days'],
        ];
    }

    /** @return array{total: int, days: list<array{day: string, date: string, count: int}>} */
    private function weeklyIntake(Account $account, CarbonImmutable $now): array
    {
        $start = $now->startOfWeek(CarbonInterface::MONDAY);
        $query = $account->leads()->toBase()
            ->where('created_at', '>=', $this->storageBoundary($start))
            ->where('created_at', '<', $this->storageBoundary($start->addWeek()))
            ->selectRaw('COUNT(*) AS weekly_total');
        $labels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

        foreach ($labels as $index => $label) {
            $day = $start->addDays($index);
            $query->selectRaw("COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) AS day_{$index}", [
                $this->storageBoundary($day), $this->storageBoundary($day->addDay()),
            ]);
        }

        $counts = $query->first();
        $days = [];
        foreach ($labels as $index => $label) {
            $days[] = [
                'day' => $label,
                'date' => $start->addDays($index)->toDateString(),
                'count' => (int) ($counts->{'day_'.$index} ?? 0),
            ];
        }

        return ['total' => (int) ($counts->weekly_total ?? 0), 'days' => $days];
    }

    private function storageBoundary(CarbonImmutable $date): string
    {
        return $date->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
