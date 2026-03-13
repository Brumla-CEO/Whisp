<?php
namespace App\Validators;

class ChatValidator
{
    public static function validateTargetId(?object $data): ?string
    {
        if (!$data || !isset($data->target_id) || trim((string) $data->target_id) === '') {
            return 'Chybí ID uživatele';
        }

        return null;
    }

    public static function validateMessagePayload(?object $data): ?string
    {
        if (!$data || !isset($data->room_id) || !isset($data->content)) {
            return 'Chybí data';
        }

        if (trim((string) $data->content) === '') {
            return 'Chybí data';
        }

        return null;
    }

    public static function validateMessageId(?object $data): ?string
    {
        if (!$data || !isset($data->message_id) || trim((string) $data->message_id) === '') {
            return 'Chybí ID zprávy';
        }

        return null;
    }

    public static function validateMessageUpdate(?object $data): ?string
    {
        if (!$data || !isset($data->message_id) || !isset($data->content) || trim((string) $data->content) === '') {
            return 'Chybí data';
        }

        return null;
    }

    public static function validateGroupCreation(?object $data): ?string
    {
        if (!$data || empty($data->name) || empty($data->members) || !is_array($data->members)) {
            return 'Chybí název skupiny nebo členové';
        }

        if (count($data->members) < 2) {
            return 'Skupina musí mít alespoň 3 členy (vy + minimálně 2 přátelé).';
        }

        return null;
    }

    public static function validateRoomIdFromQuery(array $query): ?string
    {
        if (!isset($query['room_id']) || trim((string) $query['room_id']) === '') {
            return 'Chybí room_id';
        }

        return null;
    }

    public static function validateGroupMemberPayload(?object $data): ?string
    {
        if (!$data || empty($data->room_id) || empty($data->user_id)) {
            return 'Chybí data';
        }

        return null;
    }

    public static function validateGroupUpdatePayload(?object $data): ?string
    {
        if (!$data || empty($data->room_id) || empty($data->name)) {
            return 'Chybí data';
        }

        return null;
    }
}
