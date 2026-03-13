<?php
namespace App\Validators;

class NotificationValidator
{
    public static function validateMarkReadPayload(?object $data): ?string
    {
        if (!$data || !isset($data->room_id) || trim((string) $data->room_id) === '') {
            return 'Chybí room_id';
        }

        return null;
    }
}
