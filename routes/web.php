<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::clock');

Route::get('/up', fn () => response()->json(['status' => 'ok']));
