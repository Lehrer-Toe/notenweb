<?php
session_start();

// Hauptdatenbank für Benutzer (SQLite)
$userDbPath = __DIR__ . '/data/users.db';

// Erstelle Verzeichnis falls nicht vorhanden
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}

// Erstelle oder öffne Benutzer-Datenbank
try {
    $db = new SQLite3($userDbPath);
    
    // Erstelle Benutzertabelle falls nicht vorhanden
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'teacher',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Prüfe ob Standard-Benutzer existieren und füge sie hinzu
    $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
    
    $defaultUsers = [
        ['email' => 'thomas.toellner@gmx.de', 'password' => password_hash('sohran17', PASSWORD_DEFAULT), 'role' => 'teacher'],
        ['email' => 'i.koehler@gmx.de', 'password' => password_hash('123', PASSWORD_DEFAULT), 'role' => 'teacher'],
        ['email' => 'tilama@mail.de', 'password' => password_hash('W@ndermaus17', PASSWORD_DEFAULT), 'role' => 'admin']
    ];
    
    foreach($defaultUsers as $user) {
        $checkStmt->bindValue(1, $user['email']);
        $result = $checkStmt->execute();
        $row = $result->fetchArray();
        
        if ($row['count'] == 0) {
            $insertStmt = $db->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $insertStmt->bindValue(1, $user['email']);
            $insertStmt->bindValue(2, $user['password']);
            $insertStmt->bindValue(3, $user['role']);
            $insertStmt->execute();
        }
    }
    
} catch(Exception $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Login-Verarbeitung
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bindValue(1, $email);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Erstelle Lehrerordner und persönliche Datenbank wenn nicht vorhanden
        if ($user['role'] == 'teacher') {
            $username = explode('@', $email)[0];
            $userDir = __DIR__ . '/Lehrerdaten/' . $username;
            
            if (!file_exists($userDir)) {
                mkdir($userDir, 0777, true);
                mkdir($userDir . '/Archiv', 0777, true);
            }
            
            // Erstelle persönliche SQLite Datenbank für den Lehrer
            $dbPath = $userDir . '/notendaten.db';
            if (!file_exists($dbPath)) {
                $teacherDb = new SQLite3($dbPath);
                
                // Erstelle Tabellen
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
        }
        
        // Weiterleitung basierend auf Rolle
        if ($user['role'] == 'admin') {
            header('Location: admin.php');
        } else {
            header('Location: lehrer.php');
        }
        exit;
    } else {
        $error = 'Ungültige Anmeldedaten!';
    }
}

$db->close();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotenWeb 2025/2026 - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #FF9A56;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            gap: 15px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: #4A90E2;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
        }
        
        h1 {
            color: #4A90E2;
            font-size: 28px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #4A90E2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #4A90E2;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            opacity: 0.95;
        }
        
        .btn-login:hover {
            background: #357ABD;
            opacity: 1;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }
        
        .error-message {
            background: #FEE;
            color: #C00;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .info-box {
            background: #E3F2FD;
            border-left: 4px solid #4A90E2;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .info-box h3 {
            color: #1976D2;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #333;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 5px;
        }
        
        .info-box code {
            background: #FFF;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">N</div>
            <h1>NotenWeb 2025/2026</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" required placeholder="ihre.email@beispiel.de">
            </div>
            
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn-login">Anmelden</button>
        </form>
        
        <div class="info-box">
            <h3>📚 Demo-Zugänge:</h3>
            <p><strong>Lehrer 1:</strong> <code>thomas.toellner@gmx.de</code> / <code>sohran17</code></p>
            <p><strong>Lehrer 2:</strong> <code>i.koehler@gmx.de</code> / <code>123</code></p>
            <p><strong>Admin:</strong> <code>tilama@mail.de</code> / <code>W@ndermaus17</code></p>
        </div>
        
        <div class="footer-text">
            Sicheres Notenverwaltungssystem für Lehrkräfte
        </div>
    </div>
</body>
</html>