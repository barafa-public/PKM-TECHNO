<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die(json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $this->conn->connect_error]));
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConn() {
        return $this->conn;
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($val) {
        return $this->conn->real_escape_string($val);
    }

    public function lastId() {
        return $this->conn->insert_id;
    }
}
