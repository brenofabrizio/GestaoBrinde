<?php

use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TradeRequestController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'ok' => true,
    'service' => 'gestao-brindes-backend',
    'database' => config('database.default'),
    'time' => now()->toIso8601String(),
]));

Route::prefix('/v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('/v1')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/items', [InventoryController::class, 'index'])->middleware('permission:items.view');
    Route::get('/stock/movements', [InventoryController::class, 'movements'])->middleware('permission:stock.view');
    Route::post('/items/{item}/entry', [InventoryController::class, 'entry'])->middleware('permission:stock.entry');
    Route::post('/items/{item}/exit', [InventoryController::class, 'exit'])->middleware('permission:stock.exit');
    Route::get('/trade-requests', [TradeRequestController::class, 'index']);
    Route::post('/trade-requests', [TradeRequestController::class, 'store']);
    Route::get('/trade-requests/{tradeRequest}', [TradeRequestController::class, 'show']);
    Route::post('/trade-requests/{tradeRequest}/submit', [TradeRequestController::class, 'submit']);
    Route::post('/trade-requests/{tradeRequest}/approve', [TradeRequestController::class, 'approve']);
    Route::get('/deliveries', [DeliveryController::class, 'index'])->middleware('permission:deliveries.view');
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->middleware('permission:deliveries.view');
    Route::get('/deliveries/{delivery}/qr', [DeliveryController::class, 'qr'])->middleware('permission:deliveries.view');
    Route::post('/trade-requests/{tradeRequest}/deliver', [DeliveryController::class, 'store']);
    Route::get('/events', [EventController::class, 'index'])->middleware('permission:events.view');
    Route::get('/events/{event}', [EventController::class, 'show'])->middleware('permission:events.view');
    Route::post('/events', [EventController::class, 'store'])->middleware('permission:events.manage');
    Route::post('/events/{event}/open', [EventController::class, 'open'])->middleware('permission:events.manage');
    Route::post('/events/{event}/close', [EventController::class, 'close'])->middleware('permission:events.manage');
    Route::post('/events/{event}/allocations', [EventController::class, 'allocate'])->middleware('permission:events.manage');
});

Route::get('/v1/verify/{code}', [DeliveryController::class, 'verify'])
    ->middleware('signed')
    ->name('api.v1.verify');
