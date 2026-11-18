<?php

namespace App\Http\Controllers;

use App\Http\CurrentAccount;
use App\Http\Requests\UpdateSoldCardViewRequest;
use Illuminate\Http\RedirectResponse;

class DashboardSoldViewController extends Controller
{
    public function update(UpdateSoldCardViewRequest $request, CurrentAccount $currentAccount): RedirectResponse
    {
        $account = $currentAccount->get();
        $account->fill(['sold_card_view' => $request->validated('view')]);
        if ($account->isDirty()) {
            abort_unless($account->saveOrFail(), 409);
        }

        return to_route('dashboard');
    }
}
