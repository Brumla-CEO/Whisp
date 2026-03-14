<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use stdClass;

class JWTService
{
    private const ALGO = 'HS256';
    private const DEFAULT_TTL_SECONDS = 86400;

    public static function generate(string $userId, string $role): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + self::getTtlSeconds(),
            'sub' => $userId,
            'role' => $role,
        ];

        return JWT::encode($payload, self::getSecret(), self::ALGO);
    }

    public static function decode(string $token): ?stdClass
    {
        try {
            return JWT::decode($token, new Key(self::getSecret(), self::ALGO));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function getSecret(): string
    {
        $secret = getenv('JWT_SECRET');

        if ($secret === false || trim($secret) === '') {
            throw new RuntimeException('JWT_SECRET is not configured.');
        }

        return $secret;
    }

    private static function getTtlSeconds(): int
    {
        $ttl = getenv('JWT_TTL_SECONDS');

        if ($ttl === false || trim((string) $ttl) === '') {
            return self::DEFAULT_TTL_SECONDS;
        }

        $ttlValue = (int) $ttl;

        return $ttlValue > 0 ? $ttlValue : self::DEFAULT_TTL_SECONDS;
    }
}