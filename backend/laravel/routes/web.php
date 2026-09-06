<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/up', function (Request $request) {
    if ($request->expectsJson()) {
        return response()->json(['status' => 'up'])->header('Cache-Control', 'no-store');
    }

    return response(View::file(__DIR__.'/../vendor/laravel/framework/src/Illuminate/Foundation/resources/health-up.blade.php', ['exception' => null]))->header('Cache-Control', 'no-store');
});
