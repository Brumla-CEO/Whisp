<?php

namespace App;

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\FriendController;
use App\Controllers\NotificationController;
use App\Controllers\UserController;
use App\Http\ApiResponse;
use App\Middleware\RateLimitMiddleware;

class Router
{
    public function handleRequest(): void
    {
    error_log('ROUTER DEBUG => METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'null') . ' | URI: ' . ($_SERVER['REQUEST_URI'] ?? 'null'));
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
error_log('ROUTER PATH => ' . $uri);
        header('Content-Type: application/json; charset=UTF-8');

        if ($uri === '/install_admin.php') {
            $installAdminPath = __DIR__ . '/../public/install_admin.php';
            if (file_exists($installAdminPath)) {
                require $installAdminPath;
                exit;
            }

            http_response_code(404);
            echo json_encode([
                'message' => 'Instalační soubor install_admin.php nebyl nalezen.'
            ]);
            exit;
        }

        $this->applyRateLimits($uri, $method);

        if (strpos($uri, '/api/users/') === 0 && strlen($uri) > 11) {
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

        switch ($uri) {
            case '/api/login':
                if ($method === 'POST') {
                    (new AuthController())->login();
                    return;
                }
                break;
            case '/api/register':
                if ($method === 'POST') {
                    (new AuthController())->register();
                    return;
                }
                break;
            case '/api/logout':
                if ($method === 'POST') {
                    (new AuthController())->logout();
                    return;
                }
                break;
            case '/api/user/me':
                if ($method === 'GET') {
                    (new AuthController())->me();
                    return;
                }
                break;
            case '/api/users':
                if ($method === 'GET') {
                    (new UserController())->index();
                    return;
                }
                break;
            case '/api/friends':
                if ($method === 'GET') {
                    (new FriendController())->index();
                    return;
                }
                break;
            case '/api/friends/search':
                if ($method === 'GET') {
                    (new FriendController())->search();
                    return;
                }
                break;
            case '/api/friends/add':
                if ($method === 'POST') {
                    (new FriendController())->add();
                    return;
                }
                break;
            case '/api/friends/accept':
                if ($method === 'POST') {
                    (new FriendController())->accept();
                    return;
                }
                break;
            case '/api/friends/reject':
                if ($method === 'POST') {
                    (new FriendController())->reject();
                    return;
                }
                break;
            case '/api/friends/requests':
                if ($method === 'GET') {
                    (new FriendController())->requests();
                    return;
                }
                break;
            case '/api/friends/remove':
                if ($method === 'POST') {
                    (new FriendController())->remove();
                    return;
                }
                break;
            case '/api/rooms':
                if ($method === 'GET') {
                    (new ChatController())->getRooms();
                    return;
                }
                break;
            case '/api/chat/open':
                if ($method === 'POST') {
                    (new ChatController())->openDm();
                    return;
                }
                break;
            case '/api/messages/history':
                if ($method === 'GET') {
                    (new ChatController())->getHistory();
                    return;
                }
                break;
            case '/api/messages/send':
                if ($method === 'POST') {
                    (new ChatController())->sendMessage();
                    return;
                }
                break;
            case '/api/messages/update':
                if ($method === 'POST') {
                    (new ChatController())->updateMessage();
                    return;
                }
                break;
            case '/api/messages/delete':
                if ($method === 'POST') {
                    (new ChatController())->deleteMessage();
                    return;
                }
                break;
            case '/api/groups/create':
                if ($method === 'POST') {
                    (new ChatController())->createGroup();
                    return;
                }
                break;
            case '/api/groups/members':
                if ($method === 'GET') {
                    (new ChatController())->getGroupMembers();
                    return;
                }
                break;
            case '/api/groups/add-member':
                if ($method === 'POST') {
                    (new ChatController())->addGroupMember();
                    return;
                }
                break;
            case '/api/groups/leave':
                if ($method === 'POST') {
                    (new ChatController())->leaveGroup();
                    return;
                }
                break;
            case '/api/groups/update':
                if ($method === 'POST') {
                    (new ChatController())->updateGroup();
                    return;
                }
                break;
            case '/api/groups/kick':
                if ($method === 'POST') {
                    (new ChatController())->kickMember();
                    return;
                }
                break;
            case '/api/notifications':
                if ($method === 'GET') {
                    (new NotificationController())->getUnread();
                    return;
                }
                break;
            case '/api/chat/mark-read':
                if ($method === 'POST') {
                    (new NotificationController())->markRead();
                    return;
                }
                break;
            case '/api/admin/dashboard':
                if ($method === 'GET') {
                    (new AdminController())->getDashboardStats();
                    return;
                }
                break;
            case '/api/admin/users':
                if ($method === 'GET') {
                    (new AdminController())->getUsers();
                    return;
                }
                break;
            case '/api/admin/users/delete':
                if ($method === 'POST') {
                    (new AdminController())->deleteUser();
                    return;
                }
                break;
            case '/api/admin/rooms':
                if ($method === 'GET') {
                    (new AdminController())->getRooms();
                    return;
                }
                break;
            case '/api/admin/rooms/delete':
                if ($method === 'POST') {
                    (new AdminController())->deleteRoom();
                    return;
                }
                break;
            case '/api/admin/logs':
                if ($method === 'GET') {
                    (new AdminController())->getLogs();
                    return;
                }
                break;
            case '/api/admin/users/detail':
                if ($method === 'GET') {
                    (new AdminController())->getUserDetails();
                    return;
                }
                break;
            case '/api/admin/chat/history':
                if ($method === 'GET') {
                    (new AdminController())->getRoomHistory();
                    return;
                }
                break;
            case '/api/admin/create-admin':
                if ($method === 'POST') {
                    (new AdminController())->createAdmin();
                    return;
                }
                break;
            case '/api/admin/rooms/detail':
                if ($method === 'GET') {
                    (new AdminController())->getRoomDetails();
                    return;
                }
                break;
        }

        ApiResponse::error('route_not_found', "Endpoint nenalezen: {$uri}", 404);
    }

    private function applyRateLimits(string $uri, string $method): void
    {
        if ($method !== 'POST') {
            return;
        }

        switch ($uri) {
            case '/api/login':
                RateLimitMiddleware::handle('login', 10, 60);
                return;
            case '/api/register':
                RateLimitMiddleware::handle('register', 5, 300);
                return;
            case '/api/friends/add':
                RateLimitMiddleware::handle('friends_add', 20, 60);
                return;
            case '/api/messages/send':
                RateLimitMiddleware::handle('messages_send', 120, 60);
                return;
        }
    }
}
