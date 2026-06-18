<?php
// backend/db.php

class Database {
    private $host = "localhost";
    private $db_name = "milele_escrow";
    private $username = "root"; // Change if using a live server
    private $password = "";     // Change if using a live server
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            // Establish PDO connection
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            
            // Set error mode to throw exceptions so we can catch them cleanly
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Ensure data is sent using modern unicode
            $this->conn->exec("set names utf8mb4");
            
        } catch(PDOException $exception) {
            // In a production environment, NEVER echo the exact error to the screen. 
            // We log it securely and show a generic message.
            error_log("Connection error: " . $exception->getMessage());
            die(json_encode(["status" => "error", "message" => "Database node offline."]));
        }

        return $this->conn;
    }
}
?>