<?php

namespace App\Middleware;

final class CorsMiddleware
{
    private const ALLOWED_METHODS = 'GET, POST, PUT, DELETE, OPTIONS';
    private const ALLOWED_HEADERS = 'Content-Type, Authorization, X-Requested-With';

    public static function handle(): void
    {
        header('Vary: Origin');
        header('Content-Type: application/json; charset=UTF-8');

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $allowedOrigins = self::getAllowedOrigins();

        if ($origin !== null && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: ' . self::ALLOWED_METHODS);
            header('Access-Control-Allow-Headers: ' . self::ALLOWED_HEADERS);
            header('Access-Control-Max-Age: 86400');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            if ($origin === null || !in_array($origin, $allowedOrigins, true)) {
                http_response_code(403);
                echo json_encode([
                    'message' => 'CORS origin is not allowed.'
                ]);
                exit;
            }

            http_response_code(204);
            exit;
        }
    }

    /**
     * @return string[]
     */
    private static function getAllowedOrigins(): array
    {
        $raw = getenv('CORS_ALLOWED_ORIGINS') ?: '';

        if ($raw === '') {
            return [];
        }

        $origins = array_map('trim', explode(',', $raw));
        $origins = array_filter($origins, static fn(string $origin): bool => $origin !== '');

        return array_values(array_unique($origins));
    }
}