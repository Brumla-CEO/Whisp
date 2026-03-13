<?php
namespace App\Validators;

class AuthValidator
{
    public static function validateLogin(?object $data): ?string
    {
        if (!$data || !isset($data->email) || !isset($data->password)) {
            return 'Chybí přihlašovací údaje';
        }

        if (
            !is_string($data->email) || trim($data->email) === '' ||
            !is_string($data->password) || trim($data->password) === ''
        ) {
            return 'Chybí přihlašovací údaje';
        }

        return null;
    }

    public static function validateRegister(?object $data): ?string
    {
        if (!$data || !isset($data->username) || !isset($data->email) || !isset($data->password)) {
            return 'Neplatná data';
        }

        if (
            !is_string($data->username) || trim($data->username) === '' ||
            !is_string($data->email) || trim($data->email) === '' ||
            !is_string($data->password) || trim($data->password) === ''
        ) {
            return 'Neplatná data';
        }

        $email = trim($data->email);
        $password = (string) $data->password;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Zadej platný email';
        }

        if (strlen($password) < 6) {
            return 'Heslo musí mít alespoň 6 znaků';
        }

        return null;
    }
}
