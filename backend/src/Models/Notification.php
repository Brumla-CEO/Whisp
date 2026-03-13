<?php
namespace App\Models;

use PDO;

class Notification
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function getUnreadByUserId(string $userId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead(string $userId, int $roomId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND room_id = ?"
        );
        return $stmt->execute([$userId, $roomId]);
    }

    public function hasUnreadForRoom(string $userId, int $roomId): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM notifications WHERE user_id = ? AND room_id = ? AND is_read = false LIMIT 1'
        );
        $stmt->execute([$userId, $roomId]);
        return $stmt->fetchColumn() !== false;
    }

    public function createMessageNotification(string $userId, int $roomId, string $message): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO notifications (user_id, room_id, type, content, is_read, created_at)
             VALUES (?, ?, 'message', ?, false, NOW())"
        );
        return $stmt->execute([$userId, $roomId, $message]);
    }
}
