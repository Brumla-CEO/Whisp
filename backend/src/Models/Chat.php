<?php
namespace App\Models;

use PDO;

class Chat {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Najde nebo vytvoří DM místnost pro dva uživatele
    public function getOrCreateDmRoom($user1, $user2) {
        // 1. Zkusíme najít existující DM místnost v tvé struktuře
        // Hledáme room_id v tabulce room_memberships
        $query = "
            SELECT r.id
            FROM rooms r
            JOIN room_memberships rm1 ON r.id = rm1.room_id
            JOIN room_memberships rm2 ON r.id = rm2.room_id
            WHERE r.type = 'dm'
            AND rm1.user_id = :u1
            AND rm2.user_id = :u2
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':u1' => $user1, ':u2' => $user2]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($room) {
            return $room['id']; // Místnost existuje
        }

        // 2. Pokud neexistuje, vytvoříme ji
        try {
            $this->conn->beginTransaction();

            // A) Vytvořit Room (dle tvé tabulky rooms)
            // owner_id nastavíme na iniciátora ($user1), type na 'dm'
            $stmt = $this->conn->prepare("INSERT INTO rooms (name, type, owner_id) VALUES (NULL, 'dm', ?) RETURNING id");
            $stmt->execute([$user1]);
            $roomId = $stmt->fetchColumn();

            // B) Přidat členy do room_memberships
            $stmtMembers = $this->conn->prepare("INSERT INTO room_memberships (room_id, user_id) VALUES (?, ?)");
            $stmtMembers->execute([$roomId, $user1]);
            $stmtMembers->execute([$roomId, $user2]);

            $this->conn->commit();
            return $roomId;

        } catch (\Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Odeslat zprávu do tvé tabulky 'messages'
    public function sendMessage($roomId, $senderId, $content, $replyToId = null) {
            if (!$this->isMember($roomId, $senderId)) {
                return false;
            }

            // Vkládáme zprávu (reply_to_id může být null)
            $query = "INSERT INTO messages (room_id, sender_id, content, type, reply_to_id)
                      VALUES (:room, :sender, :content, 'text', :reply_to)";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':room' => $roomId,
                ':sender' => $senderId,
                ':content' => htmlspecialchars(strip_tags($content)), // Základní sanitizace
                ':reply_to' => $replyToId
            ]);
        }

public function deleteMessage($messageId, $userId) {
        // Kontrola: Mazat může jen autor (sender_id = userId)
        $query = "UPDATE messages
                  SET is_deleted = true
                  WHERE id = :id AND sender_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $messageId, ':user_id' => $userId]);

        // Vrací true, pokud se něco změnilo (pokud zpráva existovala a patřila userovi)
        return $stmt->rowCount() > 0;
    }

    // NOVÉ: Editace zprávy
    public function editMessage($messageId, $userId, $newContent) {
        // Kontrola: Upravovat může jen autor
        // Nastavíme is_edited = true, updated_at (pokud máš sloupec edited_at, použij ten)
        $query = "UPDATE messages
                  SET content = :content,
                      is_edited = true,
                      edited_at = NOW()
                  WHERE id = :id AND sender_id = :user_id AND is_deleted = false";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':content' => htmlspecialchars(strip_tags($newContent)),
            ':id' => $messageId,
            ':user_id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    // UPRAVENO: Načítáme i sloupce pro editaci a reply
    public function getRoomMessages($roomId, $userId) {
        if (!$this->isMember($roomId, $userId)) return [];

        $query = "SELECT m.id, m.room_id, m.sender_id, m.content, m.created_at,
                         m.is_deleted, m.is_edited, m.edited_at, m.reply_to_id,
                         u.username as sender_name, u.avatar_url as sender_avatar
                  FROM messages m
                  JOIN users u ON m.sender_id = u.id
                  WHERE m.room_id = :room
                  ORDER BY m.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':room' => $roomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isMember($roomId, $userId) {
        $stmt = $this->conn->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $userId]);
        return $stmt->fetchColumn();
    }
}