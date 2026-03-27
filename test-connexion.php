<?php
require_once 'config/database.php';

try {
    $db = getDB();
    echo "<h2 style='color:green;'> Connexion réussie à LG PHARMA !</h2>";
    
    $stmt = $db->query("SELECT valeur FROM parametres WHERE cle = 'nom_pharmacie'");
    $pharmacie = $stmt->fetch();
    
    echo "<p>Nom de la pharmacie : <strong>" . $pharmacie['valeur'] . "</strong></p>";
    
    $tables = $db->query("SHOW TABLES")->fetchAll();
    echo "<p>" . count($tables) . " tables dans la base</p>";
    
} catch(Exception $e) {
    echo "<h2 style='color:red;'>❌ Erreur : " . $e->getMessage() . "</h2>";
}
?>