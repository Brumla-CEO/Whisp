<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use App\Middleware\AuthMiddleware;

class UserController {
    private $user;
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
    }

    public function index() {
        AuthMiddleware::check();
        $users = $this->user->findAll();
        echo json_encode($users);
    }

   public function delete($id) {
           $currentUser = AuthMiddleware::check();

           if ($currentUser->role !== 'admin' && $currentUser->sub != $id) {
               http_response_code(403);
               echo json_encode(["message" => "Nemáte oprávnění."]);
               return;
           }

           $userToDelete = $this->user->findById($id);

           if (isset($userToDelete['role_name']) && $userToDelete['role_name'] === 'admin') {
               if ($this->user->countAdmins() <= 1) {
                   http_response_code(403);
                   echo json_encode(["message" => "Nelze smazat posledního administrátora."]);
                   return;
               }
           }

           if ($this->user->delete($id)) {
               echo json_encode(["message" => "Účet byl úspěšně smazán."]);
           } else {
               http_response_code(500);
               echo json_encode(["message" => "Chyba při mazání účtu (pravděpodobně vazby v DB)."]);
           }
       }

    public function update($id) {
        $currentUser = AuthMiddleware::check();

        if ($currentUser->sub != $id) {
            http_response_code(403);
            echo json_encode(["message" => "Nemáte oprávnění upravovat cizí profil"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        $existingUser = $this->user->findById($id);

        if (isset($data->username) && $data->username !== $existingUser['username']) {
            if ($this->user->findByUsername($data->username)) {
                http_response_code(409);
                echo json_encode(["message" => "Toto uživatelské jméno je již obsazené"]);
                return;
            }
        }

        if ($this->user->update($id, $data)) {
            $this->user->logActivity($id, 'UPDATE_PROFILE', "Upravil profil", $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

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
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Chyba při aktualizaci"]);
        }
    }
}