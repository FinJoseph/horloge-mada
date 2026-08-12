<?php

use App\Http\Controllers\Api\EmojiController;
use App\Http\Controllers\Api\StickerController;
use Illuminate\Support\Facades\Route;

Route::get('/time', function () {
    $tz = (string) request()->query('tz', config('shift.timezone'));

    try {
        new DateTimeZone($tz);
    } catch (Throwable) {
        $tz = config('shift.timezone');
    }

    $now = now();

    return response()->json([
        'unix' => (float) $now->format('U.u'),
        'iso' => $now->toIso8601String(),
        'tz' => $tz,
    ])->header('Cache-Control', 'no-store, private');
});

Route::prefix('emojis')->group(function () {
    Route::get('/', [EmojiController::class, 'index']);
    Route::get('/groups', [EmojiController::class, 'groups']);
});

Route::get('/stickers', [StickerController::class, 'index']);
