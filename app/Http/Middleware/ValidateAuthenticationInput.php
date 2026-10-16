<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthenticationInput
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $request->validate($request->routeIs('password.confirm.store') ? [
            'password' => ['required', 'string', 'max:128'],
        ] : [
            'email' => $request->routeIs('register.store')
                ? ['sometimes', 'nullable', 'string']
                : ['required', 'string', 'email', 'max:255'],
            ...($request->routeIs('password.update') ? ['token' => ['required', 'string', 'max:255']] : []),
        ]);

        if (config('fortify.lowercase_usernames') && is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }

        return $next($request);
    }
}
