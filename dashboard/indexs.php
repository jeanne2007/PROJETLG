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
$user_id = $_SESSION['user_id'];

// Récupérer infos utilisateur
$stmt = $db->prepare("SELECT prenom, nom, email, role, photo FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Si utilisateur n'existe plus
if (!$user) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Stocker le rôle en session
$_SESSION['user_role'] = $user['role'];

// Récupérer statistiques (pour admin seulement)
$total_medicaments = 0;
$stock_bas = 0;
$ventes_aujourdhui = 0;
$chiffre_affaires = 0;
$alertes_non_lues = 0;
$alertes = [];
$dernieres_ventes = [];

// Si l'utilisateur est admin, on récupère toutes les stats
if ($user['role'] === 'admin') {
    // 1. Nombre de médicaments
    $stmt = $db->query("SELECT COUNT(*) as total FROM medicaments");
    $total_medicaments = $stmt->fetch()['total'];

    // 2. Stock bas (inférieur au seuil)
    $stmt = $db->query("SELECT COUNT(*) as total FROM medicaments WHERE stock <= seuil_alerte AND stock > 0");
    $stock_bas = $stmt->fetch()['total'];

    // 3. Ventes aujourd'hui
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ventes WHERE DATE(date_vente) = ?");
    $stmt->execute([$today]);
    $ventes_aujourdhui = $stmt->fetch()['total'];

    // 4. Chiffre d'affaires aujourd'hui
    $stmt = $db->prepare("SELECT SUM(total_global) as total FROM ventes WHERE DATE(date_vente) = ?");
    $stmt->execute([$today]);
    $chiffre_affaires = $stmt->fetch()['total'] ?? 0;

    // 5. Alertes non lues
    $stmt = $db->query("SELECT COUNT(*) as total FROM alertes WHERE vue = FALSE");
    $alertes_non_lues = $stmt->fetch()['total'];

    // Récupérer dernières alertes
    $stmt = $db->query("
        SELECT a.*, m.nom as medicament_nom 
        FROM alertes a 
        LEFT JOIN medicaments m ON a.medicament_id = m.id 
        ORDER BY a.date_creation DESC 
        LIMIT 5
    ");
    $alertes = $stmt->fetchAll();

    // Récupérer dernières ventes
    $stmt = $db->query("
        SELECT v.*, 
               GROUP_CONCAT(DISTINCT m.nom SEPARATOR ', ') as medicaments,
               COUNT(vl.id) as nb_produits,
               u.prenom as vendeur_nom
        FROM ventes v
        LEFT JOIN ventes_lignes vl ON v.id = vl.vente_id
        LEFT JOIN medicaments m ON vl.medicament_id = m.id
        LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
        GROUP BY v.id
        ORDER BY v.date_vente DESC 
        LIMIT 5
    ");
    $dernieres_ventes = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* VARIABLES */
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
        
        /* RESET */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
            line-height: 1.6;
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
        
        /* MENU */
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
        
        /* USER INFO */
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
        
        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
        }
        
        /* TOP BAR */
        .top-bar {
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
        
        .welcome-message {
            color: var(--gray);
            font-size: 15px;
        }
        
        .date-display {
            background: white;
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 14px;
            color: var(--gray);
        }
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .stat-icon.blue { background: var(--primary); }
        .stat-icon.green { background: var(--success); }
        .stat-icon.orange { background: var(--warning); }
        .stat-icon.red { background: var(--danger); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* GRILLE ALERTES (2 colonnes) */
        .alertes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        /* BOXES */
        .dashboard-box {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
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
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* TABLE */
        .simple-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .simple-table th {
            text-align: left;
            padding: 12px 0;
            color: var(--gray);
            font-weight: 500;
            border-bottom: 1px solid var(--border);
        }
        
        .simple-table td {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .simple-table tr:last-child td {
            border-bottom: none;
        }
        
        /* BADGES */
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
        
        /* QUICK ACTIONS */
        .quick-actions {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        /* GRID 2 COLONNES */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1024px) {
            .two-columns, .alertes-grid {
                grid-template-columns: 1fr;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* LIEN VOIR TOUT */
        .voir-tout {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }
        .voir-tout:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="pharmacie-info">
                    <h2><i class=""></i> LG PHARMA</h2>
                    <p>Gestion pharmaceutique</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <!-- MENU POUR ADMIN -->
                <?php if ($user['role'] === 'admin'): ?>
                <a href="indexs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'indexs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span class="nav-text">Tableau de bord</span>
                </a>
                <a href="medicament.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'medicament.php' ? 'active' : ''; ?>">
                    <i class="fas fa-capsules"></i> <span class="nav-text">Médicaments</span>
                </a>
                <a href="vente.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'vente.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> <span class="nav-text">Ventes</span>
                </a>
                <a href="rapport.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rapport.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> <span class="nav-text">Rapports</span>
                </a>
                <a href="profiles.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profiles.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i> <span class="nav-text">Mon profil</span>
                </a>
                <a href="parametre.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'parametre.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> <span class="nav-text">Paramètres</span>
                </a>
                <a href="utilisateur.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'utilisateur.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> <span class="nav-text">Utilisateurs</span>
                </a>
                <a href="journals.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'journals.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> <span class="nav-text">Journal</span>
                </a>
                <a href="alerte.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'alerte.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i> <span class="nav-text">Alertes</span>
                </a>
                
                <!-- MENU POUR EMPLOYÉ (Sophie) -->
                <?php elseif ($user['role'] === 'employe'): ?>
                <a href="vente.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'vente.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> <span class="nav-text">Ventes</span>
                </a>
                <a href="profiles.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profiles.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i> <span class="nav-text">Mon profil</span>
                </a>
                <?php endif; ?>
            </nav>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    // Afficher la photo ou les initiales
                    if (!empty($user['photo']) && file_exists('../assets/images/profiles/' . $user['photo'])) {
                        echo '<img src="../assets/images/profiles/' . $user['photo'] . '" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">';
                    } else {
                        echo strtoupper(substr($user['prenom'], 0, 1));
                    }
                    ?>
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
            <!-- Barre du haut -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>
                        <i class="fas fa-home"></i>
                        Tableau de bord
                    </h1>
                    <div class="welcome-message">
                        Bonjour, <?php echo htmlspecialchars($user['prenom']); ?> ! Bienvenue sur LG Pharma
                    </div>
                </div>
                <div class="date-display">
                    <i class="fas fa-calendar-alt"></i>
                    <?php 
                    setlocale(LC_TIME, 'fr_FR.utf8', 'fra');
                    echo strftime('%A %d %B %Y');
                    ?> - Kinshasa
                </div>
            </div>
            
            <!-- CONTENU POUR ADMIN -->
            <?php if ($user['role'] === 'admin'): ?>
            
            <!-- Cartes de statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $total_medicaments; ?></div>
                            <div class="stat-label">Médicaments</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-capsules"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $stock_bas; ?></div>
                            <div class="stat-label">Stock bas</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $ventes_aujourdhui; ?></div>
                            <div class="stat-label">Ventes aujourd'hui</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($chiffre_affaires, 0, ',', ' '); ?> FCFA</div>
                            <div class="stat-label">Chiffre d'affaires</div>
                        </div>
                        <div class="stat-icon red">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION ALERTES INTELLIGENTES -->
            <?php
            $alertes_stock_bas = getAlertesStockBas($db, 5);
            $alertes_peremption = getAlertesPeremption($db, 30, 5);
            ?>
            
            <div class="alertes-grid">
                <!-- Alertes stock bas -->
                <div class="dashboard-box">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                            Stock bas
                        </h3>
                        <a href="alerte.php" class="voir-tout">Voir tout →</a>
                    </div>
                    
                    <?php if (empty($alertes_stock_bas)): ?>
                        <p style="color: var(--success);"><i class="fas fa-check-circle"></i> Aucun stock critique</p>
                    <?php else: ?>
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Médicament</th>
                                    <th>Stock</th>
                                    <th>Seuil</th>
                                    <th>Manque</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alertes_stock_bas as $med): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($med['nom']); ?></td>
                                    <td><span class="badge badge-warning"><?php echo $med['stock']; ?></span></td>
                                    <td><?php echo $med['seuil_alerte']; ?></td>
                                    <td><strong style="color: var(--danger);"><?php echo abs($med['quantite_manquante']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Alertes péremption -->
                <div class="dashboard-box">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-calendar-times" style="color: var(--danger);"></i>
                            Péremption imminente
                        </h3>
                        <a href="alerte.php" class="voir-tout">Voir tout →</a>
                    </div>
                    
                    <?php if (empty($alertes_peremption)): ?>
                        <p style="color: var(--success);"><i class="fas fa-check-circle"></i> Aucune péremption critique</p>
                    <?php else: ?>
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Médicament</th>
                                    <th>Stock</th>
                                    <th>Péremption</th>
                                    <th>Jours restants</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alertes_peremption as $med): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($med['nom']); ?></td>
                                    <td><?php echo $med['stock']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($med['date_peremption'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $med['jours_restants'] <= 7 ? 'badge-danger' : 'badge-warning'; ?>">
                                            <?php echo $med['jours_restants']; ?> jours
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="quick-actions">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Actions rapides
                </h2>
                <div class="actions-grid">
                    <a href="medicament.php?action=ajouter" class="action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <div class="action-text">
                            <h3>Ajouter médicament</h3>
                            <p>Nouveau produit en stock</p>
                        </div>
                    </a>
                    
                    <a href="vente.php?action=nouvelle" class="action-btn">
                        <i class="fas fa-cash-register"></i>
                        <div class="action-text">
                            <h3>Nouvelle vente</h3>
                            <p>Enregistrer une vente</p>
                        </div>
                    </a>
                    
                    <a href="rapport.php" class="action-btn">
                        <i class="fas fa-file-invoice"></i>
                        <div class="action-text">
                            <h3>Voir rapports</h3>
                            <p>Statistiques et analyses</p>
                        </div>
                    </a>
                    
                    <a href="profiles.php" class="action-btn">
                        <i class="fas fa-user-edit"></i>
                        <div class="action-text">
                            <h3>Mon profil</h3>
                            <p>Modifier mes informations</p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Deux colonnes : Alertes (anciennes) et Dernières ventes -->
            <div class="two-columns">
                <!-- Alertes anciennes -->
                <div class="dashboard-box">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-bell"></i>
                            Alertes récentes
                        </h3>
                        <span class="badge <?php echo $alertes_non_lues > 0 ? 'badge-danger' : 'badge-success'; ?>">
                            <?php echo $alertes_non_lues; ?> non lues
                        </span>
                    </div>
                    
                    <?php if (empty($alertes)): ?>
                        <p style="color: var(--gray); text-align: center; padding: 20px;">
                            <i class="fas fa-check-circle"></i> Aucune alerte pour le moment
                        </p>
                    <?php else: ?>
                        <table class="simple-table">
                            <?php foreach ($alertes as $alerte): ?>
                                <tr>
                                    <td style="width: 40px;">
                                        <?php if ($alerte['niveau'] == 'danger'): ?>
                                            <i class="fas fa-exclamation-circle" style="color: var(--danger);"></i>
                                        <?php elseif ($alerte['niveau'] == 'warning'): ?>
                                            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($alerte['medicament_nom'] ?? 'Système'); ?></strong><br>
                                        <small style="color: var(--gray);"><?php echo htmlspecialchars($alerte['message']); ?></small>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <small style="color: var(--gray);">
                                            <?php 
                                            $date = new DateTime($alerte['date_creation']);
                                            echo $date->format('H:i');
                                            ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Dernières ventes -->
                <div class="dashboard-box">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-history"></i>
                            Dernières ventes
                        </h3>
                        <a href="vente.php" style="color: var(--primary); text-decoration: none; font-size: 14px;">
                            Voir tout <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($dernieres_ventes)): ?>
                        <p style="color: var(--gray); text-align: center; padding: 20px;">
                            <i class="fas fa-shopping-cart"></i> Aucune vente aujourd'hui
                        </p>
                    <?php else: ?>
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Médicaments</th>
                                    <th>Produits</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieres_ventes as $vente): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vente['medicaments'] ?? 'Plusieurs produits'); ?></strong><br>
                                            <small style="color: var(--gray);">
                                                par <?php echo htmlspecialchars($vente['vendeur_nom']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo $vente['nb_produits']; ?> produit(s)</td>
                                        <td><strong><?php echo number_format($vente['total_global'], 0, ',', ' '); ?> FCFA</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- CONTENU POUR EMPLOYÉ -->
            <?php elseif ($user['role'] === 'employe'): ?>
            <div style="text-align: center; padding: 50px; background: white; border-radius: 10px;">
                <i class="fas fa-shopping-cart" style="font-size: 80px; color: var(--primary); margin-bottom: 20px;"></i>
                <h2>Bienvenue sur votre espace vente</h2>
                <p style="color: var(--gray); margin: 20px 0;">Utilisez le menu à gauche pour accéder à vos ventes et à votre profil.</p>
                <a href="vente.php" style="display: inline-block; padding: 15px 30px; background: var(--primary); color: white; text-decoration: none; border-radius: 5px;">
                    <i class="fas fa-shopping-cart"></i> Aller aux ventes
                </a>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .action-btn, .dashboard-box');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Actualiser la page toutes les 5 minutes pour les stats
            setTimeout(() => {
                window.location.reload();
            }, 5 * 60 * 1000);
        });
    </script>
</body>
</html>