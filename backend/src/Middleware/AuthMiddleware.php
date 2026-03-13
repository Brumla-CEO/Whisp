<?php

namespace App\Middleware;

use App\Config\Database;
use App\Http\ApiResponse;
use App\Services\JWTService;
use PDO;

class AuthMiddleware
{
    public static function check()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            self::unauthorized();
        }

        $token = $matches[1];
        $decoded = JWTService::decode($token);

        if ($decoded === null) {
            self::unauthorized();
        }

        $db = (new Database())->getConnection();

        $stmt = $db->prepare(
            'SELECT id
             FROM sessions
             WHERE token = :token
               AND is_active = TRUE
               AND expires_at > NOW()
             LIMIT 1'
        );

        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetch() === false) {
            self::unauthorized();
        }

        return $decoded;
    }

    private static function unauthorized(): void
    {
        ApiResponse::error('unauthorized', 'Neautorizovaný přístup - neplatný token nebo session', 401);
        exit;
    }
}
