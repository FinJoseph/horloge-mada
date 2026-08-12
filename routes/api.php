<?php

use App\Http\Controllers\Api\EmojiController;
use Illuminate\Support\Facades\Route;

Route::prefix('emojis')->group(function () {
    Route::get('/', [EmojiController::class, 'index']);
    Route::get('/groups', [EmojiController::class, 'groups']);
});
