<?php
namespace App;

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\FriendController;
use App\Controllers\ChatController;
use App\Controllers\AdminController;
use App\Controllers\NotificationController;

class Router {
    public function handleRequest() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }

        if ($method === 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            exit(0);
        }

        // Defaultní Content-Type (pokud není OPTIONS)
        header("Content-Type: application/json; charset=UTF-8");

        // Instalace
        if ($uri === '/install.php') { require __DIR__ . '/../install.php'; exit; }

        // Dynamické routy (Users)
        if (strpos($uri, '/api/users/') === 0 && strlen($uri) > 11) {
            $id = str_replace('/api/users/', '', $uri);
            if ($method === 'DELETE') { (new UserController())->delete($id); return; }
            if ($method === 'PUT') { (new UserController())->update($id); return; }
        }

        switch ($uri) {
            // --- AUTH ---
            case '/api/login':              if ($method === 'POST') (new AuthController())->login(); break;
            case '/api/register':           if ($method === 'POST') (new AuthController())->register(); break;
            case '/api/logout':             if ($method === 'POST') (new AuthController())->logout(); break;
            case '/api/user/me':            if ($method === 'GET')  (new AuthController())->me(); break; // <--- OPRAVENO

            // --- USER & FRIENDS ---
            case '/api/users':              if ($method === 'GET') (new UserController())->index(); break;
            case '/api/friends':            if ($method === 'GET') (new FriendController())->index(); break;
            case '/api/friends/search':     if ($method === 'GET') (new FriendController())->search(); break;
            case '/api/friends/add':        if ($method === 'POST') (new FriendController())->add(); break;
            case '/api/friends/accept':     if ($method === 'POST') (new FriendController())->accept(); break;
            case '/api/friends/reject':     if ($method === 'POST') (new FriendController())->reject(); break;
            case '/api/friends/requests':   if ($method === 'GET') (new FriendController())->requests(); break;
            case '/api/friends/remove':     if ($method === 'POST') (new FriendController())->remove(); break;

            // --- CHAT & GROUPS ---
            case '/api/rooms':              if ($method === 'GET') (new ChatController())->getRooms(); break;
            case '/api/chat/open':          if ($method === 'POST') (new ChatController())->openDm(); break;
            case '/api/messages/history':   if ($method === 'GET') (new ChatController())->getHistory(); break;
            case '/api/messages/send':      if ($method === 'POST') (new ChatController())->sendMessage(); break;
            case '/api/messages/update':    if ($method === 'POST') (new ChatController())->updateMessage(); break;
            case '/api/messages/delete':    if ($method === 'POST') (new ChatController())->deleteMessage(); break;

            case '/api/groups/create':      if ($method === 'POST') (new ChatController())->createGroup(); break;
            case '/api/groups/members':     if ($method === 'GET') (new ChatController())->getGroupMembers(); break;
            case '/api/groups/add-member':  if ($method === 'POST') (new ChatController())->addGroupMember(); break;
            case '/api/groups/leave':       if ($method === 'POST') (new ChatController())->leaveGroup(); break;
            case '/api/groups/update':      if ($method === 'POST') (new ChatController())->updateGroup(); break;
            case '/api/groups/kick':        if ($method === 'POST') (new ChatController())->kickMember(); break;

            // --- NOTIFICATIONS ---
            case '/api/notifications':      if ($method === 'GET') (new NotificationController())->getUnread(); break;
            case '/api/chat/mark-read':     if ($method === 'POST') (new NotificationController())->markRead(); break;

            // --- ADMIN ---
            case '/api/admin/dashboard':    if ($method === 'GET') (new AdminController())->getDashboardStats(); break;
            case '/api/admin/users':        if ($method === 'GET') (new AdminController())->getUsers(); break;
            case '/api/admin/users/delete': if ($method === 'POST') (new AdminController())->deleteUser(); break;
            case '/api/admin/rooms':        if ($method === 'GET') (new AdminController())->getRooms(); break;
            case '/api/admin/rooms/delete': if ($method === 'POST') (new AdminController())->deleteRoom(); break;
            case '/api/admin/logs':         if ($method === 'GET') (new AdminController())->getLogs(); break;
            case '/api/admin/users/detail': if ($method === 'GET') (new AdminController())->getUserDetails(); break;
            case '/api/admin/chat/history': if ($method === 'GET') (new AdminController())->getRoomHistory(); break;
            case '/api/admin/create-admin': if ($method === 'POST') (new AdminController())->createAdmin(); break;
            case '/api/admin/rooms/detail': if ($method === 'GET') (new AdminController())->getRoomDetails(); break;

            default:
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(["message" => "Endpoint nenalezen: $uri"]);
                break;
        }
    }
}
?>
