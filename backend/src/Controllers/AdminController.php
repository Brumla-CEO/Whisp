<?php
namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Admin;
use App\Models\Chat;
use App\Models\User;
use App\Validators\AdminValidator;
use App\Validators\UserValidator;

class AdminController {
    private User $user;
    private Chat $chat;
    private Admin $adminModel;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->user = new User($db);
        $this->chat = new Chat($db);
        $this->adminModel = new Admin($db);
    }

    private function checkAdmin() {
        $currentUser = AuthMiddleware::check();
        if ($currentUser->role !== 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Pouze pro adminy"]);
            exit;
        }
        return $currentUser;
    }

    public function getDashboardStats() {
        $this->checkAdmin();
        echo json_encode($this->adminModel->getDashboardStats());
    }

    public function getUsers() {
        $this->checkAdmin();
        echo json_encode($this->adminModel->getUsers());
    }

    public function getUserDetails() {
        $this->checkAdmin();
        $validationError = AdminValidator::validateUserIdFromQuery($_GET);
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        echo json_encode($this->adminModel->getUserDetails($_GET['user_id']));
    }

    public function deleteUser() {
        $admin = $this->checkAdmin();
        $data = json_decode(file_get_contents("php://input"));

        $validationError = AdminValidator::validateUserIdPayload($data);
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        $targetUser = $this->user->findById($data->user_id);
        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(["message" => "Uživatel nenalezen"]);
            return;
        }

        if ($admin->sub == $data->user_id) {
            http_response_code(400);
            echo json_encode(["message" => "Nemůžeš smazat sám sebe."]);
            return;
        }

        if ($this->user->countAdmins() <= 1) {
            $roleName = $this->user->findRoleNameByUserId($data->user_id);
            if ($roleName === 'admin') {
                http_response_code(400);
                echo json_encode(["message" => "Nelze smazat posledního administrátora!"]);
                return;
            }
        }

        if ($this->user->delete($data->user_id, true)) {
            $this->user->logActivity(
                $admin->sub ?? $admin->id,
                'DELETE_USER',
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                "Admin smazal uživatele ID: " . $data->user_id . " ({$targetUser['username']})"
            );
            echo json_encode(["message" => "Uživatel smazán"]);
            return;
        }

        http_response_code(500);
        echo json_encode(["message" => "Chyba mazání"]);
    }

    public function getRooms() {
        $this->checkAdmin();
        echo json_encode($this->adminModel->getRooms());
    }

    public function getRoomDetails() {
        $this->checkAdmin();
        $validationError = AdminValidator::validateRoomIdFromQuery($_GET);
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        echo json_encode($this->adminModel->getRoomDetails((int) $_GET['room_id']));
    }

    public function getRoomHistory() {
        $this->checkAdmin();
        $validationError = AdminValidator::validateRoomIdFromQuery($_GET);
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        echo json_encode($this->adminModel->getRoomHistory((int) $_GET['room_id']));
    }

    public function deleteRoom() {
        $admin = $this->checkAdmin();
        $data = json_decode(file_get_contents("php://input"));

        $validationError = AdminValidator::validateRoomIdPayload($data);
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        if ($this->chat->deleteRoom($data->room_id)) {
            $this->user->logActivity($admin->sub ?? $admin->id, 'ADMIN_DELETE_ROOM', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
            echo json_encode(["message" => "Místnost smazána"]);
            return;
        }

        http_response_code(500);
        echo json_encode(["message" => "Chyba mazání"]);
    }

    public function getLogs() {
        $this->checkAdmin();
        echo json_encode($this->adminModel->getLogs());
    }

    public function createAdmin() {
        $me = $this->checkAdmin();
        $data = json_decode(file_get_contents("php://input"));

        $validationError = UserValidator::validateAdminCreate($data);
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(["message" => $validationError]);
            return;
        }

        if ($this->user->findByEmail($data->email) || $this->user->findByUsername($data->username)) {
            http_response_code(409);
            echo json_encode(["message" => "Uživatel již existuje"]);
            return;
        }

        $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
        $avatarUrl = null;

        if ($this->user->createAdmin($data->username, $data->email, $passwordHash, $avatarUrl)) {
            $this->user->logActivity(
                $me->sub ?? $me->id,
                'ADMIN_CREATED',
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                'Vytvořen nový admin: ' . $data->username
            );
            echo json_encode(["message" => "Administrátor úspěšně vytvořen"]);
            return;
        }

        http_response_code(500);
        echo json_encode(["message" => "Chyba serveru: Chyba insertu"]);
    }
}
?>
