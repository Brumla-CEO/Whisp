<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use App\Models\Chat;
use App\Middleware\AuthMiddleware;
use PDO;

class AdminController {
    private $db;
    private $user;
    private $chat;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
        $this->chat = new Chat($this->db);
    }

    // Pomocná metoda pro ověření admina
    private function checkAdmin() {
        $currentUser = AuthMiddleware::check();
        if ($currentUser->role !== 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Pouze pro adminy"]);
            exit;
        }
        return $currentUser;
    }

    // 1. DASHBOARD STATS
    public function getDashboardStats() {
        $this->checkAdmin();

        $totalUsers = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $onlineUsers = $this->db->query("SELECT COUNT(*) FROM users WHERE status = 'online'")->fetchColumn();
        $totalRooms = $this->db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
        $totalMessages = $this->db->query("SELECT COUNT(*) FROM messages")->fetchColumn();

        $stmtLogs = $this->db->query("
            SELECT al.*, u.username
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.timestamp DESC LIMIT 20
        ");
        $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "counts" => [
                "users" => $totalUsers,
                "online" => $onlineUsers,
                "rooms" => $totalRooms,
                "messages" => $totalMessages
            ],
            "recent_logs" => $logs
        ]);
    }

    // 2. SPRÁVA UŽIVATELŮ
    public function getUsers() {
        $this->checkAdmin();
        $query = "
            SELECT u.id, u.username, u.email, u.status, u.created_at, u.avatar_url, u.bio, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ";
        $stmt = $this->db->query($query);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // DETAIL UŽIVATELE (LOGY)
    public function getUserDetails() {
        $this->checkAdmin();
        if (!isset($_GET['user_id'])) { http_response_code(400); return; }

        $stmt = $this->db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
        $stmt->execute([$_GET['user_id']]);
        echo json_encode(["logs" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function deleteUser() {
            $admin = $this->checkAdmin(); // Ten kdo maže
            $data = json_decode(file_get_contents("php://input"));

            if (empty($data->user_id)) {
                http_response_code(400); echo json_encode(["message" => "Chybí ID"]); return;
            }

            // Zjisteni koho mazeme
            $targetUser = $this->user->findById($data->user_id);
            if (!$targetUser) {
                http_response_code(404); echo json_encode(["message" => "Uživatel nenalezen"]); return;
            }
            $totalAdmins = $this->user->countAdmins();

            if ($admin->sub == $data->user_id) {
                 http_response_code(400); echo json_encode(["message" => "Nemůžeš smazat sám sebe."]); return;
            }

            if ($totalAdmins <= 1) {

                $stmtRole = $this->db->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                $stmtRole->execute([$data->user_id]);
                $roleName = $stmtRole->fetchColumn();

                if ($roleName === 'admin') {
                    http_response_code(400);
                    echo json_encode(["message" => "Nelze smazat posledního administrátora!"]);
                    return;
                }
            }


            if ($this->user->delete($data->user_id)) {
                $this->user->logActivity(
                    $admin->sub ?? $admin->id,
                    'DELETE_USER',
                    $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                    "Admin smazal uživatele ID: " . $data->user_id . " ({$targetUser['username']})"
                );
                echo json_encode(["message" => "Uživatel smazán"]);
            } else {
                http_response_code(500); echo json_encode(["message" => "Chyba mazání"]);
            }
        }

    // 3. SPRÁVA MÍSTNOSTÍ
    public function getRooms() {
        $this->checkAdmin();
        $query = "
            SELECT r.id, r.name, r.type, r.created_at, u.username as owner_name,
            (SELECT COUNT(*) FROM room_memberships WHERE room_id = r.id) as member_count,
            (SELECT COUNT(*) FROM messages WHERE room_id = r.id) as msg_count
            FROM rooms r
            LEFT JOIN users u ON r.owner_id = u.id
            ORDER BY r.created_at DESC
        ";
        $stmt = $this->db->query($query);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getRoomDetails() {
        $this->checkAdmin();
        if (!isset($_GET['room_id'])) { http_response_code(400); return; }

        // Získat členy
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar_url, rm.role
            FROM room_memberships rm
            JOIN users u ON rm.user_id = u.id
            WHERE rm.room_id = ?
        ");
        $stmt->execute([$_GET['room_id']]);
        echo json_encode(["members" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function getRoomHistory() {
        $this->checkAdmin();
        if (!isset($_GET['room_id'])) { http_response_code(400); return; }

        $stmt = $this->db->prepare("
            SELECT m.content, m.created_at, u.username
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ?
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$_GET['room_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array_reverse($messages));
    }

    public function deleteRoom() {
        $admin = $this->checkAdmin();
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id)) { http_response_code(400); echo json_encode(["message" => "Chybí ID"]); return; }

        if ($this->chat->deleteRoom($data->room_id)) {
            $this->user->logActivity($admin->sub ?? $admin->id, 'ADMIN_DELETE_ROOM', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
            echo json_encode(["message" => "Místnost smazána"]);
        } else {
            http_response_code(500); echo json_encode(["message" => "Chyba mazání"]);
        }
    }

    // 4. LOGY
    public function getLogs() {
        $this->checkAdmin();
        $stmt = $this->db->query("
            SELECT al.*, u.username
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.timestamp DESC LIMIT 200
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // 5. VYTVOŘENÍ NOVÉHO ADMINA
    public function createAdmin() {
        $me = $this->checkAdmin();
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->username) || empty($data->email) || empty($data->password)) {
            http_response_code(400); echo json_encode(["message" => "Vyplňte všechna pole"]); return;
        }

        if ($this->user->findByEmail($data->email) || $this->user->findByUsername($data->username)) {
            http_response_code(409); echo json_encode(["message" => "Uživatel již existuje"]); return;
        }

        try {
            $stmtRole = $this->db->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $stmtRole->execute();
            $adminRoleId = $stmtRole->fetchColumn();

            $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
            $avatarUrl = null;

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, role_id, avatar_url, created_at, status)
                VALUES (?, ?, ?, ?, ?, NOW(), 'offline')
            ");

if ($stmt->execute([$data->username, $data->email, $passwordHash, $adminRoleId, $avatarUrl])) {

               $this->user->logActivity(
                   $me->sub ?? $me->id,
                   'ADMIN_CREATED',
                   $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                   "Vytvořen nový admin: " . $data->username);

                    echo json_encode(["message" => "Administrátor úspěšně vytvořen"]);
            } else {
                throw new \Exception("Chyba insertu");
            }
        } catch (\Exception $e) {
            http_response_code(500); echo json_encode(["message" => "Chyba serveru: " . $e->getMessage()]);
        }
    }
}
?>