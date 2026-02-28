<?php
namespace App\Sockets;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Config\Database;
use App\Services\JWTService;
use PDO;

class ChatSocket implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;
    protected $connMeta;
    private $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->connMeta = [];
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            echo "✅ [WebSocket] Server běží a DB je připojena.\n";
        } catch (\Exception $e) { echo "❌ [WebSocket] Chyba DB: " . $e->getMessage() . "\n"; }
    }

    public function onOpen(ConnectionInterface $conn) {
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $queryArgs);

        $token = $queryArgs['token'] ?? null;
        if (!$token) {
            echo "❌ [CONNECT REJECT] Chybí token.\n";
            $conn->close();
            return;
        }

        $decoded = JWTService::decode($token);
        if (!$decoded || !isset($decoded->sub)) {
             echo "❌ [CONNECT REJECT] Neplatný token.\n";
             $conn->close();
             return;
        }

        $userId = $decoded->sub;

        $this->clients->attach($conn);
        $this->userConnections[$userId] = $conn;
        $this->connMeta[$conn->resourceId] = ['userId' => $userId, 'activeRoomId' => null];

        echo "🔌 [CONNECT] User ID: {$userId} (Auth OK)\n";

        $this->updateUserStatus($userId, 'online');
        $this->broadcastUserStatus($userId, 'online');
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $senderId = $this->connMeta[$from->resourceId]['userId'] ?? null;
        if (!$senderId) return;

        $data = json_decode($msg);
        if (!$data) return;

        switch ($data->type) {
            case 'presence:set_active_room':
                $this->connMeta[$from->resourceId]['activeRoomId'] = $data->roomId ?? null;
                break;

            case 'profile_change':
                $friends = $this->getUserFriends($senderId);
                foreach ($friends as $friendId) {
                    if (isset($this->userConnections[$friendId])) {
                        $this->userConnections[$friendId]->send(json_encode(['type' => 'contact_update', 'userId' => $senderId]));
                    }
                }
                break;

            case 'group_change':

                if(isset($data->roomId)) {
                    $this->broadcastToRoom($data->roomId, ['type' => 'group_update', 'roomId' => $data->roomId]);
                }
                break;

            case 'group_kick':
                $roomId = $data->roomId;
                $kickedUserId = $data->kickedUserId;

                if (!$this->isGroupAdmin($roomId, $senderId)) {
                     echo "⚠️ [SECURITY] User $senderId se pokusil vyhazovat, ale není admin!\n";
                     return;
                }

                $this->broadcastToRoom($roomId, ['type' => 'group_update', 'roomId' => $roomId]);

                if (isset($this->userConnections[$kickedUserId])) {
                    echo "👢 [KICK] Vyhazuji uživatele $kickedUserId z room $roomId\n";
                    $this->userConnections[$kickedUserId]->send(json_encode([
                        'type' => 'kicked_from_group',
                        'roomId' => $roomId,
                        'groupName' => $data->groupName ?? 'skupiny'
                    ]));
                }
                break;

            case 'message:new':

                $this->broadcastToRoom($data->roomId, [
                    'type' => 'message:new',
                    'roomId' => $data->roomId,
                    'message' => $data->message
                ]);

                $recipients = $this->getRoomMembers($data->roomId);
                foreach ($recipients as $uid) {
                    if ($uid == $senderId) continue;

                    $isActive = false;

                    foreach ($this->connMeta as $meta) {
                        if ($meta['userId'] == $uid && $meta['activeRoomId'] == $data->roomId) {
                            $isActive = true; break;
                        }
                    }

                    if (!$isActive) {
                        $this->createNotification($uid, $data->roomId, $senderId, "Nová zpráva");
                        if (isset($this->userConnections[$uid])) {
                            $this->userConnections[$uid]->send(json_encode([
                                'type' => 'notification',
                                'roomId' => $data->roomId,
                                'from' => $senderId
                            ]));
                        }
                    }
                }
                break;

            case 'message_update':
            case 'message_delete':
                $this->broadcastToRoom($data->roomId, $data);
                break;

            case 'friend_action':
                                        if (isset($this->userConnections[$data->targetId])) {
                                            $this->userConnections[$data->targetId]->send(json_encode([
                                                'type' => 'friend_update',
                                                'action' => $data->action,
                                                'from' => $senderId
                                            ]));
                                        }
                                        break;
            case 'contact_deleted':
                            $deletedUserId = $data->userId;

                            $friends = $this->getUserFriends($deletedUserId);
                            foreach ($friends as $friendId) {
                                if (isset($this->userConnections[$friendId])) {
                                    $this->userConnections[$friendId]->send(json_encode([
                                        'type' => 'contact_deleted',
                                        'userId' => $deletedUserId
                                    ]));
                                }
                            }

                            $userRooms = $this->getUserRoomsList($deletedUserId);
                            foreach ($userRooms as $roomId) {
                                $this->broadcastToRoom($roomId, [
                                    'type' => 'group_update',
                                    'roomId' => $roomId
                                ]);
                            }
                            break;


                    }
                }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        if (isset($this->connMeta[$conn->resourceId])) {
            $userId = $this->connMeta[$conn->resourceId]['userId'];
            unset($this->userConnections[$userId]);
            unset($this->connMeta[$conn->resourceId]);
            echo "❌ [DISCONNECT] User ID: {$userId}\n";
            $this->updateUserStatus($userId, 'offline');
            $this->broadcastUserStatus($userId, 'offline');
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        $conn->close();
    }

    // --- POMOCNÉ METODY ---

    private function broadcastToRoom($roomId, $data) {
        $members = $this->getRoomMembers($roomId);
        foreach ($members as $userId) {
            if (isset($this->userConnections[$userId])) {
                $this->userConnections[$userId]->send(json_encode($data));
            }
        }
    }

    private function broadcastUserStatus($userId, $status) {
        $msg = json_encode(['type' => 'user_status', 'userId' => $userId, 'status' => $status]);
        foreach ($this->clients as $client) $client->send($msg);
    }

    private function updateUserStatus($userId, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
        } catch (\Exception $e) {}
    }

    private function getRoomMembers($roomId) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM room_memberships WHERE room_id = ?");
            $stmt->execute([$roomId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) { return []; }
    }

    private function isGroupAdmin($roomId, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT role FROM room_memberships WHERE room_id = ? AND user_id = ?");
            $stmt->execute([$roomId, $userId]);
            $role = $stmt->fetchColumn();
            return $role === 'admin';
        } catch (\Exception $e) { return false; }
    }

    private function getUserFriends($userId) {
        try {
            $stmt = $this->db->prepare("SELECT CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END as friend_id FROM friendships WHERE (requester_id = ? OR addressee_id = ?) AND status = 'accepted'");
            $stmt->execute([$userId, $userId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) { return []; }
    }

    private function createNotification($userId, $roomId, $senderId, $message) {
        try {
            // Kontrola duplicity (aby tam nebylo 100 notifikací z jedné roomky)
            $check = $this->db->prepare("SELECT id FROM notifications WHERE user_id = ? AND room_id = ? AND is_read = false");
            $check->execute([$userId, $roomId]);
            if($check->fetch()) return;

            $stmt = $this->db->prepare("INSERT INTO notifications (user_id, room_id, type, content, is_read, created_at) VALUES (?, ?, 'message', ?, false, NOW())");
            $stmt->execute([$userId, $roomId, $message]);
        } catch (\Exception $e) {}
    }

private function getUserRoomsList($userId) {
        try {
            $stmt = $this->db->prepare("SELECT room_id FROM room_memberships WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) { return []; }
    }
}
?>