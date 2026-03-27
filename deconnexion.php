<?php
// deconnexion.php
session_start();

// Vérifier que l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    // Inclure la base de données et les fonctions
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    
    $db = getDB();
    
    // Enregistrer l'action de déconnexion AVANT de détruire la session
    logAction($db, $_SESSION['user_id'], 'deconnexion', 'Déconnexion de l\'application');
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: index.php');
exit();
?>