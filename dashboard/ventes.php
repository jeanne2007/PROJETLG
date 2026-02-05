<?php
session_start();

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

// Traitement nouvelle vente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_vente'])) {
    $medicament_id = $_POST['medicament_id'];
    $quantite = intval($_POST['quantite']);
    $client_nom = trim($_POST['client_nom']) ?: NULL;
    $notes = trim($_POST['notes']) ?: NULL;
    
    // Récupérer info médicament
    $stmt = $db->prepare("SELECT prix_vente, stock FROM medicaments WHERE id = ?");
    $stmt->execute([$medicament_id]);
    $medicament = $stmt->fetch();
    
    if (!$medicament) {
        header("Location: ventes.php?action=nouvelle&message=Médicament non trouvé&type=error");
        exit();
    }
    
    if ($medicament['stock'] < $quantite) {
        header("Location: ventes.php?action=nouvelle&message=Stock insuffisant&type=error");
        exit();
    }
    
    // Calculer total
    $prix_unitaire = $medicament['prix_vente'];
    $total = $prix_unitaire * $quantite;
    
    // Enregistrer la vente
    $stmt = $db->prepare("
        INSERT INTO ventes (medicament_id, quantite, prix_unitaire, total, client_nom, vendeur_id, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $medicament_id,
        $quantite,
        $prix_unitaire,
        $total,
        $client_nom,
        $_SESSION['user_id'],
        $notes
    ]);
    
    if ($success) {
        // Mettre à jour le stock
        $new_stock = $medicament['stock'] - $quantite;
        $update = $db->prepare("UPDATE medicaments SET stock = ? WHERE id = ?");
        $update->execute([$new_stock, $medicament_id]);
        
        // Vérifier si besoin d'alerte
        $stmt = $db->prepare("SELECT seuil_alerte FROM medicaments WHERE id = ?");
        $stmt->execute([$medicament_id]);
        $seuil = $stmt->fetch()['seuil_alerte'];
        
        if ($new_stock <= $seuil) {
            $message_alerte = $new_stock == 0 ? 
                "Rupture de stock : {$medicament['nom']}" : 
                "Stock bas : {$medicament['nom']} ({$new_stock} unités restantes)";
            
            $insert = $db->prepare("
                INSERT INTO alertes (type, medicament_id, message, niveau)
                VALUES ('stock_bas', ?, ?, ?)
            ");
            $niveau = $new_stock == 0 ? 'danger' : 'warning';
            $insert->execute([$medicament_id, $message_alerte, $niveau]);
        }
        
        $vente_id = $db->lastInsertId();
        header("Location: ventes.php?action=facture&id={$vente_id}");
        exit();
    }
}

// Traitement vente rapide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vente_rapide'])) {
    $medicament_id = $_POST['medicament_id_rapide'];
    $quantite = 1; // Vente rapide = 1 unité
    
    $stmt = $db->prepare("SELECT prix_vente, stock, nom FROM medicaments WHERE id = ?");
    $stmt->execute([$medicament_id]);
    $medicament = $stmt->fetch();
    
    if ($medicament && $medicament['stock'] >= $quantite) {
        $total = $medicament['prix_vente'] * $quantite;
        
        $stmt = $db->prepare("
            INSERT INTO ventes (medicament_id, quantite, prix_unitaire, total, vendeur_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$medicament_id, $quantite, $medicament['prix_vente'], $total, $_SESSION['user_id']])) {
            // Mettre à jour stock
            $new_stock = $medicament['stock'] - 1;
            $update = $db->prepare("UPDATE medicaments SET stock = ? WHERE id = ?");
            $update->execute([$new_stock, $medicament_id]);
            
            header("Location: ventes.php?message=Vente rapide enregistrée&type=success");
            exit();
        }
    }
}

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer les ventes pour la liste
if ($action === 'liste') {
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
    $date_fin = $_GET['date_fin'] ?? date('Y-m-d');
    
    $query = "
        SELECT v.*, m.nom as medicament_nom, u.prenom as vendeur_nom
        FROM ventes v
        LEFT JOIN medicaments m ON v.medicament_id = m.id
        LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
        WHERE DATE(v.date_vente) BETWEEN ? AND ?
        ORDER BY v.date_vente DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$date_debut, $date_fin]);
    $ventes = $stmt->fetchAll();
    
    // Calculer total période
    $total_query = "SELECT SUM(total) as total FROM ventes WHERE DATE(date_vente) BETWEEN ? AND ?";
    $stmt = $db->prepare($total_query);
    $stmt->execute([$date_debut, $date_fin]);
    $total_periode = $stmt->fetch()['total'] ?? 0;
}

// Récupérer les médicaments pour les formulaires
$medicaments = $db->query("SELECT id, nom, prix_vente, stock FROM medicaments WHERE stock > 0 ORDER BY nom")->fetchAll();

// Récupérer facture
if ($action === 'facture') {
    $vente_id = $_GET['id'] ?? 0;
    
    $query = "
        SELECT v.*, m.nom as medicament_nom, m.dci, m.forme, m.dosage,
               u.prenom as vendeur_nom, u.nom as vendeur_nom_complet
        FROM ventes v
        LEFT JOIN medicaments m ON v.medicament_id = m.id
        LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
        WHERE v.id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$vente_id]);
    $facture = $stmt->fetch();
    
    if (!$facture) {
        header("Location: ventes.php?message=Facture non trouvée&type=error");
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
            --success: #10b981;
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
        
        /* SIDEBAR (identique) */
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
        
        .btn-large {
            padding: 15px 30px;
            font-size: 16px;
        }
        
        /* VENTE RAPIDE */
        .vente-rapide-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .vente-rapide-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vente-rapide-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            align-items: end;
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
            margin-bottom: 0;
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
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-periode .total {
            font-size: 24px;
            font-weight: 700;
        }
        
        /* FORMULAIRE NOUVELLE VENTE */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        /* FACTURE */
        .facture-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .facture-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
        }
        
        .facture-header h2 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .facture-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .facture-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
        }
        
        .facture-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .facture-table th {
            background: #f1f5f9;
            color: var(--dark);
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border);
        }
        
        .facture-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .facture-total {
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: right;
            font-size: 20px;
            font-weight: 700;
        }
        
        .facture-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .facture-container {
                box-shadow: none;
                padding: 0;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .pharmacie-info h2,
            .sidebar .nav-text,
            .sidebar .user-details h4,
            .sidebar .user-details p {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .form-row,
            .filtres-form,
            .vente-rapide-form {
                grid-template-columns: 1fr;
            }
            
            .facture-details {
                grid-template-columns: 1fr;
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
                <a href="index.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span class="nav-text">Tableau de bord</span>
                </a>
                <a href="medicaments.php" class="nav-item">
                    <i class="fas fa-capsules"></i>
                    <span class="nav-text">Médicaments</span>
                </a>
                <a href="ventes.php" class="nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Ventes</span>
                </a>
                <a href="rapports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span class="nav-text">Rapports</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    <span class="nav-text">Mon profil</span>
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
        
        <!-- CONTENU PRINCIPAL -->
        <main class="main-content">
            <?php if ($action === 'liste'): ?>
                <!-- PAGE LISTE DES VENTES -->
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-shopping-cart"></i> Ventes</h1>
                    </div>
                    <div class="page-actions">
                        <a href="ventes.php?action=nouvelle" class="btn btn-primary">
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
                
                <!-- VENTE RAPIDE -->
                <div class="vente-rapide-container no-print">
                    <div class="vente-rapide-title">
                        <i class="fas fa-bolt"></i> Vente rapide (1 unité)
                    </div>
                    <form method="POST" class="vente-rapide-form">
                        <div class="form-group">
                            <label>Sélectionner un médicament</label>
                            <select name="medicament_id_rapide" class="form-control" required>
                                <option value="">Choisir...</option>
                                <?php foreach ($medicaments as $med): ?>
                                    <option value="<?php echo $med['id']; ?>">
                                        <?php echo htmlspecialchars($med['nom']); ?> - 
                                        <?php echo number_format($med['prix_vente'], 0, ',', ' '); ?> FCFA 
                                        (Stock: <?php echo $med['stock']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="vente_rapide" class="btn btn-success">
                            <i class="fas fa-cash-register"></i> Vente rapide
                        </button>
                    </form>
                </div>
                
                <!-- FILTRES -->
                <div class="filtres-container no-print">
                    <form method="GET" class="filtres-form">
                        <input type="hidden" name="action" value="liste">
                        <div class="form-group">
                            <label>Date début</label>
                            <input type="date" name="date_debut" class="form-control"
                                   value="<?php echo htmlspecialchars($date_debut); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date fin</label>
                            <input type="date" name="date_fin" class="form-control"
                                   value="<?php echo htmlspecialchars($date_fin); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="height: 40px;">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- STATS PÉRIODE -->
                <div class="stats-periode no-print">
                    <div>
                        <div style="font-size: 14px;">Période : <?php echo date('d/m/Y', strtotime($date_debut)); ?> - <?php echo date('d/m/Y', strtotime($date_fin)); ?></div>
                        <div style="font-size: 12px; opacity: 0.9;"><?php echo count($ventes); ?> ventes</div>
                    </div>
                    <div class="total">
                        <?php echo number_format($total_periode, 0, ',', ' '); ?> FCFA
                    </div>
                </div>
                
                <!-- LISTE DES VENTES -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Médicament</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                                <th>Client</th>
                                <th>Vendeur</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventes)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-shopping-cart" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p style="color: var(--gray);">Aucune vente pour cette période</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ventes as $vente): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($vente['date_vente'])); ?></td>
                                        <td><?php echo htmlspecialchars($vente['medicament_nom']); ?></td>
                                        <td><?php echo $vente['quantite']; ?></td>
                                        <td><?php echo number_format($vente['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                                        <td><strong><?php echo number_format($vente['total'], 0, ',', ' '); ?> FCFA</strong></td>
                                        <td><?php echo htmlspecialchars($vente['client_nom'] ?? '--'); ?></td>
                                        <td><?php echo htmlspecialchars($vente['vendeur_nom']); ?></td>
                                        <td class="no-print">
                                            <a href="ventes.php?action=facture&id=<?php echo $vente['id']; ?>" 
                                               class="btn" style="padding: 5px 10px; background: #f1f5f9;">
                                                <i class="fas fa-receipt"></i> Facture
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($action === 'nouvelle'): ?>
                <!-- PAGE NOUVELLE VENTE -->
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-cash-register"></i> Nouvelle vente</h1>
                    </div>
                    <div class="page-actions">
                        <a href="ventes.php" class="btn" style="background: #f1f5f9; color: var(--dark);">
                            <i class="fas fa-arrow-left"></i> Annuler
                        </a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $type === 'error' ? 'error' : 'success'; ?>">
                        <i class="fas <?php echo $type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Sélectionner un médicament *</label>
                            <select name="medicament_id" id="medicament_id" class="form-control" required>
                                <option value="">Choisir un médicament...</option>
                                <?php foreach ($medicaments as $med): ?>
                                    <option value="<?php echo $med['id']; ?>" 
                                            data-prix="<?php echo $med['prix_vente']; ?>"
                                            data-stock="<?php echo $med['stock']; ?>">
                                        <?php echo htmlspecialchars($med['nom']); ?> - 
                                        <?php echo number_format($med['prix_vente'], 0, ',', ' '); ?> FCFA 
                                        (Stock: <?php echo $med['stock']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Quantité *</label>
                                <input type="number" name="quantite" id="quantite" class="form-control" 
                                       value="1" min="1" required oninput="calculerTotal()">
                            </div>
                            
                            <div class="form-group">
                                <label>Prix unitaire (FCFA)</label>
                                <input type="text" id="prix_unitaire" class="form-control" readonly>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stock disponible</label>
                                <input type="text" id="stock_disponible" class="form-control" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Total (FCFA)</label>
                                <input type="text" id="total_vente" class="form-control" readonly style="font-size: 18px; font-weight: bold;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nom du client (optionnel)</label>
                            <input type="text" name="client_nom" class="form-control" 
                                   placeholder="Nom du client">
                        </div>
                        
                        <div class="form-group">
                            <label>Notes (optionnel)</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Notes supplémentaires..."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="enregistrer_vente" class="btn btn-primary btn-large">
                                <i class="fas fa-check-circle"></i> Enregistrer la vente
                            </button>
                        </div>
                    </form>
                </div>
                
                <script>
                    function calculerTotal() {
                        var select = document.getElementById('medicament_id');
                        var selectedOption = select.options[select.selectedIndex];
                        var quantite = document.getElementById('quantite').value;
                        
                        if (selectedOption.value) {
                            var prix = parseFloat(selectedOption.getAttribute('data-prix')) || 0;
                            var stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                            
                            document.getElementById('prix_unitaire').value = prix.toLocaleString('fr-FR') + ' FCFA';
                            document.getElementById('stock_disponible').value = stock + ' unités';
                            
                            var total = prix * quantite;
                            document.getElementById('total_vente').value = total.toLocaleString('fr-FR') + ' FCFA';
                            
                            // Avertir si stock insuffisant
                            if (quantite > stock) {
                                document.getElementById('quantite').style.borderColor = 'var(--danger)';
                                document.getElementById('quantite').style.backgroundColor = '#fee2e2';
                            } else {
                                document.getElementById('quantite').style.borderColor = '';
                                document.getElementById('quantite').style.backgroundColor = '';
                            }
                        }
                    }
                    
                    document.getElementById('medicament_id').addEventListener('change', calculerTotal);
                    document.getElementById('quantite').addEventListener('input', calculerTotal);
                    
                    // Initialiser
                    calculerTotal();
                </script>
                
            <?php elseif ($action === 'facture' && $facture): ?>
                <!-- PAGE FACTURE -->
                <div class="facture-container">
                    <div class="facture-header">
                        <h2>LG PHARMA</h2>
                        <p>Binanga Numero 32, Kinshasa</p>
                        <p>Tél: +243 0812475527</p>
                        <h3 style="margin-top: 20px;">FACTURE</h3>
                    </div>
                    
                    <div class="facture-details">
                        <div class="facture-item">
                            <h4>Informations vente</h4>
                            <p><strong>N° Facture:</strong> LG-<?php echo str_pad($facture['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            <p><strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($facture['date_vente'])); ?></p>
                            <p><strong>Vendeur:</strong> <?php echo htmlspecialchars($facture['vendeur_nom_complet']); ?></p>
                        </div>
                        
                        <?php if ($facture['client_nom']): ?>
                        <div class="facture-item">
                            <h4>Client</h4>
                            <p><?php echo htmlspecialchars($facture['client_nom']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <table class="facture-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($facture['medicament_nom']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($facture['dci']); ?> - <?php echo htmlspecialchars($facture['forme'] . ' ' . $facture['dosage']); ?></small>
                                </td>
                                <td><?php echo $facture['quantite']; ?></td>
                                <td><?php echo number_format($facture['prix_unitaire'], 0, ',', ' '); ?> $</td>
                                <td><?php echo number_format($facture['total'], 0, ',', ' '); ?> $</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="facture-total">
                        TOTAL: <?php echo number_format($facture['total'], 0, ',', ' '); ?> $
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                        <p>Merci de votre confiance !</p>
                        <p><small>Pharmacienne: Jeanne Ngbo</small></p>
                    </div>
                    
                    <div class="facture-actions no-print">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                        <a href="ventes.php" class="btn" style="background: #f1f5f9; color: var(--dark);">
                            <i class="fas fa-arrow-left"></i> Retour aux ventes
                        </a>
                        <a href="ventes.php?action=nouvelle" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nouvelle vente
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>