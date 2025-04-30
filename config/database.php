<?php
// config/database.php - Veritabanı bağlantı ayarları

class Database
{
    private static $instance = null;
    private $connection;

    // Veritabanı bilgileri
    private $host = 'localhost';
    private $dbname = 'ege_robotik';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';

    private function __construct()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    // Bağlantıyı kopyalamayı engelle
    private function __clone() {}

    // Bağlantıyı unserialize etmeyi engelle
    public function __wakeup() {}
}

// Kısa veritabanı erişim fonksiyonu
function db()
{
    return Database::getInstance()->getConnection();
}
