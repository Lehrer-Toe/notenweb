<?php
/**
 * API für Klassenverwaltung - MySQL Version mit Schuljahr und Archiv
 */

session_start();
require_once '../config/database.php';

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Prüfe Session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht autorisiert']);
    exit;
}

// Initialisierung
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
        'schuljahr' => $GLOBALS['schuljahr']
    ]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// Route zur entsprechenden Funktion
switch ($action) {
    case 'list':
        listKlassen();
        break;
        
    case 'get':
        getKlasse($_GET['id'] ?? 0);
        break;
        
    case 'details':
        getKlasseDetails($_GET['id'] ?? 0);
        break;
        
    case 'create':
        createKlasse();
        break;
        
    case 'update':
        updateKlasse($_GET['id'] ?? 0);
        break;
        
    case 'delete':
        deleteKlasse($_GET['id'] ?? 0);
        break;
        
    case 'archive':
        archiveKlasse($_GET['id'] ?? 0);
        break;
        
    case 'restore':
        restoreKlasse($_GET['id'] ?? 0);
        break;
        
    case 'list_archived':
        listArchivedKlassen();
        break;
        
    case 'bulk_archive':
        bulkArchiveKlassen();
        break;
        
    case 'versetzung':
        versetzeKlasse($_GET['id'] ?? 0);
        break;
        
    case 'import_csv':
        importSchuelerCSV($_GET['id'] ?? 0);
        break;
        
    case 'export':
        exportKlasse($_GET['id'] ?? 0);
        break;
        
    case 'save-sorting':
        saveSortingSettings();
        break;
        
    case 'get-sorting':
        getSortingSettings();
        break;
        
    default:
        sendError('Ungültige Aktion');
}

/**
 * Liste alle aktiven Klassen des aktuellen Schuljahrs
 */
function listKlassen() {
    global $conn, $lehrerId, $schuljahr;
    
    try {
        $sql = "
            SELECT 
                k.id,
                k.name,
                k.stufe,
                k.typ,
                k.beschreibung,
                k.created_at,
                COUNT(DISTINCT s.id) as schueler_count,
                COUNT(DISTINCT kf.fach_id) as faecher_count,
                GROUP_CONCAT(DISTINCT f.name SEPARATOR ', ') as faecher_namen
            FROM klassen k
            LEFT JOIN schueler s ON s.klasse_id = k.id AND s.ist_aktiv = 1
            LEFT JOIN klassen_faecher kf ON kf.klasse_id = k.id
            LEFT JOIN faecher f ON kf.fach_id = f.id
            WHERE k.lehrer_id = :lehrer_id 
                AND k.schuljahr = :schuljahr
                AND k.ist_archiviert = 0
            GROUP BY k.id
            ORDER BY k.stufe, k.name
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $klassen = $stmt->fetchAll();
        
        // Kompatibilität mit Frontend
        echo json_encode(['klassen' => $klassen]);
        
    } catch (PDOException $e) {
        error_log("Fehler in listKlassen: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Klassen');
    }
}

/**
 * Einzelne Klasse mit Schülern und Fächern abrufen
 */
function getKlasse($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    try {
        // Hole Klassengrundaten
        $stmt = $conn->prepare("
            SELECT * FROM klassen 
            WHERE id = :id 
                AND lehrer_id = :lehrer_id 
                AND schuljahr = :schuljahr
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $klasse = $stmt->fetch();
        
        if (!$klasse) {
            sendError('Klasse nicht gefunden', 404);
        }
        
        // Hole Schüler (sortiert nach Einstellung)
        $sorting = getSortingFromDB();
        $orderBy = buildOrderByClause($sorting);
        
        $stmt = $conn->prepare("
            SELECT id, nachname, vorname, geschlecht, geburtsdatum, email
            FROM schueler 
            WHERE klasse_id = :id AND ist_aktiv = 1
            ORDER BY {$orderBy}
        ");
        $stmt->execute([':id' => $id]);
        $klasse['schueler'] = $stmt->fetchAll();
        
        // Hole Fächer-IDs
        $stmt = $conn->prepare("
            SELECT fach_id 
            FROM klassen_faecher 
            WHERE klasse_id = :id
        ");
        $stmt->execute([':id' => $id]);
        $klasse['faecher'] = array_column($stmt->fetchAll(), 'fach_id');
        
        echo json_encode($klasse);
        
    } catch (PDOException $e) {
        error_log("Fehler in getKlasse: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Klasse');
    }
}

/**
 * Detaillierte Klassenansicht mit Fachnamen
 */
function getKlasseDetails($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    try {
        // Hole Klasse
        $stmt = $conn->prepare("
            SELECT k.*, 
                   COUNT(DISTINCT s.id) as schueler_gesamt,
                   COUNT(DISTINCT CASE WHEN s.geschlecht = 'm' THEN s.id END) as schueler_m,
                   COUNT(DISTINCT CASE WHEN s.geschlecht = 'w' THEN s.id END) as schueler_w,
                   AVG(n.note) as notendurchschnitt
            FROM klassen k
            LEFT JOIN schueler s ON s.klasse_id = k.id AND s.ist_aktiv = 1
            LEFT JOIN noten n ON n.schueler_id = s.id AND n.ist_archiviert = 0
            WHERE k.id = :id 
                AND k.lehrer_id = :lehrer_id 
                AND k.schuljahr = :schuljahr
            GROUP BY k.id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $klasse = $stmt->fetch();
        
        if (!$klasse) {
            sendError('Klasse nicht gefunden', 404);
        }
        
        // Hole Schüler mit Notendurchschnitt
        $stmt = $conn->prepare("
            SELECT 
                s.*,
                COUNT(DISTINCT n.id) as anzahl_noten,
                AVG(n.note) as durchschnitt,
                COUNT(DISTINCT h.id) as fehlende_hausaufgaben
            FROM schueler s
            LEFT JOIN noten n ON n.schueler_id = s.id AND n.ist_archiviert = 0
            LEFT JOIN hausaufgaben h ON h.schueler_id = s.id AND h.status = 'vergessen'
            WHERE s.klasse_id = :id AND s.ist_aktiv = 1
            GROUP BY s.id
            ORDER BY s.nachname, s.vorname
        ");
        $stmt->execute([':id' => $id]);
        $klasse['schueler'] = $stmt->fetchAll();
        
        // Hole Fächer mit Namen
        $stmt = $conn->prepare("
            SELECT f.id, f.name, f.kuerzel, kf.wochenstunden
            FROM faecher f
            JOIN klassen_faecher kf ON kf.fach_id = f.id
            WHERE kf.klasse_id = :id
            ORDER BY f.name
        ");
        $stmt->execute([':id' => $id]);
        $faecher = $stmt->fetchAll();
        
        $klasse['faecher_namen'] = array_column($faecher, 'name');
        $klasse['faecher_details'] = $faecher;
        
        echo json_encode($klasse);
        
    } catch (PDOException $e) {
        error_log("Fehler in getKlasseDetails: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Details');
    }
}

/**
 * Neue Klasse erstellen
 */
function createKlasse() {
    global $conn, $lehrerId, $schuljahr;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['name'])) {
        sendError('Klassenname ist erforderlich');
    }
    
    $name = trim($input['name']);
    $stufe = $input['stufe'] ?? '';
    $typ = $input['typ'] ?? 'normal';
    $beschreibung = $input['beschreibung'] ?? '';
    $faecher = $input['faecher'] ?? [];
    $schueler = $input['schueler'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Prüfe ob Klasse bereits existiert
        $stmt = $conn->prepare("
            SELECT id FROM klassen 
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
            sendError('Eine Klasse mit diesem Namen existiert bereits in diesem Schuljahr');
        }
        
        // Erstelle Klasse
        $stmt = $conn->prepare("
            INSERT INTO klassen (lehrer_id, schuljahr, name, stufe, typ, beschreibung) 
            VALUES (:lehrer_id, :schuljahr, :name, :stufe, :typ, :beschreibung)
        ");
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr,
            ':name' => $name,
            ':stufe' => $stufe,
            ':typ' => $typ,
            ':beschreibung' => $beschreibung
        ]);
        
        $klasseId = $conn->lastInsertId();
        
        // Füge Fächer hinzu
        if (!empty($faecher)) {
            $stmt = $conn->prepare("
                INSERT INTO klassen_faecher (klasse_id, fach_id) 
                VALUES (:klasse_id, :fach_id)
            ");
            
            foreach ($faecher as $fachId) {
                if (is_numeric($fachId)) {
                    $stmt->execute([
                        ':klasse_id' => $klasseId,
                        ':fach_id' => intval($fachId)
                    ]);
                }
            }
        }
        
        // Füge Schüler hinzu
        if (!empty($schueler)) {
            $stmt = $conn->prepare("
                INSERT INTO schueler (klasse_id, nachname, vorname, geschlecht, geburtsdatum, email) 
                VALUES (:klasse_id, :nachname, :vorname, :geschlecht, :geburtsdatum, :email)
            ");
            
            foreach ($schueler as $s) {
                if (!empty($s['nachname']) || !empty($s['vorname'])) {
                    $stmt->execute([
                        ':klasse_id' => $klasseId,
                        ':nachname' => $s['nachname'] ?? '',
                        ':vorname' => $s['vorname'] ?? '',
                        ':geschlecht' => $s['geschlecht'] ?? null,
                        ':geburtsdatum' => !empty($s['geburtsdatum']) ? $s['geburtsdatum'] : null,
                        ':email' => $s['email'] ?? null
                    ]);
                }
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'id' => $klasseId,
            'message' => 'Klasse erfolgreich erstellt'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in createKlasse: " . $e->getMessage());
        sendError('Fehler beim Erstellen der Klasse: ' . $e->getMessage());
    }
}

/**
 * Klasse aktualisieren
 */
function updateKlasse($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['name'])) {
        sendError('Klassenname ist erforderlich');
    }
    
    try {
        $conn->beginTransaction();
        
        // Prüfe Berechtigung
        $stmt = $conn->prepare("
            SELECT id FROM klassen 
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
            sendError('Klasse nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Update Klasse
        $stmt = $conn->prepare("
            UPDATE klassen 
            SET name = :name, 
                stufe = :stufe,
                typ = :typ,
                beschreibung = :beschreibung,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':name' => $input['name'],
            ':stufe' => $input['stufe'] ?? '',
            ':typ' => $input['typ'] ?? 'normal',
            ':beschreibung' => $input['beschreibung'] ?? '',
            ':id' => $id
        ]);
        
        // Aktualisiere Fächer-Verknüpfungen
        $stmt = $conn->prepare("DELETE FROM klassen_faecher WHERE klasse_id = :id");
        $stmt->execute([':id' => $id]);
        
        if (!empty($input['faecher'])) {
            $stmt = $conn->prepare("
                INSERT INTO klassen_faecher (klasse_id, fach_id) 
                VALUES (:klasse_id, :fach_id)
            ");
            
            foreach ($input['faecher'] as $fachId) {
                if (is_numeric($fachId)) {
                    $stmt->execute([
                        ':klasse_id' => $id,
                        ':fach_id' => intval($fachId)
                    ]);
                }
            }
        }
        
        // Aktualisiere Schüler (Soft-Delete und Neu-Anlegen)
        if (isset($input['schueler'])) {
            // Deaktiviere alle bestehenden Schüler
            $stmt = $conn->prepare("
                UPDATE schueler SET ist_aktiv = 0 WHERE klasse_id = :id
            ");
            $stmt->execute([':id' => $id]);
            
            // Füge neue/aktualisierte Schüler hinzu
            if (!empty($input['schueler'])) {
                $stmt = $conn->prepare("
                    INSERT INTO schueler (klasse_id, nachname, vorname, geschlecht, geburtsdatum, email, ist_aktiv) 
                    VALUES (:klasse_id, :nachname, :vorname, :geschlecht, :geburtsdatum, :email, 1)
                    ON DUPLICATE KEY UPDATE 
                        nachname = VALUES(nachname),
                        vorname = VALUES(vorname),
                        geschlecht = VALUES(geschlecht),
                        ist_aktiv = 1
                ");
                
                foreach ($input['schueler'] as $s) {
                    if (!empty($s['nachname']) || !empty($s['vorname'])) {
                        $stmt->execute([
                            ':klasse_id' => $id,
                            ':nachname' => $s['nachname'] ?? '',
                            ':vorname' => $s['vorname'] ?? '',
                            ':geschlecht' => $s['geschlecht'] ?? null,
                            ':geburtsdatum' => !empty($s['geburtsdatum']) ? $s['geburtsdatum'] : null,
                            ':email' => $s['email'] ?? null
                        ]);
                    }
                }
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Klasse erfolgreich aktualisiert'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in updateKlasse: " . $e->getMessage());
        sendError('Fehler beim Aktualisieren der Klasse');
    }
}

/**
 * Klasse löschen (nur wenn keine Noten vorhanden)
 */
function deleteKlasse($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    try {
        $conn->beginTransaction();
        
        // Prüfe Berechtigung und hole Klassenname
        $stmt = $conn->prepare("
            SELECT name FROM klassen 
            WHERE id = :id 
                AND lehrer_id = :lehrer_id 
                AND schuljahr = :schuljahr
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $klasse = $stmt->fetch();
        if (!$klasse) {
            $conn->rollBack();
            sendError('Klasse nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Prüfe ob Noten vorhanden sind
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM noten n
            JOIN schueler s ON n.schueler_id = s.id
            WHERE s.klasse_id = :klasse_id
        ");
        $stmt->execute([':klasse_id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $conn->rollBack();
            sendError('Klasse kann nicht gelöscht werden, da bereits Noten vorhanden sind. Bitte archivieren Sie die Klasse stattdessen.');
        }
        
        // Lösche Klasse (CASCADE löscht auch Schüler und Verknüpfungen)
        $stmt = $conn->prepare("DELETE FROM klassen WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Klasse "' . $klasse['name'] . '" erfolgreich gelöscht'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in deleteKlasse: " . $e->getMessage());
        sendError('Fehler beim Löschen der Klasse');
    }
}

/**
 * Klasse archivieren
 */
function archiveKlasse($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    try {
        $conn->beginTransaction();
        
        // Archiviere Klasse
        $stmt = $conn->prepare("
            UPDATE klassen 
            SET ist_archiviert = 1, 
                archiviert_am = NOW(),
                archiviert_von = :user_id
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId,
            ':user_id' => $lehrerId
        ]);
        
        if ($stmt->rowCount() === 0) {
            $conn->rollBack();
            sendError('Klasse nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Archiviere auch alle Noten der Schüler
        $stmt = $conn->prepare("
            UPDATE noten n
            JOIN schueler s ON n.schueler_id = s.id
            SET n.ist_archiviert = 1
            WHERE s.klasse_id = :klasse_id
        ");
        $stmt->execute([':klasse_id' => $id]);
        
        // Log-Eintrag
        $stmt = $conn->prepare("
            INSERT INTO archiv_log (tabelle, datensatz_id, aktion, user_id, details) 
            VALUES ('klassen', :id, 'archiviert', :user_id, :details)
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $lehrerId,
            ':details' => json_encode(['schuljahr' => $GLOBALS['schuljahr']])
        ]);
        
        $conn->commit();
        
        sendResponse(null, true, 'Klasse erfolgreich archiviert');
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in archiveKlasse: " . $e->getMessage());
        sendError('Fehler beim Archivieren der Klasse');
    }
}

/**
 * Klasse aus Archiv wiederherstellen
 */
function restoreKlasse($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    try {
        $conn->beginTransaction();
        
        // Reaktiviere Klasse
        $stmt = $conn->prepare("
            UPDATE klassen 
            SET ist_archiviert = 0, 
                archiviert_am = NULL,
                archiviert_von = NULL
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        if ($stmt->rowCount() === 0) {
            $conn->rollBack();
            sendError('Klasse nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Reaktiviere auch alle Noten
        $stmt = $conn->prepare("
            UPDATE noten n
            JOIN schueler s ON n.schueler_id = s.id
            SET n.ist_archiviert = 0
            WHERE s.klasse_id = :klasse_id
        ");
        $stmt->execute([':klasse_id' => $id]);
        
        // Log-Eintrag
        $stmt = $conn->prepare("
            INSERT INTO archiv_log (tabelle, datensatz_id, aktion, user_id) 
            VALUES ('klassen', :id, 'wiederhergestellt', :user_id)
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $lehrerId
        ]);
        
        $conn->commit();
        
        sendResponse(null, true, 'Klasse erfolgreich wiederhergestellt');
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in restoreKlasse: " . $e->getMessage());
        sendError('Fehler beim Wiederherstellen der Klasse');
    }
}

/**
 * Archivierte Klassen anzeigen
 */
function listArchivedKlassen() {
    global $conn, $lehrerId;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                k.*,
                COUNT(DISTINCT s.id) as schueler_count,
                COUNT(DISTINCT n.id) as noten_count,
                u.email as archiviert_von_name
            FROM klassen k
            LEFT JOIN schueler s ON s.klasse_id = k.id
            LEFT JOIN noten n ON n.schueler_id = s.id
            LEFT JOIN users u ON k.archiviert_von = u.id
            WHERE k.lehrer_id = :lehrer_id 
                AND k.ist_archiviert = 1
            GROUP BY k.id
            ORDER BY k.archiviert_am DESC
        ");
        $stmt->execute([':lehrer_id' => $lehrerId]);
        
        $klassen = $stmt->fetchAll();
        sendResponse($klassen);
        
    } catch (PDOException $e) {
        error_log("Fehler in listArchivedKlassen: " . $e->getMessage());
        sendError('Fehler beim Abrufen der archivierten Klassen');
    }
}

/**
 * Massenarchivierung am Schuljahresende
 */
function bulkArchiveKlassen() {
    global $conn, $lehrerId, $schuljahr;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $klassenIds = $input['klassen_ids'] ?? [];
    
    if (empty($klassenIds)) {
        sendError('Keine Klassen ausgewählt');
    }
    
    try {
        $conn->beginTransaction();
        
        $placeholders = str_repeat('?,', count($klassenIds) - 1) . '?';
        
        // Archiviere alle ausgewählten Klassen
        $stmt = $conn->prepare("
            UPDATE klassen 
            SET ist_archiviert = 1, 
                archiviert_am = NOW(),
                archiviert_von = ?
            WHERE id IN ({$placeholders}) 
                AND lehrer_id = ?
                AND schuljahr = ?
        ");
        
        $params = array_merge([$lehrerId], $klassenIds, [$lehrerId, $schuljahr]);
        $stmt->execute($params);
        
        $archiviertCount = $stmt->rowCount();
        
        // Archiviere auch alle zugehörigen Noten
        $stmt = $conn->prepare("
            UPDATE noten n
            JOIN schueler s ON n.schueler_id = s.id
            SET n.ist_archiviert = 1
            WHERE s.klasse_id IN ({$placeholders})
        ");
        $stmt->execute($klassenIds);
        
        // Log-Einträge
        $stmt = $conn->prepare("
            INSERT INTO archiv_log (tabelle, datensatz_id, aktion, user_id, details) 
            VALUES ('klassen', :id, 'archiviert', :user_id, :details)
        ");
        
        foreach ($klassenIds as $klasseId) {
            $stmt->execute([
                ':id' => $klasseId,
                ':user_id' => $lehrerId,
                ':details' => json_encode(['bulk_archive' => true, 'schuljahr' => $schuljahr])
            ]);
        }
        
        $conn->commit();
        
        sendResponse([
            'archiviert' => $archiviertCount
        ], true, "{$archiviertCount} Klassen erfolgreich archiviert");
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in bulkArchiveKlassen: " . $e->getMessage());
        sendError('Fehler bei der Massenarchivierung');
    }
}

/**
 * Klasse ins nächste Schuljahr versetzen
 */
function versetzeKlasse($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $neuesSchuljahr = $input['neues_schuljahr'] ?? null;
    $neueStufe = $input['neue_stufe'] ?? null;
    
    if (!$neuesSchuljahr) {
        // Automatisch nächstes Schuljahr berechnen
        $jahre = explode('/', $schuljahr);
        $neuesSchuljahr = (intval($jahre[0]) + 1) . '/' . (intval($jahre[1]) + 1);
    }
    
    try {
        $conn->beginTransaction();
        
        // Hole Original-Klasse
        $stmt = $conn->prepare("
            SELECT * FROM klassen 
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        $original = $stmt->fetch();
        if (!$original) {
            $conn->rollBack();
            sendError('Klasse nicht gefunden', 404);
        }
        
        // Erstelle neue Klasse im neuen Schuljahr
        $stmt = $conn->prepare("
            INSERT INTO klassen (lehrer_id, schuljahr, name, stufe, typ, beschreibung) 
            VALUES (:lehrer_id, :schuljahr, :name, :stufe, :typ, :beschreibung)
        ");
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $neuesSchuljahr,
            ':name' => $original['name'],
            ':stufe' => $neueStufe ?? $original['stufe'],
            ':typ' => $original['typ'],
            ':beschreibung' => 'Versetzt aus ' . $schuljahr
        ]);
        
        $neueKlasseId = $conn->lastInsertId();
        
        // Kopiere Fächer-Verknüpfungen
        $stmt = $conn->prepare("
            INSERT INTO klassen_faecher (klasse_id, fach_id, wochenstunden)
            SELECT :neue_klasse_id, fach_id, wochenstunden
            FROM klassen_faecher
            WHERE klasse_id = :original_id
        ");
        $stmt->execute([
            ':neue_klasse_id' => $neueKlasseId,
            ':original_id' => $id
        ]);
        
        // Versetze Schüler (ohne alte Noten)
        $stmt = $conn->prepare("
            INSERT INTO schueler (klasse_id, nachname, vorname, geschlecht, geburtsdatum, email, notizen)
            SELECT :neue_klasse_id, nachname, vorname, geschlecht, geburtsdatum, email, notizen
            FROM schueler
            WHERE klasse_id = :original_id AND ist_aktiv = 1
        ");
        $stmt->execute([
            ':neue_klasse_id' => $neueKlasseId,
            ':original_id' => $id
        ]);
        
        // Archiviere alte Klasse
        $stmt = $conn->prepare("
            UPDATE klassen 
            SET ist_archiviert = 1, 
                archiviert_am = NOW(),
                archiviert_von = :user_id
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $lehrerId
        ]);
        
        $conn->commit();
        
        sendResponse([
            'neue_klasse_id' => $neueKlasseId,
            'neues_schuljahr' => $neuesSchuljahr
        ], true, 'Klasse erfolgreich ins neue Schuljahr versetzt');
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in versetzeKlasse: " . $e->getMessage());
        sendError('Fehler beim Versetzen der Klasse');
    }
}

/**
 * CSV-Import für Schüler
 */
function importSchuelerCSV($klasseId) {
    global $conn, $lehrerId;
    
    if (!$klasseId || !is_numeric($klasseId)) {
        sendError('Ungültige Klassen-ID');
    }
    
    // Prüfe ob Datei hochgeladen wurde
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        sendError('Keine gültige CSV-Datei hochgeladen');
    }
    
    $csvFile = $_FILES['csv']['tmp_name'];
    $csvContent = file_get_contents($csvFile);
    
    // Erkenne Encoding und konvertiere zu UTF-8
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    // Parse CSV
    $lines = explode("\n", $csvContent);
    $schueler = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $data = str_getcsv($line, ';'); // Deutsche CSVs nutzen oft Semikolon
        if (count($data) < 2) {
            $data = str_getcsv($line, ','); // Fallback zu Komma
        }
        
        if (count($data) >= 2) {
            $schueler[] = [
                'nachname' => trim($data[0]),
                'vorname' => trim($data[1]),
                'geschlecht' => isset($data[2]) ? trim($data[2]) : null,
                'geburtsdatum' => isset($data[3]) ? trim($data[3]) : null,
                'email' => isset($data[4]) ? trim($data[4]) : null
            ];
        }
    }
    
    if (empty($schueler)) {
        sendError('Keine gültigen Schülerdaten in der CSV-Datei gefunden');
    }
    
    try {
        $conn->beginTransaction();
        
        // Prüfe Berechtigung
        $stmt = $conn->prepare("
            SELECT id FROM klassen 
            WHERE id = :id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $klasseId,
            ':lehrer_id' => $lehrerId
        ]);
        
        if (!$stmt->fetch()) {
            $conn->rollBack();
            sendError('Klasse nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Importiere Schüler
        $stmt = $conn->prepare("
            INSERT INTO schueler (klasse_id, nachname, vorname, geschlecht, geburtsdatum, email) 
            VALUES (:klasse_id, :nachname, :vorname, :geschlecht, :geburtsdatum, :email)
        ");
        
        $importCount = 0;
        foreach ($schueler as $s) {
            if (!empty($s['nachname']) || !empty($s['vorname'])) {
                // Normalisiere Geschlecht
                $geschlecht = null;
                if (!empty($s['geschlecht'])) {
                    $g = strtolower(substr($s['geschlecht'], 0, 1));
                    if (in_array($g, ['m', 'w', 'd'])) {
                        $geschlecht = $g;
                    }
                }
                
                // Parse Geburtsdatum
                $geburtsdatum = null;
                if (!empty($s['geburtsdatum'])) {
                    $date = DateTime::createFromFormat('d.m.Y', $s['geburtsdatum']);
                    if (!$date) {
                        $date = DateTime::createFromFormat('Y-m-d', $s['geburtsdatum']);
                    }
                    if ($date) {
                        $geburtsdatum = $date->format('Y-m-d');
                    }
                }
                
                $stmt->execute([
                    ':klasse_id' => $klasseId,
                    ':nachname' => $s['nachname'],
                    ':vorname' => $s['vorname'],
                    ':geschlecht' => $geschlecht,
                    ':geburtsdatum' => $geburtsdatum,
                    ':email' => filter_var($s['email'], FILTER_VALIDATE_EMAIL) ? $s['email'] : null
                ]);
                $importCount++;
            }
        }
        
        $conn->commit();
        
        sendResponse([
            'importiert' => $importCount
        ], true, "{$importCount} Schüler erfolgreich importiert");
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in importSchuelerCSV: " . $e->getMessage());
        sendError('Fehler beim Importieren der Schülerdaten');
    }
}

/**
 * Klasse exportieren (JSON/CSV)
 */
function exportKlasse($id) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Klassen-ID');
    }
    
    $format = $_GET['format'] ?? 'json';
    
    try {
        // Hole komplette Klassendaten
        $stmt = $conn->prepare("
            SELECT k.*, COUNT(s.id) as schueler_count
            FROM klassen k
            LEFT JOIN schueler s ON s.klasse_id = k.id AND s.ist_aktiv = 1
            WHERE k.id = :id 
                AND k.lehrer_id = :lehrer_id
            GROUP BY k.id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        $klasse = $stmt->fetch();
        if (!$klasse) {
            sendError('Klasse nicht gefunden', 404);
        }
        
        // Hole Schüler mit Noten
        $stmt = $conn->prepare("
            SELECT 
                s.*,
                GROUP_CONCAT(
                    CONCAT(f.name, ':', ROUND(AVG(n.note), 1))
                    SEPARATOR '; '
                ) as noten_durchschnitt
            FROM schueler s
            LEFT JOIN noten n ON n.schueler_id = s.id AND n.ist_archiviert = 0
            LEFT JOIN faecher f ON n.fach_id = f.id
            WHERE s.klasse_id = :id AND s.ist_aktiv = 1
            GROUP BY s.id
            ORDER BY s.nachname, s.vorname
        ");
        $stmt->execute([':id' => $id]);
        $schueler = $stmt->fetchAll();
        
        if ($format === 'csv') {
            // CSV-Export
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="klasse_' . $klasse['name'] . '_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            
            // Header
            fputcsv($output, ['Nachname', 'Vorname', 'Geschlecht', 'Geburtsdatum', 'Email', 'Notendurchschnitt'], ';');
            
            // Daten
            foreach ($schueler as $s) {
                fputcsv($output, [
                    $s['nachname'],
                    $s['vorname'],
                    $s['geschlecht'],
                    $s['geburtsdatum'],
                    $s['email'],
                    $s['noten_durchschnitt']
                ], ';');
            }
            
            fclose($output);
            exit;
            
        } else {
            // JSON-Export
            $exportData = [
                'klasse' => $klasse,
                'schueler' => $schueler,
                'export_datum' => date('Y-m-d H:i:s'),
                'schuljahr' => $schuljahr
            ];
            
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="klasse_' . $klasse['name'] . '_' . date('Y-m-d') . '.json"');
            
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Fehler in exportKlasse: " . $e->getMessage());
        sendError('Fehler beim Exportieren der Klasse');
    }
}

/**
 * Sortierungseinstellungen speichern
 */
function saveSortingSettings() {
    global $conn, $lehrerId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('Keine Daten empfangen');
    }
    
    try {
        $sortingJson = json_encode($input);
        
        // Speichere oder aktualisiere Einstellung
        $stmt = $conn->prepare("
            INSERT INTO lehrer_einstellungen (lehrer_id, standard_gewichtung) 
            VALUES (:lehrer_id, :settings)
            ON DUPLICATE KEY UPDATE 
                standard_gewichtung = VALUES(standard_gewichtung),
                updated_at = NOW()
        ");
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':settings' => $sortingJson
        ]);
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        error_log("Fehler in saveSortingSettings: " . $e->getMessage());
        sendError('Fehler beim Speichern der Sortierungseinstellungen');
    }
}

/**
 * Sortierungseinstellungen abrufen
 */
function getSortingSettings() {
    global $conn, $lehrerId;
    
    try {
        $sorting = getSortingFromDB();
        echo json_encode(['sorting' => $sorting]);
        
    } catch (PDOException $e) {
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

/**
 * Hilfsfunktion: Sortierung aus DB holen
 */
function getSortingFromDB() {
    global $conn, $lehrerId;
    
    $stmt = $conn->prepare("
        SELECT standard_gewichtung 
        FROM lehrer_einstellungen 
        WHERE lehrer_id = :lehrer_id
    ");
    $stmt->execute([':lehrer_id' => $lehrerId]);
    $result = $stmt->fetch();
    
    if ($result && $result['standard_gewichtung']) {
        $sorting = json_decode($result['standard_gewichtung'], true);
        if (isset($sorting['primary'])) {
            return $sorting;
        }
    }
    
    // Standard-Sortierung
    return [
        'primary' => 'nachname',
        'secondary' => 'vorname',
        'tertiary' => 'geschlecht'
    ];
}

/**
 * Hilfsfunktion: ORDER BY Klausel erstellen
 */
function buildOrderByClause($sorting) {
    $validFields = ['nachname', 'vorname', 'geschlecht', 'geburtsdatum'];
    $orderBy = [];
    
    foreach (['primary', 'secondary', 'tertiary'] as $level) {
        if (isset($sorting[$level]) && in_array($sorting[$level], $validFields)) {
            $orderBy[] = $sorting[$level];
        }
    }
    
    return empty($orderBy) ? 'nachname, vorname' : implode(', ', $orderBy);
}
?>