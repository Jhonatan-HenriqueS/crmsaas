<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class ParseGoogleMapsLink
{
    public const HOSTS = ['google.com', 'www.google.com', 'google.com.br', 'www.google.com.br', 'maps.google.com', 'goo.gl', 'maps.app.goo.gl'];

    private const SHORT_HOSTS = ['goo.gl', 'maps.app.goo.gl'];

    private const GEOCODER = 'https://nominatim.openstreetmap.org/search';

    private const PATTERNS = [
        '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
        '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
        '/\/maps\/(?:search|place)\/(-?\d+(?:\.\d+)?),[\s+]*(-?\d+(?:\.\d+)?)/',
        '/[?&](?:q|ll)=(-?\d+(?:\.\d+)?),[\s+]*(-?\d+(?:\.\d+)?)/',
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
                if (preg_match($pattern, rawurldecode($url), $matches) && ($location = $this->location((float) $matches[1], (float) $matches[2])) !== null) {
                    return $location;
                }
            }

            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::SHORT_HOSTS, true)) {
                return $this->geocode($url);
            }

            try {
                $url = Http::withoutRedirecting()->connectTimeout(3)->timeout(5)->get($url)->header('Location');
            } catch (ConnectionException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Links shared from the Google Maps app only carry the place name and address.
     *
     * @return array{latitude: string, longitude: string}|null
     */
    private function geocode(string $url): ?array
    {
        if (! preg_match('#/maps/(?:place|search)/([^/?]+)#', (string) parse_url($url, PHP_URL_PATH), $matches)) {
            return null;
        }
        $place = Str::squish(urldecode($matches[1]));
        $queries = array_values(array_unique(array_filter([$place, preg_replace('/^\D+(?=\d)/u', '', $place), Str::after($place, ', ')])));

        foreach ($queries as $attempt => $query) {
            if ($attempt > 0) {
                Sleep::for(1)->second();
            }

            try {
                $result = Http::connectTimeout(3)->timeout(5)->acceptJson()
                    ->withUserAgent(config('app.name').' ('.config('app.url').')')
                    ->get(self::GEOCODER, ['format' => 'jsonv2', 'limit' => 1, 'q' => $query])->json('0');
            } catch (ConnectionException) {
                return null;
            }

            if (is_array($result) && is_numeric($result['lat'] ?? null) && is_numeric($result['lon'] ?? null)) {
                return $this->location((float) $result['lat'], (float) $result['lon']);
            }
        }

        return null;
    }

    /** @return array{latitude: string, longitude: string}|null */
    private function location(float $latitude, float $longitude): ?array
    {
        return abs($latitude) <= 90 && abs($longitude) <= 180
            ? ['latitude' => number_format($latitude, 7, '.', ''), 'longitude' => number_format($longitude, 7, '.', '')]
            : null;
    }
}
