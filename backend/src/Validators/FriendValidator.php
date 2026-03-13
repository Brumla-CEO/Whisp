<?php
namespace App\Validators;

class FriendValidator
{
    public static function validateTargetId(?object $data): ?string
    {
        if (!$data || !isset($data->target_id) || trim((string) $data->target_id) === '') {
            return 'Chybí ID uživatele';
        }

        return null;
    }

    public static function validateRequestId(?object $data): ?string
    {
        if (!$data || !isset($data->request_id) || trim((string) $data->request_id) === '') {
            return 'Chybí ID žádosti';
        }

        return null;
    }

    public static function validateFriendId(?object $data): ?string
    {
        if (!$data || !isset($data->friend_id) || trim((string) $data->friend_id) === '') {
            return 'Chybí ID přítele';
        }

        return null;
    }

    public static function sanitizeSearchQuery(?string $query): string
    {
        return trim((string) $query);
    }
}
