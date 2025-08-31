<?php
/**
 * NotenWeb Lehrer-Dashboard - MySQL Version
 * Hauptarbeitsbereich für Lehrer mit Schuljahr-Auswahl
 */

session_start();
require_once 'config/database.php';

// Prüfe ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: index.php');
    exit;
}

// Initialisierung
$db = Database::getInstance();
$conn = $db->getConnection();
$lehrerId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userName = explode('@', $userEmail)[0];

// Aktuelles Schuljahr abrufen oder setzen
$currentSchuljahr = $db->getCurrentSchuljahr($lehrerId);
$_SESSION['schuljahr'] = $currentSchuljahr;

// Schuljahr wechseln
if (isset($_POST['change_schuljahr'])) {
    $neuesSchuljahr = $_POST['neues_schuljahr'];
    if (preg_match('/^\d{4}\/\d{4}$/', $neuesSchuljahr)) {
        $db->setSchuljahr($lehrerId, $neuesSchuljahr);
        $_SESSION['schuljahr'] = $neuesSchuljahr;
        $currentSchuljahr = $neuesSchuljahr;
        header('Location: lehrer.php?msg=schuljahr_changed');
        exit;
    }
}

// Dashboard-Statistiken laden
try {
    // Aktive Klassen im aktuellen Schuljahr
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM klassen 
        WHERE lehrer_id = :lehrer_id 
            AND schuljahr = :schuljahr 
            AND ist_archiviert = 0
    ");
    $stmt->execute([
        ':lehrer_id' => $lehrerId,
        ':schuljahr' => $currentSchuljahr
    ]);
    $klassenCount = $stmt->fetch()['count'];
    
    // Schüler gesamt
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) as count 
        FROM schueler s
        JOIN klassen k ON s.klasse_id = k.id
        WHERE k.lehrer_id = :lehrer_id 
            AND k.schuljahr = :schuljahr 
            AND k.ist_archiviert = 0
            AND s.ist_aktiv = 1
    ");
    $stmt->execute([
        ':lehrer_id' => $lehrerId,
        ':schuljahr' => $currentSchuljahr
    ]);
    $schuelerCount = $stmt->fetch()['count'];
    
    // Fächer im aktuellen Schuljahr
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM faecher 
        WHERE lehrer_id = :lehrer_id 
            AND schuljahr = :schuljahr 
            AND ist_archiviert = 0
    ");
    $stmt->execute([
        ':lehrer_id' => $lehrerId,
        ':schuljahr' => $currentSchuljahr
    ]);
    $faecherCount = $stmt->fetch()['count'];
    
    // Noten diesen Monat
    $stmt = $conn->prepare("
        SELECT COUNT(n.id) as count 
        FROM noten n
        JOIN faecher f ON n.fach_id = f.id
        WHERE f.lehrer_id = :lehrer_id 
            AND f.schuljahr = :schuljahr 
            AND n.datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND n.ist_archiviert = 0
    ");
    $stmt->execute([
        ':lehrer_id' => $lehrerId,
        ':schuljahr' => $currentSchuljahr
    ]);
    $notenMonat = $stmt->fetch()['count'];
    
    // Archivierte Klassen (alle Schuljahre)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM klassen 
        WHERE lehrer_id = :lehrer_id 
            AND ist_archiviert = 1
    ");
    $stmt->execute([':lehrer_id' => $lehrerId]);
    $archivedCount = $stmt->fetch()['count'];
    
    // Letzte Aktivitäten
    $stmt = $conn->prepare("
        SELECT 
            n.id,
            n.note,
            n.datum,
            s.nachname,
            s.vorname,
            f.name as fach_name,
            k.name as klasse_name
        FROM noten n
        JOIN schueler s ON n.schueler_id = s.id
        JOIN faecher f ON n.fach_id = f.id
        JOIN klassen k ON s.klasse_id = k.id
        WHERE f.lehrer_id = :lehrer_id 
            AND f.schuljahr = :schuljahr
            AND n.ist_archiviert = 0
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([
        ':lehrer_id' => $lehrerId,
        ':schuljahr' => $currentSchuljahr
    ]);
    $letzteNoten = $stmt->fetchAll();
    
    // Anstehende Ereignisse (z.B. Klassenarbeiten)
    $stmt = $conn->prepare("
        SELECT 
            k.name as klasse_name,
            f.name as fach_name,
            COUNT(DISTINCT s.id) as schueler_count
        FROM klassen k
        JOIN klassen_faecher kf ON kf.klasse_id = k.id
        JOIN faecher f ON kf.fach_id = f.id
        JOIN schueler s ON s.klasse_id = k.id
        WHERE k.lehrer_id = :lehrer_id 
            AND k.schuljahr = :schuljahr 
            AND k.ist_archiviert = 0
            AND s.ist_aktiv = 1
        GROUP BY k.id, f.id
        ORDER BY k.name, f.name
        LIMIT 3
    ");
    $stmt->execute([
        ':lehrer_id' => $lehrerId,
        ':schuljahr' => $currentSchuljahr
    ]);
    $klassenFaecher = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Fehler beim Laden der Dashboard-Daten: " . $e->getMessage());
}

// Verfügbare Schuljahre für Dropdown
$currentYear = date('Y');
$schuljahre = [];
for ($i = -2; $i <= 2; $i++) {
    $year = $currentYear + $i;
    $schuljahre[] = $year . '/' . ($year + 1);
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotenWeb - Lehrer Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #FF9A56 0%, #FF6B6B 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            background: white;
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
            font-weight: bold;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
        }
        
        .schuljahr-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #F0F7FF 0%, #E3F2FD 100%);
            padding: 10px 20px;
            border-radius: 12px;
            border: 2px solid #4A90E2;
        }
        
        .schuljahr-selector label {
            color: #4A90E2;
            font-weight: 600;
            font-size: 14px;
        }
        
        .schuljahr-selector select {
            padding: 8px 12px;
            border: 2px solid #4A90E2;
            border-radius: 8px;
            background: white;
            color: #333;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .schuljahr-selector select:hover {
            background: #F0F7FF;
        }
        
        .btn-change-schuljahr {
            padding: 8px 16px;
            background: #4A90E2;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-change-schuljahr:hover {
            background: #357ABD;
            transform: translateY(-1px);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF9A56 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        /* Navigation Tabs */
        .nav-container {
            background: white;
            border-radius: 20px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .nav-tab {
            padding: 12px 20px;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .nav-tab:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }
        
        .nav-tab.active {
            background: linear-gradient(135deg, #FF9A56 0%, #FF6B6B 100%);
        }
        
        .nav-tab .icon {
            font-size: 18px;
        }
        
        .secondary-tabs {
            display: flex;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid #E0E0E0;
            flex-wrap: wrap;
        }
        
        .secondary-tab {
            padding: 8px 15px;
            background: transparent;
            color: #666;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .secondary-tab:hover {
            background: #F5F5F5;
            border-color: #4A90E2;
            color: #4A90E2;
        }
        
        /* Dashboard Content */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .content-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #F0F0F0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Statistik-Karten */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #F0F7FF 0%, #E3F2FD 100%);
            border: 2px solid #4A90E2;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(74, 144, 226, 0.2);
        }
        
        .stat-card.orange {
            background: linear-gradient(135deg, #FFF4E6 0%, #FFE0B2 100%);
            border-color: #FF9A56;
        }
        
        .stat-card.green {
            background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
            border-color: #10B981;
        }
        
        .stat-card.red {
            background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);
            border-color: #FF6B6B;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            border: 2px solid #E0E0E0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .action-card:hover {
            border-color: #4A90E2;
            background: #F0F7FF;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .action-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .action-desc {
            font-size: 13px;
            color: #666;
        }
        
        /* Recent Activities */
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #F0F0F0;
            transition: background 0.2s;
        }
        
        .activity-item:hover {
            background: #F9FAFB;
        }
        
        .activity-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .activity-meta {
            font-size: 13px;
            color: #999;
        }
        
        .note-badge {
            padding: 4px 12px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Sidebar */
        .sidebar-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .sidebar-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .class-list {
            list-style: none;
        }
        
        .class-item {
            padding: 12px;
            background: #F9FAFB;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .class-item:hover {
            background: #F0F7FF;
            transform: translateX(5px);
        }
        
        .class-name {
            font-weight: 600;
            color: #333;
        }
        
        .class-info {
            font-size: 13px;
            color: #666;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }
        
        /* Buttons */
        .btn-logout {
            padding: 10px 20px;
            background: transparent;
            color: #666;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-logout:hover {
            background: #FEE2E2;
            border-color: #FF6B6B;
            color: #FF6B6B;
        }
        
        .btn-archiv {
            padding: 10px 20px;
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-archiv:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(147, 51, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo-icon">N</div>
                <div>
                    <h1>NotenWeb</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 2px;">
                        Willkommen, <?php echo htmlspecialchars(explode('.', $userName)[0]); ?>
                    </p>
                </div>
            </div>
            
            <!-- Schuljahr-Auswahl -->
            <form method="POST" class="schuljahr-selector">
                <label for="schuljahr">📅 Schuljahr:</label>
                <select name="neues_schuljahr" id="schuljahr" onchange="this.form.submit()">
                    <?php foreach ($schuljahre as $sj): ?>
                        <option value="<?php echo $sj; ?>" <?php echo $sj === $currentSchuljahr ? 'selected' : ''; ?>>
                            <?php echo $sj; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="change_schuljahr" value="1">
            </form>
            
            <div class="user-info">
                <a href="archiv.php" class="btn-archiv">
                    📁 Archiv
                </a>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
                <a href="logout.php" class="btn-logout">
                    🚪 Abmelden
                </a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-container">
            <div class="nav-tabs">
                <a href="#" class="nav-tab active" data-module="dashboard">
                    <span class="icon">🏠</span> Dashboard
                </a>
                <a href="#" class="nav-tab" data-module="faecher" onclick="loadModule('faecher')">
                    <span class="icon">📚</span> Fächer
                </a>
                <a href="#" class="nav-tab" data-module="klassen" onclick="loadModule('klassen')">
                    <span class="icon">👥</span> Klassen
                </a>
                <a href="#" class="nav-tab" data-module="noten" onclick="loadModule('noten')">
                    <span class="icon">📊</span> Noten
                </a>
                <a href="#" class="nav-tab" data-module="plusminus" onclick="loadModule('plusminus')">
                    <span class="icon">➕</span> +/-
                </a>
                <a href="#" class="nav-tab" data-module="hausaufgaben" onclick="loadModule('hausaufgaben')">
                    <span class="icon">📝</span> Hausaufgaben
                </a>
                <a href="#" class="nav-tab" data-module="notentabelle" onclick="loadModule('notentabelle')">
                    <span class="icon">📋</span> Notentabelle
                </a>
                <a href="#" class="nav-tab" data-module="schueler" onclick="loadModule('schueler')">
                    <span class="icon">👤</span> Schüler
                </a>
            </div>
            <div class="secondary-tabs">
                <a href="#" class="secondary-tab" data-module="zeugnisse" onclick="loadModule('zeugnisse')">
                    <span>📜</span> Zeugnisse
                </a>
                <a href="#" class="secondary-tab" data-module="statistiken" onclick="loadModule('statistiken')">
                    <span>📈</span> Statistiken
                </a>
                <a href="#" class="secondary-tab" data-module="export" onclick="loadModule('export')">
                    <span>💾</span> Export
                </a>
                <a href="#" class="secondary-tab" data-module="einstellungen" onclick="loadModule('einstellungen')">
                    <span>⚙️</span> Einstellungen
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'schuljahr_changed'): ?>
            <div class="alert alert-success">
                ✅ Schuljahr erfolgreich gewechselt auf <?php echo htmlspecialchars($currentSchuljahr); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Content -->
        <div id="module-content">
            <div class="dashboard-grid">
                <!-- Main Content -->
                <div>
                    <!-- Statistiken -->
                    <div class="content-section">
                        <h2 class="section-title">📊 Übersicht - Schuljahr <?php echo htmlspecialchars($currentSchuljahr); ?></h2>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $klassenCount; ?></div>
                                <div class="stat-label">Klassen</div>
                            </div>
                            <div class="stat-card orange">
                                <div class="stat-number"><?php echo $schuelerCount; ?></div>
                                <div class="stat-label">Schüler</div>
                            </div>
                            <div class="stat-card green">
                                <div class="stat-number"><?php echo $faecherCount; ?></div>
                                <div class="stat-label">Fächer</div>
                            </div>
                            <div class="stat-card red">
                                <div class="stat-number"><?php echo $notenMonat; ?></div>
                                <div class="stat-label">Noten (30 Tage)</div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <h3 style="margin: 30px 0 15px 0; color: #333;">⚡ Schnellzugriff</h3>
                        <div class="quick-actions">
                            <a href="#" class="action-card" onclick="loadModule('noten', 'new')">
                                <div class="action-icon">📝</div>
                                <div class="action-title">Note eintragen</div>
                                <div class="action-desc">Neue Note erfassen</div>
                            </a>
                            <a href="#" class="action-card" onclick="loadModule('klassen', 'new')">
                                <div class="action-icon">👥</div>
                                <div class="action-title">Neue Klasse</div>
                                <div class="action-desc">Klasse anlegen</div>
                            </a>
                            <a href="#" class="action-card" onclick="loadModule('notentabelle')">
                                <div class="action-icon">📊</div>
                                <div class="action-title">Notentabelle</div>
                                <div class="action-desc">Übersicht anzeigen</div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Letzte Aktivitäten -->
                    <div class="content-section" style="margin-top: 20px;">
                        <h2 class="section-title">📝 Letzte Noten</h2>
                        <div class="activity-list">
                            <?php if (empty($letzteNoten)): ?>
                                <p style="text-align: center; color: #999; padding: 40px;">
                                    Noch keine Noten eingetragen
                                </p>
                            <?php else: ?>
                                <?php foreach ($letzteNoten as $note): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">📊</div>
                                        <div class="activity-details">
                                            <div class="activity-title">
                                                <?php echo htmlspecialchars($note['vorname'] . ' ' . $note['nachname']); ?>
                                                - <?php echo htmlspecialchars($note['fach_name']); ?>
                                            </div>
                                            <div class="activity-meta">
                                                Klasse <?php echo htmlspecialchars($note['klasse_name']); ?> • 
                                                <?php echo date('d.m.Y', strtotime($note['datum'])); ?>
                                            </div>
                                        </div>
                                        <div class="note-badge">
                                            Note <?php echo number_format($note['note'], 1); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Klassen-Übersicht -->
                    <div class="sidebar-section">
                        <h3 class="sidebar-title">📚 Meine Klassen</h3>
                        <ul class="class-list">
                            <?php if (empty($klassenFaecher)): ?>
                                <li style="text-align: center; color: #999; padding: 20px;">
                                    Keine Klassen vorhanden
                                </li>
                            <?php else: ?>
                                <?php foreach ($klassenFaecher as $kf): ?>
                                    <li class="class-item">
                                        <div>
                                            <div class="class-name">
                                                <?php echo htmlspecialchars($kf['klasse_name']); ?>
                                            </div>
                                            <div class="class-info">
                                                <?php echo htmlspecialchars($kf['fach_name']); ?> • 
                                                <?php echo $kf['schueler_count']; ?> Schüler
                                            </div>
                                        </div>
                                        <span style="color: #4A90E2;">→</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Archiv-Info -->
                    <div class="sidebar-section">
                        <h3 class="sidebar-title">📁 Archiv</h3>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                            Sie haben <strong><?php echo $archivedCount; ?></strong> archivierte Klassen
                        </p>
                        <a href="archiv.php" class="btn-archiv" style="width: 100%; justify-content: center;">
                            Archiv öffnen
                        </a>
                    </div>
                    
                    <!-- Hilfe -->
                    <div class="sidebar-section">
                        <h3 class="sidebar-title">💡 Tipp</h3>
                        <p style="color: #666; font-size: 14px; line-height: 1.6;">
                            Am Ende des Schuljahres können Sie Ihre Klassen archivieren und fürs neue Schuljahr versetzen. 
                            Alle Daten bleiben erhalten und können jederzeit eingesehen werden.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Module dynamisch laden
        function loadModule(moduleName, action = null) {
            const moduleContent = document.getElementById('module-content');
            
            // Tabs aktualisieren
            document.querySelectorAll('.nav-tab, .secondary-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.module === moduleName) {
                    tab.classList.add('active');
                }
            });
            
            // Modul-Inhalt laden
            moduleContent.innerHTML = '<div style="text-align: center; padding: 50px;"><div class="loading-spinner"></div><p>Lade ' + moduleName + '...</p></div>';
            
            // Hier würde normalerweise das entsprechende JS-Modul geladen
            // Für die Demo zeigen wir nur eine Meldung
            setTimeout(() => {
                const script = document.createElement('script');
                script.src = `JS/${moduleName}.js`;
                script.onload = function() {
                    if (window[moduleName + 'Init']) {
                        window[moduleName + 'Init'](action);
                    }
                };
                script.onerror = function() {
                    moduleContent.innerHTML = '<div style="text-align: center; padding: 50px; color: #999;">Modul ' + moduleName + ' wird vorbereitet...</div>';
                };
                document.body.appendChild(script);
            }, 300);
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
        
        // Loading Spinner CSS
        const style = document.createElement('style');
        style.textContent = `
            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #4A90E2;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>