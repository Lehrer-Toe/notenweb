<?php
/**
 * API für Notenverwaltung - MySQL Version
 * Unterstützt Noten, Hausaufgaben, Plus/Minus-System
 */

session_start();
require_once '../config/database.php';

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Prüfe Session
if (!isset($_SESSION['user_id'])) {
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
        'data' => $data
    ]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// Route Actions
switch ($action) {
    // === NOTEN ===
    case 'list_noten':
        listNoten();
        break;
        
    case 'get_note':
        getNote($_GET['id'] ?? 0);
        break;
        
    case 'create_note':
        createNote();
        break;
        
    case 'update_note':
        updateNote($_GET['id'] ?? 0);
        break;
        
    case 'delete_note':
        deleteNote($_GET['id'] ?? 0);
        break;
        
    case 'bulk_create_noten':
        bulkCreateNoten();
        break;
        
    // === HAUSAUFGABEN ===
    case 'list_hausaufgaben':
        listHausaufgaben();
        break;
        
    case 'toggle_hausaufgabe':
        toggleHausaufgabe();
        break;
        
    case 'hausaufgaben_statistik':
        getHausaufgabenStatistik();
        break;
        
    // === PLUS/MINUS ===
    case 'list_plusminus':
        listPlusMinus();
        break;
        
    case 'add_plusminus':
        addPlusMinus();
        break;
        
    case 'remove_plusminus':
        removePlusMinus($_GET['id'] ?? 0);
        break;
        
    // === AUSWERTUNGEN ===
    case 'notentabelle':
        getNotentabelle();
        break;
        
    case 'schueler_noten':
        getSchuelerNoten($_GET['schueler_id'] ?? 0);
        break;
        
    case 'klassen_durchschnitt':
        getKlassenDurchschnitt($_GET['klasse_id'] ?? 0);
        break;
        
    case 'zeugnisnoten':
        getZeugnisnoten($_GET['klasse_id'] ?? 0);
        break;
        
    case 'notenentwicklung':
        getNotenentwicklung($_GET['schueler_id'] ?? 0);
        break;
        
    default:
        sendError('Ungültige Aktion');
}

// ============================================
// NOTEN-FUNKTIONEN
// ============================================

/**
 * Liste Noten nach verschiedenen Filtern
 */
function listNoten() {
    global $conn, $lehrerId, $schuljahr;
    
    // Filter aus Query-Parametern
    $klasseId = $_GET['klasse_id'] ?? null;
    $fachId = $_GET['fach_id'] ?? null;
    $schuelerId = $_GET['schueler_id'] ?? null;
    $kategorieId = $_GET['kategorie_id'] ?? null;
    $vonDatum = $_GET['von_datum'] ?? null;
    $bisDatum = $_GET['bis_datum'] ?? null;
    
    try {
        $sql = "
            SELECT 
                n.*,
                s.nachname,
                s.vorname,
                f.name as fach_name,
                f.farbe as fach_farbe,
                k.name as kategorie_name,
                kl.name as klasse_name,
                u.email as erstellt_von
            FROM noten n
            JOIN schueler s ON n.schueler_id = s.id
            JOIN faecher f ON n.fach_id = f.id
            JOIN kategorien k ON n.kategorie_id = k.id
            JOIN klassen kl ON s.klasse_id = kl.id
            LEFT JOIN users u ON n.created_by = u.id
            WHERE f.lehrer_id = :lehrer_id 
                AND f.schuljahr = :schuljahr
                AND n.ist_archiviert = 0
        ";
        
        $params = [
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ];
        
        // Zusätzliche Filter
        if ($klasseId) {
            $sql .= " AND kl.id = :klasse_id";
            $params[':klasse_id'] = $klasseId;
        }
        if ($fachId) {
            $sql .= " AND n.fach_id = :fach_id";
            $params[':fach_id'] = $fachId;
        }
        if ($schuelerId) {
            $sql .= " AND n.schueler_id = :schueler_id";
            $params[':schueler_id'] = $schuelerId;
        }
        if ($kategorieId) {
            $sql .= " AND n.kategorie_id = :kategorie_id";
            $params[':kategorie_id'] = $kategorieId;
        }
        if ($vonDatum) {
            $sql .= " AND n.datum >= :von_datum";
            $params[':von_datum'] = $vonDatum;
        }
        if ($bisDatum) {
            $sql .= " AND n.datum <= :bis_datum";
            $params[':bis_datum'] = $bisDatum;
        }
        
        $sql .= " ORDER BY n.datum DESC, s.nachname, s.vorname";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $noten = $stmt->fetchAll();
        sendResponse($noten);
        
    } catch (PDOException $e) {
        error_log("Fehler in listNoten: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Noten');
    }
}

/**
 * Einzelne Note abrufen
 */
function getNote($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Noten-ID');
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT n.*, s.nachname, s.vorname, f.name as fach_name, k.name as kategorie_name
            FROM noten n
            JOIN schueler s ON n.schueler_id = s.id
            JOIN faecher f ON n.fach_id = f.id
            JOIN kategorien k ON n.kategorie_id = k.id
            WHERE n.id = :id AND f.lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        $note = $stmt->fetch();
        
        if (!$note) {
            sendError('Note nicht gefunden', 404);
        }
        
        sendResponse($note);
        
    } catch (PDOException $e) {
        error_log("Fehler in getNote: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Note');
    }
}

/**
 * Neue Note erstellen
 */
function createNote() {
    global $conn, $lehrerId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validierung
    if (!isset($input['schueler_id']) || !isset($input['fach_id']) || 
        !isset($input['kategorie_id']) || !isset($input['datum'])) {
        sendError('Pflichtfelder fehlen');
    }
    
    // Entweder note ODER punkte_erreicht/punkte_max
    if (!isset($input['note']) && (!isset($input['punkte_erreicht']) || !isset($input['punkte_max']))) {
        sendError('Note oder Punkte müssen angegeben werden');
    }
    
    try {
        // Prüfe Berechtigung (Fach gehört zum Lehrer)
        $stmt = $conn->prepare("
            SELECT id FROM faecher 
            WHERE id = :fach_id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':fach_id' => $input['fach_id'],
            ':lehrer_id' => $lehrerId
        ]);
        
        if (!$stmt->fetch()) {
            sendError('Keine Berechtigung für dieses Fach', 403);
        }
        
        // Berechne Note aus Punkten wenn nötig
        $note = $input['note'] ?? null;
        if (!$note && isset($input['punkte_erreicht']) && isset($input['punkte_max'])) {
            $prozent = ($input['punkte_erreicht'] / $input['punkte_max']) * 100;
            $note = calculateNoteFromProzent($prozent);
        }
        
        // Erstelle Note
        $stmt = $conn->prepare("
            INSERT INTO noten (
                schueler_id, fach_id, kategorie_id, note, 
                punkte_erreicht, punkte_max, typ, datum, 
                bemerkung, created_by
            ) VALUES (
                :schueler_id, :fach_id, :kategorie_id, :note,
                :punkte_erreicht, :punkte_max, :typ, :datum,
                :bemerkung, :created_by
            )
        ");
        
        $stmt->execute([
            ':schueler_id' => $input['schueler_id'],
            ':fach_id' => $input['fach_id'],
            ':kategorie_id' => $input['kategorie_id'],
            ':note' => $note,
            ':punkte_erreicht' => $input['punkte_erreicht'] ?? null,
            ':punkte_max' => $input['punkte_max'] ?? null,
            ':typ' => $input['typ'] ?? 'schriftlich',
            ':datum' => $input['datum'],
            ':bemerkung' => $input['bemerkung'] ?? null,
            ':created_by' => $lehrerId
        ]);
        
        $noteId = $conn->lastInsertId();
        
        sendResponse(['id' => $noteId], true, 'Note erfolgreich erstellt');
        
    } catch (PDOException $e) {
        error_log("Fehler in createNote: " . $e->getMessage());
        sendError('Fehler beim Erstellen der Note');
    }
}

/**
 * Note aktualisieren
 */
function updateNote($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Noten-ID');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Prüfe Berechtigung
        $stmt = $conn->prepare("
            SELECT n.id 
            FROM noten n
            JOIN faecher f ON n.fach_id = f.id
            WHERE n.id = :id AND f.lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        if (!$stmt->fetch()) {
            sendError('Note nicht gefunden oder keine Berechtigung', 404);
        }
        
        // Update Note
        $updateFields = [];
        $params = [':id' => $id];
        
        if (isset($input['note'])) {
            $updateFields[] = "note = :note";
            $params[':note'] = $input['note'];
        }
        if (isset($input['punkte_erreicht'])) {
            $updateFields[] = "punkte_erreicht = :punkte_erreicht";
            $params[':punkte_erreicht'] = $input['punkte_erreicht'];
        }
        if (isset($input['punkte_max'])) {
            $updateFields[] = "punkte_max = :punkte_max";
            $params[':punkte_max'] = $input['punkte_max'];
        }
        if (isset($input['datum'])) {
            $updateFields[] = "datum = :datum";
            $params[':datum'] = $input['datum'];
        }
        if (isset($input['bemerkung'])) {
            $updateFields[] = "bemerkung = :bemerkung";
            $params[':bemerkung'] = $input['bemerkung'];
        }
        if (isset($input['typ'])) {
            $updateFields[] = "typ = :typ";
            $params[':typ'] = $input['typ'];
        }
        
        if (empty($updateFields)) {
            sendError('Keine Änderungen angegeben');
        }
        
        $sql = "UPDATE noten SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        sendResponse(null, true, 'Note erfolgreich aktualisiert');
        
    } catch (PDOException $e) {
        error_log("Fehler in updateNote: " . $e->getMessage());
        sendError('Fehler beim Aktualisieren der Note');
    }
}

/**
 * Note löschen
 */
function deleteNote($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige Noten-ID');
    }
    
    try {
        // Prüfe Berechtigung und lösche
        $stmt = $conn->prepare("
            DELETE n FROM noten n
            JOIN faecher f ON n.fach_id = f.id
            WHERE n.id = :id AND f.lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        if ($stmt->rowCount() === 0) {
            sendError('Note nicht gefunden oder keine Berechtigung', 404);
        }
        
        sendResponse(null, true, 'Note erfolgreich gelöscht');
        
    } catch (PDOException $e) {
        error_log("Fehler in deleteNote: " . $e->getMessage());
        sendError('Fehler beim Löschen der Note');
    }
}

/**
 * Masseneingabe von Noten (z.B. für Klassenarbeit)
 */
function bulkCreateNoten() {
    global $conn, $lehrerId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['fach_id']) || !isset($input['kategorie_id']) || 
        !isset($input['datum']) || !isset($input['noten']) || !is_array($input['noten'])) {
        sendError('Pflichtfelder fehlen');
    }
    
    try {
        $conn->beginTransaction();
        
        // Prüfe Berechtigung
        $stmt = $conn->prepare("
            SELECT id FROM faecher 
            WHERE id = :fach_id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':fach_id' => $input['fach_id'],
            ':lehrer_id' => $lehrerId
        ]);
        
        if (!$stmt->fetch()) {
            $conn->rollBack();
            sendError('Keine Berechtigung für dieses Fach', 403);
        }
        
        // Erstelle Noten
        $stmt = $conn->prepare("
            INSERT INTO noten (
                schueler_id, fach_id, kategorie_id, note, 
                punkte_erreicht, punkte_max, typ, datum, 
                bemerkung, created_by
            ) VALUES (
                :schueler_id, :fach_id, :kategorie_id, :note,
                :punkte_erreicht, :punkte_max, :typ, :datum,
                :bemerkung, :created_by
            )
        ");
        
        $erstellteNoten = 0;
        foreach ($input['noten'] as $notenEintrag) {
            if (!isset($notenEintrag['schueler_id'])) continue;
            
            // Berechne Note aus Punkten wenn nötig
            $note = $notenEintrag['note'] ?? null;
            if (!$note && isset($notenEintrag['punkte_erreicht']) && $input['punkte_max']) {
                $prozent = ($notenEintrag['punkte_erreicht'] / $input['punkte_max']) * 100;
                $note = calculateNoteFromProzent($prozent);
            }
            
            if ($note !== null) {
                $stmt->execute([
                    ':schueler_id' => $notenEintrag['schueler_id'],
                    ':fach_id' => $input['fach_id'],
                    ':kategorie_id' => $input['kategorie_id'],
                    ':note' => $note,
                    ':punkte_erreicht' => $notenEintrag['punkte_erreicht'] ?? null,
                    ':punkte_max' => $input['punkte_max'] ?? null,
                    ':typ' => $input['typ'] ?? 'schriftlich',
                    ':datum' => $input['datum'],
                    ':bemerkung' => $notenEintrag['bemerkung'] ?? null,
                    ':created_by' => $lehrerId
                ]);
                $erstellteNoten++;
            }
        }
        
        $conn->commit();
        
        sendResponse([
            'erstellt' => $erstellteNoten
        ], true, "{$erstellteNoten} Noten erfolgreich erstellt");
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Fehler in bulkCreateNoten: " . $e->getMessage());
        sendError('Fehler beim Erstellen der Noten');
    }
}

// ============================================
// HAUSAUFGABEN-FUNKTIONEN
// ============================================

/**
 * Liste Hausaufgaben-Einträge
 */
function listHausaufgaben() {
    global $conn, $lehrerId, $schuljahr;
    
    $klasseId = $_GET['klasse_id'] ?? null;
    $fachId = $_GET['fach_id'] ?? null;
    $datum = $_GET['datum'] ?? date('Y-m-d');
    
    try {
        $sql = "
            SELECT 
                h.*,
                s.nachname,
                s.vorname,
                f.name as fach_name,
                k.name as klasse_name
            FROM hausaufgaben h
            JOIN schueler s ON h.schueler_id = s.id
            JOIN faecher f ON h.fach_id = f.id
            JOIN klassen k ON s.klasse_id = k.id
            WHERE f.lehrer_id = :lehrer_id 
                AND f.schuljahr = :schuljahr
                AND h.datum = :datum
        ";
        
        $params = [
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr,
            ':datum' => $datum
        ];
        
        if ($klasseId) {
            $sql .= " AND k.id = :klasse_id";
            $params[':klasse_id'] = $klasseId;
        }
        if ($fachId) {
            $sql .= " AND h.fach_id = :fach_id";
            $params[':fach_id'] = $fachId;
        }
        
        $sql .= " ORDER BY s.nachname, s.vorname";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $hausaufgaben = $stmt->fetchAll();
        sendResponse($hausaufgaben);
        
    } catch (PDOException $e) {
        error_log("Fehler in listHausaufgaben: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Hausaufgaben');
    }
}

/**
 * Hausaufgaben-Status toggle
 */
function toggleHausaufgabe() {
    global $conn, $lehrerId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['schueler_id']) || !isset($input['fach_id']) || !isset($input['datum'])) {
        sendError('Pflichtfelder fehlen');
    }
    
    try {
        // Prüfe ob Eintrag existiert
        $stmt = $conn->prepare("
            SELECT h.id, h.status 
            FROM hausaufgaben h
            JOIN faecher f ON h.fach_id = f.id
            WHERE h.schueler_id = :schueler_id 
                AND h.fach_id = :fach_id
                AND h.datum = :datum
                AND f.lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':schueler_id' => $input['schueler_id'],
            ':fach_id' => $input['fach_id'],
            ':datum' => $input['datum'],
            ':lehrer_id' => $lehrerId
        ]);
        
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Toggle oder lösche
            if ($existing['status'] === 'vergessen') {
                // Lösche Eintrag (= erledigt)
                $stmt = $conn->prepare("DELETE FROM hausaufgaben WHERE id = :id");
                $stmt->execute([':id' => $existing['id']]);
                $newStatus = 'erledigt';
            } else {
                // Sollte normalerweise nicht vorkommen
                $newStatus = 'vergessen';
            }
        } else {
            // Erstelle neuen Eintrag (vergessen)
            $stmt = $conn->prepare("
                INSERT INTO hausaufgaben (schueler_id, fach_id, datum, status, bemerkung)
                VALUES (:schueler_id, :fach_id, :datum, 'vergessen', :bemerkung)
            ");
            $stmt->execute([
                ':schueler_id' => $input['schueler_id'],
                ':fach_id' => $input['fach_id'],
                ':datum' => $input['datum'],
                ':bemerkung' => $input['bemerkung'] ?? null
            ]);
            $newStatus = 'vergessen';
        }
        
        sendResponse(['status' => $newStatus], true, 'Status aktualisiert');
        
    } catch (PDOException $e) {
        error_log("Fehler in toggleHausaufgabe: " . $e->getMessage());
        sendError('Fehler beim Aktualisieren der Hausaufgabe');
    }
}

/**
 * Hausaufgaben-Statistik
 */
function getHausaufgabenStatistik() {
    global $conn, $lehrerId, $schuljahr;
    
    $schuelerId = $_GET['schueler_id'] ?? null;
    $klasseId = $_GET['klasse_id'] ?? null;
    
    try {
        if ($schuelerId) {
            // Statistik für einzelnen Schüler
            $stmt = $conn->prepare("
                SELECT 
                    f.name as fach,
                    COUNT(*) as vergessen_gesamt,
                    COUNT(CASE WHEN h.datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as vergessen_30_tage
                FROM hausaufgaben h
                JOIN faecher f ON h.fach_id = f.id
                WHERE h.schueler_id = :schueler_id
                    AND h.status = 'vergessen'
                    AND f.schuljahr = :schuljahr
                GROUP BY f.id
                ORDER BY vergessen_gesamt DESC
            ");
            $stmt->execute([
                ':schueler_id' => $schuelerId,
                ':schuljahr' => $schuljahr
            ]);
            
        } else if ($klasseId) {
            // Statistik für ganze Klasse
            $stmt = $conn->prepare("
                SELECT 
                    s.nachname,
                    s.vorname,
                    COUNT(*) as vergessen_gesamt,
                    COUNT(CASE WHEN h.datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as vergessen_30_tage
                FROM hausaufgaben h
                JOIN schueler s ON h.schueler_id = s.id
                JOIN faecher f ON h.fach_id = f.id
                WHERE s.klasse_id = :klasse_id
                    AND h.status = 'vergessen'
                    AND f.schuljahr = :schuljahr
                GROUP BY s.id
                ORDER BY vergessen_gesamt DESC
            ");
            $stmt->execute([
                ':klasse_id' => $klasseId,
                ':schuljahr' => $schuljahr
            ]);
            
        } else {
            sendError('Schüler-ID oder Klassen-ID erforderlich');
        }
        
        $statistik = $stmt->fetchAll();
        sendResponse($statistik);
        
    } catch (PDOException $e) {
        error_log("Fehler in getHausaufgabenStatistik: " . $e->getMessage());
        sendError('Fehler beim Abrufen der Statistik');
    }
}

// ============================================
// PLUS/MINUS-FUNKTIONEN
// ============================================

/**
 * Liste Plus/Minus-Einträge
 */
function listPlusMinus() {
    global $conn, $lehrerId, $schuljahr;
    
    $klasseId = $_GET['klasse_id'] ?? null;
    $fachId = $_GET['fach_id'] ?? null;
    $schuelerId = $_GET['schueler_id'] ?? null;
    
    try {
        $sql = "
            SELECT 
                p.*,
                s.nachname,
                s.vorname,
                f.name as fach_name,
                k.name as klasse_name
            FROM plusminus p
            JOIN schueler s ON p.schueler_id = s.id
            JOIN faecher f ON p.fach_id = f.id
            JOIN klassen k ON s.klasse_id = k.id
            WHERE f.lehrer_id = :lehrer_id 
                AND f.schuljahr = :schuljahr
        ";
        
        $params = [
            ':lehrer_id' => $lehrerId,
            ':schuljahr' => $schuljahr
        ];
        
        if ($klasseId) {
            $sql .= " AND k.id = :klasse_id";
            $params[':klasse_id'] = $klasseId;
        }
        if ($fachId) {
            $sql .= " AND p.fach_id = :fach_id";
            $params[':fach_id'] = $fachId;
        }
        if ($schuelerId) {
            $sql .= " AND p.schueler_id = :schueler_id";
            $params[':schueler_id'] = $schuelerId;
        }
        
        $sql .= " ORDER BY p.datum DESC, s.nachname, s.vorname";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $eintraege = $stmt->fetchAll();
        
        // Berechne Summen pro Schüler
        if (!$schuelerId) {
            $summeSql = "
                SELECT 
                    s.id,
                    s.nachname,
                    s.vorname,
                    SUM(p.wert) as gesamt_punkte
                FROM schueler s
                LEFT JOIN plusminus p ON p.schueler_id = s.id
                LEFT JOIN faecher f ON p.fach_id = f.id
                WHERE f.lehrer_id = :lehrer_id 
                    AND f.schuljahr = :schuljahr
            ";
            
            if ($klasseId) {
                $summeSql .= " AND s.klasse_id = :klasse_id";
            }
            if ($fachId) {
                $summeSql .= " AND p.fach_id = :fach_id";
            }
            
            $summeSql .= " GROUP BY s.id ORDER BY gesamt_punkte DESC";
            
            $stmt = $conn->prepare($summeSql);
            $stmt->execute($params);
            $summen = $stmt->fetchAll();
        } else {
            $summen = [];
        }
        
        sendResponse([
            'eintraege' => $eintraege,
            'summen' => $summen
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler in listPlusMinus: " . $e->getMessage());
        sendError('Datenbankfehler beim Abrufen der Plus/Minus-Einträge');
    }
}

/**
 * Plus/Minus hinzufügen
 */
function addPlusMinus() {
    global $conn, $lehrerId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['schueler_id']) || !isset($input['fach_id']) || 
        !isset($input['wert']) || !isset($input['datum'])) {
        sendError('Pflichtfelder fehlen');
    }
    
    // Validiere Wert (-3 bis +3)
    $wert = intval($input['wert']);
    if ($wert < -3 || $wert > 3) {
        sendError('Wert muss zwischen -3 und +3 liegen');
    }
    
    try {
        // Prüfe Berechtigung
        $stmt = $conn->prepare("
            SELECT id FROM faecher 
            WHERE id = :fach_id AND lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':fach_id' => $input['fach_id'],
            ':lehrer_id' => $lehrerId
        ]);
        
        if (!$stmt->fetch()) {
            sendError('Keine Berechtigung für dieses Fach', 403);
        }
        
        // Erstelle Eintrag
        $stmt = $conn->prepare("
            INSERT INTO plusminus (schueler_id, fach_id, datum, wert, bemerkung)
            VALUES (:schueler_id, :fach_id, :datum, :wert, :bemerkung)
        ");
        $stmt->execute([
            ':schueler_id' => $input['schueler_id'],
            ':fach_id' => $input['fach_id'],
            ':datum' => $input['datum'],
            ':wert' => $wert,
            ':bemerkung' => $input['bemerkung'] ?? null
        ]);
        
        $id = $conn->lastInsertId();
        
        sendResponse(['id' => $id], true, 'Plus/Minus erfolgreich hinzugefügt');
        
    } catch (PDOException $e) {
        error_log("Fehler in addPlusMinus: " . $e->getMessage());
        sendError('Fehler beim Hinzufügen von Plus/Minus');
    }
}

/**
 * Plus/Minus entfernen
 */
function removePlusMinus($id) {
    global $conn, $lehrerId;
    
    if (!$id || !is_numeric($id)) {
        sendError('Ungültige ID');
    }
    
    try {
        // Prüfe Berechtigung und lösche
        $stmt = $conn->prepare("
            DELETE p FROM plusminus p
            JOIN faecher f ON p.fach_id = f.id
            WHERE p.id = :id AND f.lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':lehrer_id' => $lehrerId
        ]);
        
        if ($stmt->rowCount() === 0) {
            sendError('Eintrag nicht gefunden oder keine Berechtigung', 404);
        }
        
        sendResponse(null, true, 'Plus/Minus erfolgreich entfernt');
        
    } catch (PDOException $e) {
        error_log("Fehler in removePlusMinus: " . $e->getMessage());
        sendError('Fehler beim Entfernen von Plus/Minus');
    }
}

// ============================================
// AUSWERTUNGS-FUNKTIONEN
// ============================================

/**
 * Notentabelle für eine Klasse
 */
function getNotentabelle() {
    global $conn, $lehrerId, $schuljahr;
    
    $klasseId = $_GET['klasse_id'] ?? null;
    $fachId = $_GET['fach_id'] ?? null;
    
    if (!$klasseId || !$fachId) {
        sendError('Klassen-ID und Fach-ID erforderlich');
    }
    
    try {
        // Hole alle Schüler der Klasse
        $stmt = $conn->prepare("
            SELECT id, nachname, vorname, geschlecht
            FROM schueler
            WHERE klasse_id = :klasse_id AND ist_aktiv = 1
            ORDER BY nachname, vorname
        ");
        $stmt->execute([':klasse_id' => $klasseId]);
        $schueler = $stmt->fetchAll();
        
        // Hole alle Kategorien des Fachs
        $stmt = $conn->prepare("
            SELECT id, name, gewichtung
            FROM kategorien
            WHERE fach_id = :fach_id AND ist_aktiv = 1
            ORDER BY reihenfolge, name
        ");
        $stmt->execute([':fach_id' => $fachId]);
        $kategorien = $stmt->fetchAll();
        
        // Hole alle Noten
        $stmt = $conn->prepare("
            SELECT 
                n.*,
                s.nachname,
                s.vorname
            FROM noten n
            JOIN schueler s ON n.schueler_id = s.id
            WHERE s.klasse_id = :klasse_id
                AND n.fach_id = :fach_id
                AND n.ist_archiviert = 0
            ORDER BY n.datum, n.id
        ");
        $stmt->execute([
            ':klasse_id' => $klasseId,
            ':fach_id' => $fachId
        ]);
        $alleNoten = $stmt->fetchAll();
        
        // Strukturiere Noten nach Schüler und Kategorie
        $notenMatrix = [];
        foreach ($schueler as $s) {
            $notenMatrix[$s['id']] = [
                'schueler' => $s,
                'kategorien' => [],
                'durchschnitt' => 0,
                'note_gesamt' => 0
            ];
            
            foreach ($kategorien as $k) {
                $notenMatrix[$s['id']]['kategorien'][$k['id']] = [
                    'noten' => [],
                    'durchschnitt' => 0
                ];
            }
        }
        
        // Fülle Matrix mit Noten
        foreach ($alleNoten as $note) {
            if (isset($notenMatrix[$note['schueler_id']])) {
                $notenMatrix[$note['schueler_id']]['kategorien'][$note['kategorie_id']]['noten'][] = $note;
            }
        }
        
        // Berechne Durchschnitte
        foreach ($notenMatrix as $sId => &$sData) {
            $gewichteterDurchschnitt = 0;
            $gesamtGewicht = 0;
            
            foreach ($kategorien as $k) {
                $kNoten = $sData['kategorien'][$k['id']]['noten'];
                if (!empty($kNoten)) {
                    $summe = array_sum(array_column($kNoten, 'note'));
                    $durchschnitt = $summe / count($kNoten);
                    $sData['kategorien'][$k['id']]['durchschnitt'] = round($durchschnitt, 2);
                    
                    $gewichteterDurchschnitt += $durchschnitt * $k['gewichtung'];
                    $gesamtGewicht += $k['gewichtung'];
                }
            }
            
            if ($gesamtGewicht > 0) {
                $sData['durchschnitt'] = round($gewichteterDurchschnitt / $gesamtGewicht, 2);
                $sData['note_gesamt'] = round($sData['durchschnitt']);
            }
        }
        
        sendResponse([
            'kategorien' => $kategorien,
            'notentabelle' => array_values($notenMatrix),
            'klasse_id' => $klasseId,
            'fach_id' => $fachId,
            'schuljahr' => $schuljahr
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler in getNotentabelle: " . $e->getMessage());
        sendError('Fehler beim Erstellen der Notentabelle');
    }
}

/**
 * Alle Noten eines Schülers
 */
function getSchuelerNoten($schuelerId) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$schuelerId || !is_numeric($schuelerId)) {
        sendError('Ungültige Schüler-ID');
    }
    
    try {
        // Hole Schülerdaten
        $stmt = $conn->prepare("
            SELECT s.*, k.name as klasse_name
            FROM schueler s
            JOIN klassen k ON s.klasse_id = k.id
            WHERE s.id = :schueler_id AND k.lehrer_id = :lehrer_id
        ");
        $stmt->execute([
            ':schueler_id' => $schuelerId,
            ':lehrer_id' => $lehrerId
        ]);
        
        $schueler = $stmt->fetch();
        if (!$schueler) {
            sendError('Schüler nicht gefunden', 404);
        }
        
        // Hole Noten gruppiert nach Fach
        $stmt = $conn->prepare("
            SELECT 
                f.name as fach_name,
                f.farbe as fach_farbe,
                k.name as kategorie_name,
                n.*
            FROM noten n
            JOIN faecher f ON n.fach_id = f.id
            JOIN kategorien k ON n.kategorie_id = k.id
            WHERE n.schueler_id = :schueler_id
                AND f.schuljahr = :schuljahr
                AND n.ist_archiviert = 0
            ORDER BY f.name, n.datum DESC
        ");
        $stmt->execute([
            ':schueler_id' => $schuelerId,
            ':schuljahr' => $schuljahr
        ]);
        
        $noten = $stmt->fetchAll();
        
        // Gruppiere nach Fach
        $notenNachFach = [];
        foreach ($noten as $note) {
            $fach = $note['fach_name'];
            if (!isset($notenNachFach[$fach])) {
                $notenNachFach[$fach] = [
                    'fach_farbe' => $note['fach_farbe'],
                    'noten' => [],
                    'durchschnitt' => 0
                ];
            }
            $notenNachFach[$fach]['noten'][] = $note;
        }
        
        // Berechne Durchschnitte
        foreach ($notenNachFach as $fach => &$fachData) {
            $summe = array_sum(array_column($fachData['noten'], 'note'));
            $anzahl = count($fachData['noten']);
            if ($anzahl > 0) {
                $fachData['durchschnitt'] = round($summe / $anzahl, 2);
            }
        }
        
        sendResponse([
            'schueler' => $schueler,
            'noten_nach_fach' => $notenNachFach,
            'gesamt_durchschnitt' => calculateGesamtDurchschnitt($notenNachFach)
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler in getSchuelerNoten: " . $e->getMessage());
        sendError('Fehler beim Abrufen der Schülernoten');
    }
}

/**
 * Klassendurchschnitt für ein Fach
 */
function getKlassenDurchschnitt($klasseId) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$klasseId || !is_numeric($klasseId)) {
        sendError('Ungültige Klassen-ID');
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                f.name as fach_name,
                AVG(n.note) as durchschnitt,
                COUNT(DISTINCT n.schueler_id) as schueler_mit_noten,
                COUNT(n.id) as anzahl_noten,
                MIN(n.note) as beste_note,
                MAX(n.note) as schlechteste_note
            FROM faecher f
            JOIN klassen_faecher kf ON kf.fach_id = f.id
            LEFT JOIN noten n ON n.fach_id = f.id
            LEFT JOIN schueler s ON n.schueler_id = s.id
            WHERE kf.klasse_id = :klasse_id
                AND f.schuljahr = :schuljahr
                AND (n.ist_archiviert = 0 OR n.ist_archiviert IS NULL)
                AND (s.klasse_id = :klasse_id OR s.klasse_id IS NULL)
            GROUP BY f.id
            ORDER BY f.name
        ");
        $stmt->execute([
            ':klasse_id' => $klasseId,
            ':schuljahr' => $schuljahr
        ]);
        
        $durchschnitte = $stmt->fetchAll();
        
        sendResponse($durchschnitte);
        
    } catch (PDOException $e) {
        error_log("Fehler in getKlassenDurchschnitt: " . $e->getMessage());
        sendError('Fehler beim Berechnen des Klassendurchschnitts');
    }
}

/**
 * Zeugnisnoten berechnen
 */
function getZeugnisnoten($klasseId) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$klasseId || !is_numeric($klasseId)) {
        sendError('Ungültige Klassen-ID');
    }
    
    $halbjahr = $_GET['halbjahr'] ?? 'gesamt';
    
    try {
        // Bestimme Datumsgrenzen
        $vonDatum = null;
        $bisDatum = null;
        
        if ($halbjahr === '1') {
            $jahre = explode('/', $schuljahr);
            $vonDatum = $jahre[0] . '-08-01';
            $bisDatum = ($jahre[0] + 1) . '-01-31';
        } elseif ($halbjahr === '2') {
            $jahre = explode('/', $schuljahr);
            $vonDatum = ($jahre[0] + 1) . '-02-01';
            $bisDatum = $jahre[1] . '-07-31';
        }
        
        // Hole Zeugnisnoten
        $sql = "
            SELECT 
                s.id as schueler_id,
                s.nachname,
                s.vorname,
                f.id as fach_id,
                f.name as fach_name,
                ROUND(AVG(n.note * k.gewichtung) / AVG(k.gewichtung), 0) as zeugnisnote,
                COUNT(n.id) as anzahl_noten
            FROM schueler s
            CROSS JOIN faecher f
            JOIN klassen_faecher kf ON kf.fach_id = f.id AND kf.klasse_id = s.klasse_id
            LEFT JOIN noten n ON n.schueler_id = s.id AND n.fach_id = f.id AND n.ist_archiviert = 0
            LEFT JOIN kategorien k ON n.kategorie_id = k.id
            WHERE s.klasse_id = :klasse_id
                AND s.ist_aktiv = 1
                AND f.schuljahr = :schuljahr
        ";
        
        $params = [
            ':klasse_id' => $klasseId,
            ':schuljahr' => $schuljahr
        ];
        
        if ($vonDatum && $bisDatum) {
            $sql .= " AND (n.datum BETWEEN :von_datum AND :bis_datum OR n.datum IS NULL)";
            $params[':von_datum'] = $vonDatum;
            $params[':bis_datum'] = $bisDatum;
        }
        
        $sql .= " GROUP BY s.id, f.id ORDER BY s.nachname, s.vorname, f.name";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $zeugnisnoten = $stmt->fetchAll();
        
        // Strukturiere Daten für bessere Übersicht
        $zeugnisMatrix = [];
        foreach ($zeugnisnoten as $eintrag) {
            $sId = $eintrag['schueler_id'];
            if (!isset($zeugnisMatrix[$sId])) {
                $zeugnisMatrix[$sId] = [
                    'schueler_id' => $sId,
                    'nachname' => $eintrag['nachname'],
                    'vorname' => $eintrag['vorname'],
                    'faecher' => []
                ];
            }
            
            $zeugnisMatrix[$sId]['faecher'][$eintrag['fach_name']] = [
                'note' => $eintrag['zeugnisnote'],
                'anzahl_noten' => $eintrag['anzahl_noten']
            ];
        }
        
        sendResponse([
            'zeugnisnoten' => array_values($zeugnisMatrix),
            'halbjahr' => $halbjahr,
            'schuljahr' => $schuljahr
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler in getZeugnisnoten: " . $e->getMessage());
        sendError('Fehler beim Berechnen der Zeugnisnoten');
    }
}

/**
 * Notenentwicklung eines Schülers
 */
function getNotenentwicklung($schuelerId) {
    global $conn, $lehrerId, $schuljahr;
    
    if (!$schuelerId || !is_numeric($schuelerId)) {
        sendError('Ungültige Schüler-ID');
    }
    
    $fachId = $_GET['fach_id'] ?? null;
    
    try {
        $sql = "
            SELECT 
                n.datum,
                n.note,
                f.name as fach_name,
                k.name as kategorie_name,
                n.bemerkung
            FROM noten n
            JOIN faecher f ON n.fach_id = f.id
            JOIN kategorien k ON n.kategorie_id = k.id
            WHERE n.schueler_id = :schueler_id
                AND f.schuljahr = :schuljahr
                AND n.ist_archiviert = 0
        ";
        
        $params = [
            ':schueler_id' => $schuelerId,
            ':schuljahr' => $schuljahr
        ];
        
        if ($fachId) {
            $sql .= " AND n.fach_id = :fach_id";
            $params[':fach_id'] = $fachId;
        }
        
        $sql .= " ORDER BY n.datum, n.id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $entwicklung = $stmt->fetchAll();
        
        // Berechne gleitenden Durchschnitt
        $gleitenderDurchschnitt = [];
        $fensterGroesse = 5;
        
        for ($i = 0; $i < count($entwicklung); $i++) {
            $start = max(0, $i - $fensterGroesse + 1);
            $ende = $i + 1;
            $fenster = array_slice($entwicklung, $start, $ende - $start);
            $durchschnitt = array_sum(array_column($fenster, 'note')) / count($fenster);
            
            $gleitenderDurchschnitt[] = [
                'datum' => $entwicklung[$i]['datum'],
                'durchschnitt' => round($durchschnitt, 2)
            ];
        }
        
        sendResponse([
            'entwicklung' => $entwicklung,
            'gleitender_durchschnitt' => $gleitenderDurchschnitt
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler in getNotenentwicklung: " . $e->getMessage());
        sendError('Fehler beim Abrufen der Notenentwicklung');
    }
}

// ============================================
// HILFSFUNKTIONEN
// ============================================

/**
 * Berechne Note aus Prozent
 */
function calculateNoteFromProzent($prozent) {
    if ($prozent >= 92) return 1.0;
    if ($prozent >= 81) return 2.0;
    if ($prozent >= 67) return 3.0;
    if ($prozent >= 50) return 4.0;
    if ($prozent >= 30) return 5.0;
    return 6.0;
}

/**
 * Berechne Gesamtdurchschnitt
 */
function calculateGesamtDurchschnitt($notenNachFach) {
    $summe = 0;
    $anzahl = 0;
    
    foreach ($notenNachFach as $fachData) {
        if ($fachData['durchschnitt'] > 0) {
            $summe += $fachData['durchschnitt'];
            $anzahl++;
        }
    }
    
    return $anzahl > 0 ? round($summe / $anzahl, 2) : 0;
}
?>