<?php
namespace App\Sockets;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Config\Database;
use App\Services\JWTService;
use PDO;
use Throwable;

class ChatSocket implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;

    /**
     * @var array<string, array<int, ConnectionInterface>>
     */
    protected array $userConnections;

    /**
     * @var array<int, array{authenticated: bool, userId: ?string, activeRoomId: mixed}>
     */
    protected array $connMeta;

    private PDO $db;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->userConnections = [];
        $this->connMeta = [];

        try {
            $database = new Database();
            $this->db = $database->getConnection();
            echo "✅ [WebSocket] Server běží a DB je připojena.\n";
        } catch (Throwable $e) {
            echo "❌ [WebSocket] Chyba DB: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);

        $this->connMeta[$conn->resourceId] = [
            'authenticated' => false,
            'userId' => null,
            'activeRoomId' => null,
        ];

        echo "🟡 [CONNECT] Socket otevřen, čekám na auth. Resource ID: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg);

        if (!$data || !isset($data->type)) {
            $this->sendErrorAndClose($from, 'Invalid WebSocket payload.');
            return;
        }

        $meta = $this->connMeta[$from->resourceId] ?? null;

        if ($meta === null) {
            $this->sendErrorAndClose($from, 'Connection metadata missing.');
            return;
        }

        if ($data->type === 'auth') {
            $this->handleAuthMessage($from, $data);
            return;
        }

        if (($meta['authenticated'] ?? false) !== true) {
            $this->sendErrorAndClose($from, 'Unauthorized WebSocket connection.');
            return;
        }

        $senderId = $meta['userId'] ?? null;
        if (!$senderId) {
            $this->sendErrorAndClose($from, 'Missing authenticated user.');
            return;
        }

        switch ($data->type) {
            case 'presence:set_active_room':
                $this->connMeta[$from->resourceId]['activeRoomId'] = $data->roomId ?? null;
                break;

            case 'profile_change':
                $friends = $this->getUserFriends($senderId);
                foreach ($friends as $friendId) {
                    $this->sendToUser($friendId, [
                        'type' => 'contact_update',
                        'userId' => $senderId,
                    ]);
                }
                break;

            case 'group_change':
                if (isset($data->roomId)) {
                    $this->broadcastToRoom($data->roomId, [
                        'type' => 'group_update',
                        'roomId' => $data->roomId,
                    ]);
                }
                break;

            case 'group_kick':
                $roomId = $data->roomId ?? null;
                $kickedUserId = $data->kickedUserId ?? null;

                if (!$roomId || !$kickedUserId) {
                    return;
                }

                if (!$this->isGroupAdmin($roomId, $senderId)) {
                    echo "⚠️ [SECURITY] User {$senderId} se pokusil vyhazovat, ale není admin.\n";
                    return;
                }

                $this->broadcastToRoom($roomId, [
                    'type' => 'group_update',
                    'roomId' => $roomId,
                ]);

                echo "👢 [KICK] Vyhazuji uživatele {$kickedUserId} z room {$roomId}\n";

                $this->sendToUser($kickedUserId, [
                    'type' => 'kicked_from_group',
                    'roomId' => $roomId,
                    'groupName' => $data->groupName ?? 'skupiny',
                ]);
                break;

            case 'message:new':
                if (!isset($data->roomId, $data->message)) {
                    return;
                }

                $this->broadcastToRoom($data->roomId, [
                    'type' => 'message:new',
                    'roomId' => $data->roomId,
                    'message' => $data->message,
                ]);

                $recipients = $this->getRoomMembers($data->roomId);

                foreach ($recipients as $uid) {
                    if ($uid == $senderId) {
                        continue;
                    }

                    $isActive = false;

                    foreach ($this->connMeta as $connectionMeta) {
                        if (
                            ($connectionMeta['authenticated'] ?? false) === true
                            && ($connectionMeta['userId'] ?? null) == $uid
                            && ($connectionMeta['activeRoomId'] ?? null) == $data->roomId
                        ) {
                            $isActive = true;
                            break;
                        }
                    }

                    if (!$isActive) {
                        $this->createNotification($uid, $data->roomId, $senderId, 'Nová zpráva');

                        $this->sendToUser($uid, [
                            'type' => 'notification',
                            'roomId' => $data->roomId,
                            'from' => $senderId,
                        ]);
                    }
                }
                break;

            case 'message_update':
            case 'message_delete':
                if (!isset($data->roomId)) {
                    return;
                }

                $this->broadcastToRoom($data->roomId, (array) $data);
                break;

            case 'friend_action':
                if (!isset($data->targetId, $data->action)) {
                    return;
                }

                $this->sendToUser($data->targetId, [
                    'type' => 'friend_update',
                    'action' => $data->action,
                    'from' => $senderId,
                ]);
                break;

            case 'contact_deleted':
                $deletedUserId = $data->userId ?? null;
                if (!$deletedUserId) {
                    return;
                }

                $friends = $this->getUserFriends($deletedUserId);
                foreach ($friends as $friendId) {
                    $this->sendToUser($friendId, [
                        'type' => 'contact_deleted',
                        'userId' => $deletedUserId,
                    ]);
                }

                $userRooms = $this->getUserRoomsList($deletedUserId);
                foreach ($userRooms as $roomId) {
                    $this->broadcastToRoom($roomId, [
                        'type' => 'group_update',
                        'roomId' => $roomId,
                    ]);
                }
                break;

            case 'admin_user_deleted':
                $targetUserId = $data->targetId ?? null;
                if (!$targetUserId) {
                    return;
                }

                $this->sendToUser((string) $targetUserId, [
                    'type' => 'admin_user_deleted',
                    'targetId' => (string) $targetUserId,
                    'deletedUsername' => $data->deletedUsername ?? null,
                ]);
                break;

            case 'admin_room_deleted':
                $roomId = $data->roomId ?? null;
                $memberIds = is_array($data->memberIds ?? null) ? $data->memberIds : [];
                if (!$roomId || empty($memberIds)) {
                    return;
                }

                foreach ($memberIds as $memberId) {
                    $this->sendToUser((string) $memberId, [
                        'type' => 'group_deleted',
                        'roomId' => $roomId,
                        'groupName' => $data->roomName ?? 'Skupina',
                    ]);
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);

        $meta = $this->connMeta[$conn->resourceId] ?? null;
        if ($meta === null) {
            return;
        }

        $userId = $meta['userId'] ?? null;

        unset($this->connMeta[$conn->resourceId]);

        if ($userId !== null) {
            $this->removeUserConnection($userId, $conn->resourceId);

            echo "❌ [DISCONNECT] User ID: {$userId}, Resource ID: {$conn->resourceId}\n";

            if (!$this->hasActiveConnections($userId)) {
                $this->updateUserStatus($userId, 'offline');
                $this->broadcastUserStatus($userId, 'offline');
            }
        } else {
            echo "❌ [DISCONNECT] Neautorizovaný socket odpojen. Resource ID: {$conn->resourceId}\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "❌ [WS ERROR] " . $e->getMessage() . "\n";
        $conn->close();
    }

    private function handleAuthMessage(ConnectionInterface $conn, object $data): void
    {
        $meta = $this->connMeta[$conn->resourceId] ?? null;
        if ($meta === null) {
            $this->sendErrorAndClose($conn, 'Connection metadata missing.');
            return;
        }

        if (($meta['authenticated'] ?? false) === true) {
            $conn->send(json_encode([
                'type' => 'auth_ok',
                'userId' => $meta['userId'],
            ]));
            return;
        }

        $token = isset($data->token) && is_string($data->token) ? trim($data->token) : '';

        if ($token === '') {
            $this->sendErrorAndClose($conn, 'Missing WebSocket auth token.');
            return;
        }

        $userId = $this->validateSocketToken($token);

        if ($userId === null) {
            echo "❌ [AUTH FAIL] Resource ID {$conn->resourceId}\n";
            $this->sendErrorAndClose($conn, 'Unauthorized');
            return;
        }

        $this->connMeta[$conn->resourceId]['authenticated'] = true;
        $this->connMeta[$conn->resourceId]['userId'] = $userId;
        $this->connMeta[$conn->resourceId]['activeRoomId'] = null;

        $this->userConnections[$userId][$conn->resourceId] = $conn;

        echo "🔌 [AUTH OK] User ID: {$userId}, Resource ID: {$conn->resourceId}\n";

        if (count($this->userConnections[$userId]) === 1) {
            $this->updateUserStatus($userId, 'online');
            $this->broadcastUserStatus($userId, 'online');
        }

        $conn->send(json_encode([
            'type' => 'auth_ok',
            'userId' => $userId,
        ]));
    }

    private function validateSocketToken(string $token): ?string
    {
        $decoded = JWTService::decode($token);

        if (!$decoded || !isset($decoded->sub)) {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM sessions
                 WHERE token = :token
                   AND is_active = TRUE
                   AND expires_at > NOW()
                 LIMIT 1'
            );

            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->execute();

            $sessionId = $stmt->fetchColumn();

            if ($sessionId === false) {
                return null;
            }

            return (string) $decoded->sub;
        } catch (Throwable $e) {
            echo "❌ [AUTH DB ERROR] " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function sendToUser(string $userId, array $payload): void
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        $encoded = json_encode($payload);

        foreach ($this->userConnections[$userId] as $connection) {
            $connection->send($encoded);
        }
    }

    private function removeUserConnection(string $userId, int $resourceId): void
    {
        if (!isset($this->userConnections[$userId][$resourceId])) {
            return;
        }

        unset($this->userConnections[$userId][$resourceId]);

        if (empty($this->userConnections[$userId])) {
            unset($this->userConnections[$userId]);
        }
    }

    private function hasActiveConnections(string $userId): bool
    {
        return isset($this->userConnections[$userId]) && count($this->userConnections[$userId]) > 0;
    }

    private function sendErrorAndClose(ConnectionInterface $conn, string $message): void
    {
        $conn->send(json_encode([
            'type' => 'auth_error',
            'message' => $message,
        ]));
        $conn->close();
    }

    private function broadcastToRoom($roomId, array $data): void
    {
        $members = $this->getRoomMembers($roomId);
        foreach ($members as $userId) {
            $this->sendToUser((string) $userId, $data);
        }
    }

    private function broadcastUserStatus($userId, $status): void
    {
        $msg = json_encode([
            'type' => 'user_status',
            'userId' => $userId,
            'status' => $status,
        ]);

        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    private function updateUserStatus($userId, $status): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->execute([$status, $userId]);
        } catch (Throwable $e) {
        }
    }

    private function getRoomMembers($roomId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT user_id FROM room_memberships WHERE room_id = ?');
            $stmt->execute([$roomId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function isGroupAdmin($roomId, $userId): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT role FROM room_memberships WHERE room_id = ? AND user_id = ?');
            $stmt->execute([$roomId, $userId]);
            $role = $stmt->fetchColumn();
            return $role === 'admin';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getUserFriends($userId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END as friend_id
                 FROM friendships
                 WHERE (requester_id = ? OR addressee_id = ?)
                   AND status = 'accepted'"
            );
            $stmt->execute([$userId, $userId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function createNotification($userId, $roomId, $senderId, $message): void
    {
        try {
            $check = $this->db->prepare(
                'SELECT id
                 FROM notifications
                 WHERE user_id = ?
                   AND room_id = ?
                   AND is_read = false'
            );
            $check->execute([$userId, $roomId]);

            if ($check->fetch()) {
                return;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO notifications (user_id, room_id, type, content, is_read, created_at)
                 VALUES (?, ?, 'message', ?, false, NOW())"
            );
            $stmt->execute([$userId, $roomId, $message]);
        } catch (Throwable $e) {
        }
    }

    private function getUserRoomsList($userId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT room_id FROM room_memberships WHERE user_id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
    }
}
