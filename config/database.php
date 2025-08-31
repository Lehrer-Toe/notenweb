<?php
/**
 * NotenWeb 2025/2026 - MySQL Datenbank-Konfiguration
 * Zentrale Verbindung für alle Module
 */

class Database {
    private static $instance = null;
    private $connection;
    
    // All-Inkl Datenbankzugangsdaten
    private $host = 'localhost';
    private $database = 'd044a68c';
    private $username = 'd044a68c';
    private $password = 'S@rdox17';
    private $charset = 'utf8mb4';
    
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            error_log("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
            die("Datenbankfehler. Bitte kontaktieren Sie den Administrator.");
        }
    }
    
    // Singleton Pattern für einmalige Verbindung
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Hilfsfunktion: Aktuelles Schuljahr für Lehrer abrufen
    public function getCurrentSchuljahr($lehrerId) {
        $stmt = $this->connection->prepare("
            SELECT schuljahr 
            FROM lehrer_einstellungen 
            WHERE lehrer_id = ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$lehrerId]);
        $result = $stmt->fetch();
        
        // Standard-Schuljahr wenn nichts gesetzt
        return $result ? $result['schuljahr'] : date('Y') . '/' . (date('Y') + 1);
    }
    
    // Hilfsfunktion: Schuljahr setzen/ändern
    public function setSchuljahr($lehrerId, $schuljahr) {
        // Prüfe ob bereits Einstellung existiert
        $stmt = $this->connection->prepare("
            SELECT id FROM lehrer_einstellungen WHERE lehrer_id = ?
        ");
        $stmt->execute([$lehrerId]);
        
        if ($stmt->fetch()) {
            // Update
            $stmt = $this->connection->prepare("
                UPDATE lehrer_einstellungen 
                SET schuljahr = ?, updated_at = NOW() 
                WHERE lehrer_id = ?
            ");
            $stmt->execute([$schuljahr, $lehrerId]);
        } else {
            // Insert
            $stmt = $this->connection->prepare("
                INSERT INTO lehrer_einstellungen (lehrer_id, schuljahr, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$lehrerId, $schuljahr]);
        }
        
        return true;
    }
    
    // Archivierungs-Funktionen
    public function archiveKlasse($klasseId, $lehrerId) {
        $db = $this->connection;
        
        try {
            $db->beginTransaction();
            
            // Setze Klasse auf archiviert
            $stmt = $db->prepare("
                UPDATE klassen 
                SET ist_archiviert = 1, 
                    archiviert_am = NOW(),
                    archiviert_von = ?
                WHERE id = ? 
                AND lehrer_id = ?
            ");
            $stmt->execute([$lehrerId, $klasseId, $lehrerId]);
            
            // Archiviere auch alle zugehörigen Noten
            $stmt = $db->prepare("
                UPDATE noten n
                JOIN schueler s ON n.schueler_id = s.id
                SET n.ist_archiviert = 1
                WHERE s.klasse_id = ?
            ");
            $stmt->execute([$klasseId]);
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Archivierungsfehler: " . $e->getMessage());
            return false;
        }
    }
    
    // Klasse aus Archiv wiederherstellen
    public function restoreKlasse($klasseId, $lehrerId) {
        $db = $this->connection;
        
        try {
            $db->beginTransaction();
            
            // Reaktiviere Klasse
            $stmt = $db->prepare("
                UPDATE klassen 
                SET ist_archiviert = 0, 
                    archiviert_am = NULL,
                    archiviert_von = NULL
                WHERE id = ? 
                AND lehrer_id = ?
            ");
            $stmt->execute([$klasseId, $lehrerId]);
            
            // Reaktiviere auch alle zugehörigen Noten
            $stmt = $db->prepare("
                UPDATE noten n
                JOIN schueler s ON n.schueler_id = s.id
                SET n.ist_archiviert = 0
                WHERE s.klasse_id = ?
            ");
            $stmt->execute([$klasseId]);
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Wiederherstellungsfehler: " . $e->getMessage());
            return false;
        }
    }
    
    // Prüfe ob Benutzer Admin ist
    public function isAdmin($userId) {
        $stmt = $this->connection->prepare("
            SELECT role FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result && $result['role'] === 'admin';
    }
    
    // Helfer für sichere Transaktionen
    public function transaction($callback) {
        $this->connection->beginTransaction();
        try {
            $result = $callback($this->connection);
            $this->connection->commit();
            return $result;
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
    
    // Prepared Statement Wrapper für einfachere Nutzung
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    // Fetch one row
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    // Fetch all rows
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    // Insert und gebe ID zurück
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_map(function($field) { return ':' . $field; }, $fields);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $values)
        );
        
        $stmt = $this->connection->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        
        return $this->connection->lastInsertId();
    }
    
    // Update
    public function update($table, $data, $where, $whereParams = []) {
        $setPairs = array_map(function($field) { 
            return $field . ' = :' . $field; 
        }, array_keys($data));
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setPairs),
            $where
        );
        
        $stmt = $this->connection->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        foreach ($whereParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }
    
    // Delete
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }
}

// Globale Hilfsfunktion für schnellen Zugriff
function db() {
    return Database::getInstance()->getConnection();
}

// Session Helper für Lehrer-ID und Schuljahr
function getCurrentLehrerId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentSchuljahr() {
    $lehrerId = getCurrentLehrerId();
    if (!$lehrerId) return null;
    
    return Database::getInstance()->getCurrentSchuljahr($lehrerId);
}
?>