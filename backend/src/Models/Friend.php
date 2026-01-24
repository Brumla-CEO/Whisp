<?php
namespace App\Models;

use PDO;

class Friend {
    private $conn;
    private $table = "friendships";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. Odeslat žádost o přátelství
    public function sendRequest($fromId, $toId) {
        // Kontrola, zda už vztah neexistuje (v jakémkoliv směru)
        if ($this->exists($fromId, $toId)) {
            return false;
        }

        $query = "INSERT INTO {$this->table} (requester_id, addressee_id, status)
                  VALUES (:from, :to, 'pending')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':from', $fromId);
        $stmt->bindValue(':to', $toId);

        return $stmt->execute();
    }

    // 2. Přijmout žádost
    public function acceptRequest($requestId) {
        $query = "UPDATE {$this->table} SET status = 'accepted' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $requestId);
        return $stmt->execute();
    }

    // 3. Odmítnout/Zrušit přátelství
    public function remove($requestId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $requestId);
        return $stmt->execute();
    }

    // 4. Získat seznam přátel (pro Sidebar)
    // Tohle je složitější SQL - spojuje users a hledá lidi, kde jsi figuroval TY
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

    // 5. Získat příchozí čekající žádosti (pro Modální okno)
    public function getPendingRequests($userId) {
        $query = "
            SELECT f.id as request_id, u.username, u.avatar_url, f.created_at
            FROM {$this->table} f
            JOIN users u ON f.requester_id = u.id
            WHERE f.addressee_id = :uid AND f.status = 'pending'
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':uid', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Pomocná funkce: Existuje už vztah?
    public function exists($id1, $id2) {
        $query = "SELECT id FROM {$this->table}
                  WHERE (requester_id = :id1 AND addressee_id = :id2)
                     OR (requester_id = :id2 AND addressee_id = :id1)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id1' => $id1, ':id2' => $id2]);
        return $stmt->fetchColumn();
    }
}