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

    public function getRooms() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $rooms = $this->chatModel->getUserRooms($myId);
        echo json_encode($rooms);
    }

    public function openDm() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $data = json_decode(file_get_contents("php://input"));
        $targetUserId = $data->target_id ?? null;

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
            echo json_encode(["message" => "Chyba při vytváření chatu"]);
        }
    }

    public function sendMessage() {
            header('Content-Type: application/json');
            $currentUser = AuthMiddleware::check();
            $senderId = $currentUser->sub ?? $currentUser->id;

            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->room_id) || !isset($data->content)) {
                http_response_code(400);
                echo json_encode(["message" => "Chybí data"]);
                return;
            }

            $replyToId = $data->reply_to_id ?? null;

            $message = $this->chatModel->sendMessage($data->room_id, $senderId, $data->content, $replyToId);

            if ($message) {
                echo json_encode(["message" => "Odesláno", "data" => $message]);
            } else {
                http_response_code(403);
                echo json_encode(["message" => "Nemůžete posílat zprávy do této skupiny (nejste členem)."]);
            }
        }

    public function getHistory() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        if (!isset($_GET['room_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí room_id"]);
            return;
        }

        $messages = $this->chatModel->getRoomMessages($_GET['room_id'], $myId);
        echo json_encode($messages);
    }

    public function deleteMessage() {
        header('Content-Type: application/json');
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
            http_response_code(403);
            echo json_encode(["message" => "Nelze smazat"]);
        }
    }

    public function updateMessage() {
        header('Content-Type: application/json');
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

    public function createGroup() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->name) || empty($data->members) || !is_array($data->members)) {
            http_response_code(400);
            echo json_encode(["message" => "Chybí název skupiny nebo členové"]);
            return;
        }

        if (count($data->members) < 2) {
            http_response_code(400);
            echo json_encode(["message" => "Skupina musí mít alespoň 3 členy (vy + minimálně 2 přátelé)."]);
            return;
        }

        $roomId = $this->chatModel->createGroup($data->name, $myId, $data->members);

        if ($roomId) {
            echo json_encode(["message" => "Skupina vytvořena", "room_id" => $roomId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Chyba při vytváření skupiny"]);
        }
    }

    public function getGroupMembers() {
        header('Content-Type: application/json');
        if (!isset($_GET['room_id'])) {
            http_response_code(400); echo json_encode(["message" => "Chybí room_id"]); return;
        }
        $members = $this->chatModel->getGroupMembers($_GET['room_id']);
        echo json_encode($members);
    }

    public function addGroupMember() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id) || empty($data->user_id)) {
            http_response_code(400); echo json_encode(["message" => "Chybí data"]); return;
        }

        if ($this->chatModel->addGroupMember($data->room_id, $data->user_id)) {
            echo json_encode(["message" => "Člen přidán"]);
        } else {
            http_response_code(500); echo json_encode(["message" => "Nelze přidat (možná už tam je)"]);
        }
    }

   public function leaveGroup() {
           header('Content-Type: application/json');
           $currentUser = AuthMiddleware::check();
           $myId = $currentUser->sub ?? $currentUser->id;
           $data = json_decode(file_get_contents("php://input"));

           if (empty($data->room_id)) {
               http_response_code(400); echo json_encode(["message" => "Chybí room_id"]); return;
           }

           if ($this->chatModel->leaveGroupSafe($data->room_id, $myId)) {

               $count = $this->chatModel->getMemberCount($data->room_id);
               if ($count == 0) {
                   echo json_encode(["message" => "Opustili jste skupinu (skupina je nyní prázdná a archivována)"]);
               } else {
                   echo json_encode(["message" => "Opustili jste skupinu"]);
               }

           } else {
               http_response_code(500);
               echo json_encode(["message" => "Chyba při opouštění skupiny"]);
           }
       }

    public function updateGroup() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id) || empty($data->name)) {
            http_response_code(400); echo json_encode(["message" => "Chybí data"]); return;
        }

        $role = $this->chatModel->getMemberRole($data->room_id, $myId);
        if ($role !== 'admin') {
            http_response_code(403); echo json_encode(["message" => "Nemáte oprávnění"]); return;
        }

        if ($this->chatModel->updateGroupInfo($data->room_id, $data->name, $data->avatar_url ?? null)) {
            echo json_encode(["message" => "Skupina upravena"]);
        } else {
            http_response_code(500); echo json_encode(["message" => "Chyba úpravy"]);
        }
    }

    public function kickMember() {
        header('Content-Type: application/json');
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id) || empty($data->user_id)) {
            http_response_code(400); echo json_encode(["message" => "Chybí data"]); return;
        }

        $role = $this->chatModel->getMemberRole($data->room_id, $myId);
        if ($role !== 'admin') {
            http_response_code(403); echo json_encode(["message" => "Nemáte oprávnění vyhazovat"]); return;
        }

        if ($this->chatModel->removeGroupMember($data->room_id, $data->user_id)) {
            echo json_encode(["message" => "Uživatel odstraněn"]);
        } else {
            http_response_code(500); echo json_encode(["message" => "Chyba odstranění"]);
        }
    }


}