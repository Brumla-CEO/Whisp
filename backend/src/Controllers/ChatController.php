<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\Chat;
use App\Middleware\AuthMiddleware;

class ChatController {
    private $chatModel;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->chatModel = new Chat($db);
    }

    // 1. Otevřít (nebo vytvořit) konverzaci
    public function openDm() {
        $currentUser = AuthMiddleware::check();
        // Získání ID z tokenu (podle toho jak máš JWT, buď sub nebo id)
        $myId = $currentUser->sub ?? $currentUser->id;

        $data = json_decode(file_get_contents("php://input"));
        $targetUserId = $data->target_id;

        if (!$targetUserId) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí ID uživatele"]);
            return;
        }

        $roomId = $this->chatModel->getOrCreateDmRoom($myId, $targetUserId);

        if ($roomId) {
            echo json_encode(["room_id" => $roomId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Chyba při vytváření místnosti"]);
        }
    }

    // 2. Odeslat zprávu (požaduje room_id)
    public function sendMessage() {
            $currentUser = AuthMiddleware::check();
            $myId = $currentUser->sub ?? $currentUser->id;
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->room_id) || !isset($data->content)) {
                http_response_code(400);
                echo json_encode(["message" => "Chybí data"]);
                return;
            }

            $replyToId = $data->reply_to_id ?? null;

            if ($this->chatModel->sendMessage($data->room_id, $myId, $data->content, $replyToId)) {
                echo json_encode(["message" => "Odesláno"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Chyba při ukládání zprávy"]);
            }
        }

    // 3. Načíst historii
    public function getHistory() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $roomId = $_GET['room_id'] ?? null;

        if (!$roomId) {
            http_response_code(400);
            return;
        }

        $messages = $this->chatModel->getRoomMessages($roomId, $myId);
        echo json_encode($messages);
    }
public function deleteMessage() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->message_id)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí ID zprávy"]);
            return;
        }

        if ($this->chatModel->deleteMessage($data->message_id, $myId)) {
            echo json_encode(["message" => "Zpráva smazána"]);
        } else {
            http_response_code(403); // Forbidden nebo Not Found
            echo json_encode(["message" => "Nelze smazat (cizí zpráva nebo neexistuje)"]);
        }
    }

    // NOVÉ: Úprava zprávy
    public function updateMessage() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->message_id) || !isset($data->content)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí data"]);
            return;
        }

        if ($this->chatModel->editMessage($data->message_id, $myId, $data->content)) {
            echo json_encode(["message" => "Zpráva upravena"]);
        } else {
            http_response_code(403);
            echo json_encode(["message" => "Nelze upravit"]);
        }
    }
}