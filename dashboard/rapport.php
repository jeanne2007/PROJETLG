<?php
session_start();

// Vérifier connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// 🔐 Vérifier que l'utilisateur est ADMIN
require_once '../includes/check_role.php';
checkAdmin(); // Seul l'admin peut accéder aux rapports

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$periode = $_GET['periode'] ?? 'jour';
$date = $_GET['date'] ?? date('Y-m-d');
$mois = $_GET['mois'] ?? date('Y-m');

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Données selon période
$titre_periode = '';
$donnees = [];
$total_ventes = 0;
$total_ca = 0;

if ($periode === 'jour') {
    $titre_periode = date('d/m/Y', strtotime($date));
    
    // Ventes du jour
    $stmt = $db->prepare("
        SELECT v.*, 
               GROUP_CONCAT(m.nom SEPARATOR ', ') as medicaments,
               COUNT(vl.id) as nb_produits
        FROM ventes v 
        LEFT JOIN ventes_lignes vl ON v.id = vl.vente_id
        LEFT JOIN medicaments m ON vl.medicament_id = m.id 
        WHERE DATE(v.date_vente) = ?
        GROUP BY v.id
        ORDER BY v.date_vente DESC
    ");
    $stmt->execute([$date]);
    $donnees = $stmt->fetchAll();
    
    // Stats du jour
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as nb_ventes,
            SUM(total_global) as chiffre_affaires,
            COUNT(DISTINCT client_nom) as nb_clients,
            SUM(vl.quantite) as total_quantite
        FROM ventes v
        LEFT JOIN ventes_lignes vl ON v.id = vl.vente_id
        WHERE DATE(v.date_vente) = ?
    ");
    $stmt->execute([$date]);
    $stats = $stmt->fetch();
    
    $total_ventes = $stats['nb_ventes'] ?? 0;
    $total_ca = $stats['chiffre_affaires'] ?? 0;
    
} elseif ($periode === 'semaine') {
    // Calculer début et fin de semaine
    $date_obj = new DateTime($date);
    $debut_semaine = $date_obj->modify('monday this week')->format('Y-m-d');
    $fin_semaine = $date_obj->modify('+6 days')->format('Y-m-d');
    $titre_periode = "Semaine du " . date('d/m/Y', strtotime($debut_semaine)) . " au " . date('d/m/Y', strtotime($fin_semaine));
    
    // Par jour de la semaine
    $stmt = $db->prepare("
        SELECT 
            DATE(v.date_vente) as jour,
            COUNT(DISTINCT v.id) as nb_ventes,
            SUM(v.total_global) as chiffre_affaires,
            SUM(vl.quantite) as total_quantite
        FROM ventes v
        LEFT JOIN ventes_lignes vl ON v.id = vl.vente_id
        WHERE DATE(v.date_vente) BETWEEN ? AND ?
        GROUP BY DATE(v.date_vente)
        ORDER BY jour
    ");
    $stmt->execute([$debut_semaine, $fin_semaine]);
    $donnees = $stmt->fetchAll();
    
    // Total semaine
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as nb_ventes,
            SUM(v.total_global) as chiffre_affaires
        FROM ventes v
        WHERE DATE(v.date_vente) BETWEEN ? AND ?
    ");
    $stmt->execute([$debut_semaine, $fin_semaine]);
    $stats = $stmt->fetch();
    
    $total_ventes = $stats['nb_ventes'] ?? 0;
    $total_ca = $stats['chiffre_affaires'] ?? 0;
    
} elseif ($periode === 'mois') {
    $mois_debut = $mois . '-01';
    $mois_fin = date('Y-m-t', strtotime($mois_debut));
    $titre_periode = strftime('%B %Y', strtotime($mois_debut));
    
    // Par jour du mois
    $stmt = $db->prepare("
        SELECT 
            DATE(v.date_vente) as jour,
            COUNT(DISTINCT v.id) as nb_ventes,
            SUM(v.total_global) as chiffre_affaires,
            SUM(vl.quantite) as total_quantite
        FROM ventes v
        LEFT JOIN ventes_lignes vl ON v.id = vl.vente_id
        WHERE DATE(v.date_vente) BETWEEN ? AND ?
        GROUP BY DATE(v.date_vente)
        ORDER BY jour
    ");
    $stmt->execute([$mois_debut, $mois_fin]);
    $donnees = $stmt->fetchAll();
    
    // Total mois
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as nb_ventes,
            SUM(v.total_global) as chiffre_affaires,
            AVG(v.total_global) as panier_moyen
        FROM ventes v
        WHERE DATE(v.date_vente) BETWEEN ? AND ?
    ");
    $stmt->execute([$mois_debut, $mois_fin]);
    $stats = $stmt->fetch();
    
    $total_ventes = $stats['nb_ventes'] ?? 0;
    $total_ca = $stats['chiffre_affaires'] ?? 0;
}

// --- ANALYSES AVANCÉES ---

// 1. Mois le plus rentable
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(date_vente, '%Y-%m') as mois,
        SUM(total_global) as total
    FROM ventes
    GROUP BY mois
    ORDER BY total DESC
    LIMIT 1
");
$mois_rentable = $stmt->fetch();

// 2. Médicament le plus vendu
$stmt = $db->query("
    SELECT 
        m.nom,
        SUM(vl.quantite) as total_vendu,
        SUM(vl.total_ligne) as chiffre_affaires
    FROM ventes_lignes vl
    LEFT JOIN medicaments m ON vl.medicament_id = m.id
    GROUP BY vl.medicament_id
    ORDER BY total_vendu DESC
    LIMIT 1
");
$top_medicament = $stmt->fetch();

// 3. Performance des vendeurs (avec tous les employés)
$stmt = $db->query("
    SELECT 
        u.id,
        u.prenom,
        u.nom,
        COUNT(DISTINCT v.id) as nb_ventes,
        COALESCE(SUM(v.total_global), 0) as total_ca,
        COALESCE(AVG(v.total_global), 0) as panier_moyen
    FROM utilisateurs u
    LEFT JOIN ventes v ON u.id = v.vendeur_id
    WHERE u.role IN ('admin', 'employe')
    GROUP BY u.id
    ORDER BY total_ca DESC
");
$performance_vendeurs = $stmt->fetchAll();

// 4. Ventes par mois pour graphique global (gardé pour compatibilité)
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(date_vente, '%Y-%m') as mois,
        SUM(total_global) as total
    FROM ventes
    GROUP BY mois
    ORDER BY mois DESC
    LIMIT 12
");
$ventes_mensuelles = $stmt->fetchAll();

// Top 5 médicaments
$stmt = $db->prepare("
    SELECT 
        m.nom,
        SUM(vl.quantite) as total_vendu,
        SUM(vl.total_ligne) as chiffre_affaires
    FROM ventes_lignes vl
    LEFT JOIN medicaments m ON vl.medicament_id = m.id
    GROUP BY vl.medicament_id
    ORDER BY total_vendu DESC
    LIMIT 5
");
$stmt->execute();
$top_medicaments = $stmt->fetchAll();

// Alertes stock
$stmt = $db->query("
    SELECT m.*, 
           DATEDIFF(date_peremption, CURDATE()) as jours_restants
    FROM medicaments m
    WHERE (stock <= seuil_alerte AND stock > 0) 
       OR (date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    ORDER BY stock ASC, date_peremption ASC
    LIMIT 10
");
$alertes_stock = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - LG PHARMA</title>
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
        
        /* FILTRES */
        .filtres-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        
        .filtres-form {
            display: grid;
            grid-template-columns: auto 1fr 1fr auto;
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
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        /* STATS GRID PRINCIPALE */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            text-align: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin: 0 auto 15px;
        }
        
        .stat-icon.blue { background: var(--primary); }
        .stat-icon.green { background: var(--success); }
        .stat-icon.orange { background: var(--warning); }
        .stat-icon.red { background: var(--danger); }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray);
        }
        
        /* GRILLE ANALYSES */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .analytics-card h3 {
            color: var(--gray);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .analytics-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .analytics-card .sub {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* GRID 2 COLONNES */
        .two-columns {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        /* BOXES */
        .dashboard-box {
            background: white;
            padding: 24px;
            border-radius: 10px;
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
        
        /* BADGE */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* BARRE DE PROGRÈS */
        .progress-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .progress-bar .bar {
            width: 100px;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar .fill {
            height: 8px;
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .two-columns, .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .filtres-form {
                grid-template-columns: 1fr;
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
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
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
                <a href="indexs.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span class="nav-text">Tableau de bord</span>
                </a>
                <a href="medicament.php" class="nav-item">
                    <i class="fas fa-capsules"></i>
                    <span class="nav-text">Médicaments</span>
                </a>
                <a href="vente.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Ventes</span>
                </a>
                <a href="rapport.php" class="nav-item active">
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
        
        <!-- CONTENU PRINCIPAL -->
        <main class="main-content">
            <!-- En-tête -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-chart-bar"></i> Rapports et statistiques</h1>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filtres-container">
                <form method="GET" class="filtres-form">
                    <div class="form-group">
                        <label>Période</label>
                        <select name="periode" class="form-control" onchange="updateDateFields(this.value)">
                            <option value="jour" <?php echo $periode === 'jour' ? 'selected' : ''; ?>>Journalier</option>
                            <option value="semaine" <?php echo $periode === 'semaine' ? 'selected' : ''; ?>>Hebdomadaire</option>
                            <option value="mois" <?php echo $periode === 'mois' ? 'selected' : ''; ?>>Mensuel</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="date-field" style="display: <?php echo $periode === 'jour' || $periode === 'semaine' ? 'block' : 'none'; ?>">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
                    </div>
                    
                    <div class="form-group" id="mois-field" style="display: <?php echo $periode === 'mois' ? 'block' : 'none'; ?>">
                        <label>Mois</label>
                        <input type="month" name="mois" class="form-control" value="<?php echo htmlspecialchars($mois); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="height: 40px;">
                            <i class="fas fa-filter"></i> Appliquer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Stats résumé de la période sélectionnée -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_ventes; ?></div>
                    <div class="stat-label">Ventes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_ca, 0, ',', ' '); ?> FCFA</div>
                    <div class="stat-label">Chiffre d'affaires</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-capsules"></i>
                    </div>
                    <div class="stat-value"><?php echo count($top_medicaments); ?></div>
                    <div class="stat-label">Médicaments vendus</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?php echo count($alertes_stock); ?></div>
                    <div class="stat-label">Alertes actives</div>
                </div>
            </div>
            
            <!-- ANALYSES CLÉS (mois rentable + top médicament) -->
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3><i class="fas fa-calendar-alt"></i> Mois le plus rentable</h3>
                    <div class="value">
                        <?php 
                        if ($mois_rentable) {
                            $annee = substr($mois_rentable['mois'], 0, 4);
                            $mois_num = substr($mois_rentable['mois'], 5, 2);
                            $mois_noms = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                            echo $mois_noms[$mois_num - 1] . ' ' . $annee;
                        } else {
                            echo 'Aucune vente';
                        }
                        ?>
                    </div>
                    <div class="sub">
                        <?php echo $mois_rentable ? number_format($mois_rentable['total'], 0, ',', ' ') . ' FCFA' : ''; ?>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3><i class="fas fa-capsules"></i> Médicament le plus vendu</h3>
                    <div class="value">
                        <?php echo $top_medicament ? htmlspecialchars($top_medicament['nom']) : 'Aucune vente'; ?>
                    </div>
                    <div class="sub">
                        <?php echo $top_medicament ? $top_medicament['total_vendu'] . ' unités vendues' : ''; ?>
                    </div>
                </div>
            </div>
            
            <!-- PERFORMANCE DES EMPLOYÉS -->
            <div class="dashboard-box" style="margin-bottom: 30px;">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-users"></i>
                        Performance des employés
                    </h3>
                </div>
                
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Ventes</th>
                            <th>Chiffre d'affaires</th>
                            <th>Panier moyen</th>
                            <th>Part (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_ca_global = array_sum(array_column($performance_vendeurs, 'total_ca'));
                        foreach ($performance_vendeurs as $emp): 
                            $pourcentage = $total_ca_global > 0 ? round(($emp['total_ca'] / $total_ca_global) * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?></strong>
                            </td>
                            <td><?php echo $emp['nb_ventes']; ?> vente(s)</td>
                            <td><strong><?php echo number_format($emp['total_ca'], 0, ',', ' '); ?> FCFA</strong></td>
                            <td><?php echo number_format($emp['panier_moyen'], 0, ',', ' '); ?> FCFA</td>
                            <td>
                                <div class="progress-bar">
                                    <span><?php echo $pourcentage; ?>%</span>
                                    <div class="bar">
                                        <div class="fill" style="width: <?php echo $pourcentage; ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Top 5 médicaments -->
            <div class="dashboard-box" style="margin-bottom: 30px;">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-trophy"></i>
                        Top 5 médicaments
                    </h3>
                </div>
                
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Médicament</th>
                            <th>Quantité vendue</th>
                            <th>Chiffre d'affaires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_medicaments as $med): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($med['nom']); ?></strong></td>
                            <td><?php echo $med['total_vendu']; ?> unités</td>
                            <td><?php echo number_format($med['chiffre_affaires'], 0, ',', ' '); ?> FCFA</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Détail des ventes (selon période) -->
            <div class="dashboard-box">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-list"></i>
                        Détail des ventes - <?php echo $titre_periode; ?>
                    </h3>
                </div>
                
                <?php if (empty($donnees)): ?>
                    <p style="text-align: center; color: var(--gray); padding: 20px;">
                        Aucune vente pour cette période
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <?php if ($periode === 'jour'): ?>
                                        <th>Heure</th>
                                        <th>Médicaments</th>
                                        <th>Produits</th>
                                        <th>Total</th>
                                        <th>Client</th>
                                    <?php else: ?>
                                        <th>Date</th>
                                        <th>Ventes</th>
                                        <th>Quantité</th>
                                        <th>Chiffre d'affaires</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donnees as $item): ?>
                                    <tr>
                                        <?php if ($periode === 'jour'): ?>
                                            <td><?php echo date('H:i', strtotime($item['date_vente'])); ?></td>
                                            <td><?php echo htmlspecialchars(substr($item['medicaments'] ?? '', 0, 30)) . '...'; ?></td>
                                            <td><?php echo $item['nb_produits']; ?></td>
                                            <td><strong><?php echo number_format($item['total_global'], 0, ',', ' '); ?> FCFA</strong></td>
                                            <td><?php echo htmlspecialchars($item['client_nom'] ?? '--'); ?></td>
                                        <?php else: ?>
                                            <td><?php echo date('d/m/Y', strtotime($item['jour'])); ?></td>
                                            <td><?php echo $item['nb_ventes']; ?></td>
                                            <td><?php echo $item['total_quantite']; ?></td>
                                            <td><strong><?php echo number_format($item['chiffre_affaires'], 0, ',', ' '); ?> FCFA</strong></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Alertes stock -->
            <div class="dashboard-box" style="margin-top: 30px;">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Alertes stock
                    </h3>
                </div>
                
                <?php if (empty($alertes_stock)): ?>
                    <p style="text-align: center; color: var(--success); padding: 20px;">
                        <i class="fas fa-check-circle"></i> Aucune alerte stock
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Médicament</th>
                                    <th>Stock</th>
                                    <th>Seuil</th>
                                    <th>Péremption</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alertes_stock as $med): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($med['nom']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $med['stock'] == 0 ? 'badge-danger' : 'badge-warning'; ?>">
                                                <?php echo $med['stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $med['seuil_alerte']; ?></td>
                                        <td>
                                            <?php if ($med['date_peremption']): ?>
                                                <?php 
                                                $jours = $med['jours_restants'];
                                                if ($jours < 0): ?>
                                                    <span style="color: var(--danger);">Périmé</span>
                                                <?php elseif ($jours <= 30): ?>
                                                    <span style="color: var(--warning);"><?php echo $jours; ?> jours</span>
                                                <?php else: ?>
                                                    <?php echo date('d/m/Y', strtotime($med['date_peremption'])); ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Gestion des champs date selon période
        function updateDateFields(periode) {
            const dateField = document.getElementById('date-field');
            const moisField = document.getElementById('mois-field');
            
            if (periode === 'jour' || periode === 'semaine') {
                dateField.style.display = 'block';
                moisField.style.display = 'none';
            } else if (periode === 'mois') {
                dateField.style.display = 'none';
                moisField.style.display = 'block';
            }
        }
    </script>
</body>
</html>