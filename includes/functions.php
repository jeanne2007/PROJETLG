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

// ============================================
// FONCTIONS D'ALERTES INTELLIGENTES
// ============================================

/**
 * Récupère les médicaments avec stock bas (stock <= seuil_alerte)
 * @param object $db Connexion PDO
 * @param int $limite Nombre maximum de résultats
 * @return array Liste des médicaments en alerte stock
 */
function getAlertesStockBas($db, $limite = 10) {
    $stmt = $db->prepare("
        SELECT 
            m.*,
            (m.seuil_alerte - m.stock) as quantite_manquante
        FROM medicaments m
        WHERE m.stock <= m.seuil_alerte
        ORDER BY (m.stock * 1.0 / m.seuil_alerte) ASC
        LIMIT :limite
    ");
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Récupère les médicaments proches de la date de péremption
 * @param object $db Connexion PDO
 * @param int $jours Nombre de jours avant péremption (défaut: 30)
 * @param int $limite Nombre maximum de résultats
 * @return array Liste des médicaments proches de la péremption
 */
function getAlertesPeremption($db, $jours = 30, $limite = 10) {
    $stmt = $db->prepare("
        SELECT 
            m.*,
            DATEDIFF(m.date_peremption, CURDATE()) as jours_restants
        FROM medicaments m
        WHERE m.date_peremption IS NOT NULL
          AND m.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :jours DAY)
        ORDER BY m.date_peremption ASC
        LIMIT :limite
    ");
    $stmt->bindParam(':jours', $jours, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Génère la liste complète de réapprovisionnement
 * @param object $db Connexion PDO
 * @return array Liste des médicaments à commander
 */
function getListeReapprovisionnement($db) {
    $stmt = $db->query("
        SELECT 
            m.id,
            m.nom,
            m.forme,
            m.dosage,
            m.stock,
            m.seuil_alerte,
            (m.seuil_alerte - m.stock) as quantite_a_commander,
            m.fournisseur,
            m.prix_achat,
            (m.prix_achat * (m.seuil_alerte - m.stock)) as cout_total_estime
        FROM medicaments m
        WHERE m.stock <= m.seuil_alerte
        ORDER BY quantite_a_commander DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Compte le nombre total d'alertes actives (stock bas + péremption)
 * @param object $db Connexion PDO
 * @return int Nombre total d'alertes
 */
function countAlertesActives($db) {
    // Alertes stock bas
    $stmt1 = $db->query("
        SELECT COUNT(*) as total 
        FROM medicaments 
        WHERE stock <= seuil_alerte
    ");
    $stock_bas = $stmt1->fetch()['total'];
    
    // Alertes péremption (30 jours)
    $stmt2 = $db->prepare("
        SELECT COUNT(*) as total 
        FROM medicaments 
        WHERE date_peremption IS NOT NULL 
          AND date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt2->execute();
    $peremption = $stmt2->fetch()['total'];
    
    return $stock_bas + $peremption;
}

/**
 * Vérifie si un médicament a une alerte critique
 * @param object $db Connexion PDO
 * @param int $medicament_id ID du médicament
 * @return array|false Informations d'alerte ou false
 */
function checkAlerteMedicament($db, $medicament_id) {
    $stmt = $db->prepare("
        SELECT 
            id,
            nom,
            stock,
            seuil_alerte,
            date_peremption,
            CASE 
                WHEN stock <= 0 THEN 'rupture'
                WHEN stock <= seuil_alerte THEN 'stock_bas'
                ELSE 'ok'
            END as statut_stock,
            DATEDIFF(date_peremption, CURDATE()) as jours_avant_peremption
        FROM medicaments
        WHERE id = ?
    ");
    $stmt->execute([$medicament_id]);
    $medicament = $stmt->fetch();
    
    if (!$medicament) {
        return false;
    }
    
    $alertes = [];
    
    // Vérifier stock
    if ($medicament['stock'] <= $medicament['seuil_alerte']) {
        $alertes[] = [
            'type' => 'stock',
            'niveau' => $medicament['stock'] <= 0 ? 'critique' : 'warning',
            'message' => "Stock " . ($medicament['stock'] <= 0 ? "épuisé" : "bas") . " (" . $medicament['stock'] . " unités)"
        ];
    }
    
    // Vérifier péremption
    if ($medicament['date_peremption'] && $medicament['jours_avant_peremption'] <= 30) {
        $alertes[] = [
            'type' => 'peremption',
            'niveau' => $medicament['jours_avant_peremption'] <= 7 ? 'critique' : 'warning',
            'message' => "Péremption dans " . $medicament['jours_avant_peremption'] . " jours"
        ];
    }
    
    return [
        'medicament' => $medicament['nom'],
        'alertes' => $alertes
    ];
}

// ============================================
// FONCTION JOURNAL DES ACTIONS (AJOUTÉE)
// ============================================

/**
 * Enregistre une action dans le journal
 * @param object $db Connexion PDO
 * @param int $user_id ID de l'utilisateur
 * @param string $action Type d'action (connexion, ajout, modification, suppression, vente)
 * @param string $description Description détaillée
 */
function logAction($db, $user_id, $action, $description) {
    // Récupérer le nom de l'utilisateur
    $stmt = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $user_nom = $user ? $user['prenom'] . ' ' . $user['nom'] : 'Inconnu';
    
    // Récupérer l'adresse IP
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Insérer dans le journal
    $stmt = $db->prepare("
        INSERT INTO journal (user_id, user_nom, action, description, ip_address, date_action)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $user_nom, $action, $description, $ip_address]);
}
?>