<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService {
    private static $secret = "toto_je_moje_super_tajne_a_velmi_dlouhe_heslo_pro_jwt_123";
    private static $algo = 'HS256';

    public static function generate($userId, $role) {
        $payload = [
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24),
            'sub' => $userId,
            'role' => $role
        ];
        return JWT::encode($payload, self::$secret, self::$algo);
    }

    public static function decode($token) {
        try {
            return JWT::decode($token, new Key(self::$secret, self::$algo));
        } catch (\Exception $e) {
            return null;
        }
    }
}