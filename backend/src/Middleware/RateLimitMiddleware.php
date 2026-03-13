<?php

namespace App\Middleware;

use App\Http\ApiResponse;

final class RateLimitMiddleware
{
    public static function handle(string $key, int $limit, int $windowSeconds): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();

        $directory = sys_get_temp_dir() . '/whisp_rate_limits';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $storageKey = sha1($key . '|' . $ip);
        $file = $directory . '/' . $storageKey . '.json';

        $timestamps = [];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            $decoded = json_decode($raw ?: '[]', true);
            if (is_array($decoded)) {
                $timestamps = $decoded;
            }
        }

        $threshold = $now - $windowSeconds;
        $timestamps = array_values(array_filter($timestamps, static fn ($timestamp) => is_int($timestamp) && $timestamp >= $threshold));

        if (count($timestamps) >= $limit) {
            header('Retry-After: ' . max(1, $windowSeconds));
            ApiResponse::error('rate_limited', 'Příliš mnoho požadavků. Zkuste to znovu později.', 429);
            exit;
        }

        $timestamps[] = $now;
        file_put_contents($file, json_encode($timestamps), LOCK_EX);
    }
}
