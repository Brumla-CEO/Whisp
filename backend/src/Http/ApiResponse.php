<?php

namespace App\Http;

final class ApiResponse
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload);
    }

    public static function success(array $payload = [], int $status = 200): void
    {
        self::json($payload, $status);
    }

    public static function error(string $code, string $message, int $status): void
    {
        self::json([
            'message' => $message,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
