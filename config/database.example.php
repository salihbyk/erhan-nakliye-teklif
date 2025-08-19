<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'your_database_name';
    private $username = 'your_username';
    private $password = 'your_password';
    private $charset = 'utf8mb4';
    public $pdo;

    public function getConnection() {
        $this->pdo = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Domain ortamında foreign key sorunlarını önlemek için kontrolü kapat
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        } catch(PDOException $exception) {
            error_log("Bağlantı hatası: " . $exception->getMessage());
        }

        return $this->pdo;
    }
}
?>
