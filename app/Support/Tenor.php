<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Tenor
{
    public static function configured(): bool
    {
        return (string) config('tenor.api_key') !== '';
    }

    public static function trending(): array
    {
        $ttl = config('tenor.ttl', 300);

        return Cache::remember('api.tenor.trending.'.app()->getLocale(), $ttl, fn () => self::fetch('trending', '', 0));
    }

    public static function search(string $query, int $pos = 0): array
    {
        $q = mb_substr(trim($query), 0, 80);
        $pos = max(0, $pos);

        if ($q === '') {
            return ['results' => [], 'next' => 0, 'source' => 'tenor'];
        }

        $ttl = config('tenor.ttl', 300);
        $key = 'api.tenor.search.'.app()->getLocale().'.'.md5(mb_strtolower($q).'.'.$pos);

        return Cache::remember($key, $ttl, fn () => self::fetch('search', $q, $pos));
    }

    protected static function fetch(string $action, string $query, int $pos): array
    {
        if (! self::configured()) {
            return ['results' => [], 'next' => 0, 'source' => 'tenor', 'error' => 'no_key'];
        }

        try {
            $response = self::request($action, $query, $pos);

            if (! $response->ok()) {
                return ['results' => [], 'next' => 0, 'source' => 'tenor', 'error' => 'http_'.$response->status()];
            }

            return [
                'results' => self::normalize($response->json('results', [])),
                'next' => $pos + count($response->json('results', [])),
                'source' => 'tenor',
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return ['results' => [], 'next' => 0, 'source' => 'tenor', 'error' => 'http_'.$e->response->status()];
        } catch (\Throwable) {
            return ['results' => [], 'next' => 0, 'source' => 'tenor', 'error' => 'exception'];
        }
    }

    protected static function request(string $action, string $query, int $pos): Response
    {
        $params = [
            'key' => (string) config('tenor.api_key'),
            'limit' => 20,
            'media_filter' => 'basic',
            'contentfilter' => 'medium',
            'locale' => (string) config('tenor.locale', 'fr_FR'),
        ];

        if ($action === 'search') {
            $params['q'] = $query;
        }

        if ($pos > 0) {
            $params['pos'] = $pos;
        }

        $url = rtrim((string) config('tenor.base_url', 'https://tenor.com/v2'), '/').'/'.$action;

        return Http::timeout(config('tenor.timeout', 10.0))
            ->retry(2, 250)
            ->get($url, $params);
    }

    protected static function normalize(array $results): array
    {
        $items = [];

        foreach ($results as $r) {
            $formats = $r['media_formats'] ?? [];
            $gif = $formats['gif'] ?? $formats['mediumgif'] ?? [];
            $tiny = $formats['tinygif'] ?? $gif;
            $preview = $formats['preview'] ?? $tiny;
            $mp4 = $formats['mp4'] ?? $formats['loopedmp4'] ?? [];

            if (empty($gif['url'])) {
                continue;
            }

            $items[] = [
                'url' => $gif['url'],
                'preview' => $preview['url'] ?? $tiny['url'] ?? '',
                'mp4' => $mp4['url'] ?? '',
                'alt' => $r['content_description'] ?? $r['id'] ?? 'sticker',
            ];
        }

        return $items;
    }
}
