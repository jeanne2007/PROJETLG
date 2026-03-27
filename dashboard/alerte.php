<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();

// Vérifier que l'utilisateur est admin
$stmt = $db->prepare("SELECT role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    header("Location: indexs.php");
    exit();
}

// Récupérer toutes les alertes (sans limite)
$alertes_stock_bas = getAlertesStockBas($db, 100); // 100 = pas de limite
$alertes_peremption = getAlertesPeremption($db, 30, 100); // 30 jours, pas de limite
$liste_reappro = getListeReapprovisionnement($db);

// Récupérer infos utilisateur pour la sidebar
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_info = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
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
        }
        
        .pharmacie-info p {
            font-size: 13px;
            color: var(--gray);
        }
        
        .sidebar-nav {
            padding: 20px 0;
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
        
        .nav-item:hover, .nav-item.active {
            background: #f0f9ff;
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .user-info {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            position: absolute;
            bottom: 0;
            width: 100%;
            background: white;
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
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
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
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-print {
            background: white;
            color: var(--dark);
            border: 1px solid var(--border);
        }
        
        .dashboard-box {
            background: white;
            padding: 24px;
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }
        
        .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .box-title {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px 0;
            color: var(--gray);
            font-weight: 500;
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .total-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: right;
            font-weight: 600;
        }
        
        @media print {
            .sidebar, .page-header, .btn, .btn-print {
                display: none;
            }
            .main-content {
                margin-left: 0;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="pharmacie-info">
                    <h2>LG PHARMA</h2>
                    <p>Gestion pharmaceutique</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="indexs.php" class="nav-item">
                    <i class="fas fa-home"></i> <span class="nav-text">Tableau de bord</span>
                </a>
                <a href="medicament.php" class="nav-item">
                    <i class="fas fa-capsules"></i> <span class="nav-text">Médicaments</span>
                </a>
                <a href="vente.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i> <span class="nav-text">Ventes</span>
                </a>
                <a href="rapport.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i> <span class="nav-text">Rapports</span>
                </a>
                <a href="profiles.php" class="nav-item">
                    <i class="fas fa-user-cog"></i> <span class="nav-text">Mon profil</span>
                </a>
                <a href="parametre.php" class="nav-item">
                    <i class="fas fa-cog"></i> <span class="nav-text">Paramètres</span>
                </a>
                <a href="utilisateur.php" class="nav-item">
                    <i class="fas fa-users"></i> <span class="nav-text">Utilisateurs</span>
                </a>
                <a href="journals.php" class="nav-item">
                    <i class="fas fa-history"></i> <span class="nav-text">Journal</span>
                </a>
                <a href="alerte.php" class="nav-item active">
                    <i class="fas fa-bell"></i> <span class="nav-text">Alertes</span>
                </a>
            </nav>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_info['prenom'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user_info['prenom']); ?></h4>
                    <p><?php echo htmlspecialchars($user_info['role']); ?></p>
                </div>
            </div>
        </aside>
        
        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-bell" style="color: var(--primary);"></i> Alertes intelligentes</h1>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="generer_liste_.php" class="btn btn-primary" target="_blank">
                        <i class="fas fa-file-pdf"></i> Télécharger la liste d'achat
                    </a>
                    <button onclick="window.print()" class="btn btn-outline">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
            </div>
            
            <!-- ALERTES STOCK BAS -->
            <div class="dashboard-box">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                        Stock bas
                    </h3>
                    <span class="badge badge-warning"><?php echo count($alertes_stock_bas); ?> médicaments</span>
                </div>
                
                <?php if (empty($alertes_stock_bas)): ?>
                    <p style="color: var(--success);"><i class="fas fa-check-circle"></i> Aucun stock critique</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Médicament</th>
                                <th>Stock actuel</th>
                                <th>Seuil</th>
                                <th>Manque</th>
                                <th>Fournisseur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertes_stock_bas as $med): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($med['nom']); ?></strong></td>
                                <td><span class="badge badge-warning"><?php echo $med['stock']; ?></span></td>
                                <td><?php echo $med['seuil_alerte']; ?></td>
                                <td><strong style="color: var(--danger);"><?php echo abs($med['quantite_manquante']); ?></strong></td>
                                <td><?php echo htmlspecialchars($med['fournisseur'] ?? 'Non renseigné'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            
            <!-- LISTE DE RÉAPPROVISIONNEMENT -->
            <div class="dashboard-box">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-truck"></i>
                        Liste de réapprovisionnement
                    </h3>
                    <span class="badge badge-primary"><?php echo count($liste_reappro); ?> articles</span>
                </div>
                
                <?php if (empty($liste_reappro)): ?>
                    <p style="color: var(--success);"><i class="fas fa-check-circle"></i> Aucun besoin de réapprovisionnement</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Médicament</th>
                                <th>Stock</th>
                                <th>Seuil</th>
                                <th>À commander</th>
                                <th>Fournisseur</th>
                                <th>Prix unitaire</th>
                                <th>Coût total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_cout = 0;
                            $total_articles = 0;
                            foreach ($liste_reappro as $med): 
                                $total_articles += $med['quantite_a_commander'];
                                $total_cout += $med['cout_total_estime'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($med['nom']); ?></strong></td>
                                <td><span class="badge badge-warning"><?php echo $med['stock']; ?></span></td>
                                <td><?php echo $med['seuil_alerte']; ?></td>
                                <td><strong style="color: var(--danger);"><?php echo abs($med['quantite_a_commander']); ?></strong></td>
                                <td><?php echo htmlspecialchars($med['fournisseur'] ?? 'Non renseigné'); ?></td>
                                <td><?php echo number_format($med['prix_achat'], 0, ',', ' '); ?> FCFA</td>
                                <td><?php echo number_format($med['cout_total_estime'], 0, ',', ' '); ?> FCFA</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="total-box">
                        <p>Total articles à commander : <strong><?php echo $total_articles; ?></strong></p>
                        <p>Coût total estimé : <strong><?php echo number_format($total_cout, 0, ',', ' '); ?> FCFA</strong></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- BOUTON RETOUR -->
            <div style="text-align: center; margin-top: 20px;">
                <a href="indexs.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>
        </main>
    </div>
</body>
</html>