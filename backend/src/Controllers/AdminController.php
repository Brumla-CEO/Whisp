<?php
namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use App\Middleware\AuthMiddleware;

class AdminController {
    private $user;
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
    }

    public function getDashboardStats() {
        // Ověření, že požadavek posílá admin
        $currentUser = AuthMiddleware::check();
        if ($currentUser->role !== 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Pouze pro adminy"]);
            return;
        }

        $logs = $this->user->getAllLogs();
        $users = $this->user->findAll();

        echo json_encode([
            "logs" => $logs,
            "users" => $users,
            "total_users" => count($users)
        ]);
    }
}