<?php
session_start();

// Prüfe ob Benutzer eingeloggt ist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: index.php');
    exit;
}

$userEmail = $_SESSION['email'];
$userName = explode('@', $userEmail)[0];
$userDir = __DIR__ . '/Lehrerdaten/' . $userName;
$dbPath = $userDir . '/notendaten.db';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotenWeb 2025/2026 - Lehrer Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #FF9A56;
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
            width: 45px;
            height: 45px;
            background: #4A90E2;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header h1 {
            color: #4A90E2;
            font-size: 26px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #FF6B6B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
            background: rgba(74, 144, 226, 0.9);
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
        }
        
        .nav-tab:hover {
            background: rgba(74, 144, 226, 1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }
        
        .nav-tab.active {
            background: #357ABD;
        }
        
        .nav-tab .icon {
            font-size: 18px;
        }
        
        .secondary-tabs {
            display: flex;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid #E0E0E0;
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
        }
        
        .secondary-tab:hover {
            background: #F5F5F5;
            border-color: #4A90E2;
            color: #4A90E2;
        }
        
        /* Main Content */
        .content-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            min-height: 500px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #F0F0F0;
        }
        
        .content-title {
            font-size: 24px;
            color: #4A90E2;
            font-weight: 600;
        }
        
        .btn-primary {
            padding: 10px 20px;
            background: #FF6B6B;
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
        }
        
        .btn-primary:hover {
            background: #FF5252;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }
        
        /* Cards für Fächer/Klassen */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: #FAFAFA;
            border: 2px solid #E0E0E0;
            border-radius: 15px;
            padding: 20px;
            position: relative;
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: #4A90E2;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 20px;
            color: #4A90E2;
            font-weight: 600;
        }
        
        .card-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-icon:hover {
            background: #E0E0E0;
        }
        
        .card-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .info-box {
            background: #E3F2FD;
            border-left: 4px solid #4A90E2;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .info-box p {
            color: #333;
            line-height: 1.6;
        }
        
        /* Logout Button */
        .btn-logout {
            padding: 8px 16px;
            background: transparent;
            color: #666;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: #FEE;
            border-color: #F44336;
            color: #F44336;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 22px;
            color: #333;
            font-weight: 600;
        }

        .btn-close {
            width: 30px;
            height: 30px;
            border: none;
            background: #F5F5F5;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-size: 15px;
        }

        .btn-save {
            width: 100%;
            padding: 12px;
            background: #FF6B6B;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-save:hover {
            background: #FF5252;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo-icon">N</div>
                <h1>NotenWeb 2025/2026</h1>
            </div>
            <div class="user-info">
                <span>👨‍🏫 Willkommen, <?php echo htmlspecialchars(explode('.', $userName)[0]); ?></span>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
                <button class="btn-logout" onclick="window.location.href='logout.php'">Abmelden</button>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-container">
            <div class="nav-tabs">
                <button class="nav-tab active" data-module="faecher">
                    <span class="icon">📚</span> Fächer
                </button>
                <button class="nav-tab" data-module="klassen">
                    <span class="icon">👥</span> Klassen
                </button>
                <button class="nav-tab" data-module="noten">
                    <span class="icon">📊</span> Noten
                </button>
                <button class="nav-tab" data-module="plusminus">
                    <span class="icon">➕</span> +/-
                </button>
                <button class="nav-tab" data-module="hausaufgaben">
                    <span class="icon">📝</span> Hausaufgaben
                </button>
                <button class="nav-tab" data-module="notentabelle">
                    <span class="icon">📋</span> Notentabelle
                </button>
                <button class="nav-tab" data-module="schueler">
                    <span class="icon">👤</span> Schüler
                </button>
                <button class="nav-tab" data-module="listen">
                    <span class="icon">📄</span> Listen
                </button>
            </div>
            <div class="secondary-tabs">
                <button class="secondary-tab" data-module="kursag">
                    <span>➕</span> Kurs/AG
                </button>
                <button class="secondary-tab" data-module="zeugnisse">
                    <span>📜</span> Zeugnisse
                </button>
                <button class="secondary-tab" data-module="notenskala">
                    <span>🔧</span> Notenskala
                </button>
                <button class="secondary-tab" data-module="einstellungen">
                    <span>⚙️</span> Einstellungen
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-container">
            <div id="module-content">
                <!-- Dynamischer Inhalt wird hier geladen -->
            </div>
        </div>
    </div>

    <!-- Modals werden hier eingefügt -->
    <div id="modals-container"></div>

    <script>
        // Globale Variablen
        const userDir = '<?php echo $userName; ?>';
        let currentModule = 'faecher';

        // Tab Navigation
        document.querySelectorAll('.nav-tab, .secondary-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Entferne active von allen Tabs
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.secondary-tab').forEach(t => t.classList.remove('active'));
                
                // Setze active auf geklickten Tab
                this.classList.add('active');
                
                // Lade Modul
                currentModule = this.dataset.module;
                loadModule(currentModule);
            });
        });

        // Modul laden
        function loadModule(moduleName) {
            const script = document.createElement('script');
            script.src = `JS/${moduleName}.js`;
            script.onload = function() {
                // Rufe die Init-Funktion des Moduls auf
                if (window[moduleName + 'Init']) {
                    window[moduleName + 'Init']();
                }
            };
            document.body.appendChild(script);
        }

        // Initial laden
        loadModule('faecher');
    </script>
</body>
</html>