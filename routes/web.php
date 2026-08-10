<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::clock');

Route::get('/up', fn () => response()->json(['status' => 'ok']));

Route::get('/sitemap.xml', function () {
    $url = rtrim(config('app.url'), '/');

    return response()->view('sitemap', ['url' => $url])
        ->header('Content-Type', 'application/xml');
});
