<?php
namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiResponse;
use App\Models\Chat;
use App\Middleware\AuthMiddleware;

class ChatController {
    private $chatModel;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->chatModel = new Chat($db);
    }

    public function getRooms() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $rooms = $this->chatModel->getUserRooms($myId);
        ApiResponse::success($rooms);
    }

    public function openDm() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $data = json_decode(file_get_contents("php://input"));
        $targetUserId = $data->target_id ?? null;

        if (!$targetUserId) {
            ApiResponse::error('validation_error', 'Chybí ID uživatele', 400);
            return;
        }

        $roomId = $this->chatModel->getOrCreateDmRoom($myId, $targetUserId);

        if ($roomId) {
            ApiResponse::success(["room_id" => $roomId]);
            return;
        }

        ApiResponse::error('chat_open_forbidden', 'Soukromý chat lze otevřít pouze s aktuálním přítelem.', 403);
    }

    public function sendMessage() {
        $currentUser = AuthMiddleware::check();
        $senderId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->room_id) || !isset($data->content)) {
            ApiResponse::error('validation_error', 'Chybí data', 400);
            return;
        }

        $replyToId = $data->reply_to_id ?? null;
        $message = $this->chatModel->sendMessage($data->room_id, $senderId, $data->content, $replyToId);

        if ($message) {
            ApiResponse::success(["message" => "Odesláno", "data" => $message]);
            return;
        }

        ApiResponse::error('message_send_forbidden', 'Do tohoto chatu již nelze odesílat zprávy.', 403);
    }

    public function getHistory() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        if (!isset($_GET['room_id'])) {
            ApiResponse::error('validation_error', 'Chybí room_id', 400);
            return;
        }

        if (!$this->chatModel->canAccessRoom((int) $_GET['room_id'], $myId)) {
            ApiResponse::error('room_access_forbidden', 'Do tohoto chatu již nemáte přístup.', 403);
            return;
        }

        $messages = $this->chatModel->getRoomMessages((int) $_GET['room_id'], $myId);
        ApiResponse::success($messages);
    }

    public function deleteMessage() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->message_id)) {
            ApiResponse::error('validation_error', 'Chybí ID zprávy', 400);
            return;
        }

        if ($this->chatModel->deleteMessage($data->message_id, $myId)) {
            ApiResponse::success(["message" => "Zpráva smazána"]);
            return;
        }

        ApiResponse::error('message_delete_forbidden', 'Nelze smazat', 403);
    }

    public function updateMessage() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->message_id) || !isset($data->content)) {
            ApiResponse::error('validation_error', 'Chybí data', 400);
            return;
        }

        if ($this->chatModel->editMessage($data->message_id, $myId, $data->content)) {
            ApiResponse::success(["message" => "Zpráva upravena"]);
            return;
        }

        ApiResponse::error('message_edit_forbidden', 'Nelze upravit', 403);
    }

    public function createGroup() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->name) || empty($data->members) || !is_array($data->members)) {
            ApiResponse::error('validation_error', 'Chybí název skupiny nebo členové', 400);
            return;
        }

        if (count($data->members) < 2) {
            ApiResponse::error('validation_error', 'Skupina musí mít alespoň 3 členy (vy + minimálně 2 přátelé).', 400);
            return;
        }

        $roomId = $this->chatModel->createGroup($data->name, $myId, $data->members);

        if ($roomId) {
            ApiResponse::success(["message" => "Skupina vytvořena", "room_id" => $roomId]);
            return;
        }

        ApiResponse::error('group_create_failed', 'Chyba při vytváření skupiny', 500);
    }

    public function getGroupMembers() {
        if (!isset($_GET['room_id'])) {
            ApiResponse::error('validation_error', 'Chybí room_id', 400);
            return;
        }
        $members = $this->chatModel->getGroupMembers($_GET['room_id']);
        ApiResponse::success($members);
    }

    public function addGroupMember() {
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id) || empty($data->user_id)) {
            ApiResponse::error('validation_error', 'Chybí data', 400);
            return;
        }

        if ($this->chatModel->addGroupMember($data->room_id, $data->user_id)) {
            ApiResponse::success(["message" => "Člen přidán"]);
            return;
        }

        ApiResponse::error('group_member_add_failed', 'Nelze přidat (možná už tam je)', 500);
    }

    public function leaveGroup() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id)) {
            ApiResponse::error('validation_error', 'Chybí room_id', 400);
            return;
        }

        if ($this->chatModel->leaveGroupSafe($data->room_id, $myId)) {
            $count = $this->chatModel->getMemberCount($data->room_id);
            if ($count == 0) {
                ApiResponse::success(["message" => "Opustili jste skupinu (skupina je nyní prázdná a archivována)"]);
                return;
            }
            ApiResponse::success(["message" => "Opustili jste skupinu"]);
            return;
        }

        ApiResponse::error('group_leave_failed', 'Chyba při opouštění skupiny', 500);
    }

    public function updateGroup() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id) || empty($data->name)) {
            ApiResponse::error('validation_error', 'Chybí data', 400);
            return;
        }

        $role = $this->chatModel->getMemberRole($data->room_id, $myId);
        if ($role !== 'admin') {
            ApiResponse::error('forbidden', 'Nemáte oprávnění', 403);
            return;
        }

        if ($this->chatModel->updateGroupInfo($data->room_id, $data->name, $data->avatar_url ?? null)) {
            ApiResponse::success(["message" => "Skupina upravena"]);
            return;
        }

        ApiResponse::error('group_update_failed', 'Chyba úpravy', 500);
    }

    public function kickMember() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->room_id) || empty($data->user_id)) {
            ApiResponse::error('validation_error', 'Chybí data', 400);
            return;
        }

        $role = $this->chatModel->getMemberRole($data->room_id, $myId);
        if ($role !== 'admin') {
            ApiResponse::error('forbidden', 'Nemáte oprávnění vyhazovat', 403);
            return;
        }

        if ($this->chatModel->removeGroupMember($data->room_id, $data->user_id)) {
            ApiResponse::success(["message" => "Uživatel odstraněn"]);
            return;
        }

        ApiResponse::error('group_member_remove_failed', 'Chyba odstranění', 500);
    }
}
