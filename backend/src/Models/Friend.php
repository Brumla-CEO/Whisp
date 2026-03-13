<?php
namespace App\Models;

use PDO;
use Throwable;

class Friend {
    private $conn;
    private $table = "friendships";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function sendRequest($fromId, $toId) {
        if ($this->exists($fromId, $toId)) {
            return false;
        }

        $checkAdmin = "
            SELECT r.name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ";

        $stmtAdmin = $this->conn->prepare($checkAdmin);
        $stmtAdmin->execute([':id' => $toId]);
        $roleName = $stmtAdmin->fetchColumn();

        if ($roleName === 'admin') {
            return false;
        }

        $query = "INSERT INTO {$this->table} (requester_id, addressee_id, status, created_at)
                  VALUES (:from, :to, 'pending', NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':from', $fromId);
        $stmt->bindValue(':to', $toId);

        return $stmt->execute();
    }

    public function acceptRequest($requestId) {
        try {
            $this->conn->beginTransaction();
            $query = "UPDATE {$this->table} SET status = 'accepted' WHERE id = :id AND status = 'pending'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $requestId);
            $stmt->execute();

            if ($stmt->rowCount() !== 1) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function rejectRequest($requestId) {
        return $this->remove($requestId);
    }

    public function remove($requestId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $requestId);
        return $stmt->execute();
    }

    public function getFriends($userId) {
        $query = "
            SELECT u.id, u.username, u.avatar_url, u.status, u.bio, f.id as friendship_id
            FROM {$this->table} f
            JOIN users u ON (
                CASE
                    WHEN f.requester_id = :uid THEN f.addressee_id = u.id
                    ELSE f.requester_id = u.id
                END
            )
            WHERE (f.requester_id = :uid OR f.addressee_id = :uid)
            AND f.status = 'accepted'
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':uid', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeFriendship($userId, $friendId) {
        try {
            $this->conn->beginTransaction();
            $query = "DELETE FROM {$this->table}
                      WHERE ((requester_id = :uid AND addressee_id = :fid)
                         OR (requester_id = :fid AND addressee_id = :uid))
                        AND status = 'accepted'";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([':uid' => $userId, ':fid' => $friendId]);
            $affected = $stmt->rowCount();
            $this->conn->commit();
            return $affected > 0;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function getPendingRequests($userId) {
        $query = "
            SELECT f.id as request_id, u.username, u.avatar_url, f.created_at, u.id as requester_id
            FROM {$this->table} f
            JOIN users u ON f.requester_id = u.id
            WHERE f.addressee_id = :uid AND f.status = 'pending'
            ORDER BY f.created_at DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':uid', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchAvailableUsers(string $currentUserId, string $query, int $limit = 10): array {
        $sql = "SELECT u.id, u.username, u.avatar_url, u.status
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.username LIKE :query
                  AND u.id != :currentUserId
                  AND u.username NOT LIKE 'deleted_%'
                  AND r.name != 'admin'
                LIMIT :limit";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':currentUserId', $currentUserId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($users, fn(array $user): bool => !$this->exists($currentUserId, $user['id'])));
    }

    public function areFriends(string $userId, string $otherUserId): bool {
        $query = "SELECT id FROM {$this->table}
                  WHERE ((requester_id = :id1 AND addressee_id = :id2)
                      OR (requester_id = :id2 AND addressee_id = :id1))
                    AND status = 'accepted'
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id1' => $userId, ':id2' => $otherUserId]);
        return $stmt->fetchColumn() !== false;
    }

    public function exists($id1, $id2) {
        $query = "SELECT id FROM {$this->table}
                  WHERE (requester_id = :id1 AND addressee_id = :id2)
                     OR (requester_id = :id2 AND addressee_id = :id1)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id1' => $id1, ':id2' => $id2]);
        return $stmt->fetchColumn();
    }
}
