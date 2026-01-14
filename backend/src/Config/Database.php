<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    private $host = "db";
    private $db_name = "whisp_db";
    private $username = "whisp_user";
    private $password = "whisp_password";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {

            $dsn = "pgsql:host=" . $this->host . ";port=5432;dbname=" . $this->db_name;

            $this->conn = new PDO($dsn, $this->username, $this->password);

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch(PDOException $exception) {
            echo "Chyba připojení k DB: " . $exception->getMessage();
            exit();
        }

        return $this->conn;
    }
}
?>