<?php
namespace App\Models;

use PDO;

class Chat {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // =========================================================
    // 1. ZÍSKÁVÁNÍ DAT (Rooms, Messages)
    // =========================================================

    public function getUserRooms($userId) {
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
                (SELECT content FROM messages WHERE room_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM notifications WHERE room_id = r.id AND user_id = :uid AND is_read = false) as unread_count
            FROM rooms r
            JOIN room_memberships rm ON r.id = rm.room_id
            WHERE rm.user_id = :uid
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrCreateDmRoom($user1, $user2) {
        $sql = "SELECT r.id FROM rooms r
                JOIN room_memberships rm1 ON r.id = rm1.room_id
                JOIN room_memberships rm2 ON r.id = rm2.room_id
                WHERE r.type = 'dm' AND rm1.user_id = ? AND rm2.user_id = ?
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user1, $user2]);

        if ($row = $stmt->fetch()) {
            return $row['id'];
        }

        $this->conn->beginTransaction();
        try {
            $this->conn->exec("INSERT INTO rooms (type, created_at) VALUES ('dm', NOW())");
            $roomId = $this->conn->lastInsertId();

            $stmt = $this->conn->prepare("INSERT INTO room_memberships (room_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$roomId, $user1]);
            $stmt->execute([$roomId, $user2]);

            $this->conn->commit();
            return $roomId;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function getRoomMessages($roomId, $userId, $limit = 50) {
        $query = "
            SELECT m.*, u.username, u.avatar_url
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = :room_id
            AND (m.is_deleted = false OR m.is_deleted IS NULL)
            ORDER BY m.created_at ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':room_id', $roomId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================
    // 2. PRÁCE SE ZPRÁVAMI (Send, Edit, Delete)
    // =========================================================

    public function sendMessage($roomId, $senderId, $content, $replyToId = null) {
        $checkStmt = $this->conn->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ?");
        $checkStmt->execute([$roomId, $senderId]);

        if (!$checkStmt->fetch()) {
            return false;
        }

        $query = "INSERT INTO messages (room_id, sender_id, content, reply_to_id, created_at)
                  VALUES (:room_id, :sender_id, :content, :reply_to, NOW()) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':room_id', $roomId);
        $stmt->bindValue(':sender_id', $senderId);
        $stmt->bindValue(':content', $content);
        $stmt->bindValue(':reply_to', $replyToId);

        if ($stmt->execute()) {
            $msgId = $stmt->fetchColumn();
            $sql = "SELECT m.*, u.username, u.avatar_url
                    FROM messages m
                    JOIN users u ON m.sender_id = u.id
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

    // =========================================================
    // 3. SPRÁVA SKUPIN (Create, Members, Update, Kick)
    // =========================================================

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
        } catch (\Exception $e) {
            $this->conn->rollBack();
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

    public function getUnreadNotifications($userId) {
        $query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead($userId, $roomId) {
        $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND room_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId, $roomId]);
    }

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
                    ");
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

            } catch (\Exception $e) {
                $this->conn->rollBack();
                return false;
            }
        }
}
?>