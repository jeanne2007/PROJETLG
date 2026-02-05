<?php
// debug.php - Vérifier si tout fonctionne
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DEBUG LG PHARMA</h1>";

// Test connexion base
try {
    require_once 'config/database.php';
    $db = getDB();
    echo "<p style='color:green;'>✅ Connexion BD OK</p>";
    
    // Vérifier tables
    $tables = $db->query("SHOW TABLES")->fetchAll();
    echo "<p>📊 Tables trouvées : " . count($tables) . "</p>";
    
    // Vérifier utilisateurs
    $users = $db->query("SELECT COUNT(*) as total FROM utilisateurs")->fetch();
    echo "<p>👥 Utilisateurs : " . $users['total'] . "</p>";
    
    if ($users['total'] > 0) {
        $user = $db->query("SELECT email, prenom FROM utilisateurs LIMIT 1")->fetch();
        echo "<p>📧 Premier utilisateur : " . $user['email'] . " (" . $user['prenom'] . ")</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur BD : " . $e->getMessage() . "</p>";
}

// Test sessions
session_start();
echo "<h3>SESSION :</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>