<?php
// api/faecher.php - API für Fächerverwaltung mit SQLite
session_start();

// Headers
header('Content-Type: application/json; charset=utf-8');

// Prüfe Session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// Pfade
$userName = explode('@', $_SESSION['email'])[0];
$dbPath = dirname(__DIR__) . '/Lehrerdaten/' . $userName . '/notendaten.db';

// Öffne Datenbank
try {
    $db = new SQLite3($dbPath);
    $db->exec('PRAGMA foreign_keys = ON');
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}

// Action
$action = $_GET['action'] ?? '';

switch($action) {
    case 'list':
        $faecher = [];
        $result = $db->query("SELECT * FROM faecher ORDER BY name");
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $kategorien = [];
            $stmt = $db->prepare("SELECT * FROM kategorien WHERE fach_id = ? ORDER BY name");
            $stmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
            $katResult = $stmt->execute();
            
            while ($kat = $katResult->fetchArray(SQLITE3_ASSOC)) {
                $kategorien[] = [
                    'id' => (int)$kat['id'],
                    'name' => $kat['name'],
                    'gewichtung' => (int)$kat['gewichtung']
                ];
            }
            
            $faecher[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'kategorien' => $kategorien
            ];
        }
        
        echo json_encode($faecher);
        break;
        
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM faecher WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $fach = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($fach) {
            $kategorien = [];
            $stmt = $db->prepare("SELECT * FROM kategorien WHERE fach_id = ? ORDER BY name");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $katResult = $stmt->execute();
            
            while ($kat = $katResult->fetchArray(SQLITE3_ASSOC)) {
                $kategorien[] = [
                    'id' => (int)$kat['id'],
                    'name' => $kat['name'],
                    'gewichtung' => (int)$kat['gewichtung']
                ];
            }
            
            echo json_encode([
                'id' => (int)$fach['id'],
                'name' => $fach['name'],
                'kategorien' => $kategorien
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fach nicht gefunden']);
        }
        break;
        
    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        
        $db->exec('BEGIN');
        
        $stmt = $db->prepare("INSERT INTO faecher (name) VALUES (?)");
        $stmt->bindValue(1, $data['name'], SQLITE3_TEXT);
        $stmt->execute();
        
        $fachId = $db->lastInsertRowID();
        
        $stmt = $db->prepare("INSERT INTO kategorien (fach_id, name, gewichtung) VALUES (?, ?, ?)");
        foreach ($data['kategorien'] as $kat) {
            $stmt->bindValue(1, $fachId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $kat['name'], SQLITE3_TEXT);
            $stmt->bindValue(3, $kat['gewichtung'] ?? 1, SQLITE3_INTEGER);
            $stmt->execute();
        }
        
        $db->exec('COMMIT');
        
        echo json_encode(['success' => true, 'id' => $fachId]);
        break;
        
    case 'update':
        $id = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true);
        
        $db->exec('BEGIN');
        
        $stmt = $db->prepare("UPDATE faecher SET name = ? WHERE id = ?");
        $stmt->bindValue(1, $data['name'], SQLITE3_TEXT);
        $stmt->bindValue(2, $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $db->exec("DELETE FROM kategorien WHERE fach_id = $id");
        
        $stmt = $db->prepare("INSERT INTO kategorien (fach_id, name, gewichtung) VALUES (?, ?, ?)");
        foreach ($data['kategorien'] as $kat) {
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $kat['name'], SQLITE3_TEXT);
            $stmt->bindValue(3, $kat['gewichtung'] ?? 1, SQLITE3_INTEGER);
            $stmt->execute();
        }
        
        $db->exec('COMMIT');
        
        echo json_encode(['success' => true]);
        break;
        
    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        
        $db->exec('BEGIN');
        $db->exec("DELETE FROM kategorien WHERE fach_id = $id");
        $db->exec("DELETE FROM klassen_faecher WHERE fach_id = $id");
        $db->exec("DELETE FROM faecher WHERE id = $id");
        $db->exec('COMMIT');
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
}

$db->close();
?>