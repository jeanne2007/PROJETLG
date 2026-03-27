<?php
session_start(); // UN SEUL session_start() au début

// Vérifier connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$action = $_GET['action'] ?? 'liste';
$message = $_GET['message'] ?? '';
$type = $_GET['type'] ?? '';

// Gestion du panier en session
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// ============================================
// SUPPRIMER UNE VENTE
// ============================================
if (isset($_GET['delete_vente'])) {
    $vente_id = $_GET['delete_vente'];
    
    // Vérifier que l'utilisateur est admin
    $stmt = $db->prepare("SELECT role FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_role = $stmt->fetch()['role'];
    
    if ($user_role !== 'admin') {
        header("Location: vente.php?message=Action non autorisée&type=error");
        exit();
    }
    
    try {
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Récupérer les lignes de vente pour remettre les stocks
        $stmt = $db->prepare("SELECT medicament_id, quantite FROM ventes_lignes WHERE vente_id = ?");
        $stmt->execute([$vente_id]);
        $lignes = $stmt->fetchAll();
        
        // Remettre les stocks
        foreach ($lignes as $ligne) {
            $stmt = $db->prepare("UPDATE medicaments SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$ligne['quantite'], $ligne['medicament_id']]);
        }
        
        // Supprimer les lignes de vente
        $stmt = $db->prepare("DELETE FROM ventes_lignes WHERE vente_id = ?");
        $stmt->execute([$vente_id]);
        
        // Supprimer la vente
        $stmt = $db->prepare("DELETE FROM ventes WHERE id = ?");
        $stmt->execute([$vente_id]);
        
        // Log de l'action
        logAction($db, $_SESSION['user_id'], 'suppression_vente', 'Suppression de la vente N° LG-' . str_pad($vente_id, 6, '0', STR_PAD_LEFT));
        
        $db->commit();
        
        header("Location: vente.php?message=Vente supprimée avec succès&type=success");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        header("Location: vente.php?message=Erreur lors de la suppression&type=error");
        exit();
    }
}

// ============================================
// MODIFIER UNE VENTE (page d'édition)
// ============================================
if ($action === 'modifier' && isset($_GET['id'])) {
    $vente_id = $_GET['id'];
    
    // Vérifier que l'utilisateur est admin
    $stmt = $db->prepare("SELECT role FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_role = $stmt->fetch()['role'];
    
    if ($user_role !== 'admin') {
        header("Location: vente.php?message=Action non autorisée&type=error");
        exit();
    }
    
    // Récupérer la vente
    $stmt = $db->prepare("SELECT * FROM ventes WHERE id = ?");
    $stmt->execute([$vente_id]);
    $vente_modif = $stmt->fetch();
    
    if (!$vente_modif) {
        header("Location: vente.php?message=Vente non trouvée&type=error");
        exit();
    }
    
    // Récupérer les lignes
    $stmt = $db->prepare("
        SELECT vl.*, m.nom as medicament_nom 
        FROM ventes_lignes vl
        LEFT JOIN medicaments m ON vl.medicament_id = m.id
        WHERE vl.vente_id = ?
    ");
    $stmt->execute([$vente_id]);
    $lignes_modif = $stmt->fetchAll();
    
    // Récupérer le vendeur
    $stmt = $db->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
    $stmt->execute([$vente_modif['vendeur_id']]);
    $vendeur = $stmt->fetch();
}

// ============================================
// TRAITEMENT DE LA MODIFICATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_vente'])) {
    $vente_id = $_POST['vente_id'];
    $client_nom = trim($_POST['client_nom']) ?: NULL;
    $notes = trim($_POST['notes']) ?: NULL;
    
    // Vérifier que l'utilisateur est admin
    $stmt = $db->prepare("SELECT role FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_role = $stmt->fetch()['role'];
    
    if ($user_role !== 'admin') {
        header("Location: vente.php?message=Action non autorisée&type=error");
        exit();
    }
    
    // Mettre à jour l'en-tête
    $stmt = $db->prepare("UPDATE ventes SET client_nom = ?, notes = ? WHERE id = ?");
    $stmt->execute([$client_nom, $notes, $vente_id]);
    
    logAction($db, $_SESSION['user_id'], 'modification_vente', 'Modification de la vente N° LG-' . str_pad($vente_id, 6, '0', STR_PAD_LEFT));
    
    header("Location: vente.php?message=Vente modifiée avec succès&type=success");
    exit();
}

// ============================================
// AJOUTER au panier (inchangé)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_panier'])) {
    $medicament_id = $_POST['medicament_id'];
    $quantite = intval($_POST['quantite']);
    
    // Récupérer info médicament
    $stmt = $db->prepare("SELECT id, nom, prix_vente, stock FROM medicaments WHERE id = ?");
    $stmt->execute([$medicament_id]);
    $medicament = $stmt->fetch();
    
    if (!$medicament) {
        header("Location: vente.php?action=nouvelle&message=Médicament non trouvé&type=error");
        exit();
    }
    
    if ($medicament['stock'] < $quantite) {
        header("Location: vente.php?action=nouvelle&message=Stock insuffisant pour {$medicament['nom']}&type=error");
        exit();
    }
    
    // Vérifier si déjà dans panier
    $found = false;
    foreach ($_SESSION['panier'] as &$item) {
        if ($item['id'] == $medicament_id) {
            $item['quantite'] += $quantite;
            $item['total'] = $item['quantite'] * $item['prix'];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $_SESSION['panier'][] = [
            'id' => $medicament['id'],
            'nom' => $medicament['nom'],
            'prix' => $medicament['prix_vente'],
            'quantite' => $quantite,
            'total' => $medicament['prix_vente'] * $quantite
        ];
    }
    
    header("Location: vente.php?action=nouvelle&message=Produit ajouté au panier&type=success");
    exit();
}

// SUPPRIMER du panier
if (isset($_GET['supprimer_panier'])) {
    $index = $_GET['supprimer_panier'];
    if (isset($_SESSION['panier'][$index])) {
        unset($_SESSION['panier'][$index]);
        $_SESSION['panier'] = array_values($_SESSION['panier']);
    }
    header("Location: vente.php?action=nouvelle");
    exit();
}

// VIDER le panier
if (isset($_GET['vider_panier'])) {
    $_SESSION['panier'] = [];
    header("Location: vente.php?action=nouvelle");
    exit();
}

// VALIDER la vente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_vente'])) {
    if (empty($_SESSION['panier'])) {
        header("Location: vente.php?action=nouvelle&message=Panier vide&type=error");
        exit();
    }
    
    $client_nom = trim($_POST['client_nom']) ?: NULL;
    $notes = trim($_POST['notes']) ?: NULL;
    $total_global = 0;
    
    // Vérifier les stocks
    foreach ($_SESSION['panier'] as $item) {
        $stmt = $db->prepare("SELECT stock FROM medicaments WHERE id = ?");
        $stmt->execute([$item['id']]);
        $stock = $stmt->fetch()['stock'];
        
        if ($stock < $item['quantite']) {
            header("Location: vente.php?action=nouvelle&message=Stock insuffisant pour {$item['nom']}&type=error");
            exit();
        }
        $total_global += $item['total'];
    }
    
    // Transaction
    $db->beginTransaction();
    
    try {
        // 1. Insérer l'en-tête
        $stmt = $db->prepare("
            INSERT INTO ventes (date_vente, client_nom, vendeur_id, total_global, notes)
            VALUES (NOW(), ?, ?, ?, ?)
        ");
        $stmt->execute([$client_nom, $_SESSION['user_id'], $total_global, $notes]);
        $vente_id = $db->lastInsertId();
        
        // 2. Insérer les lignes
        $stmt_ligne = $db->prepare("
            INSERT INTO ventes_lignes (vente_id, medicament_id, quantite, prix_unitaire, total_ligne)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt_stock = $db->prepare("UPDATE medicaments SET stock = stock - ? WHERE id = ?");
        
        foreach ($_SESSION['panier'] as $item) {
            $stmt_ligne->execute([
                $vente_id,
                $item['id'],
                $item['quantite'],
                $item['prix'],
                $item['total']
            ]);
            
            $stmt_stock->execute([$item['quantite'], $item['id']]);
            
            // Vérifier seuil d'alerte
            $stmt = $db->prepare("SELECT stock, seuil_alerte, nom FROM medicaments WHERE id = ?");
            $stmt->execute([$item['id']]);
            $med = $stmt->fetch();
            
            if ($med['stock'] <= $med['seuil_alerte']) {
                $message_alerte = $med['stock'] == 0 ? 
                    "Rupture de stock : {$med['nom']}" : 
                    "Stock bas : {$med['nom']} ({$med['stock']} restants)";
                
                $insert = $db->prepare("
                    INSERT INTO alertes (type, medicament_id, message, niveau)
                    VALUES ('stock_bas', ?, ?, ?)
                ");
                $niveau = $med['stock'] == 0 ? 'danger' : 'warning';
                $insert->execute([$item['id'], $message_alerte, $niveau]);
            }
        }
        
        $db->commit();
        $_SESSION['panier'] = [];
        
        // ✅ LOG : Enregistrement d'une vente
        logAction($db, $_SESSION['user_id'], 'vente', 'Vente enregistrée N° LG-' . str_pad($vente_id, 6, '0', STR_PAD_LEFT) . ' - Total: ' . number_format($total_global, 0, ',', ' ') . ' FCFA');
        
        header("Location: vente.php?action=facture&id={$vente_id}");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        header("Location: vente.php?action=nouvelle&message=Erreur lors de l'enregistrement&type=error");
        exit();
    }
}

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer les ventes pour la liste
if ($action === 'liste') {
    $date_debut = $_GET['date_debut'] ?? '2000-01-01';
    $date_fin = $_GET['date_fin'] ?? date('Y-m-d');
    
    $query = "
        SELECT 
            v.id,
            v.date_vente,
            v.client_nom,
            v.vendeur_id,
            v.total_global,
            v.notes,
            u.prenom as vendeur_nom,
            COUNT(DISTINCT vl.id) as nb_produits,
            GROUP_CONCAT(DISTINCT m.nom SEPARATOR ', ') as liste_produits
        FROM ventes v
        LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
        LEFT JOIN ventes_lignes vl ON v.id = vl.vente_id
        LEFT JOIN medicaments m ON vl.medicament_id = m.id
        WHERE DATE(v.date_vente) BETWEEN ? AND ?
        GROUP BY v.id, v.date_vente, v.client_nom, v.vendeur_id, v.total_global, v.notes, u.prenom
        ORDER BY v.date_vente DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$date_debut, $date_fin]);
    $ventes = $stmt->fetchAll();
    
    // Total période
    $total_query = "SELECT SUM(total_global) as total FROM ventes WHERE DATE(date_vente) BETWEEN ? AND ?";
    $stmt = $db->prepare($total_query);
    $stmt->execute([$date_debut, $date_fin]);
    $total_periode = $stmt->fetch()['total'] ?? 0;
}

// Médicaments pour le formulaire
$medicaments = $db->query("SELECT id, nom, prix_vente, stock FROM medicaments WHERE stock > 0 ORDER BY nom")->fetchAll();

// Récupérer facture
if ($action === 'facture' && isset($_GET['id'])) {
    $vente_id = $_GET['id'];
    
    // En-tête
    $query = "
        SELECT v.*, u.prenom as vendeur_prenom, u.nom as vendeur_nom
        FROM ventes v
        LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
        WHERE v.id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$vente_id]);
    $facture = $stmt->fetch();
    
    if ($facture) {
        // Lignes de facture
        $query_lignes = "
            SELECT vl.*, m.nom as medicament_nom, m.dci, m.forme, m.dosage
            FROM ventes_lignes vl
            LEFT JOIN medicaments m ON vl.medicament_id = m.id
            WHERE vl.vente_id = ?
        ";
        
        $stmt = $db->prepare($query_lignes);
        $stmt->execute([$vente_id]);
        $lignes_facture = $stmt->fetchAll();
    } else {
        header("Location: vente.php?message=Facture non trouvée&type=error");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventes - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --success-dark: #0d9488;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .pharmacie-info h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .pharmacie-info p {
            font-size: 13px;
            color: var(--gray);
        }
        
        .sidebar-nav {
            padding: 20px 0;
            flex: 1;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .nav-item:hover {
            background: #f8fafc;
            color: var(--primary);
        }
        
        .nav-item.active {
            background: #f0f9ff;
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 500;
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .user-info {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .user-details h4 {
            font-size: 15px;
            font-weight: 600;
        }
        
        .user-details p {
            font-size: 13px;
            color: var(--gray);
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        /* FILTRES */
        .filtres-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .filtres-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        
        /* TABLE */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--primary);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        /* STATS PERIODE */
        .stats-periode {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
        
        .stats-periode .total {
            font-size: 24px;
            font-weight: 700;
        }
        
        /* BADGE */
        .badge {
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        /* LAYOUT VENTE */
        .vente-layout {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
        }
        
        .selection-col, .panier-col {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .panier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .btn-remove {
            color: var(--danger);
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.2s;
        }
        
        .btn-remove:hover {
            background: #fee2e2;
        }
        
        /* STYLES SPÉCIFIQUES À LA FACTURE */
        .facture-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
            font-family: 'Arial', 'Helvetica', sans-serif;
        }
        
        .facture-entete {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px dashed var(--primary);
        }
        
        .facture-entete h1 {
            color: var(--primary);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 2px;
        }
        
        .facture-entete .sous-titre {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .facture-entete .facture-numero {
            background: var(--primary);
            color: white;
            display: inline-block;
            padding: 8px 25px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(59, 130, 246, 0.3);
        }
        
        .facture-infos {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .info-gauche, .info-droite {
            flex: 1;
        }
        
        .info-gauche p, .info-droite p {
            margin: 8px 0;
            color: var(--dark);
        }
        
        .info-gauche strong, .info-droite strong {
            color: var(--primary);
            width: 100px;
            display: inline-block;
        }
        
        .facture-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            font-size: 14px;
        }
        
        .facture-table thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .facture-table th {
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .facture-table th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .facture-table th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .facture-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .facture-table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .facture-total-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: right;
            margin: 20px 0 30px;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
        
        .facture-total-box .total-label {
            font-size: 16px;
            opacity: 0.9;
            margin-right: 15px;
        }
        
        .facture-total-box .total-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .facture-notes {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--warning);
            margin: 20px 0;
            font-style: italic;
        }
        
        .facture-notes strong {
            color: var(--warning);
            margin-right: 10px;
        }
        
        .facture-pied {
            margin-top: 40px;
            text-align: center;
            color: var(--gray);
            font-size: 12px;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }
        
        .facture-pied p {
            margin: 5px 0;
        }
        
        .facture-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-print {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
            text-decoration: none;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
        }
        
        .btn-retour {
            background: white;
            color: var(--dark);
            padding: 12px 30px;
            border: 1px solid var(--border);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-size: 16px;
            text-decoration: none;
        }
        
        .btn-retour:hover {
            background: #f8fafc;
        }
        
        /* Styles pour l'impression */
        @media print {
            body {
                background-color: white;
                margin: 0;
                padding: 15px;
            }
            
            .sidebar, .page-header, .facture-actions, .user-info, .nav-item, .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .facture-container {
                box-shadow: none;
                padding: 15px;
                max-width: 100%;
            }
            
            .facture-entete h1 {
                color: black;
            }
            
            .facture-table thead {
                background: #f0f0f0 !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .facture-total-box {
                background: #f0f0f0 !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .facture-total-box .total-value {
                color: black;
            }
            
            .facture-infos {
                border-left: 4px solid #000;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .pharmacie-info h2,
            .sidebar .nav-text,
            .sidebar .user-details {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
            .filtres-form,
            .vente-layout {
                grid-template-columns: 1fr;
            }
            .facture-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="pharmacie-info">
                    <h2><i class="fas fa-pills"></i> LG PHARMA</h2>
                    <p>Gestion pharmaceutique</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="indexs.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span class="nav-text">Tableau de bord</span>
                </a>
                <a href="medicament.php" class="nav-item">
                    <i class="fas fa-capsules"></i>
                    <span class="nav-text">Médicaments</span>
                </a>
                <a href="vente.php" class="nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Ventes</span>
                </a>
                <a href="rapport.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span class="nav-text">Rapports</span>
                </a>
                <a href="profiles.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    <span class="nav-text">Mon profil</span>
                </a>
                <a href="parametre.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Paramètres</span>
                </a>
                <a href="utilisateur.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Utilisateurs</span>
                </a>
                <a href="journals.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span class="nav-text">Journal</span>
                </a>
                <a href="alerte.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span class="nav-text">Alertes</span>
                </a>
            </nav>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['prenom'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user['prenom']); ?></h4>
                    <p><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
                <a href="../deconnexion.php" title="Déconnexion" style="margin-left: auto; color: #666;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>
        
        <main class="main-content">
            <?php if ($action === 'liste'): ?>
                <!-- PAGE LISTE -->
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-shopping-cart"></i> Ventes</h1>
                    </div>
                    <div class="page-actions">
                        <a href="vente.php?action=nouvelle" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouvelle vente
                        </a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $type === 'error' ? 'error' : 'success'; ?>">
                        <i class="fas <?php echo $type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- FILTRES -->
                <div class="filtres-container no-print">
                    <form method="GET" class="filtres-form">
                        <input type="hidden" name="action" value="liste">
                        <div class="form-group">
                            <label>Date début</label>
                            <input type="date" name="date_debut" class="form-control" value="<?php echo $date_debut; ?>">
                        </div>
                        <div class="form-group">
                            <label>Date fin</label>
                            <input type="date" name="date_fin" class="form-control" value="<?php echo $date_fin; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                    </form>
                </div>
                
                <!-- STATS -->
                <div class="stats-periode no-print">
                    <div>
                        <div>Période: <?php echo date('d/m/Y', strtotime($date_debut)); ?> - <?php echo date('d/m/Y', strtotime($date_fin)); ?></div>
                        <div><?php echo count($ventes); ?> vente(s)</div>
                    </div>
                    <div class="total"><?php echo number_format($total_periode, 0, ',', ' '); ?> FCFA</div>
                </div>
                
                <!-- TABLEAU AVEC BOUTONS MODIFIER ET SUPPRIMER -->
                <div class="table-container">
                     <table>
                        <thead>
                             <tr>
                                <th>Date</th>
                                <th>N° Facture</th>
                                <th>Produits</th>
                                <th>Total</th>
                                <th>Client</th>
                                <th>Vendeur</th>
                                <th>Actions</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventes)): ?>
                                 <tr><td colspan="7" style="text-align: center; padding: 40px;">Aucune vente</td></tr>
                            <?php else: ?>
                                <?php foreach ($ventes as $vente): ?>
                                     <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($vente['date_vente'])); ?></td>
                                        <td><strong>LG-<?php echo str_pad($vente['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td>
                                            <?php if ($vente['nb_produits'] > 0): ?>
                                                <span class="badge"><?php echo $vente['nb_produits']; ?> produit(s)</span>
                                                <?php if (!empty($vente['liste_produits'])): ?>
                                                    <br><small style="color: var(--gray);"><?php echo htmlspecialchars(substr($vente['liste_produits'], 0, 30)); ?>...</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge" style="background: var(--gray);">1 produit</span>
                                                <br><small style="color: var(--gray);">Ancienne vente</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo number_format($vente['total_global'], 0, ',', ' '); ?> FCFA</strong></td>
                                        <td><?php echo $vente['client_nom'] ?? '--'; ?></td>
                                        <td><?php echo $vente['vendeur_nom']; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="vente.php?action=facture&id=<?php echo $vente['id']; ?>" class="btn" style="background: #f1f5f9;" title="Facture">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                <a href="vente.php?action=modifier&id=<?php echo $vente['id']; ?>" class="btn" style="background: #f0f9ff; color: #2563eb;" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="if(confirm('⚠️ Supprimer cette vente ? Les stocks seront remis à leur état précédent.')) window.location.href='vente.php?delete_vente=<?php echo $vente['id']; ?>'" class="btn" style="background: #fee2e2; color: #dc2626;" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                     </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                     </table>
                </div>
                
            <?php elseif ($action === 'modifier' && isset($vente_modif)): ?>
                <!-- PAGE MODIFICATION VENTE -->
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> Modifier la vente</h1>
                    </div>
                    <div class="page-actions">
                        <a href="vente.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $type === 'error' ? 'error' : 'success'; ?>">
                        <i class="fas <?php echo $type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container" style="max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <form method="POST" action="">
                        <input type="hidden" name="vente_id" value="<?php echo $vente_modif['id']; ?>">
                        
                        <div class="form-group">
                            <label><i class="fas fa-receipt"></i> N° Facture</label>
                            <input type="text" class="form-control" value="LG-<?php echo str_pad($vente_modif['id'], 6, '0', STR_PAD_LEFT); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date</label>
                            <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($vente_modif['date_vente'])); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Vendeur</label>
                            <input type="text" class="form-control" value="<?php echo $vendeur['prenom'] . ' ' . $vendeur['nom']; ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Client</label>
                            <input type="text" name="client_nom" class="form-control" value="<?php echo htmlspecialchars($vente_modif['client_nom'] ?? ''); ?>" placeholder="Nom du client (optionnel)">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Notes supplémentaires..."><?php echo htmlspecialchars($vente_modif['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="box-header" style="margin-top: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                            <h3 class="box-title"><i class="fas fa-list"></i> Produits vendus</h3>
                        </div>
                        
                        <div class="table-container" style="margin-bottom: 20px;">
                            <table class="simple-table" style="width: 100%;">
                                <thead>
                                     <tr>
                                        <th>Médicament</th>
                                        <th style="text-align: center;">Quantité</th>
                                        <th style="text-align: right;">Prix unitaire</th>
                                        <th style="text-align: right;">Total</th>
                                     </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignes_modif as $ligne): ?>
                                      <tr>
                                          <td><strong><?php echo htmlspecialchars($ligne['medicament_nom']); ?></strong></td>
                                          <td style="text-align: center;"><?php echo $ligne['quantite']; ?></td>
                                          <td style="text-align: right;"><?php echo number_format($ligne['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                                          <td style="text-align: right;"><?php echo number_format($ligne['total_ligne'], 0, ',', ' '); ?> FCFA</td>
                                      </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                     <tr style="background: #f8fafc;">
                                         <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL</td>
                                         <td style="text-align: right; font-weight: bold; color: var(--primary);"><?php echo number_format($vente_modif['total_global'], 0, ',', ' '); ?> FCFA</td>
                                      </tr>
                                </tfoot>
                             </table>
                        </div>
                        
                        <div class="form-actions" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                            <a href="vente.php" class="btn btn-outline">Annuler</a>
                            <button type="submit" name="modifier_vente" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 8px;">
                        <p><strong>⚠️ Note importante :</strong></p>
                        <p>Pour modifier les produits vendus, supprimez cette vente et créez-en une nouvelle avec les bons produits.</p>
                    </div>
                </div>
                
            <?php elseif ($action === 'nouvelle'): ?>
                <!-- PAGE NOUVELLE VENTE (inchangée) -->
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-cash-register"></i> Nouvelle vente</h1>
                    </div>
                    <div class="page-actions">
                        <a href="vente.php" class="btn" style="background: #f1f5f9;">Annuler</a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <div class="vente-layout">
                    <!-- COLONNE AJOUT -->
                    <div class="selection-col">
                        <h3><i class="fas fa-plus-circle"></i> Ajouter un produit</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Médicament</label>
                                <select name="medicament_id" id="medicament_id" class="form-control" required>
                                    <option value="">Choisir...</option>
                                    <?php foreach ($medicaments as $med): ?>
                                        <option value="<?php echo $med['id']; ?>" 
                                                data-prix="<?php echo $med['prix_vente']; ?>"
                                                data-stock="<?php echo $med['stock']; ?>">
                                            <?php echo $med['nom']; ?> - <?php echo number_format($med['prix_vente'], 0, ',', ' '); ?> FCFA (Stock: <?php echo $med['stock']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label>Quantité</label>
                                    <input type="number" name="quantite" id="quantite" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label>Prix unitaire</label>
                                    <input type="text" id="prix_unitaire" class="form-control" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Stock disponible</label>
                                <input type="text" id="stock_disponible" class="form-control" readonly>
                            </div>
                            
                            <button type="submit" name="ajouter_panier" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-cart-plus"></i> Ajouter au panier
                            </button>
                        </form>
                    </div>
                    
                    <!-- COLONNE PANIER -->
                    <div class="panier-col">
                        <div class="panier-header">
                            <h3><i class="fas fa-shopping-cart"></i> Panier (<?php echo count($_SESSION['panier']); ?>)</h3>
                            <?php if (!empty($_SESSION['panier'])): ?>
                                <a href="vente.php?action=nouvelle&vider_panier=1" class="btn" style="background: #fee2e2; color: var(--danger);">Vider</a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($_SESSION['panier'])): ?>
                            <p style="text-align: center; padding: 40px; color: var(--gray);">Panier vide</p>
                        <?php else: ?>
                            <table style="width: 100%; margin-bottom: 20px;">
                                <thead>
                                     <tr>
                                        <th>Produit</th>
                                        <th>Qté</th>
                                        <th>Total</th>
                                        <th></th>
                                     </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_panier = 0;
                                    foreach ($_SESSION['panier'] as $index => $item): 
                                        $total_panier += $item['total'];
                                    ?>
                                         <tr>
                                            <td><?php echo $item['nom']; ?></td>
                                            <td><?php echo $item['quantite']; ?></td>
                                            <td><?php echo number_format($item['total'], 0, ',', ' '); ?> FCFA</td>
                                            <td>
                                                <a href="vente.php?action=nouvelle&supprimer_panier=<?php echo $index; ?>" class="btn-remove">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </td>
                                         </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                     <tr>
                                        <td colspan="2" style="text-align: right; font-weight: bold;">TOTAL</td>
                                        <td style="font-weight: bold; color: var(--primary);"><?php echo number_format($total_panier, 0, ',', ' '); ?> FCFA</td>
                                        <td></td>
                                     </tr>
                                </tfoot>
                             </table>
                            
                            <form method="POST">
                                <div class="form-group">
                                    <label>Client (optionnel)</label>
                                    <input type="text" name="client_nom" class="form-control" placeholder="Nom du client">
                                </div>
                                <div class="form-group">
                                    <label>Notes (optionnel)</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Notes..."></textarea>
                                </div>
                                <button type="submit" name="valider_vente" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-check-circle"></i> Valider la vente
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <script>
                    function calculerTotal() {
                        var select = document.getElementById('medicament_id');
                        var option = select.options[select.selectedIndex];
                        var quantite = document.getElementById('quantite').value;
                        
                        if (option.value) {
                            var prix = parseFloat(option.getAttribute('data-prix')) || 0;
                            var stock = parseInt(option.getAttribute('data-stock')) || 0;
                            
                            document.getElementById('prix_unitaire').value = prix.toLocaleString('fr-FR') + ' FCFA';
                            document.getElementById('stock_disponible').value = stock + ' unités';
                            
                            if (quantite > stock) {
                                document.getElementById('quantite').style.borderColor = 'var(--danger)';
                            } else {
                                document.getElementById('quantite').style.borderColor = '';
                            }
                        }
                    }
                    
                    document.getElementById('medicament_id').addEventListener('change', calculerTotal);
                    document.getElementById('quantite').addEventListener('input', calculerTotal);
                    calculerTotal();
                </script>
                
            <?php elseif ($action === 'facture' && isset($facture)): ?>
                <!-- PAGE FACTURE (inchangée) -->
                <div class="facture-container">
                    <div class="facture-entete">
                        <h1>LG PHARMA</h1>
                        <div class="sous-titre">
                            <i class="fas fa-map-marker-alt"></i> Binanga Numero 32, Kinshasa<br>
                            <i class="fas fa-phone"></i> Tél: +243 0812475527
                        </div>
                        <div class="facture-numero">
                            FACTURE N° LG-<?php echo str_pad($facture['id'], 6, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                    
                    <div class="facture-infos">
                        <div class="info-gauche">
                            <p><strong><i class="fas fa-calendar"></i> Date:</strong> <?php echo date('d/m/Y', strtotime($facture['date_vente'])); ?></p>
                            <p><strong><i class="fas fa-clock"></i> Heure:</strong> <?php echo date('H:i', strtotime($facture['date_vente'])); ?></p>
                            <p><strong><i class="fas fa-user"></i> Vendeur:</strong> <?php echo $facture['vendeur_prenom'] . ' ' . $facture['vendeur_nom']; ?></p>
                        </div>
                        <div class="info-droite">
                            <?php if ($facture['client_nom']): ?>
                                <p><strong><i class="fas fa-user-tie"></i> Client:</strong> <?php echo $facture['client_nom']; ?></p>
                            <?php endif; ?>
                            <p><strong><i class="fas fa-hashtag"></i> N° Facture:</strong> LG-<?php echo str_pad($facture['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                    
                    <table class="facture-table">
                        <thead>
                             <tr>
                                <th>Désignation</th>
                                <th style="text-align: center;">Qté</th>
                                <th style="text-align: right;">Prix unitaire</th>
                                <th style="text-align: right;">Total</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes_facture as $ligne): ?>
                             <tr>
                                <td>
                                    <strong><?php echo $ligne['medicament_nom']; ?></strong><br>
                                    <small style="color: var(--gray);">
                                        <?php echo $ligne['forme'] . ' ' . $ligne['dosage']; ?>
                                        <?php if ($ligne['dci']): ?> - <?php echo $ligne['dci']; ?><?php endif; ?>
                                    </small>
                                </td>
                                <td style="text-align: center;"><?php echo $ligne['quantite']; ?></td>
                                <td style="text-align: right;"><?php echo number_format($ligne['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($ligne['total_ligne'], 0, ',', ' '); ?> FCFA</td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </table>
                    
                    <div class="facture-total-box">
                        <span class="total-label">TOTAL À PAYER:</span>
                        <span class="total-value"><?php echo number_format($facture['total_global'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                    
                    <?php if ($facture['notes']): ?>
                    <div class="facture-notes">
                        <strong><i class="fas fa-sticky-note"></i> Notes:</strong> <?php echo $facture['notes']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="facture-pied">
                        <p><i class="fas fa-check-circle" style="color: var(--success);"></i> Cette facture tient lieu de justificatif de paiement</p>
                        <p>Merci de votre confiance et à bientôt dans votre pharmacie LG PHARMA</p>
                        <p style="margin-top: 10px;">Pharmacienne: Jeanne Ngbo</p>
                    </div>
                    
                    <div class="facture-actions no-print">
                        <button onclick="window.print()" class="btn-print">
                            <i class="fas fa-print"></i> Imprimer la facture
                        </button>
                        <a href="vente.php" class="btn-retour">
                            <i class="fas fa-arrow-left"></i> Retour aux ventes
                        </a>
                        <a href="vente.php?action=nouvelle" class="btn-print" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                            <i class="fas fa-plus"></i> Nouvelle vente
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>