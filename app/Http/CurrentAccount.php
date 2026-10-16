<?php

namespace App\Http;

use App\Models\Account;
use Illuminate\Http\Request;

class CurrentAccount
{
    public function __construct(private readonly Request $request) {}

    public function get(): Account
    {
        $account = $this->request->attributes->get(self::class);

        abort_unless($account instanceof Account, 404);

        return $account;
    }
}
