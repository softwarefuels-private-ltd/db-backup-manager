<?php

use Illuminate\Support\Facades\Route;

// SPA entrypoint - render Vue 3 application shell
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
