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
        // Ochrana: Pouze pro přihlášené
        AuthMiddleware::check();

        $users = $this->user->findAll();
        echo json_encode($users);
    }
    public function delete($id) {
        AuthMiddleware::check();


        $userToDelete = $this->user->findById($id);

        if ($userToDelete['role_id'] === 'admin_role_id_zde') {
            if ($this->user->countAdmins() <= 1) {
                http_response_code(403);
                echo json_encode(["message" => "Kritická chyba: Nelze smazat posledního administrátora."]);
                return;
            }
        }

        if ($this->user->delete($id)) {
            echo json_encode(["message" => "Uživatel byl smazán"]);
        }
    }
public function update($id) {
    // 1. Ověření identity
    $currentUser = AuthMiddleware::check();

    // 2. Kontrola oprávnění
    if ($currentUser->sub != $id) { // Pozor: v PHP je bezpečnější != nebo porovnání stringů, $currentUser->sub může být int/string
        http_response_code(403);
        echo json_encode(["message" => "Nemáte oprávnění upravovat cizí profil"]);
        return;
    }

    $data = json_decode(file_get_contents("php://input"));

    // Získání aktuálních dat uživatele pro porovnání
    $existingUser = $this->user->findById($id);

    // 3. Kontrola unikátnosti jména (pokud se mění)
    if (isset($data->username) && $data->username !== $existingUser['username']) {
        if ($this->user->findByUsername($data->username)) {
            http_response_code(409);
            echo json_encode(["message" => "Toto uživatelské jméno je již obsazené"]);
            return;
        }
    }

    if ($this->user->update($id, $data)) {
        echo json_encode(["message" => "Profil byl úspěšně aktualizován"]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Chyba při aktualizaci profilu"]);
    }
}
}