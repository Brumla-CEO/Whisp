<?php
namespace App\Validators;

class UserValidator
{
    public static function validateProfileUpdate(?object $data): ?string
    {
        if (!$data || !isset($data->username) || !isset($data->email)) {
            return 'Neplatná data';
        }

        if (trim((string) $data->username) === '' || trim((string) $data->email) === '') {
            return 'Neplatná data';
        }

        return null;
    }

    public static function validateAdminCreate(?object $data): ?string
    {
        if (!$data || empty($data->username) || empty($data->email) || empty($data->password)) {
            return 'Vyplňte všechna pole';
        }

        return null;
    }
}
