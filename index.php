<?php
/**
 * NotenWeb 2025/2026 - Login-System mit MySQL
 * Haupteinstiegspunkt der Anwendung
 */

session_start();
require_once 'config/database.php';

// Wenn bereits eingeloggt, weiterleiten
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: lehrer.php');
    }
    exit;
}

// Initialisiere Datenbank-Verbindung
$db = Database::getInstance();
$conn = $db->getConnection();

// Login-Verarbeitung
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Bitte E-Mail und Passwort eingeben!';
    } else {
        try {
            // Prüfe Anmeldedaten
            $stmt = $conn->prepare("
                SELECT id, email, password, role, is_active 
                FROM users 
                WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Prüfe ob Benutzer aktiv ist
                if (!$user['is_active']) {
                    $error = 'Ihr Account wurde deaktiviert. Bitte kontaktieren Sie den Administrator.';
                } else {
                    // Login erfolgreich
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last_login
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET last_login = NOW() 
                        WHERE id = :id
                    ");
                    $stmt->execute([':id' => $user['id']]);
                    
                    // Erstelle Session-Eintrag für Tracking
                    $sessionId = session_id();
                    $stmt = $conn->prepare("
                        INSERT INTO sessions (id, user_id, ip_address, user_agent)
                        VALUES (:session_id, :user_id, :ip, :agent)
                        ON DUPLICATE KEY UPDATE 
                            last_activity = NOW(),
                            ip_address = VALUES(ip_address),
                            user_agent = VALUES(user_agent)
                    ");
                    $stmt->execute([
                        ':session_id' => $sessionId,
                        ':user_id' => $user['id'],
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    // Initialisiere Lehrer-Einstellungen wenn nötig
                    if ($user['role'] === 'teacher') {
                        initializeTeacherSettings($user['id']);
                        
                        // Setze aktuelles Schuljahr in Session
                        $_SESSION['schuljahr'] = $db->getCurrentSchuljahr($user['id']);
                    }
                    
                    // Weiterleitung basierend auf Rolle
                    if ($user['role'] === 'admin') {
                        header('Location: admin.php');
                    } else {
                        header('Location: lehrer.php');
                    }
                    exit;
                }
            } else {
                $error = 'Ungültige Anmeldedaten!';
                
                // Log fehlgeschlagenen Login-Versuch
                error_log("Failed login attempt for: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);
            }
            
        } catch (PDOException $e) {
            error_log("Login-Fehler: " . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

/**
 * Initialisiere Lehrer-Einstellungen beim ersten Login
 */
function initializeTeacherSettings($lehrerId) {
    global $conn;
    
    try {
        // Prüfe ob Einstellungen existieren
        $stmt = $conn->prepare("
            SELECT id FROM lehrer_einstellungen 
            WHERE lehrer_id = :lehrer_id
        ");
        $stmt->execute([':lehrer_id' => $lehrerId]);
        
        if (!$stmt->fetch()) {
            // Erstelle Standard-Einstellungen
            $currentYear = date('Y');
            $nextYear = $currentYear + 1;
            $schuljahr = $currentYear . '/' . $nextYear;
            
            $stmt = $conn->prepare("
                INSERT INTO lehrer_einstellungen (
                    lehrer_id, 
                    schuljahr, 
                    notenskala_typ,
                    standard_gewichtung
                ) VALUES (
                    :lehrer_id, 
                    :schuljahr, 
                    'standard',
                    :gewichtung
                )
            ");
            $stmt->execute([
                ':lehrer_id' => $lehrerId,
                ':schuljahr' => $schuljahr,
                ':gewichtung' => json_encode([
                    'schriftlich' => 2,
                    'muendlich' => 1,
                    'praktisch' => 1
                ])
            ]);
            
            // Erstelle Standard-Notenskala
            createDefaultNotenskala($lehrerId);
        }
        
    } catch (PDOException $e) {
        error_log("Fehler beim Initialisieren der Lehrer-Einstellungen: " . $e->getMessage());
    }
}

/**
 * Erstelle Standard-Notenskala für neuen Lehrer
 */
function createDefaultNotenskala($lehrerId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO notenskalen (lehrer_id, name, typ, definition, ist_standard)
            VALUES (:lehrer_id, :name, :typ, :definition, 1)
        ");
        
        $stmt->execute([
            ':lehrer_id' => $lehrerId,
            ':name' => 'Deutsche Notenskala 1-6',
            ':typ' => 'standard',
            ':definition' => json_encode([
                '1' => ['name' => 'sehr gut', 'von' => 92, 'bis' => 100],
                '2' => ['name' => 'gut', 'von' => 81, 'bis' => 91],
                '3' => ['name' => 'befriedigend', 'von' => 67, 'bis' => 80],
                '4' => ['name' => 'ausreichend', 'von' => 50, 'bis' => 66],
                '5' => ['name' => 'mangelhaft', 'von' => 30, 'bis' => 49],
                '6' => ['name' => 'ungenügend', 'von' => 0, 'bis' => 29]
            ])
        ]);
        
    } catch (PDOException $e) {
        error_log("Fehler beim Erstellen der Standard-Notenskala: " . $e->getMessage());
    }
}

// Bereinige alte Sessions (älter als 24 Stunden)
try {
    $stmt = $conn->prepare("
        DELETE FROM sessions 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
} catch (PDOException $e) {
    // Silent fail - nicht kritisch
}

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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .main-container {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        
        @media (max-width: 968px) {
            .main-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .info-section {
                display: none;
            }
        }
        
        .login-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .info-section {
            color: white;
            padding: 40px;
        }
        
        .info-section h2 {
            font-size: 36px;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .info-section p {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        
        .features {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .feature-text {
            flex: 1;
        }
        
        .feature-text h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .feature-text p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 40px;
            gap: 15px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        h1 {
            color: #333;
            font-size: 32px;
            font-weight: 600;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #FAFAFA;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
            animation: shake 0.3s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .success-message {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 13px;
            padding-top: 20px;
            border-top: 1px solid #E0E0E0;
        }
        
        .version-badge {
            display: inline-block;
            background: #F0F0F0;
            color: #666;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin-top: 10px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border: 2px solid #E0E0E0;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .remember-me label {
            margin: 0;
            cursor: pointer;
            user-select: none;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 5px;
            font-size: 18px;
        }
        
        .password-toggle button:hover {
            color: #667eea;
        }
        
        .login-help {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-help a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .login-help a:hover {
            text-decoration: underline;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #E8F5E9;
            color: #2E7D32;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .status-indicator .dot {
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Info Section -->
        <div class="info-section">
            <h2>🎓 Willkommen bei NotenWeb</h2>
            <p>Das moderne Notenverwaltungssystem für Lehrkräfte. Verwalten Sie Ihre Klassen, Schüler und Noten digital und effizient.</p>
            
            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">📊</div>
                    <div class="feature-text">
                        <h4>Schuljahr-Verwaltung</h4>
                        <p>Organisieren Sie Ihre Daten nach Schuljahren</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">📚</div>
                    <div class="feature-text">
                        <h4>Fächer & Kategorien</h4>
                        <p>Flexible Notenkategorien mit Gewichtung</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">📁</div>
                    <div class="feature-text">
                        <h4>Archiv-System</h4>
                        <p>Sichere Archivierung mit Wiederherstellung</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">🔒</div>
                    <div class="feature-text">
                        <h4>MySQL-Datenbank</h4>
                        <p>Sichere Speicherung bei All-Inkl</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Login Container -->
        <div class="login-container">
            <div class="logo">
                <div class="logo-icon">N</div>
                <h1>NotenWeb</h1>
            </div>
            
            <p class="subtitle">Schuljahr 2025/2026</p>
            
            <div class="status-indicator">
                <span class="dot"></span>
                <span>System online - MySQL-Datenbank aktiv</span>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" autocomplete="on">
                <div class="form-group">
                    <label for="email">E-Mail-Adresse</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        placeholder="ihre.email@schule.de"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        autocomplete="email"
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <div class="password-toggle">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="••••••••"
                            autocomplete="current-password"
                        >
                        <button type="button" onclick="togglePassword()" tabindex="-1">
                            <span id="password-icon">👁️</span>
                        </button>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">Angemeldet bleiben</label>
                </div>
                
                <button type="submit" class="btn-login">
                    Anmelden
                </button>
            </form>
            
            <div class="login-help">
                <a href="mailto:admin@notenweb.de">Passwort vergessen?</a>
            </div>
            
            <div class="footer-text">
                Sicheres Notenverwaltungssystem für Lehrkräfte
                <br>
                <span class="version-badge">Version 2.0 MySQL</span>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                passwordIcon.textContent = '👁️';
            }
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.error-message, .success-message');
            messages.forEach(function(msg) {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(function() {
                    msg.style.display = 'none';
                }, 500);
            });
        }, 5000);
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>