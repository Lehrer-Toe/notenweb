<?php
/**
 * API für Fächerverwaltung - MySQL Version mit Schuljahr-Filter
 */

session_start();
require_once '../config/database.php';

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Prüfe Session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// Initialisiere Datenbank
$db = Database::getInstance();
$conn = $db->getConnection();
$lehrerId = $_SESSION['user_id'];
$schuljahr = $db->getCurrentSchuljahr($lehrerId);

// Action-Parameter
$action = $_GET['action'] ?? '';

// Response-Helper
function sendResponse($data, $success = true, $message = '') {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'schuljahr' => $GLOBALS['schuljahr'] // Immer aktuelles Schuljahr mitsenden
    ]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse(null, false, $message);
}

// Route Actions
switch($action) {
    case 'list':
        listFaecher();
        break;
        
    case 'get':
        getFach($_GET['id'] ?? 0);
        break;
        
    case 'create':
        createFach();
        break;
        
    case 'update':
        updateFach($_GET['id'] ?? 0);
        break;
        
    case 'delete':
        deleteFach($_GET['id'] ?? 0);
        break;
        
    case 'archive':
        archiveFach($_GET['id'] ?? 0);
        break;
        
    case 'restore':
        restoreFach($_GET['id'] ?? 0);
        break;
        
    case 'list_archived':
        listArchivedFaecher();
        break;
        
    case 'set_schuljahr':
        setSchuljahr();
        break;
        
    case 'duplicate':
        duplicateFach($_GET['id'] ?? 0);
        break;
        
    default:
        sendError('Ungültige Aktion');
}

/**
 * Liste alle aktiven Fächer des aktuellen Schuljahrs
 */
function listFaecher() {
    global $conn, $lehrerId, $schuljahr;
    
    try {
        // Hole Fächer mit Kategorien
        $sql = "
            SELECT 
                f.id,
                f.name,
                f.kuerzel,
                f.farbe,
                f.created_at,
                COUNT(DISTINCT kf.klasse_id) as klassen_count,
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        '{\"id\":', k.id,
                        ',\"name\":\"', k.name, '\"',
                        ',\"gewichtung\":', k.gewichtung, '}'
                    ) SEPARATOR ','
                ) as kategorien_json
            FROM faecher f
            LEFT JOIN kategorien k ON k.fach_id = f.id AND k.ist_aktiv = 1
            LEFT JOIN klassen_faecher kf ON kf.fach_id = f.id
            WHERE f.lehrer_id = :lehrer_id 
                AND f.schuljahr = :schuljahr
                AND f.ist_archiviert = 0
            GROUP BY f.id
            ORDER BY f.name ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $faecher = [];
        while ($row = $stmt->fetch()) {
            // Parse Kategorien JSON
            $kategorien = [];
            if (!empty($row['kategorien_json'])) {
                $jsonStr = '[' . $row['kategorien_json'] . ']';
                $kategorien = json_decode($jsonStr, true) ?: [];
            }
            
            $faecher[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'kuerzel' => $row['kuerzel'],
                'farbe' => $row['farbe'],
                'klassen_count' => (int)$row['klassen_count'],
                'kategorien' => $kategorien,
                'created_at' => $row['created_at']
            ];
        }
        
        // Direkt als Array zurückgeben für Kompatibilität
        echo json_encode($faecher);
        
    } catch (PDOException $e) {
        error_log("Fehler in listFaecher: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Fächer');
    }
}

/**
 * Einzelnes Fach abrufen
 */
function getFach($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Fach-ID');
    }
    
    try {
        // Hole Fach
        $stmt = $conn->prepare("
            SELECT * FROM faecher 
            WHERE id = :id 
                AND lehrer_id = :lehrer_id 
                AND schuljahr = :schuljahr
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $fach = $stmt->fetch();
        
        if (!$fach) {
            sendError('Fach nicht gefunden', 404);
        }
        
        // Hole Kategorien
        $stmt = $conn->prepare("
            SELECT id, name, gewichtung, reihenfolge 
            FROM kategorien 
            WHERE fach_id = :fach_id AND ist_aktiv = 1
            ORDER BY reihenfolge, name
        ");
        $stmt->execute([':fach_id' => $id]);
        
        $kategorien = [];
        while ($kat = $stmt->fetch()) {
            $kategorien[] = [
                'id' => (int)$kat['id'],
                'name' => $kat['name'],
                'gewichtung' => (float)$kat['gewichtung']
            ];
        }
        
        // Rückgabe im erwarteten Format
        echo json_encode([
            'id' => (int)$fach['id'],
            'name' => $fach['name'],
            'kuerzel' => $fach['kuerzel'],
            'farbe' => $fach['farbe'],
            'kategorien' => $kategorien
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler in getFach: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen des Fachs');
    }
}

/**
 * Neues Fach erstellen
 */
function createFach() {
    global $conn, $lehrerId, $schuljahr;
    
    // Lese JSON-Input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['name'])) {
        sendError('Fachname ist erforderlich');
    }
    
    $name = trim($input['name']);
    $kuerzel = $input['kuerzel'] ?? substr($name, 0, 3);
    $farbe = $input['farbe'] ?? '#4A90E2';
    $kategorien = $input['kategorien'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Prüfe ob Fach bereits existiert
        $stmt = $conn->prepare("
            SELECT id FROM faecher 
            WHERE lehrer_id = :lehrer_id 
                AND schuljahr = :schuljahr 
                AND name = :name
        ");
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr,
            ':name' => $name
        ]);
        
        if ($stmt->fetch()) {
            $conn->rollBack();
            sendError('Ein Fach mit diesem Namen existiert bereits in diesem Schuljahr');
        }
        
        // Erstelle Fach
        $stmt = $conn->prepare("
            INSERT INTO faecher (lehrer_id, schuljahr, name, kuerzel, farbe) 
            VALUES (:lehrer_id, :schuljahr, :name, :kuerzel, :farbe)
        ");
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr,
            ':name' => $name,
            ':kuerzel' => $kuerzel,
            ':farbe' => $farbe
        ]);
        
        $fachId = $conn->lastInsertId();
        
        // Füge Kategorien hinzu
        if (!empty($kategorien)) {
            $stmt = $conn->prepare("
                INSERT INTO kategorien (fach_id, name, gewichtung, reihenfolge) 
                VALUES (:fach_id, :name, :gewichtung, :reihenfolge)
            ");
            
            foreach ($kategorien as $index => $kat) {
                $stmt->execute([
                    ':fach_id' => $fachId,
                    ':name' => $kat['name'],
                    ':gewichtung' => $kat['gewichtung'] ?? 1,
                    ':reihenfolge' => $index
                ]);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'id' => $fachId,
            'message' => 'Fach erfolgreich erstellt'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in createFach: " . $e->getMessage());
        sendError('Fehler beim Erstellen des Fachs');
    }
}

/**
 * Fach aktualisieren
 */
function updateFach($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Fach-ID');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['name'])) {
        sendError('Fachname ist erforderlich');
    }
    
    try {
        $conn->beginTransaction();
        
        // Prüfe Berechtigung
        $stmt = $conn->prepare("
            SELECT id FROM faecher 
            WHERE id = :id 
                AND lehrer_id = :lehrer_id 
                AND schuljahr = :schuljahr
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        if (!$stmt->fetch()) {
            $conn->rollBack();
            sendError('Fach nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Update Fach
        $stmt = $conn->prepare("
            UPDATE faecher 
            SET name = :name, 
                kuerzel = :kuerzel,
                farbe = :farbe,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':name' => $input['name'],
            ':kuerzel' => $input['kuerzel'] ?? substr($input['name'], 0, 3),
            ':farbe' => $input['farbe'] ?? '#4A90E2',
            ':id' => $id
        ]);
        
        // Deaktiviere alte Kategorien (soft delete)
        $stmt = $conn->prepare("
            UPDATE kategorien SET ist_aktiv = 0 WHERE fach_id = :fach_id
        ");
        $stmt->execute([':fach_id' => $id]);
        
        // Füge neue/aktualisierte Kategorien hinzu
        if (!empty($input['kategorien'])) {
            $stmt = $conn->prepare("
                INSERT INTO kategorien (fach_id, name, gewichtung, reihenfolge, ist_aktiv) 
                VALUES (:fach_id, :name, :gewichtung, :reihenfolge, 1)
                ON DUPLICATE KEY UPDATE 
                    gewichtung = VALUES(gewichtung),
                    reihenfolge = VALUES(reihenfolge),
                    ist_aktiv = 1
            ");
            
            foreach ($input['kategorien'] as $index => $kat) {
                $stmt->execute([
                    ':fach_id' => $id,
                    ':name' => $kat['name'],
                    ':gewichtung' => $kat['gewichtung'] ?? 1,
                    ':reihenfolge' => $index
                ]);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Fach erfolgreich aktualisiert'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in updateFach: " . $e->getMessage());
        sendError('Fehler beim Aktualisieren des Fachs');
    }
}

/**
 * Fach löschen (nur wenn keine Noten vorhanden)
 */
function deleteFach($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Fach-ID');
    }
    
    try {
        $conn->beginTransaction();
        
        // Prüfe ob Fach existiert und Berechtigung vorhanden
        $stmt = $conn->prepare("
            SELECT name FROM faecher 
            WHERE id = :id 
                AND lehrer_id = :lehrer_id 
                AND schuljahr = :schuljahr
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $fach = $stmt->fetch();
        if (!$fach) {
            $conn->rollBack();
            sendError('Fach nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Prüfe ob Noten vorhanden sind
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM noten WHERE fach_id = :fach_id
        ");
        $stmt->execute([':fach_id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $conn->rollBack();
            sendError('Fach kann nicht gelöscht werden, da bereits Noten vorhanden sind. Bitte archivieren Sie das Fach stattdessen.');
        }
        
        // Lösche Fach (CASCADE löscht auch Kategorien und Verknüpfungen)
        $stmt = $conn->prepare("DELETE FROM faecher WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Fach "' . $fach['name'] . '" erfolgreich gelöscht'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in deleteFach: " . $e->getMessage());
        sendError('Fehler beim Löschen des Fachs');
    }
}

/**
 * Fach archivieren
 */
function archiveFach($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Fach-ID');
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE faecher 
            SET ist_archiviert = 1, updated_at = NOW()
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        if ($stmt->rowCount() === 0) {
            sendError('Fach nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Log-Eintrag
        $stmt = $conn->prepare("
            INSERT INTO archiv_log (tabelle, datensatz_id, aktion, user_id) 
            VALUES ('faecher', :id, 'archiviert', :user_id)
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $lehrerId
        ]);
        
        sendResponse(null, true, 'Fach erfolgreich archiviert');
        
    } catch (PDOException $e) {
        error_log("Fehler in archiveFach: " . $e->getMessage());
        sendError('Fehler beim Archivieren des Fachs');
    }
}

/**
 * Fach aus Archiv wiederherstellen
 */
function restoreFach($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Fach-ID');
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE faecher 
            SET ist_archiviert = 0, updated_at = NOW()
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        if ($stmt->rowCount() === 0) {
            sendError('Fach nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Log-Eintrag
        $stmt = $conn->prepare("
            INSERT INTO archiv_log (tabelle, datensatz_id, aktion, user_id) 
            VALUES ('faecher', :id, 'wiederhergestellt', :user_id)
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $lehrerId
        ]);
        
        sendResponse(null, true, 'Fach erfolgreich wiederhergestellt');
        
    } catch (PDOException $e) {
        error_log("Fehler in restoreFach: " . $e->getMessage());
        sendError('Fehler beim Wiederherstellen des Fachs');
    }
}

/**
 * Archivierte Fächer anzeigen
 */
function listArchivedFaecher() {
    global $conn, $lehrerId;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                f.*,
                COUNT(DISTINCT n.id) as noten_count
            FROM faecher f
            LEFT JOIN noten n ON n.fach_id = f.id
            WHERE f.lehrer_id = :lehrer_id 
                AND f.ist_archiviert = 1
            GROUP BY f.id
            ORDER BY f.updated_at DESC
        ");
        $stmt->execute([':lehrer_id' => $lehrerId]);
        
        $faecher = $stmt->fetchAll();
        sendResponse($faecher);
        
    } catch (PDOException $e) {
        error_log("Fehler in listArchivedFaecher: " . $e->getMessage());
        sendError('Fehler beim Abrufen der archivierten Fächer');
    }
}

/**
 * Schuljahr ändern
 */
function setSchuljahr() {
    global $db, $lehrerId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['schuljahr'])) {
        sendError('Schuljahr ist erforderlich');
    }
    
    // Validiere Schuljahr-Format (z.B. 2025/2026)
    if (!preg_match('/^\d{4}\/\d{4}$/', $input['schuljahr'])) {
        sendError('Ungültiges Schuljahr-Format. Erwartet: YYYY/YYYY');
    }
    
    if ($db->setSchuljahr($lehrerId, $input['schuljahr'])) {
        $_SESSION['schuljahr'] = $input['schuljahr'];
        sendResponse(['schuljahr' => $input['schuljahr']], true, 'Schuljahr erfolgreich geändert');
    } else {
        sendError('Fehler beim Ändern des Schuljahrs');
    }
}

/**
 * Fach duplizieren (z.B. für neues Schuljahr)
 */
function duplicateFach($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Fach-ID');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $neuesSchuljahr = $input['schuljahr'] ?? $schuljahr;
    $neuerName = $input['name'] ?? null;
    
    try {
        $conn->beginTransaction();
        
        // Hole Original-Fach
        $stmt = $conn->prepare("
            SELECT * FROM faecher 
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        $originalFach = $stmt->fetch();
        if (!$originalFach) {
            $conn->rollBack();
            sendError('Fach nicht gefunden', 404);
        }
        
        // Erstelle Kopie
        $stmt = $conn->prepare("
            INSERT INTO faecher (lehrer_id, schuljahr, name, kuerzel, farbe) 
            VALUES (:lehrer_id, :schuljahr, :name, :kuerzel, :farbe)
        ");
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $neuesSchuljahr,
            ':name' => $neuerName ?? $originalFach['name'] . ' (Kopie)',
            ':kuerzel' => $originalFach['kuerzel'],
            ':farbe' => $originalFach['farbe']
        ]);
        
        $neuesFachId = $conn->lastInsertId();
        
        // Kopiere Kategorien
        $stmt = $conn->prepare("
            INSERT INTO kategorien (fach_id, name, gewichtung, reihenfolge)
            SELECT :neues_fach_id, name, gewichtung, reihenfolge
            FROM kategorien
            WHERE fach_id = :original_fach_id AND ist_aktiv = 1
        ");
        $stmt->execute([
            ':neues_fach_id' => $neuesFachId,
            ':original_fach_id' => $id
        ]);
        
        $conn->commit();
        
        sendResponse(['id' => $neuesFachId], true, 'Fach erfolgreich dupliziert');
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in duplicateFach: " . $e->getMessage());
        sendError('Fehler beim Duplizieren des Fachs');
    }
}
?>