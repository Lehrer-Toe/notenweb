<?php
// logout.php - Abmelde-Script
session_start();

// Lösche alle Session-Variablen
$_SESSION = array();

// Lösche das Session-Cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Zerstöre die Session
session_destroy();

// Weiterleitung zur Login-Seite
header('Location: index.php');
exit;
?>