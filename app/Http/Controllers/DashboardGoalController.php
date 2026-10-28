<?php

namespace App\Http\Controllers;

use App\Actions\FlashCrudSuccess;
use App\Http\CurrentAccount;
use App\Http\Requests\UpdateAccountGoalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DashboardGoalController extends Controller
{
    public function update(UpdateAccountGoalRequest $request, CurrentAccount $currentAccount): RedirectResponse
    {
        $account = $currentAccount->get();
        $account->fill($request->goal());
        if ($account->isDirty()) {
            abort_unless($account->saveOrFail(), 409);
            FlashCrudSuccess::updated('Meta', feminine: true, verb: 'salva');
        }

        return to_route('dashboard');
    }

    public function destroy(CurrentAccount $currentAccount): RedirectResponse
    {
        $account = $currentAccount->get();
        Gate::authorize('update', $account);
        $account->fill(['goal_amount' => null, 'goal_deadline' => null]);
        if ($account->isDirty()) {
            abort_unless($account->saveOrFail(), 409);
            FlashCrudSuccess::deleted('Meta', feminine: true);
        }

        return to_route('dashboard');
    }
}
