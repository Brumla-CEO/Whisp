<?php
namespace App\Middleware;

use App\Services\JWTService;
use App\Config\Database;

class AuthMiddleware {
    public static function check() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $decoded = \App\Services\JWTService::decode($token);

            if ($decoded) {
                $db = (new Database())->getConnection();
                // Kontrola existence a aktivity session
                $stmt = $db->prepare("SELECT id FROM sessions WHERE token = ? AND is_active = TRUE LIMIT 1");
                $stmt->execute([$token]);

                if ($stmt->fetch()) {
                    return $decoded;
                }
            }
        }

        http_response_code(401);
        echo json_encode(["message" => "Neautorizovaný přístup - neplatný token nebo session"]);
        exit;
    }
}