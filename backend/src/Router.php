<?php
namespace App;

use App\Controllers\AuthController;
use App\Controllers\UserController;

class Router {
    public function handleRequest() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];


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
            case '/api/user/me':
                if ($method === 'GET') {
                    $decoded = \App\Middleware\AuthMiddleware::check();
                    echo json_encode(["message" => "Přihlášen", "user_data" => $decoded]);
                }
                break;
            case '/api/logout':
                if ($method === 'POST') (new \App\Controllers\AuthController())->logout();
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