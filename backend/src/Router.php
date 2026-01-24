<?php
namespace App;

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware; // Přidáno použití namespace

class Router {
    public function handleRequest() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Dynamické routy (API Users)
        if (strpos($uri, '/api/users/') === 0) {
            $id = str_replace('/api/users/', '', $uri);

            if ($method === 'DELETE') {
                (new UserController())->delete($id);
                return;
            }

            if ($method === 'PUT') {
                (new UserController())->update($id);
                return;
            }
        }

        // PEVNÉ CESTY
        switch ($uri) {
            case '/api/register':
                if ($method === 'POST') (new AuthController())->register();
                break;
            case '/api/login':
                if ($method === 'POST') (new AuthController())->login();
                break;
            case '/api/users':
                if ($method === 'GET') (new UserController())->index();
                break;

            // --- ZDE JE HLAVNÍ OPRAVA ---
            case '/api/user/me':
                if ($method === 'GET') {
                    // 1. Ověříme token
                    $decoded = AuthMiddleware::check();

                    // 2. Načteme uživatele z DB podle ID v tokenu (zde předpokládám, že token má 'sub' nebo 'id')
                    $userId = $decoded->sub ?? $decoded->id;

                    $db = (new \App\Config\Database())->getConnection();
                    $userModel = new \App\Models\User($db);
                    $freshUserData = $userModel->findById($userId);

                    if ($freshUserData) {
                        // Přidáme informaci o roli, pokud není v findById, ale většinou stačí data z DB
                        echo json_encode(["message" => "Přihlášen", "user_data" => $freshUserData]);
                    } else {
                        http_response_code(404);
                        echo json_encode(["message" => "Uživatel nenalezen"]);
                    }
                }
                break;
            // -----------------------------

            case '/api/logout':
                if ($method === 'POST') (new AuthController())->logout();
                break;
            case '/api/admin/stats':
                if ($method === 'GET') {
                    (new \App\Controllers\AdminController())->getDashboardStats();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(["message" => "Endpoint nenalezen"]);
        }
    }
}