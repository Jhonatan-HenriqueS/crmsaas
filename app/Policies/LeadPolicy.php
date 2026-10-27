<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class LeadPolicy
{
    public function viewAny(User $user, Account $account): Response
    {
        return Gate::forUser($user)->inspect('view', $account);
    }

    public function view(User $user, Lead $lead, Account $account): Response
    {
        return $lead->account_id === $account->id
            ? $this->viewAny($user, $account)
            : Response::denyAsNotFound();
    }

    public function create(User $user, Account $account): Response
    {
        return Gate::forUser($user)->inspect('update', $account);
    }

    public function update(User $user, Lead $lead, Account $account): Response
    {
        return $lead->account_id === $account->id
            ? Gate::forUser($user)->inspect('update', $account)
            : Response::denyAsNotFound();
    }

    public function delete(User $user, Lead $lead, Account $account): Response
    {
        return $this->update($user, $lead, $account);
    }
}
