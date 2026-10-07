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

// The Vercel PHP runtime strips `/api` from PATH_INFO when routing to the
// function. Laravel must receive the original public request path.
if (isset($_SERVER['REQUEST_URI'])) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (is_string($requestPath) && $requestPath !== '') {
        $_SERVER['PATH_INFO'] = $requestPath;
        $_SERVER['SCRIPT_NAME'] = '/';
        $_SERVER['PHP_SELF'] = '/';
    }
}

$app->handleRequest(Request::capture());
