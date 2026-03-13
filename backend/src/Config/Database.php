<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $host = getenv('DB_HOST') ?: 'db';
            $db_name = getenv('DB_NAME') ?: 'whisp_db';
            $username = getenv('DB_USER') ?: 'whisp_user';
            $password = getenv('DB_PASS') ?: 'whisp_password';

            $dsn = "pgsql:host=" . $host . ";port=5432;dbname=" . $db_name;

            $this->conn = new PDO($dsn, $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            echo "Chyba připojení k DB. Zkontrolujte logy.";
            exit();
        }

        return $this->conn;
    }
}
?>