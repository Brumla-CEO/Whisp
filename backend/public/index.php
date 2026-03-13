<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\ApiResponse;

set_exception_handler(static function (\Throwable $throwable): void {
    error_log($throwable->getMessage() . "
" . $throwable->getTraceAsString());
    ApiResponse::error('internal_error', 'Interní chyba serveru.', 500);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

\App\Middleware\CorsMiddleware::handle();

$router = new \App\Router();
$router->handleRequest();
