<?php
namespace App\Models;

use PDO;

class User {
    private PDO $conn;
    private string $table_name = "users";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function delete($id, $byAdmin = false) {
        try {
            $this->conn->beginTransaction();

            $stmtInfo = $this->conn->prepare(
                "SELECT u.username, u.email, r.name as role_name
                 FROM users u
                 LEFT JOIN roles r ON u.role_id = r.id
                 WHERE u.id = ?"
            );
            $stmtInfo->execute([$id]);
            $userData = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            $originalName = $userData['username'];
            $userRole = $userData['role_name'] ?? null;

            if (!$byAdmin) {
                $this->logActivity(
                    $id,
                    'ACCOUNT_DELETED',
                    $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                    "Uživatel smazal svůj účet (Původní: $originalName)"
                );
            }

            $stmtRooms = $this->conn->prepare("SELECT id FROM rooms WHERE owner_id = ? AND type = 'group'");
            $stmtRooms->execute([$id]);
            $ownedRooms = $stmtRooms->fetchAll(PDO::FETCH_COLUMN);

            foreach ($ownedRooms as $roomId) {
                $stmtNext = $this->conn->prepare(
                    "SELECT user_id FROM room_memberships WHERE room_id = ? AND user_id != ? ORDER BY joined_at ASC LIMIT 1"
                );
                $stmtNext->execute([$roomId, $id]);
                $heirId = $stmtNext->fetchColumn();

                if ($heirId) {
                    $this->conn->prepare("UPDATE rooms SET owner_id = ? WHERE id = ?")->execute([$heirId, $roomId]);
                    $this->conn->prepare("UPDATE room_memberships SET role = 'admin' WHERE room_id = ? AND user_id = ?")
                        ->execute([$roomId, $heirId]);
                } else {
                    $this->conn->prepare("UPDATE rooms SET owner_id = NULL WHERE id = ?")->execute([$roomId]);
                }
            }

            $this->conn->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$id]);
            $this->conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$id]);
            $this->conn->prepare("DELETE FROM friendships WHERE requester_id = ? OR addressee_id = ?")->execute([$id, $id]);
            $this->conn->prepare("DELETE FROM room_memberships WHERE user_id = ?")->execute([$id]);

            $this->conn->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);


            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }

    public function findById($id) {
        $query = "SELECT id, username, email, role_id, bio, avatar_url, status FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {
        $query = "SELECT u.*, r.name as role_name FROM {$this->table_name} u JOIN roles r ON u.role_id = r.id WHERE u.email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUsername($username) {
        $query = "SELECT id FROM {$this->table_name} WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($username, $email, $password, $avatarUrl) {
        $roleId = $this->getRoleIdByName('user');
        if ($roleId === null) {
            return false;
        }

        $query = "INSERT INTO {$this->table_name} (username, email, password_hash, role_id, avatar_url, created_at)
                  VALUES (:username, :email, :password, :role_id, :avatar_url, NOW()) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT));
        $stmt->bindValue(':role_id', $roleId);
        $stmt->bindValue(':avatar_url', $avatarUrl);
        if ($stmt->execute()) {
            return $stmt->fetchColumn();
        }
        return false;
    }

    public function createAdmin(string $username, string $email, string $passwordHash, ?string $avatarUrl = null): bool
    {
        $roleId = $this->getRoleIdByName('admin');
        if ($roleId === null) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table_name} (username, email, password_hash, role_id, avatar_url, created_at, status)
             VALUES (?, ?, ?, ?, ?, NOW(), 'offline')"
        );

        return $stmt->execute([$username, $email, $passwordHash, $roleId, $avatarUrl]);
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE {$this->table_name} SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function logActivity($userId, $action, $ip, $details = null) {
        $stmt = $this->conn->prepare(
            "INSERT INTO activity_logs (user_id, action, ip_address, details, timestamp)
             VALUES (?, ?, ?, ?, NOW())"
        );
        return $stmt->execute([$userId, $action, $ip, $details]);
    }

    public function findAll() {
        $query = "SELECT u.id, u.username, u.email, r.name as role, u.status, u.created_at FROM {$this->table_name} u JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAdmins() {
        $query = "SELECT COUNT(*) FROM {$this->table_name} u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function update($id, $data) {
        $query = "UPDATE {$this->table_name} SET username = :username, email = :email, bio = :bio, avatar_url = :avatar_url WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':username' => $data->username,
            ':email' => $data->email,
            ':bio' => $data->bio ?? null,
            ':avatar_url' => $data->avatar_url ?? null,
            ':id' => $id,
        ]);
        return true;
    }

    public function getRoleNameById(int|string|null $roleId): ?string
    {
        if ($roleId === null || $roleId === '') {
            return null;
        }

        $stmt = $this->conn->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
        $stmt->execute([$roleId]);
        $roleName = $stmt->fetchColumn();
        return $roleName === false ? null : (string) $roleName;
    }

    public function getRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->conn->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $roleId = $stmt->fetchColumn();
        return $roleId === false ? null : (int) $roleId;
    }

    public function getActivityLogsByUserId(string $userId, int $limit = 50): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT ?');
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRoleNameByUserId(string $userId): ?string
    {
        $stmt = $this->conn->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $roleName = $stmt->fetchColumn();
        return $roleName === false ? null : (string) $roleName;
    }
}
?>
