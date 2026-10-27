<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        Inertia::clearHistory();

        return $request->wantsJson()
            ? response()->noContent()
            : to_route('login');
    }
}
