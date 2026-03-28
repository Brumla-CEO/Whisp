<?php
namespace App\Models;

use PDO;
use Throwable;

class Chat {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getUserRooms(string $userId): array {
        $query = "
            SELECT
                r.id,
                r.type,
                r.owner_id,
                CASE
                    WHEN r.type = 'dm' THEN (
                        SELECT u.username
                        FROM room_memberships rm2
                        JOIN users u ON rm2.user_id = u.id
                        WHERE rm2.room_id = r.id AND rm2.user_id != :uid
                        LIMIT 1
                    )
                    ELSE r.name
                END as name,
                CASE
                    WHEN r.type = 'dm' THEN (
                        SELECT u.avatar_url
                        FROM room_memberships rm3
                        JOIN users u ON rm3.user_id = u.id
                        WHERE rm3.room_id = r.id AND rm3.user_id != :uid
                        LIMIT 1
                    )
                    ELSE r.avatar_url
                END as avatar_url,
                (SELECT content FROM messages WHERE room_id = r.id AND (is_deleted = false OR is_deleted IS NULL) ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM notifications WHERE room_id = r.id AND user_id = :uid AND is_read = false) as unread_count
            FROM rooms r
            JOIN room_memberships rm ON r.id = rm.room_id
            WHERE rm.user_id = :uid
              AND (
                    r.type = 'group'
                    OR EXISTS (
                        SELECT 1
                        FROM room_memberships peer_rm
                        JOIN friendships f ON (
                            (f.requester_id = :uid AND f.addressee_id = peer_rm.user_id)
                            OR (f.requester_id = peer_rm.user_id AND f.addressee_id = :uid)
                        )
                        WHERE peer_rm.room_id = r.id
                          AND peer_rm.user_id != :uid
                          AND f.status = 'accepted'
                    )
                  )
            ORDER BY r.created_at DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrCreateDmRoom(string $user1, string $user2): int|false {
        if (!$this->areUsersFriends($user1, $user2)) {
            return false;
        }

        $sql = "SELECT r.id FROM rooms r
                JOIN room_memberships rm1 ON r.id = rm1.room_id
                JOIN room_memberships rm2 ON r.id = rm2.room_id
                WHERE r.type = 'dm' AND rm1.user_id = ? AND rm2.user_id = ?
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user1, $user2]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return (int) $row['id'];
        }

        $this->conn->beginTransaction();
        try {
            $this->conn->exec("INSERT INTO rooms (type, created_at) VALUES ('dm', NOW())");
            $roomId = (int) $this->conn->lastInsertId();

            $stmt = $this->conn->prepare("INSERT INTO room_memberships (room_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$roomId, $user1]);
            $stmt->execute([$roomId, $user2]);

            $this->conn->commit();
            return $roomId;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function canAccessRoom(int|string $roomId, string $userId): bool {
        $roomType = $this->getRoomType($roomId);
        if ($roomType === null) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$roomId, $userId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        if ($roomType === 'group') {
            return true;
        }

        $peerId = $this->getPeerUserIdForDm($roomId, $userId);
        if ($peerId === null) {
            return false;
        }

        return $this->areUsersFriends($userId, $peerId);
    }

    public function getRoomMessages(int|string $roomId, string $userId, int $limit = 50): array {
        if (!$this->canAccessRoom($roomId, $userId)) {
            return [];
        }

        $query = "
            SELECT m.*, COALESCE(u.username, 'deleted_user') AS username, u.avatar_url
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = :room_id
              AND (m.is_deleted = false OR m.is_deleted IS NULL)
            ORDER BY m.created_at ASC
            LIMIT :limit
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':room_id', (int) $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sendMessage(int|string $roomId, string $senderId, string $content, int|string|null $replyToId = null): array|false {
        if (!$this->canAccessRoom($roomId, $senderId)) {
            return false;
        }

        $query = "INSERT INTO messages (room_id, sender_id, content, reply_to_id, created_at)
                  VALUES (:room_id, :sender_id, :content, :reply_to, NOW()) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':room_id', (int) $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':sender_id', $senderId);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':reply_to', $replyToId, $replyToId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        if ($stmt->execute()) {
            $msgId = $stmt->fetchColumn();
            $sql = "SELECT m.*, COALESCE(u.username, 'deleted_user') AS username, u.avatar_url
                    FROM messages m
                    LEFT JOIN users u ON m.sender_id = u.id
                    WHERE m.id = ?";
            $stmtFetch = $this->conn->prepare($sql);
            $stmtFetch->execute([$msgId]);
            return $stmtFetch->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function deleteMessage($messageId, $userId) {
        $stmt = $this->conn->prepare("UPDATE messages SET is_deleted = TRUE WHERE id = ? AND sender_id = ?");
        return $stmt->execute([$messageId, $userId]);
    }

    public function editMessage($messageId, $userId, $newContent) {
        $stmt = $this->conn->prepare("UPDATE messages SET content = ?, is_edited = TRUE WHERE id = ? AND sender_id = ?");
        return $stmt->execute([$newContent, $messageId, $userId]);
    }

    public function createGroup($name, $ownerId, $memberIds) {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("INSERT INTO rooms (name, type, owner_id, created_at) VALUES (?, 'group', ?, NOW())");
            $stmt->execute([$name, $ownerId]);
            $roomId = $this->conn->lastInsertId();

            $stmtM = $this->conn->prepare("INSERT INTO room_memberships (room_id, user_id, role) VALUES (?, ?, ?)");
            $stmtM->execute([$roomId, $ownerId, 'admin']);

            foreach ($memberIds as $mid) {
                $stmtM->execute([$roomId, $mid, 'member']);
            }

            $this->conn->commit();
            return $roomId;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function getGroupMembers($roomId) {
        $query = "SELECT u.id, u.username, u.avatar_url, u.status, rm.role
                  FROM room_memberships rm
                  JOIN users u ON rm.user_id = u.id
                  WHERE rm.room_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$roomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addGroupMember($roomId, $userId) {
        $stmt = $this->conn->prepare("INSERT INTO room_memberships (room_id, user_id, role) VALUES (?, ?, 'member')");
        return $stmt->execute([$roomId, $userId]);
    }

    public function removeGroupMember($roomId, $userId) {
        $stmt = $this->conn->prepare("DELETE FROM room_memberships WHERE room_id = ? AND user_id = ?");
        return $stmt->execute([$roomId, $userId]);
    }

    public function getMemberRole($roomId, $userId) {
        $stmt = $this->conn->prepare("SELECT role FROM room_memberships WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $userId]);
        return $stmt->fetchColumn();
    }

    public function updateGroupInfo($roomId, $name, $avatarUrl) {
        $stmt = $this->conn->prepare("UPDATE rooms SET name = ?, avatar_url = ? WHERE id = ?");
        return $stmt->execute([$name, $avatarUrl, $roomId]);
    }

    public function deleteRoom($roomId) {
        $stmt = $this->conn->prepare("DELETE FROM rooms WHERE id = ?");
        return $stmt->execute([$roomId]);
    }

    public function getMemberCount($roomId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM room_memberships WHERE room_id = ?");
        $stmt->execute([$roomId]);
        return $stmt->fetchColumn();
    }

    /*public function getUnreadNotifications($userId) {
        $query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }*/

    /*public function markAsRead($userId, $roomId) {
        $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND room_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId, $roomId]);
    }*/

    public function leaveGroupSafe($roomId, $userId) {
        $this->conn->beginTransaction();
        try {
            $stmtRoom = $this->conn->prepare("SELECT owner_id FROM rooms WHERE id = ?");
            $stmtRoom->execute([$roomId]);
            $ownerId = $stmtRoom->fetchColumn();

            if ($ownerId && $ownerId == $userId) {
                $stmtNext = $this->conn->prepare("
                        SELECT user_id
                        FROM room_memberships
                        WHERE room_id = ? AND user_id != ?
                        ORDER BY joined_at ASC
                        LIMIT 1
                    "
                );
                $stmtNext->execute([$roomId, $userId]);
                $heirId = $stmtNext->fetchColumn();

                if ($heirId) {
                    $this->conn->prepare("UPDATE rooms SET owner_id = ? WHERE id = ?")->execute([$heirId, $roomId]);
                    $this->conn->prepare("UPDATE room_memberships SET role = 'admin' WHERE room_id = ? AND user_id = ?")->execute([$roomId, $heirId]);
                } else {
                    $this->conn->prepare("UPDATE rooms SET owner_id = NULL WHERE id = ?")->execute([$roomId]);
                }
            }

            $stmtDel = $this->conn->prepare("DELETE FROM room_memberships WHERE room_id = ? AND user_id = ?");
            $stmtDel->execute([$roomId, $userId]);

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    private function getRoomType(int|string $roomId): ?string
    {
        $stmt = $this->conn->prepare('SELECT type FROM rooms WHERE id = ? LIMIT 1');
        $stmt->execute([$roomId]);
        $type = $stmt->fetchColumn();
        return $type === false ? null : (string) $type;
    }

    private function getPeerUserIdForDm(int|string $roomId, string $userId): ?string
    {
        $stmt = $this->conn->prepare('SELECT user_id FROM room_memberships WHERE room_id = ? AND user_id != ? LIMIT 1');
        $stmt->execute([$roomId, $userId]);
        $peerId = $stmt->fetchColumn();
        return $peerId === false ? null : (string) $peerId;
    }

    private function areUsersFriends(string $userId, string $otherUserId): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id
             FROM friendships
             WHERE ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))
               AND status = 'accepted'
             LIMIT 1"
        );
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        return $stmt->fetchColumn() !== false;
    }
}
