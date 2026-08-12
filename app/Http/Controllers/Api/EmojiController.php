<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmojiController extends Controller
{
    public const GROUPS = [
        0 => ['id' => 0, 'emoji' => '😀', 'label' => 'visages et émotions'],
        1 => ['id' => 1, 'emoji' => '👋', 'label' => 'personnes et gestes'],
        2 => ['id' => 2, 'emoji' => '🏻', 'label' => 'teints de peau et composants'],
        3 => ['id' => 3, 'emoji' => '🐵', 'label' => 'animaux et nature'],
        4 => ['id' => 4, 'emoji' => '🍎', 'label' => 'nourriture et boissons'],
        5 => ['id' => 5, 'emoji' => '🌍', 'label' => 'voyage et lieux'],
        6 => ['id' => 6, 'emoji' => '🎃', 'label' => 'activités'],
        7 => ['id' => 7, 'emoji' => '🕶️', 'label' => 'objets'],
        8 => ['id' => 8, 'emoji' => '🚻', 'label' => 'symboles'],
        9 => ['id' => 9, 'emoji' => '🏁', 'label' => 'drapeaux'],
    ];

    public function index(Request $request): JsonResponse
    {
        $emojis = $this->all();
        $q = mb_strtolower(trim((string) $request->query('q', '')));

        if ($request->has('group') && is_numeric($request->query('group'))) {
            $group = (int) $request->query('group');
            $emojis = array_values(array_filter(
                $emojis,
                fn ($e) => $e['group'] === $group,
            ));
        }

        if ($request->has('tone') && $request->query('tone') !== '') {
            $tone = (string) $request->query('tone');
            $emojis = array_values(array_filter(
                $emojis,
                fn ($e) => $tone === 'none' ? $e['tone'] === null : (int) $e['tone'] === (int) $tone,
            ));
        }

        if ($q !== '') {
            $emojis = array_values(array_filter($emojis, function ($e) use ($q) {
                foreach ($e['shortcodes'] as $s) {
                    if (str_contains(mb_strtolower($s), $q)) {
                        return true;
                    }
                }
                if (str_contains(mb_strtolower($e['annotation']), $q)) {
                    return true;
                }
                foreach ($e['tags'] as $t) {
                    if (str_contains(mb_strtolower($t), $q)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($request->has('page') || $request->has('per_page')) {
            $total = count($emojis);
            $perPage = max(1, min((int) $request->query('per_page', 500), 2000));
            $page = max(1, (int) $request->query('page', 1));
            $chunks = array_chunk($emojis, $perPage);

            return response()->json([
                'data' => $chunks[$page - 1] ?? [],
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'pages' => count($chunks),
                ],
            ]);
        }

        return response()->json($emojis);
    }

    public function groups(): JsonResponse
    {
        return response()->json(array_values(self::GROUPS));
    }

    protected function all(): array
    {
        $path = database_path('data/emoji.json');
        $key = 'api.emojis.v'.md5((string) filemtime($path));

        return Cache::rememberForever($key, function () use ($path) {
            $raw = json_decode((string) file_get_contents($path), true);
            $emojis = [];

            foreach ($raw as $e) {
                $emojis[] = $this->normalize($e);
                foreach ($e['skins'] ?? [] as $skin) {
                    $variation = $e;
                    $variation['emoji'] = $skin['emoji'];
                    $variation['tone'] = $skin['tone'];
                    unset($variation['skins']);
                    $emojis[] = $this->normalize($variation);
                }
            }

            return $emojis;
        });
    }

    protected function normalize(array $e): array
    {
        return [
            'emoji' => $e['emoji'],
            'shortcodes' => $e['shortcodes'] ?? [],
            'annotation' => $e['annotation'] ?? '',
            'tags' => $e['tags'] ?? [],
            'group' => $e['group'] ?? null,
            'tone' => $e['tone'] ?? null,
            'version' => $e['version'] ?? null,
            'hex' => $this->toHex($e['emoji']),
        ];
    }

    protected function toHex(string $emoji): string
    {
        $hex = [];
        $length = mb_strlen($emoji);

        for ($i = 0; $i < $length; $i++) {
            $hex[] = strtoupper(dechex(mb_ord(mb_substr($emoji, $i, 1))));
        }

        return implode('-', $hex);
    }
}
