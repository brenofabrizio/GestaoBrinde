<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\HandleCors;
use App\Services\EnsurePermission;

// Vercel functions are stateless. The web entry point only serves the welcome
// page, and API routes do not use Laravel sessions, so do not let the default
// database session driver block every request when the database is unavailable.
if (getenv('VERCEL') && getenv('SESSION_DRIVER') === false) {
    putenv('SESSION_DRIVER=array');
    $_ENV['SESSION_DRIVER'] = 'array';
    $_SERVER['SESSION_DRIVER'] = 'array';
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        $middleware->alias(['permission' => EnsurePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(static function (\Throwable $exception): void {
            error_log(sprintf(
                '[app-exception] %s: %s',
                get_class($exception),
                mb_substr($exception->getMessage(), 0, 500),
            ));
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
