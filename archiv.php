<?php
/**
 * NotenWeb - Archiv-Verwaltung Frontend
 * Übersicht und Verwaltung archivierter Daten
 */

session_start();
require_once 'config/database.php';

// Prüfe ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Initialisierung
$db = Database::getInstance();
$conn = $db->getConnection();
$lehrerId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userName = explode('@', $userEmail)[0];
$isAdmin = $_SESSION['role'] === 'admin';

// Statistiken laden
try {
    // Basis-Query je nach Rolle
    $whereClause = $isAdmin ? "" : " WHERE lehrer_id = :lehrer_id";
    $params = $isAdmin ? [] : [':lehrer_id' => $lehrerId];
    
    // Archivierte Klassen zählen
    $sql = "SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT schuljahr) as schuljahre_count
            FROM klassen 
            WHERE ist_archiviert = 1" . ($isAdmin ? "" : " AND lehrer_id = :lehrer_id");
    $stmt = $conn->prepare($sql);
    if (!$isAdmin) $stmt->execute([':lehrer_id' => $lehrerId]);
    else $stmt->execute();
    $klassenStats = $stmt->fetch();
    
    // Archivierte Fächer zählen
    $sql = "SELECT COUNT(*) as total 
            FROM faecher 
            WHERE ist_archiviert = 1" . ($isAdmin ? "" : " AND lehrer_id = :lehrer_id");
    $stmt = $conn->prepare($sql);
    if (!$isAdmin) $stmt->execute([':lehrer_id' => $lehrerId]);
    else $stmt->execute();
    $faecherCount = $stmt->fetch()['total'];
    
    // Schuljahre im Archiv
    $sql = "SELECT DISTINCT schuljahr 
            FROM klassen 
            WHERE ist_archiviert = 1" . ($isAdmin ? "" : " AND lehrer_id = :lehrer_id") . "
            ORDER BY schuljahr DESC";
    $stmt = $conn->prepare($sql);
    if (!$isAdmin) $stmt->execute([':lehrer_id' => $lehrerId]);
    else $stmt->execute();
    $schuljahre = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Letzte Archivierungen
    $sql = "SELECT 
                a.*,
                u.email as user_email
            FROM archiv_log a
            JOIN users u ON a.user_id = u.id
            " . ($isAdmin ? "" : "WHERE a.user_id = :lehrer_id ") . "
            ORDER BY a.created_at DESC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    if (!$isAdmin) $stmt->execute([':lehrer_id' => $lehrerId]);
    else $stmt->execute();
    $recentArchives = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Fehler beim Laden der Archiv-Statistiken: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotenWeb - Archiv</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
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
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
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
        
        .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 2px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        /* Navigation */
        .nav-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: #F3F4F6;
            color: #666;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-tab:hover {
            background: #E5E7EB;
            border-color: #9333EA;
        }
        
        .nav-tab.active {
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
            color: white;
        }
        
        /* Statistik-Karten */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin: 0 auto 15px;
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
        
        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #F0F0F0;
        }
        
        .section-title {
            font-size: 22px;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Filter */
        .filter-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 15px;
            background: #F9FAFB;
            border-radius: 10px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            background: white;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-select:hover,
        .filter-select:focus {
            border-color: #9333EA;
            outline: none;
        }
        
        /* Archive List */
        .archive-list {
            display: grid;
            gap: 15px;
        }
        
        .archive-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .archive-item:hover {
            background: white;
            border-color: #9333EA;
            box-shadow: 0 5px 15px rgba(147, 51, 234, 0.1);
        }
        
        .archive-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #E5E7EB 0%, #F3F4F6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .archive-details {
            flex: 1;
        }
        
        .archive-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .archive-meta {
            font-size: 13px;
            color: #666;
        }
        
        .archive-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(147, 51, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-secondary {
            background: #F3F4F6;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #E5E7EB;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-back {
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
        
        .btn-back:hover {
            background: #F3F4F6;
            border-color: #9333EA;
            color: #9333EA;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #E5E7EB 0%, #F3F4F6 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        
        .empty-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .empty-text {
            color: #666;
            font-size: 14px;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #E5E7EB;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-dot {
            position: absolute;
            left: -25px;
            top: 5px;
            width: 12px;
            height: 12px;
            background: white;
            border: 3px solid #9333EA;
            border-radius: 50%;
        }
        
        .timeline-content {
            background: #F9FAFB;
            padding: 15px;
            border-radius: 10px;
        }
        
        .timeline-time {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .timeline-desc {
            font-size: 14px;
            color: #666;
        }
        
        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #F3F4F6;
            border-top: 4px solid #9333EA;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo-icon">📁</div>
                <div>
                    <h1>Archiv-Verwaltung</h1>
                    <p class="subtitle">
                        <?php echo $isAdmin ? 'Administrator-Ansicht' : 'Ihre archivierten Daten'; ?>
                    </p>
                </div>
            </div>
            
            <div class="user-info">
                <a href="lehrer.php" class="btn-back">
                    ← Zurück zum Dashboard
                </a>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="nav-container">
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showTab('overview')">
                    📊 Übersicht
                </button>
                <button class="nav-tab" onclick="showTab('klassen')">
                    👥 Klassen
                </button>
                <button class="nav-tab" onclick="showTab('faecher')">
                    📚 Fächer
                </button>
                <button class="nav-tab" onclick="showTab('versetzung')">
                    🎓 Versetzung
                </button>
                <button class="nav-tab" onclick="showTab('export')">
                    💾 Export
                </button>
                <button class="nav-tab" onclick="showTab('history')">
                    📝 Historie
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div id="content">
            <!-- Übersicht Tab -->
            <div id="overview-tab" class="tab-content">
                <!-- Statistiken -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-number"><?php echo $klassenStats['total'] ?? 0; ?></div>
                        <div class="stat-label">Archivierte Klassen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-number"><?php echo $faecherCount ?? 0; ?></div>
                        <div class="stat-label">Archivierte Fächer</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-number"><?php echo $klassenStats['schuljahre_count'] ?? 0; ?></div>
                        <div class="stat-label">Schuljahre im Archiv</div>
                    </div>
                </div>
                
                <!-- Schuljahre -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            📅 Archivierte Schuljahre
                        </h2>
                    </div>
                    
                    <?php if (empty($schuljahre)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📁</div>
                            <div class="empty-title">Noch keine archivierten Schuljahre</div>
                            <div class="empty-text">
                                Am Ende eines Schuljahres können Sie Ihre Klassen und Fächer hier archivieren.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="archive-list">
                            <?php foreach ($schuljahre as $sj): ?>
                                <div class="archive-item">
                                    <div class="archive-icon">📅</div>
                                    <div class="archive-details">
                                        <div class="archive-title">Schuljahr <?php echo htmlspecialchars($sj); ?></div>
                                        <div class="archive-meta">
                                            <?php
                                            // Zähle Klassen für dieses Schuljahr
                                            $stmt = $conn->prepare("
                                                SELECT COUNT(*) as count 
                                                FROM klassen 
                                                WHERE schuljahr = :sj 
                                                    AND ist_archiviert = 1
                                                    " . ($isAdmin ? "" : " AND lehrer_id = :lehrer_id")
                                            );
                                            $params = [':sj' => $sj];
                                            if (!$isAdmin) $params[':lehrer_id'] = $lehrerId;
                                            $stmt->execute($params);
                                            $klassenInSj = $stmt->fetch()['count'];
                                            ?>
                                            <?php echo $klassenInSj; ?> Klassen archiviert
                                        </div>
                                    </div>
                                    <div class="archive-actions">
                                        <button class="btn btn-secondary btn-sm" onclick="viewSchuljahr('<?php echo $sj; ?>')">
                                            Anzeigen
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Letzte Aktivitäten -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            📝 Letzte Archivierungen
                        </h2>
                    </div>
                    
                    <?php if (empty($recentArchives)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📝</div>
                            <div class="empty-title">Keine Aktivitäten</div>
                            <div class="empty-text">Hier werden Ihre Archivierungsaktivitäten angezeigt.</div>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recentArchives as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-time">
                                            <?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div class="timeline-title">
                                            <?php echo ucfirst($log['tabelle']); ?> <?php echo $log['aktion']; ?>
                                        </div>
                                        <div class="timeline-desc">
                                            von <?php echo htmlspecialchars($log['user_email']); ?>
                                            <?php if ($log['details']): ?>
                                                <?php $details = json_decode($log['details'], true); ?>
                                                <?php if (isset($details['name'])): ?>
                                                    - <?php echo htmlspecialchars($details['name']); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Klassen Tab -->
            <div id="klassen-tab" class="tab-content" style="display: none;">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            👥 Archivierte Klassen
                        </h2>
                        <button class="btn btn-primary" onclick="archiveCurrentClasses()">
                            📁 Aktuelle Klassen archivieren
                        </button>
                    </div>
                    
                    <!-- Filter -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label class="filter-label">Schuljahr</label>
                            <select class="filter-select" id="klassen-schuljahr-filter" onchange="loadArchivedKlassen()">
                                <option value="">Alle Schuljahre</option>
                                <?php foreach ($schuljahre as $sj): ?>
                                    <option value="<?php echo $sj; ?>"><?php echo $sj; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="klassen-list" class="archive-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Lade archivierte Klassen...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Fächer Tab -->
            <div id="faecher-tab" class="tab-content" style="display: none;">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            📚 Archivierte Fächer
                        </h2>
                    </div>
                    
                    <div id="faecher-list" class="archive-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Lade archivierte Fächer...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Versetzung Tab -->
            <div id="versetzung-tab" class="tab-content" style="display: none;">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            🎓 Versetzungs-Assistent
                        </h2>
                    </div>
                    
                    <div style="background: #F0F7FF; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h3 style="margin-bottom: 10px; color: #4A90E2;">So funktioniert die Versetzung:</h3>
                        <ol style="margin-left: 20px; color: #666; line-height: 1.8;">
                            <li>Wählen Sie das alte und neue Schuljahr</li>
                            <li>System zeigt Ihnen eine Vorschau der Versetzung</li>
                            <li>Passen Sie bei Bedarf Klassennamen an</li>
                            <li>Führen Sie die Versetzung durch</li>
                            <li>Alte Klassen werden automatisch archiviert</li>
                        </ol>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="filter-group">
                            <label class="filter-label">Von Schuljahr</label>
                            <select class="filter-select" id="versetzung-von">
                                <option value="2024/2025">2024/2025</option>
                                <option value="2025/2026" selected>2025/2026</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Nach Schuljahr</label>
                            <select class="filter-select" id="versetzung-nach">
                                <option value="2025/2026">2025/2026</option>
                                <option value="2026/2027" selected>2026/2027</option>
                            </select>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" onclick="prepareVersetzung()" style="width: 100%;">
                        🔍 Versetzung vorbereiten
                    </button>
                    
                    <div id="versetzung-preview" style="margin-top: 30px;"></div>
                </div>
            </div>
            
            <!-- Export Tab -->
            <div id="export-tab" class="tab-content" style="display: none;">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            💾 Daten exportieren
                        </h2>
                    </div>
                    
                    <div style="display: grid; gap: 20px;">
                        <div class="archive-item" style="cursor: pointer;" onclick="exportArchive('json')">
                            <div class="archive-icon">📄</div>
                            <div class="archive-details">
                                <div class="archive-title">JSON Export</div>
                                <div class="archive-meta">Komplettes Archiv als JSON-Datei</div>
                            </div>
                            <button class="btn btn-primary btn-sm">
                                Exportieren
                            </button>
                        </div>
                        
                        <?php if ($isAdmin): ?>
                        <div class="archive-item" style="cursor: pointer;" onclick="createBackup()">
                            <div class="archive-icon">💾</div>
                            <div class="archive-details">
                                <div class="archive-title">Datenbank-Backup</div>
                                <div class="archive-meta">SQL-Dump der gesamten Datenbank (nur Admin)</div>
                            </div>
                            <button class="btn btn-danger btn-sm">
                                Backup erstellen
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Historie Tab -->
            <div id="history-tab" class="tab-content" style="display: none;">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            📝 Archiv-Historie
                        </h2>
                    </div>
                    
                    <div id="history-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Lade Historie...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab-Verwaltung
        function showTab(tabName) {
            // Alle Tabs ausblenden
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Alle Tab-Buttons deaktivieren
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Gewählten Tab anzeigen
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Tab-Button aktivieren
            event.target.classList.add('active');
            
            // Daten laden je nach Tab
            switch(tabName) {
                case 'klassen':
                    loadArchivedKlassen();
                    break;
                case 'faecher':
                    loadArchivedFaecher();
                    break;
                case 'history':
                    loadHistory();
                    break;
            }
        }
        
        // Archivierte Klassen laden
        function loadArchivedKlassen() {
            const schuljahr = document.getElementById('klassen-schuljahr-filter')?.value || '';
            const container = document.getElementById('klassen-list');
            
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Lade...</p></div>';
            
            fetch(`api/archiv.php?action=list_klassen&schuljahr=${schuljahr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    if (!data.data || data.data.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">📁</div>
                                <div class="empty-title">Keine archivierten Klassen</div>
                                <div class="empty-text">In diesem Schuljahr wurden noch keine Klassen archiviert.</div>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = '';
                    data.data.forEach(klasse => {
                        html += `
                            <div class="archive-item">
                                <div class="archive-icon">👥</div>
                                <div class="archive-details">
                                    <div class="archive-title">Klasse ${klasse.name}</div>
                                    <div class="archive-meta">
                                        Schuljahr ${klasse.schuljahr} • 
                                        ${klasse.schueler_count || 0} Schüler • 
                                        Archiviert am ${new Date(klasse.archiviert_am).toLocaleDateString('de-DE')}
                                    </div>
                                </div>
                                <div class="archive-actions">
                                    <button class="btn btn-success btn-sm" onclick="restoreKlasse(${klasse.id})">
                                        ♻️ Wiederherstellen
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Fehler:', error);
                    container.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-title">Fehler beim Laden</div></div>';
                });
        }
        
        // Archivierte Fächer laden
        function loadArchivedFaecher() {
            const container = document.getElementById('faecher-list');
            
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Lade...</p></div>';
            
            fetch('api/archiv.php?action=list_faecher')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    if (!data.data || data.data.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">📚</div>
                                <div class="empty-title">Keine archivierten Fächer</div>
                                <div class="empty-text">Es wurden noch keine Fächer archiviert.</div>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = '';
                    data.data.forEach(fach => {
                        html += `
                            <div class="archive-item">
                                <div class="archive-icon">📚</div>
                                <div class="archive-details">
                                    <div class="archive-title">${fach.name}</div>
                                    <div class="archive-meta">
                                        Schuljahr ${fach.schuljahr} • 
                                        ${fach.kategorien_count || 0} Kategorien
                                    </div>
                                </div>
                                <div class="archive-actions">
                                    <button class="btn btn-success btn-sm" onclick="restoreFach(${fach.id})">
                                        ♻️ Wiederherstellen
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Fehler:', error);
                    container.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-title">Fehler beim Laden</div></div>';
                });
        }
        
        // Historie laden
        function loadHistory() {
            const container = document.getElementById('history-list');
            
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Lade...</p></div>';
            
            fetch('api/archiv.php?action=get_history&limit=20')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    if (!data.data || data.data.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">📝</div>
                                <div class="empty-title">Keine Historie</div>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = '<div class="timeline">';
                    data.data.forEach(entry => {
                        const icon = entry.aktion === 'archiviert' ? '📁' : '♻️';
                        html += `
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <div class="timeline-time">
                                        ${new Date(entry.created_at).toLocaleString('de-DE')}
                                    </div>
                                    <div class="timeline-title">
                                        ${icon} ${entry.tabelle} ${entry.aktion}
                                    </div>
                                    <div class="timeline-desc">
                                        von ${entry.user_email}
                                        ${entry.details ? ' - ' + JSON.stringify(entry.details) : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Fehler:', error);
                    container.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-title">Fehler beim Laden</div></div>';
                });
        }
        
        // Klasse wiederherstellen
        function restoreKlasse(klasseId) {
            if (!confirm('Möchten Sie diese Klasse wirklich wiederherstellen?')) return;
            
            fetch('api/archiv.php?action=restore_klasse', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'klasse_id=' + klasseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                alert(data.message || 'Klasse erfolgreich wiederhergestellt');
                loadArchivedKlassen();
            })
            .catch(error => {
                alert('Fehler: ' + error.message);
            });
        }
        
        // Fach wiederherstellen
        function restoreFach(fachId) {
            if (!confirm('Möchten Sie dieses Fach wirklich wiederherstellen?')) return;
            
            fetch('api/archiv.php?action=restore_fach', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'fach_id=' + fachId
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                alert(data.message || 'Fach erfolgreich wiederhergestellt');
                loadArchivedFaecher();
            })
            .catch(error => {
                alert('Fehler: ' + error.message);
            });
        }
        
        // Versetzung vorbereiten
        function prepareVersetzung() {
            const von = document.getElementById('versetzung-von').value;
            const nach = document.getElementById('versetzung-nach').value;
            
            fetch('api/archiv.php?action=prepare_versetzung', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    altes_schuljahr: von,
                    neues_schuljahr: nach
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                
                const preview = document.getElementById('versetzung-preview');
                
                if (!data.data || data.data.length === 0) {
                    preview.innerHTML = '<div class="empty-state"><div class="empty-title">Keine Klassen zum Versetzen gefunden</div></div>';
                    return;
                }
                
                let html = '<h3 style="margin-bottom: 20px;">Versetzungsvorschau:</h3>';
                html += '<div class="archive-list">';
                
                data.data.forEach(item => {
                    html += `
                        <div class="archive-item">
                            <div class="archive-icon">🎓</div>
                            <div class="archive-details">
                                <div class="archive-title">
                                    ${item.alt.name} → ${item.neu.name}
                                </div>
                                <div class="archive-meta">
                                    ${item.alt.schueler} Schüler • ${item.alt.faecher} Fächer
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                html += `
                    <button class="btn btn-success" onclick='executeVersetzung(${JSON.stringify(data.data)})' style="width: 100%; margin-top: 20px;">
                        ✅ Versetzung durchführen
                    </button>
                `;
                
                preview.innerHTML = html;
            })
            .catch(error => {
                alert('Fehler: ' + error.message);
            });
        }
        
        // Versetzung durchführen
        function executeVersetzung(plan) {
            if (!confirm('Möchten Sie die Versetzung wirklich durchführen? Die alten Klassen werden archiviert.')) return;
            
            fetch('api/archiv.php?action=execute_versetzung', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    versetzungsplan: plan,
                    archivieren: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                alert(data.message || 'Versetzung erfolgreich durchgeführt');
                document.getElementById('versetzung-preview').innerHTML = '';
            })
            .catch(error => {
                alert('Fehler: ' + error.message);
            });
        }
        
        // Export
        function exportArchive(format) {
            window.location.href = `api/archiv.php?action=export_archive&format=${format}`;
        }
        
        // Backup (nur Admin)
        function createBackup() {
            if (!confirm('Möchten Sie ein vollständiges Datenbank-Backup erstellen?')) return;
            window.location.href = 'api/archiv.php?action=create_backup';
        }
        
        // Schuljahr anzeigen
        function viewSchuljahr(schuljahr) {
            showTab('klassen');
            document.getElementById('klassen-schuljahr-filter').value = schuljahr;
            loadArchivedKlassen();
        }
        
        // Aktuelle Klassen archivieren
        function archiveCurrentClasses() {
            window.location.href = 'lehrer.php#klassen';
        }
    </script>
</body>
</html>