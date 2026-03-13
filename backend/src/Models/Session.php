<?php
namespace App\Models;

use PDO;

class Session
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function create(string $userId, string $token, string $expiresAt): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO sessions (user_id, token, expires_at, is_active) VALUES (?, ?, ?, TRUE)"
        );

        return $stmt->execute([$userId, $token, $expiresAt]);
    }

    public function deactivateByToken(string $token): bool
    {
        $stmt = $this->conn->prepare("UPDATE sessions SET is_active = FALSE WHERE token = ?");
        return $stmt->execute([$token]);
    }

    public function findUserIdByToken(string $token): string|false
    {
        $stmt = $this->conn->prepare("SELECT user_id FROM sessions WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        return $stmt->fetchColumn();
    }
}
