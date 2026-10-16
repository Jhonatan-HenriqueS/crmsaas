<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    public function view(User $user, Account $account): Response
    {
        return $user->ownedAccounts()->whereKey($account->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Account $account): Response
    {
        return $this->view($user, $account);
    }
}
