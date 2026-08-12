<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Tenor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StickerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(Tenor::trending());
        }

        return response()->json(Tenor::search($q, (int) $request->query('pos', 0)));
    }
}
