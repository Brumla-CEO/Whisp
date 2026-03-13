<?php
namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiResponse;
use App\Models\Session;
use App\Models\User;
use App\Middleware\AuthMiddleware;
use App\Validators\AuthValidator;

class AuthController {
    private User $user;
    private Session $sessionModel;
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
        $this->sessionModel = new Session($this->db);
    }

    public function me() {
        $currentUserData = AuthMiddleware::check();
        $userId = $currentUserData->sub ?? $currentUserData->id;

        $userData = $this->user->findById($userId);

        if (!$userData) {
            ApiResponse::error('user_not_found', 'Uživatel nenalezen', 404);
            return;
        }

        $roleName = $this->user->getRoleNameById($userData['role_id']);

        ApiResponse::success([
            "user" => [
                "id" => $userData['id'],
                "username" => $userData['username'],
                "email" => $userData['email'],
                "role" => $roleName ?? 'user',
                "avatar_url" => $userData['avatar_url'],
                "status" => $userData['status'],
                "bio" => $userData['bio']
            ]
        ]);
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        $validationError = AuthValidator::validateLogin($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        $userData = $this->user->findByEmail($data->email);

        if (!$userData || !password_verify($data->password, $userData['password_hash'])) {
            ApiResponse::error('invalid_credentials', 'Neplatný email nebo heslo', 401);
            return;
        }

        $this->user->updateStatus($userData['id'], 'online');
        $roleName = $userData['role_name'] ?? 'user';
        $token = \App\Services\JWTService::generate($userData['id'], $roleName);
        $this->user->logActivity($userData['id'], 'LOGIN', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

        $this->sessionModel->create($userData['id'], $token, date('Y-m-d H:i:s', time() + 86400));

        ApiResponse::success([
            "token" => $token,
            "user" => [
                "id" => $userData['id'],
                "username" => $userData['username'],
                "email" => $userData['email'],
                "role" => $roleName,
                "avatar_url" => $userData['avatar_url'],
                "status" => "online",
                "bio" => $userData['bio']
            ]
        ]);
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));

        $validationError = AuthValidator::validateRegister($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        if ($this->user->findByUsername($data->username)) {
            ApiResponse::error('username_taken', 'Uživatelské jméno je již obsazené', 409);
            return;
        }

        if ($this->user->findByEmail($data->email)) {
            ApiResponse::error('email_taken', 'Tento email je již registrovaný', 409);
            return;
        }

        $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($data->username);
        $userId = $this->user->create($data->username, $data->email, $data->password, $avatarUrl);

        if (!$userId) {
            ApiResponse::error('registration_failed', 'Chyba při registraci', 500);
            return;
        }

        $this->user->logActivity($userId, 'REGISTER', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        $this->user->updateStatus($userId, 'online');
        $token = \App\Services\JWTService::generate($userId, 'user');

        $this->sessionModel->create($userId, $token, date('Y-m-d H:i:s', time() + 86400));

        ApiResponse::success([
            "message" => "Registrace úspěšná",
            "token" => $token,
            "user" => [
                "id" => $userId,
                "username" => $data->username,
                "email" => $data->email,
                "role" => "user",
                "avatar_url" => $avatarUrl,
                "status" => "online",
                "bio" => ""
            ]
        ], 201);
    }

    public function logout() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];

            $this->sessionModel->deactivateByToken($token);
            $uid = $this->sessionModel->findUserIdByToken($token);

            if ($uid) {
                $this->user->logActivity($uid, 'LOGOUT', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
                $this->user->updateStatus($uid, 'offline');
            }
        }
        ApiResponse::success(["message" => "Odhlášeno"]);
    }
}
