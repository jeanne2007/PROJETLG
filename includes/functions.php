<?php
// includes/functions.php
// Fonctions utilitaires pour LG PHARMA

function estConnecte() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirectSiNonConnecte($url = 'index.php') {
    if (!estConnecte()) {
        header("Location: " . $url);
        exit();
    }
}

function getParametre($cle) {
    $db = getDB();
    $stmt = $db->prepare("SELECT valeur FROM parametres WHERE cle = ?");
    $stmt->execute([$cle]);
    $result = $stmt->fetch();
    return $result['valeur'] ?? '';
}

function getNomPharmacie() {
    return getParametre('nom_pharmacie');
}

function getPharmacienne() {
    return getParametre('pharmacienne');
}
?>