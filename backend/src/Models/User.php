<?php
namespace App\Models;

use PDO;

class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

        public function delete($id, $byAdmin = false) {
                try {
                    $this->conn->beginTransaction();

                    // 1. Získat info o uživateli (role a jméno)
                    $stmtInfo = $this->conn->prepare("
                        SELECT u.username, u.email, r.name as role_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        WHERE u.id = ?
                    ");
                    $stmtInfo->execute([$id]);
                    $userData = $stmtInfo->fetch();

                    if (!$userData) return false;

                    $originalName = $userData['username'];
                    $userRole = $userData['role_name'];


                    if (!$byAdmin && $userRole !== 'admin') {
                        $this->logActivity(
                            $id,
                            'ACCOUNT_DELETED',
                            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                            "Uživatel smazal svůj účet (Původní: $originalName)"
                        );
                    }

                    $this->conn->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$id]);
                    $this->conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$id]);
                    $this->conn->prepare("DELETE FROM friendships WHERE requester_id = ? OR addressee_id = ?")->execute([$id, $id]);
                    $this->conn->prepare("DELETE FROM room_memberships WHERE user_id = ?")->execute([$id]);

                    $stmtRooms = $this->conn->prepare("SELECT id FROM rooms WHERE owner_id = ? AND type = 'group'");
                    $stmtRooms->execute([$id]);
                    $ownedRooms = $stmtRooms->fetchAll(\PDO::FETCH_COLUMN);

                    foreach ($ownedRooms as $roomId) {
                        $stmtNext = $this->conn->prepare("SELECT user_id FROM room_memberships WHERE room_id = ? AND user_id != ? ORDER BY joined_at ASC LIMIT 1");
                        $stmtNext->execute([$roomId, $id]);
                        $heirId = $stmtNext->fetchColumn();

                        if ($heirId) {
                            $this->conn->prepare("UPDATE rooms SET owner_id = ? WHERE id = ?")->execute([$heirId, $roomId]);
                            $this->conn->prepare("UPDATE room_memberships SET role = 'admin' WHERE room_id = ? AND user_id = ?")->execute([$roomId, $heirId]);
                        } else {
                            $this->conn->prepare("UPDATE rooms SET owner_id = NULL WHERE id = ?")->execute([$roomId]);
                        }
                    }

                    if ($userRole === 'admin') {

                        $this->conn->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$id]);

                        $stmtDelete = $this->conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmtDelete->execute([$id]);

                    } else {

                        $randomHash = substr(md5(uniqid(rand(), true)), 0, 5);
                        $safeName = substr($originalName, 0, 30);
                        $newSystemName = "deleted_" . $safeName . "_" . $randomHash;
                        $archiveBio = "Uživatel smazán. Původní jméno: " . $originalName;

                        $query = "UPDATE {$this->table_name} SET
                                    username = :new_name,
                                    email = :new_email,
                                    password_hash = :junk_pass,
                                    avatar_url = :ghost_avatar,
                                    bio = :archive_bio,
                                    status = 'offline'
                                  WHERE id = :id";

                        $stmt = $this->conn->prepare($query);
                        $stmt->execute([
                            ':new_name' => $newSystemName,
                            ':new_email' => "del_{$randomHash}@whisp.local",
                            ':junk_pass' => "DELETED_" . $randomHash,
                            ':ghost_avatar' => "https://api.dicebear.com/7.x/initials/svg?seed=?",
                            ':archive_bio' => $archiveBio,
                            ':id' => $id
                        ]);
                    }

                    $this->conn->commit();
                    return true;

                } catch (\Exception $e) {
                    $this->conn->rollBack();
                    error_log("Delete user error: " . $e->getMessage());
                    return false;
                }
            }

    public function findById($id) {
        $query = "SELECT id, username, email, role_id, bio, avatar_url, status FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {
        $query = "SELECT u.*, r.name as role_name FROM {$this->table_name} u JOIN roles r ON u.role_id = r.id WHERE u.email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUsername($username) {
        $query = "SELECT id FROM {$this->table_name} WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":username", $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($username, $email, $password, $avatarUrl) {
         $roleQuery = "SELECT id FROM roles WHERE name = 'user' LIMIT 1";
         $roleStmt = $this->conn->query($roleQuery);
         $role = $roleStmt->fetch();

         $query = "INSERT INTO {$this->table_name} (username, email, password_hash, role_id, avatar_url, created_at)
                   VALUES (:username, :email, :password, :role_id, :avatar_url, NOW()) RETURNING id";
         $stmt = $this->conn->prepare($query);
         $stmt->bindValue(":username", $username);
         $stmt->bindValue(":email", $email);
         $stmt->bindValue(":password", password_hash($password, PASSWORD_DEFAULT));
         $stmt->bindValue(":role_id", $role['id']);
         $stmt->bindValue(":avatar_url", $avatarUrl);
         if ($stmt->execute()) return $stmt->fetchColumn();
         return false;
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE {$this->table_name} SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function logActivity($userId, $action, $ip, $details = null) {
            $stmt = $this->conn->prepare("
                INSERT INTO activity_logs (user_id, action, ip_address, details, timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
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
        return (int)$stmt->fetchColumn();
    }

    public function update($id, $data) {
        $query = "UPDATE {$this->table_name} SET username = :username, email = :email, bio = :bio, avatar_url = :avatar_url WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ":username" => $data->username,
            ":email" => $data->email,
            ":bio" => $data->bio ?? null,
            ":avatar_url" => $data->avatar_url ?? null,
            ":id" => $id
        ]);
        return true;
    }
}
?>