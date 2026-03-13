<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use App\Middleware\AuthMiddleware;

class AuthController {
    private $user;
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
    }

    public function me() {
        $currentUserData = AuthMiddleware::check();
        $userId = $currentUserData->sub ?? $currentUserData->id;

        $userData = $this->user->findById($userId);

        $stmt = $this->db->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$userData['role_id']]);
        $roleName = $stmt->fetchColumn();

        if ($userData) {
            echo json_encode([
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
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Uživatel nenalezen"]);
        }
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if (!$data || !isset($data->email) || !isset($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí přihlašovací údaje"]);
            return;
        }

        $userData = $this->user->findByEmail($data->email);

        if ($userData && password_verify($data->password, $userData['password_hash'])) {
            $this->user->updateStatus($userData['id'], 'online');

            $roleName = $userData['role_name'] ?? 'user';
            $token = \App\Services\JWTService::generate($userData['id'], $roleName);

            $this->user->logActivity($userData['id'], 'LOGIN', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

            // Uložíme session
            $stmt = $this->db->prepare("INSERT INTO sessions (user_id, token, expires_at, is_active) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$userData['id'], $token, date('Y-m-d H:i:s', time() + 86400)]);

            echo json_encode([
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
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Neplatný email nebo heslo"]);
        }
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));

        if (!$data || !isset($data->username) || !isset($data->email) || !isset($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Neplatná data"]);
            return;
        }

        if ($this->user->findByUsername($data->username)) {
            http_response_code(409);
            echo json_encode(["message" => "Uživatelské jméno je již obsazené"]);
            return;
        }

        if ($this->user->findByEmail($data->email)) {
            http_response_code(409);
            echo json_encode(["message" => "Tento email je již registrovaný"]);
            return;
        }

        $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($data->username);
        $userId = $this->user->create($data->username, $data->email, $data->password, $avatarUrl);

        if ($userId) {
            $this->user->logActivity($userId, 'REGISTER', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
            $this->user->updateStatus($userId, 'online');
            $token = \App\Services\JWTService::generate($userId, 'user');

            $stmt = $this->db->prepare("INSERT INTO sessions (user_id, token, expires_at, is_active) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$userId, $token, date('Y-m-d H:i:s', time() + 86400)]);

            http_response_code(201);
            echo json_encode([
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
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Chyba při registraci"]);
        }
    }

    public function logout() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];

            $stmt = $this->db->prepare("UPDATE sessions SET is_active = FALSE WHERE token = ?");
            $stmt->execute([$token]);

            // Zjistíme ID usera pro log a status
            $stmtUser = $this->db->prepare("SELECT user_id FROM sessions WHERE token = ? LIMIT 1");
            $stmtUser->execute([$token]);
            $uid = $stmtUser->fetchColumn();

            if ($uid) {
                $this->user->logActivity($uid, 'LOGOUT', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
                $this->user->updateStatus($uid, 'offline');
            }
        }
        echo json_encode(["message" => "Odhlášeno"]);
    }
}
?>
