<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ParseGoogleMapsLink
{
    public const HOSTS = ['google.com', 'www.google.com', 'google.com.br', 'www.google.com.br', 'maps.google.com', 'goo.gl', 'maps.app.goo.gl'];

    private const SHORT_HOSTS = ['goo.gl', 'maps.app.goo.gl'];

    private const PATTERNS = [
        '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
        '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
        '/[?&](?:q|ll)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
    ];

    public static function allows(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::HOSTS, true);
    }

    /** @return array{latitude: string, longitude: string}|null */
    public function handle(string $url): ?array
    {
        for ($redirects = 0; $redirects <= 3 && self::allows($url); $redirects++) {
            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, rawurldecode($url), $matches) && abs((float) $matches[1]) <= 90 && abs((float) $matches[2]) <= 180) {
                    return ['latitude' => number_format((float) $matches[1], 7, '.', ''), 'longitude' => number_format((float) $matches[2], 7, '.', '')];
                }
            }

            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::SHORT_HOSTS, true)) {
                return null;
            }

            try {
                $url = Http::withoutRedirecting()->connectTimeout(3)->timeout(5)->get($url)->header('Location');
            } catch (ConnectionException) {
                return null;
            }
        }

        return null;
    }
}
