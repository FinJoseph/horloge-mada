<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StickerApiTest extends TestCase
{
    public function test_trending_requires_key(): void
    {
        config(['tenor.api_key' => '']);

        $this->getJson('/api/stickers')
            ->assertOk()
            ->assertJsonPath('source', 'tenor')
            ->assertJsonPath('error', 'no_key')
            ->assertJsonCount(0, 'results');
    }

    public function test_trending_normalizes_tenor_results(): void
    {
        config(['tenor.api_key' => 'test-key']);

        Http::fake([
            'tenor.com/v2/*' => Http::response([
                'results' => [
                    [
                        'id' => 'sticker-1',
                        'content_description' => 'Un chat mignon',
                        'media_formats' => [
                            'gif' => ['url' => 'https://media.example/g.gif'],
                            'tinygif' => ['url' => 'https://media.example/t.gif'],
                            'preview' => ['url' => 'https://media.example/p.gif'],
                            'mp4' => ['url' => 'https://media.example/m.mp4'],
                        ],
                    ],
                    [
                        'id' => 'sticker-2',
                        'media_formats' => [
                            'mediumgif' => ['url' => 'https://media.example/2.gif'],
                        ],
                    ],
                ],
            ]),
        ]);

        Cache::forget('api.tenor.trending.fr');

        $this->getJson('/api/stickers')
            ->assertOk()
            ->assertJsonPath('source', 'tenor')
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.url', 'https://media.example/g.gif')
            ->assertJsonPath('results.0.mp4', 'https://media.example/m.mp4')
            ->assertJsonPath('results.1.url', 'https://media.example/2.gif')
            ->assertJsonMissingPath('error');
    }

    public function test_search_passes_query_and_paginates(): void
    {
        config(['tenor.api_key' => 'test-key']);

        Http::fake([
            'tenor.com/v2/*' => Http::response([
                'results' => [
                    ['id' => 'a', 'media_formats' => ['gif' => ['url' => 'https://media.example/a.gif']]],
                    ['id' => 'b', 'media_formats' => ['gif' => ['url' => 'https://media.example/b.gif']]],
                ],
            ]),
        ]);

        Cache::forget('api.tenor.search.fr.'.md5('chat.0'));

        $this->getJson('/api/stickers?q=chat')
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('next', 2);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && $request['q'] === 'chat'
                && $request['key'] === 'test-key';
        });
    }

    public function test_search_handles_http_error(): void
    {
        config(['tenor.api_key' => 'test-key']);

        Http::fake([
            'tenor.com/v2/*' => Http::response([], 500),
        ]);

        Cache::forget('api.tenor.search.fr.'.md5('erreur.0'));

        $this->getJson('/api/stickers?q=erreur')
            ->assertOk()
            ->assertJsonPath('error', 'http_500')
            ->assertJsonCount(0, 'results');
    }
}
