<?php
namespace App\Models;

use PDO;

class Friend {
    private $conn;
    private $table = "friendships";

    public function __construct($db) {
        $this->conn = $db;
    }

        public function sendRequest($fromId, $toId) {
            // Kontrola, zda už vztah neexistuje
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
        $query = "UPDATE {$this->table} SET status = 'accepted' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $requestId);
        return $stmt->execute();
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
                       $query = "DELETE FROM {$this->table}
                      WHERE (requester_id = :uid AND addressee_id = :fid)
                         OR (requester_id = :fid AND addressee_id = :uid)";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([':uid' => $userId, ':fid' => $friendId]);
        }

    public function getPendingRequests($userId) {
        $query = "
            SELECT f.id as request_id, u.username, u.avatar_url, f.created_at, u.id as requester_id
            FROM {$this->table} f
            JOIN users u ON f.requester_id = u.id
            WHERE f.addressee_id = :uid AND f.status = 'pending'
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':uid', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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