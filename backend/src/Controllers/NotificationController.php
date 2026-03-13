<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\Chat;
use App\Middleware\AuthMiddleware;

class NotificationController {
    private $chatModel;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->chatModel = new Chat($db);
    }

    public function getUnread() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $notifications = $this->chatModel->getUnreadNotifications($myId);
        echo json_encode($notifications);
    }

    public function markRead() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->room_id)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí room_id"]);
            return;
        }

        $this->chatModel->markAsRead($myId, $data->room_id);
        echo json_encode(["message" => "Přečteno"]);
    }
}