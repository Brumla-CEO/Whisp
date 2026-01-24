<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\Friend;
use App\Models\User;
use App\Middleware\AuthMiddleware;

class FriendController {
    private $db;
    private $friendModel;
    private $userModel;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->friendModel = new Friend($this->db);
        $this->userModel = new User($this->db);
    }

    // Vyhledávání lidí (pro přidání)
    public function search() {
        AuthMiddleware::check();

        $username = $_GET['q'] ?? '';
        if (strlen($username) < 1) {
            echo json_encode([]);
            return;
        }

        // Najdeme uživatele podle jména (musíš mít metodu findLike v User modelu nebo použít raw SQL tady)
        // Pro jednoduchost udělám rychlý query tady, ale správně by to mělo být v User.php
        $stmt = $this->db->prepare("SELECT id, username, avatar_url FROM users WHERE username LIKE :q LIMIT 5");
        $stmt->execute([':q' => "%$username%"]);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode($users);
    }

    // Odeslat žádost
    public function add() {
        $currentUser = AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        // ID z tokenu vs ID z inputu
        $myId = $currentUser->sub ?? $currentUser->id;
        $targetId = $data->target_id;

        if ($myId === $targetId) {
            http_response_code(400);
            echo json_encode(["message" => "Nemůžeš přidat sám sebe"]);
            return;
        }

        if ($this->friendModel->sendRequest($myId, $targetId)) {
            echo json_encode(["message" => "Žádost odeslána"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Žádost už existuje nebo nastala chyba"]);
        }
    }

    // Seznam přátel (pro Sidebar)
    public function index() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $friends = $this->friendModel->getFriends($myId);
        echo json_encode($friends);
    }

    // Seznam žádostí (pro notifikace)
    public function requests() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $requests = $this->friendModel->getPendingRequests($myId);
        echo json_encode($requests);
    }

    // Přijmout žádost
    public function accept() {
        AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        if ($this->friendModel->acceptRequest($data->request_id)) {
            echo json_encode(["message" => "Přátelství navázáno!"]);
        }
    }
}