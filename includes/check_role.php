<?php
/**
 * Vérifie que l'utilisateur est administrateur
 * Si pas admin → redirection vers indexs.php avec message d'erreur
 */
function checkAdmin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: indexs.php?error=Accès non autorisé");
        exit();
    }
}

/**
 * Vérifie que l'utilisateur est connecté
 * Utilisé pour toutes les pages du dashboard
 */
function checkVendeur() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est admin (retourne true/false)
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
?>