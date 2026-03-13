<?php
namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiResponse;
use App\Models\Notification;
use App\Middleware\AuthMiddleware;
use App\Validators\NotificationValidator;

class NotificationController {
    private Notification $notificationModel;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->notificationModel = new Notification($db);
    }

    public function getUnread() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $notifications = $this->notificationModel->getUnreadByUserId($myId);
        ApiResponse::success($notifications);
    }

    public function markRead() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;
        $data = json_decode(file_get_contents("php://input"));

        $validationError = NotificationValidator::validateMarkReadPayload($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        $this->notificationModel->markAsRead($myId, (int) $data->room_id);
        ApiResponse::success(["message" => "Přečteno"]);
    }
}
