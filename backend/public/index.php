<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// The Vercel build may preload a stale provider manifest. Re-register the
// view provider so its finder/engine bindings are present before error
// handling or the welcome route tries to render a view.
$app->register(\Illuminate\View\ViewServiceProvider::class, true);

// Keep the incoming public URL when Vercel rewrites it to api/index.php.
// The rewrite passes the original path as an internal query parameter because
// PHP runtimes may expose the function path instead of the requested path.
if (getenv('VERCEL') && isset($_GET['__vercel_path']) && is_string($_GET['__vercel_path'])) {
    $requestPath = '/' . ltrim($_GET['__vercel_path'], '/');
    unset($_GET['__vercel_path']);

    $_SERVER['REQUEST_URI'] = $requestPath;
    $_SERVER['PATH_INFO'] = $requestPath;
    $_SERVER['SCRIPT_NAME'] = '/';
    $_SERVER['PHP_SELF'] = '/';
    $_SERVER['QUERY_STRING'] = http_build_query($_GET);
}

$app->handleRequest(Request::capture());
