<?php
session_start();

// Prüfe ob Admin eingeloggt ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit;
}

// SQLite Datenbankverbindung
$userDbPath = __DIR__ . '/data/users.db';

try {
    $db = new SQLite3($userDbPath);
} catch(Exception $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Lehrer hinzufügen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_teacher'])) {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'teacher')");
        $stmt->bindValue(1, $email);
        $stmt->bindValue(2, $password);
        $result = $stmt->execute();
        
        if ($result) {
            // Erstelle Lehrerordner und Datenbank
            $username = explode('@', $email)[0];
            $userDir = __DIR__ . '/Lehrerdaten/' . $username;
            
            if (!file_exists($userDir)) {
                mkdir($userDir, 0777, true);
                mkdir($userDir . '/Archiv', 0777, true);
            }
            
            // Erstelle persönliche SQLite Datenbank für den neuen Lehrer
            $dbPath = $userDir . '/notendaten.db';
            if (!file_exists($dbPath)) {
                $teacherDb = new SQLite3($dbPath);
                
                // Erstelle alle notwendigen Tabellen
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS faecher (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    gewichtung INTEGER DEFAULT 1
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS kategorien (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    fach_id INTEGER,
                    name TEXT NOT NULL,
                    gewichtung INTEGER DEFAULT 1,
                    FOREIGN KEY (fach_id) REFERENCES faecher(id)
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS klassen (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    schuljahr TEXT NOT NULL
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS klassen_faecher (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    klasse_id INTEGER,
                    fach_id INTEGER,
                    FOREIGN KEY (klasse_id) REFERENCES klassen(id),
                    FOREIGN KEY (fach_id) REFERENCES faecher(id)
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS schueler (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nachname TEXT NOT NULL,
                    vorname TEXT NOT NULL,
                    geschlecht TEXT,
                    klasse_id INTEGER,
                    FOREIGN KEY (klasse_id) REFERENCES klassen(id)
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS noten (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    schueler_id INTEGER,
                    fach_id INTEGER,
                    kategorie_id INTEGER,
                    note REAL,
                    typ TEXT,
                    datum DATE,
                    bemerkung TEXT,
                    FOREIGN KEY (schueler_id) REFERENCES schueler(id),
                    FOREIGN KEY (fach_id) REFERENCES faecher(id),
                    FOREIGN KEY (kategorie_id) REFERENCES kategorien(id)
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS hausaufgaben (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    schueler_id INTEGER,
                    fach_id INTEGER,
                    datum DATE,
                    status TEXT,
                    bemerkung TEXT,
                    FOREIGN KEY (schueler_id) REFERENCES schueler(id),
                    FOREIGN KEY (fach_id) REFERENCES faecher(id)
                )");
                
                $teacherDb->exec("CREATE TABLE IF NOT EXISTS plusminus (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    schueler_id INTEGER,
                    fach_id INTEGER,
                    datum DATE,
                    wert INTEGER,
                    bemerkung TEXT,
                    FOREIGN KEY (schueler_id) REFERENCES schueler(id),
                    FOREIGN KEY (fach_id) REFERENCES faecher(id)
                )");
                
                $teacherDb->close();
            }
            
            $success = "Lehrer erfolgreich hinzugefügt!";
        } else {
            $error = "Fehler beim Hinzufügen des Lehrers.";
        }
    } catch(Exception $e) {
        $error = "Fehler: Diese E-Mail-Adresse existiert bereits.";
    }
}

// Lehrer löschen
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    
    // Hole E-Mail des zu löschenden Lehrers
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bindValue(1, $deleteId);
    $result = $stmt->execute();
    $teacher = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($teacher) {
        // Lösche Lehrerordner
        $username = explode('@', $teacher['email'])[0];
        $userDir = __DIR__ . '/Lehrerdaten/' . $username;
        
        // Rekursives Löschen des Ordners
        if (file_exists($userDir)) {
            function deleteDir($dir) {
                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    (is_dir("$dir/$file")) ? deleteDir("$dir/$file") : unlink("$dir/$file");
                }
                return rmdir($dir);
            }
            deleteDir($userDir);
        }
        
        // Lösche Lehrer aus Datenbank
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        $stmt->bindValue(1, $deleteId);
        $stmt->execute();
    }
    
    header('Location: admin.php');
    exit;
}

// Alle Lehrer laden
$result = $db->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY email");
$teachers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $teachers[] = $row;
}

// Statistiken
$teacherCount = count($teachers);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotenWeb Admin - Verwaltung</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: #667eea;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
        }
        
        h1 {
            color: #667eea;
            font-size: 28px;
        }
        
        .admin-badge {
            background: #f59e0b;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-add {
            padding: 12px 30px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            align-self: flex-end;
        }
        
        .btn-add:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .teachers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .teachers-table thead {
            background: #f3f4f6;
        }
        
        .teachers-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .teachers-table td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .teachers-table tr:hover {
            background: #f9fafb;
        }
        
        .btn-delete {
            padding: 6px 12px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .btn-logout {
            padding: 8px 20px;
            background: transparent;
            color: #666;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .info-notice {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-notice h4 {
            margin-bottom: 5px;
        }
        
        .info-notice p {
            margin: 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo-icon">N</div>
                <h1>NotenWeb Admin</h1>
                <span class="admin-badge">ADMINISTRATOR</span>
            </div>
            <button class="btn-logout" onclick="window.location.href='logout.php'">Abmelden</button>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="info-notice">
                <h4>💾 SQLite-Datenbanksystem</h4>
                <p>Jeder Lehrer erhält eine eigene SQLite-Datenbank im Ordner <code>Lehrerdaten/[benutzername]/</code>. 
                   Alle Daten werden lokal gespeichert - keine MySQL-Datenbank erforderlich!</p>
            </div>

            <!-- Statistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $teacherCount; ?></div>
                    <div class="stat-label">Registrierte Lehrer</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="stat-number">SQLite</div>
                    <div class="stat-label">Datenbank-System</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="stat-number">✓</div>
                    <div class="stat-label">System Aktiv</div>
                </div>
            </div>

            <!-- Neuen Lehrer hinzufügen -->
            <div class="section">
                <h2 class="section-title">Neuen Lehrer hinzufügen</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">E-Mail-Adresse</label>
                            <input type="email" id="email" name="email" required placeholder="lehrer@schule.de">
                        </div>
                        <div class="form-group">
                            <label for="password">Passwort</label>
                            <input type="password" id="password" name="password" required placeholder="Sicheres Passwort">
                        </div>
                        <button type="submit" name="add_teacher" class="btn-add">+ Lehrer hinzufügen</button>
                    </div>
                </form>
            </div>

            <!-- Lehrerliste -->
            <div class="section">
                <h2 class="section-title">Registrierte Lehrer</h2>
                <?php if (count($teachers) > 0): ?>
                    <table class="teachers-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>E-Mail-Adresse</th>
                                <th>Registriert am</th>
                                <th>Datenbank-Ordner</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td>#<?php echo $teacher['id']; ?></td>
                                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($teacher['created_at'])); ?></td>
                                    <td>
                                        <code>Lehrerdaten/<?php echo explode('@', $teacher['email'])[0]; ?>/</code>
                                    </td>
                                    <td>
                                        <button class="btn-delete" onclick="if(confirm('Lehrer und alle seine Daten wirklich löschen?')) window.location.href='admin.php?delete=<?php echo $teacher['id']; ?>'">
                                            Löschen
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">Noch keine Lehrer registriert.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$db->close();
?>