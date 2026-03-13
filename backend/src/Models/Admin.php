<?php
namespace App\Models;

use PDO;

class Admin
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function getDashboardStats(): array
    {
        $totalUsers = (int) $this->conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $onlineUsers = (int) $this->conn->query("SELECT COUNT(*) FROM users WHERE status = 'online'")->fetchColumn();
        $totalRooms = (int) $this->conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
        $totalMessages = (int) $this->conn->query("SELECT COUNT(*) FROM messages")->fetchColumn();

        return [
            'counts' => [
                'users' => $totalUsers,
                'online' => $onlineUsers,
                'rooms' => $totalRooms,
                'messages' => $totalMessages,
            ],
            'recent_logs' => $this->getRecentLogs(20),
        ];
    }

    public function getUsers(): array
    {
        $query = "
            SELECT u.id, u.username, u.email, u.status, u.created_at, u.avatar_url, u.bio, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ";

        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserDetails(string $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50'
        );
        $stmt->execute([$userId]);
        return ['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getRooms(): array
    {
        $query = "
            SELECT r.id, r.name, r.type, r.created_at, u.username as owner_name,
            (SELECT COUNT(*) FROM room_memberships WHERE room_id = r.id) as member_count,
            (SELECT COUNT(*) FROM messages WHERE room_id = r.id) as msg_count
            FROM rooms r
            LEFT JOIN users u ON r.owner_id = u.id
            ORDER BY r.created_at DESC
        ";

        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoomDetails(int $roomId): array
    {
        $stmt = $this->conn->prepare(
            "
            SELECT u.id, u.username, u.avatar_url, rm.role
            FROM room_memberships rm
            JOIN users u ON rm.user_id = u.id
            WHERE rm.room_id = ?
            "
        );
        $stmt->execute([$roomId]);
        return ['members' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getRoomHistory(int $roomId): array
    {
        $stmt = $this->conn->prepare(
            "
            SELECT m.content, m.created_at, u.username
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ?
            ORDER BY m.created_at DESC
            LIMIT 50
            "
        );
        $stmt->execute([$roomId]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getLogs(int $limit = 200): array
    {
        $stmt = $this->conn->prepare(
            "
            SELECT al.*, u.username
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.timestamp DESC LIMIT :limit
            "
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentLogs(int $limit = 20): array
    {
        return $this->getLogs($limit);
    }

    public function createAdmin(string $username, string $email, string $passwordHash, ?string $avatarUrl = null): bool
    {
        $adminRoleId = $this->getRoleIdByName('admin');
        if ($adminRoleId === null) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "
            INSERT INTO users (username, email, password_hash, role_id, avatar_url, created_at, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'offline')
            "
        );

        return $stmt->execute([$username, $email, $passwordHash, $adminRoleId, $avatarUrl]);
    }

    public function getRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->conn->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
