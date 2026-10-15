<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerification
{
    public function __construct(private EnsureEmailIsVerified $verification) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if ($request->user() && ! config('auth.email_verification_required')) {
            return $next($request);
        }

        return $this->verification->handle($request, $next, $redirectToRoute);
    }
}
