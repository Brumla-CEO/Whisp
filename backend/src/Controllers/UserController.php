<?php
namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Validators\UserValidator;

class UserController {
    private User $user;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->user = new User($db);
    }

    public function index() {
        AuthMiddleware::check();
        echo json_encode($this->user->findAll());
    }

    public function delete($id) {
        $currentUser = AuthMiddleware::check();

        if ($currentUser->role !== 'admin' && $currentUser->sub != $id) {
            http_response_code(403);
            echo json_encode(["message" => "Nemáte oprávnění."]);
            return;
        }

        $userToDelete = $this->user->findById($id);
        $roleName = $this->user->findRoleNameByUserId($id);

        if ($roleName === 'admin' && $this->user->countAdmins() <= 1) {
            http_response_code(403);
            echo json_encode(["message" => "Nelze smazat posledního administrátora."]);
            return;
        }

        if ($this->user->delete($id)) {
            echo json_encode(["message" => "Účet byl úspěšně smazán."]);
            return;
        }

        http_response_code(500);
        echo json_encode(["message" => "Chyba při mazání účtu (pravděpodobně vazby v DB)."]);
    }

    public function update($id) {
        $currentUser = AuthMiddleware::check();

        if ($currentUser->sub != $id) {
            http_response_code(403);
            echo json_encode(["message" => "Nemáte oprávnění upravovat cizí profil"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        $validationError = UserValidator::validateProfileUpdate($data);

        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        $existingUser = $this->user->findById($id);

        if (isset($data->username) && $data->username !== $existingUser['username']) {
            if ($this->user->findByUsername($data->username)) {
                http_response_code(409);
                echo json_encode(["message" => "Toto uživatelské jméno je již obsazené"]);
                return;
            }
        }

        if ($this->user->update($id, $data)) {
            $this->user->logActivity($id, 'UPDATE_PROFILE', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'Upravil profil');

            $updatedUser = $this->user->findById($id);

            echo json_encode([
                "message" => "Profil aktualizován",
                "user" => [
                    "id" => $updatedUser['id'],
                    "username" => $updatedUser['username'],
                    "email" => $updatedUser['email'],
                    "role" => $currentUser->role ?? 'user',
                    "avatar_url" => $updatedUser['avatar_url'],
                    "bio" => $updatedUser['bio'],
                    "status" => "online"
                ]
            ]);
            return;
        }

        http_response_code(500);
        echo json_encode(["message" => "Chyba při aktualizaci"]);
    }
}
