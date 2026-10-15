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
            $destination = $this->internalDestination($request, $intended);

            if ($destination === null) {
                $request->session()->forget('url.intended');
            } else {
                $request->session()->put('url.intended', $destination);
            }
        }

        return $next($request);
    }

    private function internalDestination(Request $request, mixed $intended): ?string
    {
        if (! is_string($intended) || preg_match('/[\x00-\x20\x7f\\\\]/', $intended)) {
            return null;
        }

        $url = parse_url($intended);

        if ($url === false || isset($url['user']) || isset($url['pass'])) {
            return null;
        }

        if (isset($url['scheme']) || isset($url['host']) || isset($url['port'])) {
            $scheme = strtolower($url['scheme'] ?? '');
            $port = $url['port'] ?? ($scheme === 'https' ? 443 : 80);

            if (! in_array($scheme, ['http', 'https'], true)
                || $scheme !== $request->getScheme()
                || strtolower($url['host'] ?? '') !== strtolower($request->getHost())
                || $port !== $request->getPort()) {
                return null;
            }
        }

        $path = $url['path'] ?? '/';
        $decodedPath = rawurldecode($path);

        if (! str_starts_with($path, '/') || str_starts_with($decodedPath, '//')
            || preg_match('/[\x00-\x1f\x7f\\\\]/', $decodedPath)) {
            return null;
        }

        return $path
            .(isset($url['query']) ? '?'.$url['query'] : '')
            .(isset($url['fragment']) ? '#'.$url['fragment'] : '');
    }
}
