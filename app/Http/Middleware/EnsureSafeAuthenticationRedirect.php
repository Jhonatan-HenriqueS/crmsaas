<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSafeAuthenticationRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $intended = $request->session()->get('url.intended');

        if ($intended !== null) {
            $origin = $request->getSchemeAndHttpHost();

            if (is_string($intended) && str_starts_with($intended, $origin.'/')) {
                $intended = substr($intended, strlen($origin));
            }

            if (! is_string($intended)
                || ! str_starts_with($intended, '/')
                || str_starts_with($intended, '//')
                || preg_match('/[\x00-\x20\x7f\\\\]/', $intended)) {
                $request->session()->forget('url.intended');
            } else {
                $request->session()->put('url.intended', $intended);
            }
        }

        return $next($request);
    }
}
