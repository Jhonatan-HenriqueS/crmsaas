<?php

namespace App\Http\Middleware;

use App\Http\CurrentAccount;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->remove(CurrentAccount::class);
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $accounts = $user->ownedAccounts()->limit(2)->get();

        abort_unless($accounts->count() === 1, 404);

        $request->attributes->set(CurrentAccount::class, $accounts->sole());

        try {
            return $next($request);
        } finally {
            $request->attributes->remove(CurrentAccount::class);
        }
    }
}
