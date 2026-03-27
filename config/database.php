<?php
// config/database.php
// Connexion à la base LG PHARMA

class Database {
    private $host = "127.0.0.1";
    private $port = "3306";
    private $db_name = "lgpharma";
    private $username = "root";
    private $password = "";
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            // DSN pour MariaDB sur port 3306
            $dsn = "mysql:host=" . $this->host . ":" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Définir le fuseau horaire de Kinshasa
            $this->conn->exec("SET time_zone = '+01:00'");
            
        } catch(PDOException $e) {
            die("
            <div style='padding:20px; background:#ffebee; color:#c62828; border-radius:10px; margin:20px;'>
                <h3>❌ Erreur de connexion à la base LG PHARMA</h3>
                <p><strong>Message :</strong> " . $e->getMessage() . "</p>
                <p><strong>Vérifie :</strong></p>
                <ul>
                    <li>WAMP/XAMPP est démarré</li>
                    <li>MySQL tourne sur le port 3306</li>
                    <li>La base 'lgpharma' existe dans phpMyAdmin</li>
                </ul>
                <p><a href='http://localhost/phpmyadmin' target='_blank'>🔗 Ouvrir phpMyAdmin</a></p>
            </div>
            ");
        }

        return $this->conn;
    }
}

// Fonction utilitaire pour obtenir la connexion
function getDB() {
    static $db = null;
    if ($db === null) {
        $database = new Database();
        $db = $database->connect();
    }
    return $db;
}

// Test de connexion (à commenter après)
/*
try {
    $db = getDB();
    echo "<div style='padding:15px; background:#e6f4ea; color:#34a853; border-radius:5px; margin:10px;'>
            ✅ Connecté à LG PHARMA (Kinshasa) !
          </div>";
} catch(Exception $e) {
    echo "<div style='padding:15px; background:#fce8e6; color:#ea4335; border-radius:5px; margin:10px;'>
            ❌ Erreur : " . $e->getMessage() . "
          </div>";
}
*/
?>