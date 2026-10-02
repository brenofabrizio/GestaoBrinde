<?php

use Illuminate\Support\Facades\Route;

Route::get('/__debug-routes', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'path' => $request->path(),
        'uri' => $request->getRequestUri(),
        'routes' => collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->values(),
    ]);
});

Route::get('/', function () {
    return view('welcome');
});
