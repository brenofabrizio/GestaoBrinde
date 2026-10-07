<?php

declare(strict_types=1);

if (getenv('VERCEL')) {
    // Laravel writes package and provider manifests during bootstrap, while
    // the deployed bundle is read-only and may not contain bootstrap/cache.
    $cacheDirectory = sys_get_temp_dir() . '/gestao-brinde/bootstrap-cache';

    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
        throw new RuntimeException('Unable to create the Laravel runtime cache directory.');
    }

    foreach ([
        'APP_PACKAGES_CACHE' => 'packages.php',
        'APP_SERVICES_CACHE' => 'services.php',
    ] as $name => $file) {
        $path = $cacheDirectory . '/' . $file;
        putenv($name . '=' . $path);
        $_ENV[$name] = $path;
        $_SERVER[$name] = $path;
    }
}

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
