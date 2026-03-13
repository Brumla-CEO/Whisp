<?php
namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiResponse;
use App\Models\Friend;
use App\Middleware\AuthMiddleware;
use App\Validators\FriendValidator;

class FriendController {
    private $db;
    private $friendModel;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->friendModel = new Friend($this->db);
    }

    public function search() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $username = FriendValidator::sanitizeSearchQuery($_GET['q'] ?? '');
        if (strlen($username) < 1) {
            ApiResponse::success([]);
            return;
        }

        $results = $this->friendModel->searchAvailableUsers($myId, $username);

        ApiResponse::success($results);
    }

    public function add() {
        $currentUser = AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        $myId = $currentUser->sub ?? $currentUser->id;
        $validationError = FriendValidator::validateTargetId($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        $targetId = $data->target_id;

        if ($myId == $targetId) {
            ApiResponse::error('validation_error', 'Nemůžeš přidat sám sebe', 400);
            return;
        }

        if ($this->friendModel->sendRequest($myId, $targetId)) {
            ApiResponse::success(["message" => "Žádost odeslána"]);
            return;
        }

        ApiResponse::error('friend_request_failed', 'Žádost už existuje nebo nastala chyba', 400);
    }

    public function index() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $friends = $this->friendModel->getFriends($myId);
        ApiResponse::success($friends);
    }

    public function requests() {
        $currentUser = AuthMiddleware::check();
        $myId = $currentUser->sub ?? $currentUser->id;

        $requests = $this->friendModel->getPendingRequests($myId);
        ApiResponse::success($requests);
    }

    public function accept() {
        AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        $validationError = FriendValidator::validateRequestId($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        if ($this->friendModel->acceptRequest($data->request_id)) {
            ApiResponse::success(["message" => "Přátelství navázáno!"]);
            return;
        }

        ApiResponse::error('friend_accept_failed', 'Chyba při přijímání žádosti', 500);
    }

    public function reject() {
        AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        $validationError = FriendValidator::validateRequestId($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        if ($this->friendModel->rejectRequest($data->request_id)) {
            ApiResponse::success(["message" => "Žádost odmítnuta"]);
            return;
        }

        ApiResponse::error('friend_reject_failed', 'Chyba při odmítání', 500);
    }

    public function remove() {
        $currentUser = AuthMiddleware::check();
        $data = json_decode(file_get_contents("php://input"));

        $validationError = FriendValidator::validateFriendId($data);
        if ($validationError !== null) {
            ApiResponse::error('validation_error', $validationError, 400);
            return;
        }

        $myId = $currentUser->sub ?? $currentUser->id;

        if ($this->friendModel->removeFriendship($myId, $data->friend_id)) {
            ApiResponse::success(["message" => "Přítel odebrán"]);
            return;
        }

        ApiResponse::error('friend_remove_failed', 'Chyba při odebírání', 500);
    }
}
