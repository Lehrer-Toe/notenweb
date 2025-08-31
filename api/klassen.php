<?php
// api/klassen.php - API für Klassenverwaltung mit SQLite
session_start();

// Prüfe ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

// CORS-Header für AJAX
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Hole Benutzerdaten
$userEmail = $_SESSION['email'];
$userName = explode('@', $userEmail)[0];
$userDir = dirname(__DIR__) . '/Lehrerdaten/' . $userName;
$dbPath = $userDir . '/notendaten.db';

// Stelle sicher, dass Datenbank existiert
if (!file_exists($dbPath)) {
    // Erstelle Verzeichnis falls nicht vorhanden
    if (!file_exists($userDir)) {
        mkdir($userDir, 0777, true);
    }
}

try {
    // Öffne SQLite-Datenbank
    $db = new SQLite3($dbPath);
    $db->exec('PRAGMA foreign_keys = ON');
    
    // Erstelle Tabellen falls nicht vorhanden
    createTables($db);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler: ' . $e->getMessage()]);
    exit;
}

// Hole Action-Parameter
$action = $_GET['action'] ?? '';

// Route zur entsprechenden Funktion
switch ($action) {
    case 'list':
        listKlassen($db);
        break;
        
    case 'get':
        getKlasse($db, $_GET['id'] ?? 0);
        break;
        
    case 'details':
        getKlasseDetails($db, $_GET['id'] ?? 0);
        break;
        
    case 'create':
        createKlasse($db);
        break;
        
    case 'update':
        updateKlasse($db, $_GET['id'] ?? 0);
        break;
        
    case 'delete':
        deleteKlasse($db, $_GET['id'] ?? 0);
        break;
        
    case 'save-sorting':
        saveSortingSettings($db);
        break;
        
    case 'get-sorting':
        getSortingSettings($db);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Aktion']);
}

$db->close();

// === FUNKTIONEN ===

function createTables($db) {
    try {
        // Klassen-Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS klassen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            schuljahr TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Schüler-Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS schueler (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nachname TEXT NOT NULL,
            vorname TEXT NOT NULL,
            geschlecht TEXT,
            klasse_id INTEGER,
            FOREIGN KEY (klasse_id) REFERENCES klassen(id) ON DELETE CASCADE
        )");
        
        // Klassen-Fächer Verknüpfung
        $db->exec("CREATE TABLE IF NOT EXISTS klassen_faecher (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            klasse_id INTEGER,
            fach_id INTEGER,
            FOREIGN KEY (klasse_id) REFERENCES klassen(id) ON DELETE CASCADE,
            FOREIGN KEY (fach_id) REFERENCES faecher(id) ON DELETE CASCADE
        )");
        
        // Einstellungen-Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS einstellungen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schluessel TEXT UNIQUE,
            wert TEXT
        )");
        
        // Erstelle auch die Fächer-Tabellen (falls noch nicht von faecher.php erstellt)
        $db->exec("CREATE TABLE IF NOT EXISTS faecher (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS kategorien (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fach_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            gewichtung INTEGER DEFAULT 1,
            FOREIGN KEY (fach_id) REFERENCES faecher(id) ON DELETE CASCADE
        )");
        
    } catch (Exception $e) {
        // Ignoriere Fehler bei Tabellenerstellung (falls bereits vorhanden)
    }
}

function listKlassen($db) {
    try {
        $query = "
            SELECT 
                k.*,
                COUNT(DISTINCT s.id) as schueler_count,
                COUNT(DISTINCT kf.fach_id) as faecher_count
            FROM klassen k
            LEFT JOIN schueler s ON s.klasse_id = k.id
            LEFT JOIN klassen_faecher kf ON kf.klasse_id = k.id
            GROUP BY k.id
            ORDER BY k.schuljahr DESC, k.name ASC
        ";
        
        $result = $db->query($query);
        
        if (!$result) {
            throw new Exception($db->lastErrorMsg());
        }
        
        $klassen = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $klassen[] = $row;
        }
        
        echo json_encode(['klassen' => $klassen]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Abrufen der Klassen: ' . $e->getMessage()]);
    }
}

function getKlasse($db, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Klassen-ID']);
        return;
    }
    
    try {
        // Hole Klassengrundaten
        $stmt = $db->prepare("SELECT * FROM klassen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $klasse = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$klasse) {
            http_response_code(404);
            echo json_encode(['error' => 'Klasse nicht gefunden']);
            return;
        }
        
        // Hole Schüler
        $stmt = $db->prepare("
            SELECT id, nachname, vorname, geschlecht 
            FROM schueler 
            WHERE klasse_id = :id 
            ORDER BY nachname, vorname
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $schueler = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $schueler[] = $row;
        }
        $klasse['schueler'] = $schueler;
        
        // Hole Fächer-IDs
        $stmt = $db->prepare("SELECT fach_id FROM klassen_faecher WHERE klasse_id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $faecher = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $faecher[] = intval($row['fach_id']);
        }
        $klasse['faecher'] = $faecher;
        
        echo json_encode($klasse);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Abrufen der Klasse: ' . $e->getMessage()]);
    }
}

function getKlasseDetails($db, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Klassen-ID']);
        return;
    }
    
    try {
        // Hole Klassengrundaten
        $stmt = $db->prepare("SELECT * FROM klassen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $klasse = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$klasse) {
            http_response_code(404);
            echo json_encode(['error' => 'Klasse nicht gefunden']);
            return;
        }
        
        // Hole Schüler
        $stmt = $db->prepare("
            SELECT id, nachname, vorname, geschlecht 
            FROM schueler 
            WHERE klasse_id = :id 
            ORDER BY nachname, vorname
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $schueler = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $schueler[] = $row;
        }
        $klasse['schueler'] = $schueler;
        
        // Hole Fächer mit Namen
        $stmt = $db->prepare("
            SELECT f.id, f.name 
            FROM faecher f
            JOIN klassen_faecher kf ON kf.fach_id = f.id
            WHERE kf.klasse_id = :id
            ORDER BY f.name
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $faecherNamen = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $faecherNamen[] = $row['name'];
        }
        $klasse['faecher_namen'] = $faecherNamen;
        
        echo json_encode($klasse);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Abrufen der Details: ' . $e->getMessage()]);
    }
}

function createKlasse($db) {
    // Lese JSON-Daten
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Daten empfangen']);
        return;
    }
    
    $name = $input['name'] ?? '';
    $schuljahr = $input['schuljahr'] ?? '';
    $faecher = $input['faecher'] ?? [];
    $schueler = $input['schueler'] ?? [];
    
    // Validierung
    if (empty($name) || empty($schuljahr)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name und Schuljahr sind Pflichtfelder']);
        return;
    }
    
    try {
        // Starte Transaktion
        $db->exec('BEGIN TRANSACTION');
        
        // Erstelle Klasse
        $stmt = $db->prepare("INSERT INTO klassen (name, schuljahr) VALUES (:name, :schuljahr)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':schuljahr', $schuljahr, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            throw new Exception('Fehler beim Erstellen der Klasse');
        }
        
        $klasseId = $db->lastInsertRowID();
        
        // Füge Fächer hinzu (konvertiere sicherheitshalber nochmal zu Integer)
        if (!empty($faecher) && is_array($faecher)) {
            $stmt = $db->prepare("INSERT INTO klassen_faecher (klasse_id, fach_id) VALUES (:klasse_id, :fach_id)");
            
            foreach ($faecher as $fachId) {
                // Erzwinge Integer-Konvertierung (egal ob String oder Integer kommt)
                $fachIdInt = is_numeric($fachId) ? intval($fachId) : 0;
                
                if ($fachIdInt > 0) {
                    $stmt->bindValue(':klasse_id', $klasseId, SQLITE3_INTEGER);
                    $stmt->bindValue(':fach_id', $fachIdInt, SQLITE3_INTEGER);
                    
                    if (!$stmt->execute()) {
                        error_log('Fehler beim Speichern von Fach ' . $fachIdInt . ': ' . $db->lastErrorMsg());
                    }
                }
            }
        }
        
        // Füge Schüler hinzu
        if (!empty($schueler)) {
            $stmt = $db->prepare("
                INSERT INTO schueler (nachname, vorname, geschlecht, klasse_id) 
                VALUES (:nachname, :vorname, :geschlecht, :klasse_id)
            ");
            
            foreach ($schueler as $s) {
                $stmt->bindValue(':nachname', $s['nachname'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':vorname', $s['vorname'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':geschlecht', $s['geschlecht'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':klasse_id', $klasseId, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
        
        // Commit Transaktion
        $db->exec('COMMIT');
        
        echo json_encode([
            'success' => true,
            'id' => $klasseId,
            'message' => 'Klasse erfolgreich erstellt'
        ]);
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Erstellen der Klasse: ' . $e->getMessage()]);
    }
}

function updateKlasse($db, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Klassen-ID']);
        return;
    }
    
    // Lese JSON-Daten
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Daten empfangen']);
        return;
    }
    
    $name = $input['name'] ?? '';
    $schuljahr = $input['schuljahr'] ?? '';
    $faecher = $input['faecher'] ?? [];
    $schueler = $input['schueler'] ?? [];
    
    // Validierung
    if (empty($name) || empty($schuljahr)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name und Schuljahr sind Pflichtfelder']);
        return;
    }
    
    try {
        // Starte Transaktion
        $db->exec('BEGIN TRANSACTION');
        
        // Prüfe ob Klasse existiert
        $stmt = $db->prepare("SELECT id FROM klassen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if (!$result->fetchArray()) {
            throw new Exception('Klasse nicht gefunden');
        }
        
        // Update Klasse
        $stmt = $db->prepare("UPDATE klassen SET name = :name, schuljahr = :schuljahr WHERE id = :id");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':schuljahr', $schuljahr, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            throw new Exception('Fehler beim Aktualisieren der Klasse');
        }
        
        // Lösche alte Fächer-Verknüpfungen
        $stmt = $db->prepare("DELETE FROM klassen_faecher WHERE klasse_id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Füge neue Fächer hinzu
        if (!empty($faecher) && is_array($faecher)) {
            $stmt = $db->prepare("INSERT INTO klassen_faecher (klasse_id, fach_id) VALUES (:klasse_id, :fach_id)");
            
            foreach ($faecher as $fachId) {
                $fachIdInt = intval($fachId);
                if ($fachIdInt > 0) {
                    $stmt->bindValue(':klasse_id', $id, SQLITE3_INTEGER);
                    $stmt->bindValue(':fach_id', $fachIdInt, SQLITE3_INTEGER);
                    $stmt->execute();
                }
            }
        }
        
        // Lösche alte Schüler
        $stmt = $db->prepare("DELETE FROM schueler WHERE klasse_id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Füge neue Schüler hinzu
        if (!empty($schueler)) {
            $stmt = $db->prepare("
                INSERT INTO schueler (nachname, vorname, geschlecht, klasse_id) 
                VALUES (:nachname, :vorname, :geschlecht, :klasse_id)
            ");
            
            foreach ($schueler as $s) {
                $stmt->bindValue(':nachname', $s['nachname'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':vorname', $s['vorname'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':geschlecht', $s['geschlecht'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':klasse_id', $id, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
        
        // Commit Transaktion
        $db->exec('COMMIT');
        
        echo json_encode([
            'success' => true,
            'message' => 'Klasse erfolgreich aktualisiert'
        ]);
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Aktualisieren der Klasse: ' . $e->getMessage()]);
    }
}

function deleteKlasse($db, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Klassen-ID']);
        return;
    }
    
    try {
        // Starte Transaktion
        $db->exec('BEGIN TRANSACTION');
        
        // Prüfe ob Klasse existiert
        $stmt = $db->prepare("SELECT name FROM klassen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $klasse = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$klasse) {
            throw new Exception('Klasse nicht gefunden');
        }
        
        // Lösche Schüler (CASCADE löscht automatisch abhängige Daten)
        $stmt = $db->prepare("DELETE FROM schueler WHERE klasse_id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Lösche Fächer-Verknüpfungen
        $stmt = $db->prepare("DELETE FROM klassen_faecher WHERE klasse_id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Lösche Klasse selbst
        $stmt = $db->prepare("DELETE FROM klassen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            throw new Exception('Fehler beim Löschen der Klasse');
        }
        
        // Commit Transaktion
        $db->exec('COMMIT');
        
        echo json_encode([
            'success' => true,
            'message' => 'Klasse "' . $klasse['name'] . '" erfolgreich gelöscht'
        ]);
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Löschen der Klasse: ' . $e->getMessage()]);
    }
}

function saveSortingSettings($db) {
    // Lese JSON-Daten
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Daten empfangen']);
        return;
    }
    
    try {
        $sortingJson = json_encode($input);
        
        // Speichere oder aktualisiere Einstellung
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO einstellungen (schluessel, wert) 
            VALUES ('schueler_sortierung', :wert)
        ");
        $stmt->bindValue(':wert', $sortingJson, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            throw new Exception('Fehler beim Speichern der Sortierungseinstellungen');
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
    }
}

function getSortingSettings($db) {
    try {
        $stmt = $db->prepare("SELECT wert FROM einstellungen WHERE schluessel = 'schueler_sortierung'");
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row) {
            $sorting = json_decode($row['wert'], true);
            echo json_encode(['sorting' => $sorting]);
        } else {
            // Standard-Sortierung
            echo json_encode([
                'sorting' => [
                    'primary' => 'nachname',
                    'secondary' => 'vorname',
                    'tertiary' => 'geschlecht'
                ]
            ]);
        }
        
    } catch (Exception $e) {
        // Bei Fehler: Standard-Sortierung zurückgeben
        echo json_encode([
            'sorting' => [
                'primary' => 'nachname',
                'secondary' => 'vorname',
                'tertiary' => 'geschlecht'
            ]
        ]);
    }
}

?>