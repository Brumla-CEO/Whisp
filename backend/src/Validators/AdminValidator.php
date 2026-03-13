<?php
namespace App\Validators;

class AdminValidator
{
    public static function validateUserIdFromQuery(array $query): ?string
    {
        if (!isset($query['user_id']) || trim((string) $query['user_id']) === '') {
            return 'Chybí ID';
        }

        return null;
    }

    public static function validateRoomIdFromQuery(array $query): ?string
    {
        if (!isset($query['room_id']) || trim((string) $query['room_id']) === '') {
            return 'Chybí ID';
        }

        return null;
    }

    public static function validateUserIdPayload(?object $data): ?string
    {
        if (!$data || empty($data->user_id)) {
            return 'Chybí ID';
        }

        return null;
    }

    public static function validateRoomIdPayload(?object $data): ?string
    {
        if (!$data || empty($data->room_id)) {
            return 'Chybí ID';
        }

        return null;
    }
}
