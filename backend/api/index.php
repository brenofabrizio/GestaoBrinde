<?php

declare(strict_types=1);

try {
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
