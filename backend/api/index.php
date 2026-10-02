<?php

declare(strict_types=1);

try {
    error_log('Vercel request: ' . json_encode([
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'path_info' => $_SERVER['PATH_INFO'] ?? null,
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    ], JSON_UNESCAPED_SLASHES));

    require dirname(__DIR__) . '/public/index.php';
} catch (Throwable $exception) {
    error_log(sprintf(
        'Laravel bootstrap failure: %s: %s%s%s',
        get_class($exception),
        $exception->getMessage(),
        PHP_EOL,
        $exception->getTraceAsString(),
    ));

    http_response_code(500);
    echo 'Application bootstrap failed.';
}
