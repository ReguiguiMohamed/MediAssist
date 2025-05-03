<?php
class Database {
    private $host = "localhost";
    private $db_name = "mediassist";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new PDOException("Connection failed: " . $e->getMessage());
        }
    }
}