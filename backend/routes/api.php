<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OperationalDashboardController;
use App\Http\Controllers\Api\TradeRequestController;
use App\Services\EnsurePasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'ok' => true,
    'service' => 'gestao-brindes-backend',
    'database' => config('database.default'),
    'time' => now()->toIso8601String(),
]));

Route::get('/v1/readiness', function () {
    $checks = [];
    $ready = true;

    try {
        DB::connection()->getPdo();
        DB::select('select 1');
        $checks['database'] = ['status' => 'ok', 'driver' => config('database.default')];
    } catch (Throwable) {
        $checks['database'] = ['status' => 'failed', 'driver' => config('database.default')];
        $ready = false;
    }

    $appKeyConfigured = is_string(config('app.key')) && trim((string) config('app.key')) !== '';
    $checks['app_key'] = ['status' => $appKeyConfigured ? 'ok' : 'failed'];
    $ready = $ready && $appKeyConfigured;

    $origins = config('cors.allowed_origins', []);
    $corsConfigured = is_array($origins) && $origins !== [];
    $checks['cors'] = ['status' => $corsConfigured ? 'ok' : 'failed'];
    $ready = $ready && $corsConfigured;

    $disk = (string) config('filesystems.default');
    $storageConfigured = ! app()->environment('production') || $disk === 's3';
    $checks['storage'] = ['status' => $storageConfigured ? 'ok' : 'failed', 'disk' => $disk];
    $ready = $ready && $storageConfigured;

    return response()->json([
        'ready' => $ready,
        'service' => 'gestao-brindes-backend',
        'checks' => $checks,
    ], $ready ? 200 : 503);
});

Route::prefix('/v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,15');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,15');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,15');
});

Route::middleware(['auth:sanctum', EnsurePasswordChanged::class])->prefix('/v1')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [OperationalDashboardController::class, 'dashboard'])->middleware('permission:dashboard.view');
    Route::get('/reports/{type}', [OperationalDashboardController::class, 'report'])->middleware('permission:reports.view');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
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
    Route::get('/deliveries/{delivery}/pdf', [DeliveryController::class, 'pdf'])->middleware('permission:deliveries.view');
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
