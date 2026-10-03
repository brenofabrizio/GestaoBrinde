<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\JsonSnapshotConflict;

// PHP built-in dev server: serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

try {
    require dirname(__DIR__) . '/app/bootstrap.php';

    try {
        $request = Request::fromGlobals();
    } catch (HttpException $e) {
        Response::fromException($e)->send();
        exit;
    }

    App::create()->handle($request)->send();
} catch (JsonSnapshotConflict $e) {
    Response::json([
        'ok' => false,
        'error' => [
            'code' => 'CONCURRENT_UPDATE',
            'message' => 'Outra gravação ocorreu ao mesmo tempo. Atualize a tela e tente novamente; a gravação não substituiu os dados confirmados.',
        ],
    ], 409)->send();
}
