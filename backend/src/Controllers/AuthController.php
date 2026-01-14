<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use App\Services\JWTService;

class AuthController {
    private $user;
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if (!$data || !isset($data->email) || !isset($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí přihlašovací údaje"]);
            return;
        }

        $userData = $this->user->findByEmail($data->email);

        // Ověření hesla
        if ($userData && password_verify($data->password, $userData['password_hash'])) {


            $this->user->updateStatus($userData['id'], 'online');

            $token = \App\Services\JWTService::generate($userData['id'], $userData['role_name'] ?? 'user');
            $this->user->logActivity($userData['id'], 'LOGIN', $_SERVER['REMOTE_ADDR']);

            $stmt = $this->db->prepare(
                "INSERT INTO sessions (user_id, token, expires_at, is_active) VALUES (?, ?, ?, TRUE)"
            );
            $stmt->execute([$userData['id'], $token, date('Y-m-d H:i:s', time() + 86400)]);

            echo json_encode([
                "token" => $token,
                "user" => [
                    "id" => $userData['id'],
                    "username" => $userData['username'],
                    "role" => $userData['role_name'] ?? 'user',
                    "status" => "online"
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

        // 1. Kontrola unikátnosti jména
        if ($this->user->findByUsername($data->username)) {
            http_response_code(409);
            echo json_encode(["message" => "Uživatelské jméno je již obsazené"]);
            return;
        }

        // 2. Kontrola unikátnosti emailu
        if ($this->user->findByEmail($data->email)) {
            http_response_code(409);
            echo json_encode(["message" => "Tento email je již registrovaný"]);
            return;
        }

        // 3. Vytvoření uživatele
        $userId = $this->user->create($data->username, $data->email, $data->password);
        $this->user->logActivity($userId, 'REGISTER', $_SERVER['REMOTE_ADDR']);
        if ($userId) {
            $this->user->updateStatus($userId, 'online'); // Nastavení online statusu
            $token = \App\Services\JWTService::generate($userId, 'user');

            // Uložení session
            $stmt = $this->db->prepare("INSERT INTO sessions (user_id, token, expires_at, is_active) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$userId, $token, date('Y-m-d H:i:s', time() + 86400)]);

            http_response_code(201);
            echo json_encode([
                "message" => "Registrace úspěšná",
                "token" => $token,
                "user" => [
                    "id" => $userId,
                    "username" => $data->username,
                    "role" => "user",
                    "status" => "online"
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
            // 1. Deaktivovat session
            $stmt = $this->db->prepare("UPDATE sessions SET is_active = FALSE WHERE token = ?");
            $stmt->execute([$token]);
            $this->user->logActivity($session['user_id'], 'LOGOUT', $_SERVER['REMOTE_ADDR']);

            // 2. Najít uživatele podle tokenu a dát ho OFFLINE
            $stmt = $this->db->prepare("SELECT user_id FROM sessions WHERE token = ?");
            $stmt->execute([$token]);
            $session = $stmt->fetch();
            if ($session) {
                $this->user->updateStatus($session['user_id'], 'offline');
            }
        }
        echo json_encode(["message" => "Odhlášeno"]);
    }
}