<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\Friend;
use App\Models\User;
use App\Middleware\AuthMiddleware;
use PDO;

class FriendController {
    private $db;
    private $friendModel;
    private $userModel;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->friendModel = new Friend($this->db);
        $this->userModel = new User($this->db);
    }

        public function search() {
            header('Content-Type: application/json');

            $currentUser = AuthMiddleware::check();
            $myId = $currentUser->sub ?? $currentUser->id;

            $username = $_GET['q'] ?? '';
            if (strlen($username) < 1) {
                echo json_encode([]);
                return;
            }

            $sql = "SELECT u.id, u.username, u.avatar_url, u.status
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.username LIKE :q
                    AND u.id != :myId
                    AND u.username NOT LIKE 'deleted_%'
                    AND r.name != 'admin'
                    LIMIT 10";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':q' => "%$username%",
                ':myId' => $myId
            ]);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $results = [];
            foreach ($users as $user) {
                if (!$this->friendModel->exists($myId, $user['id'])) {
                    $results[] = $user;
                }
            }

            echo json_encode($results);
        }

    public function add() {
        header('Content-Type: application/json');

        $currentUser = AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        $myId = $currentUser->sub ?? $currentUser->id;
        $targetId = $data->target_id ?? null;

        if (!$targetId) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí ID uživatele"]);
            return;
        }

        if ($myId == $targetId) {
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

    public function index() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $friends = $this->friendModel->getFriends($myId);
        echo json_encode($friends);
    }

    // Seznam žádostí (pro notifikace)
    public function requests() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $requests = $this->friendModel->getPendingRequests($myId);
        echo json_encode($requests);
    }

    // Přijmout žádost
    public function accept() {
        header('Content-Type: application/json');
        AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->request_id)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí ID žádosti"]);
            return;
        }

        if ($this->friendModel->acceptRequest($data->request_id)) {
            echo json_encode(["message" => "Přátelství navázáno!"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Chyba při přijímání žádosti"]);
        }
    }

    // Odmítnout žádost
        public function reject() {
            header('Content-Type: application/json');
            AuthMiddleware::check();
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->request_id)) {
                http_response_code(400);
                echo json_encode(["message" => "Chybí ID žádosti"]);
                return;
            }

            // Zavolá model pro smazání řádku (tím umožníme budoucí nové odeslání)
            if ($this->friendModel->rejectRequest($data->request_id)) {
                echo json_encode(["message" => "Žádost odmítnuta"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Chyba při odmítání"]);
            }
        }

    public function remove() {
            header('Content-Type: application/json');
            AuthMiddleware::check();
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->friend_id)) {
                http_response_code(400); echo json_encode(["message" => "Chybí ID přítele"]); return;
            }

            $currentUser = AuthMiddleware::check();
            $myId = $currentUser->sub ?? $currentUser->id;

            if ($this->friendModel->removeFriendship($myId, $data->friend_id)) {
                echo json_encode(["message" => "Přítel odebrán"]);
            } else {
                http_response_code(500); echo json_encode(["message" => "Chyba při odebírání"]);
            }
        }
}