<?php
namespace App\Models;

use PDO;

class User {
    private $conn;
    private $table_name = "users";

    // ... (atributy a konstruktor nechte stejné) ...
    public function __construct($db) {
        $this->conn = $db;
    }

    // OPRAVENO: Musí vracet bio a avatar_url
    public function findById($id) {
        $query = "SELECT id, username, email, role_id, bio, avatar_url, status
                  FROM {$this->table_name}
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ... (metody create, findAll nechte stejné) ...

    public function update($id, $data) {
        $query = "UPDATE {$this->table_name}
                  SET username = :username,
                      email = :email,
                      bio = :bio,
                      avatar_url = :avatar_url
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(":username", $data->username);
        $stmt->bindValue(":email", $data->email);
        $stmt->bindValue(":bio", $data->bio ?? null);
        $stmt->bindValue(":avatar_url", $data->avatar_url ?? null);
        $stmt->bindValue(":id", $id);

        return $stmt->execute();
    }

    // ... (zbytek souboru delete, findByUsername atd. nechte stejné) ...

    // UJISTĚTE SE, že zde máte i ostatní metody (delete, findByUsername, logActivity...)
    // Pokud je nemáte po ruce, zkopírujte je z původního souboru.
    public function findByUsername($username) {
        $query = "SELECT id FROM {$this->table_name} WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":username", $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

     public function delete($id) {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":id", $id);
        return $stmt->execute();
    }

    public function findByEmail($email) {
        $query = "SELECT u.*, r.name as role_name
                  FROM {$this->table_name} u
                  JOIN roles r ON u.role_id = r.id
                  WHERE u.email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($username, $email, $password) {
        // Získání ID role 'user'
        $roleQuery = "SELECT id FROM roles WHERE name = 'user' LIMIT 1";
        $roleStmt = $this->conn->query($roleQuery);
        $role = $roleStmt->fetch();

        $query = "INSERT INTO {$this->table_name} (username, email, password_hash, role_id)
                  VALUES (:username, :email, :password, :role_id) RETURNING id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":username", $username);
        $stmt->bindValue(":email", $email);
        $stmt->bindValue(":password", password_hash($password, PASSWORD_DEFAULT));
        $stmt->bindValue(":role_id", $role['id']);

        if ($stmt->execute()) {
            return $stmt->fetchColumn();
        }
        return false;
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE {$this->table_name} SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }

    public function logActivity($userId, $action, $ipAddress) {
        $query = "INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId, $action, $ipAddress]);
    }

    public function findAll() {
        $query = "SELECT u.id, u.username, u.email, r.name as role, u.status, u.created_at
                  FROM {$this->table_name} u
                  JOIN roles r ON u.role_id = r.id
                  ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAdmins() {
        $query = "SELECT COUNT(*) FROM {$this->table_name} u
                  JOIN roles r ON u.role_id = r.id
                  WHERE r.name = 'admin'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

public function getAllLogs() {
    $query = "SELECT l.*, u.username
              FROM activity_logs l
              LEFT JOIN users u ON l.user_id = u.id
              ORDER BY l.timestamp DESC LIMIT 100";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}