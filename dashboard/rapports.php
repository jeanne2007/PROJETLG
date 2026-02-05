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
        SELECT v.*, m.nom as medicament_nom 
        FROM ventes v 
        LEFT JOIN medicaments m ON v.medicament_id = m.id 
        WHERE DATE(v.date_vente) = ?
        ORDER BY v.date_vente DESC
    ");
    $stmt->execute([$date]);
    $donnees = $stmt->fetchAll();
    
    // Stats du jour
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as nb_ventes,
            SUM(total) as chiffre_affaires,
            COUNT(DISTINCT client_nom) as nb_clients,
            SUM(quantite) as total_quantite
        FROM ventes 
        WHERE DATE(date_vente) = ?
    ");
    $stmt->execute([$date]);
    $stats = $stmt->fetch();
    
    $total_ventes = $stats['nb_ventes'] ?? 0;
    $total_ca = $stats['chiffre_affaires'] ?? 0;
    
} elseif ($periode === 'semaine') {
    // Calculer début et fin de semaine
    $date_obj = new DateTime($date);
    $debut_semaine = $date_obj->modify('this week')->format('Y-m-d');
    $fin_semaine = $date_obj->modify('+6 days')->format('Y-m-d');
    $titre_periode = "Semaine du " . date('d/m/Y', strtotime($debut_semaine)) . " au " . date('d/m/Y', strtotime($fin_semaine));
    
    // Par jour de la semaine
    $stmt = $db->prepare("
        SELECT 
            DATE(date_vente) as jour,
            COUNT(*) as nb_ventes,
            SUM(total) as chiffre_affaires,
            SUM(quantite) as total_quantite
        FROM ventes 
        WHERE DATE(date_vente) BETWEEN ? AND ?
        GROUP BY DATE(date_vente)
        ORDER BY jour
    ");
    $stmt->execute([$debut_semaine, $fin_semaine]);
    $donnees = $stmt->fetchAll();
    
    // Total semaine
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as nb_ventes,
            SUM(total) as chiffre_affaires
        FROM ventes 
        WHERE DATE(date_vente) BETWEEN ? AND ?
    ");
    $stmt->execute([$debut_semaine, $fin_semaine]);
    $stats = $stmt->fetch();
    
    $total_ventes = $stats['nb_ventes'] ?? 0;
    $total_ca = $stats['chiffre_affaires'] ?? 0;
    
} elseif ($periode === 'mois') {
    $mois_debut = $mois . '-01';
    $mois_fin = date('Y-m-t', strtotime($mois_debut));
    $titre_periode = date('F Y', strtotime($mois_debut));
    
    // Par jour du mois
    $stmt = $db->prepare("
        SELECT 
            DATE(date_vente) as jour,
            COUNT(*) as nb_ventes,
            SUM(total) as chiffre_affaires,
            SUM(quantite) as total_quantite
        FROM ventes 
        WHERE DATE(date_vente) BETWEEN ? AND ?
        GROUP BY DATE(date_vente)
        ORDER BY jour
    ");
    $stmt->execute([$mois_debut, $mois_fin]);
    $donnees = $stmt->fetchAll();
    
    // Total mois
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as nb_ventes,
            SUM(total) as chiffre_affaires,
            AVG(total) as panier_moyen
        FROM ventes 
        WHERE DATE(date_vente) BETWEEN ? AND ?
    ");
    $stmt->execute([$mois_debut, $mois_fin]);
    $stats = $stmt->fetch();
    
    $total_ventes = $stats['nb_ventes'] ?? 0;
    $total_ca = $stats['chiffre_affaires'] ?? 0;
}

// Top 5 médicaments
$stmt = $db->prepare("
    SELECT 
        m.nom,
        SUM(v.quantite) as total_vendu,
        SUM(v.total) as chiffre_affaires
    FROM ventes v
    LEFT JOIN medicaments m ON v.medicament_id = m.id
    GROUP BY v.medicament_id
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* STATS GRID */
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
        
        /* CHART */
        .chart-container {
            height: 300px;
            position: relative;
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
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .two-columns {
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
        
        /* EXPORT */
        .export-options {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
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
                <a href="ventes.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Ventes</span>
                </a>
                <a href="rapports.php" class="nav-item active">
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
            
            <!-- Stats résumé -->
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
            
            <!-- Graphique et Top médicaments -->
            <div class="two-columns">
                <!-- Graphique -->
                <div class="dashboard-box">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-chart-line"></i>
                            Évolution des ventes - <?php echo $titre_periode; ?>
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="ventesChart"></canvas>
                    </div>
                </div>
                
                <!-- Top médicaments -->
                <div class="dashboard-box">
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
                                <th>Vendu</th>
                                <th>CA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_medicaments)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--gray); padding: 20px;">
                                        Aucune donnée disponible
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_medicaments as $index => $med): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($med['nom']); ?></strong>
                                        </td>
                                        <td><?php echo $med['total_vendu']; ?> unités</td>
                                        <td><?php echo number_format($med['chiffre_affaires'], 0, ',', ' '); ?> FCFA</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Alertes stock et Liste détaillée -->
            <div class="two-columns">
                <!-- Liste détaillée -->
                <div class="dashboard-box">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-list"></i>
                            Détail des ventes
                        </h3>
                    </div>
                    <?php if (empty($donnees)): ?>
                        <p style="text-align: center; color: var(--gray); padding: 20px;">
                            Aucune vente pour cette période
                        </p>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table class="simple-table">
                                <thead>
                                    <tr>
                                        <?php if ($periode === 'jour'): ?>
                                            <th>Heure</th>
                                            <th>Médicament</th>
                                            <th>Quantité</th>
                                            <th>Total</th>
                                            <th>Client</th>
                                        <?php else: ?>
                                            <th>Date</th>
                                            <th>Nombre de ventes</th>
                                            <th>Quantité vendue</th>
                                            <th>Chiffre d'affaires</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donnees as $item): ?>
                                        <tr>
                                            <?php if ($periode === 'jour'): ?>
                                                <td><?php echo date('H:i', strtotime($item['date_vente'])); ?></td>
                                                <td><?php echo htmlspecialchars($item['medicament_nom']); ?></td>
                                                <td><?php echo $item['quantite']; ?></td>
                                                <td><?php echo number_format($item['total'], 0, ',', ' '); ?> FCFA</td>
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
                <div class="dashboard-box">
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
                        <div style="max-height: 400px; overflow-y: auto;">
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
        
        // Graphique Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('ventesChart').getContext('2d');
            
            <?php if ($periode === 'jour'): ?>
                // Données pour journée (par heure)
                const labels = [
                    '08:00', '09:00', '10:00', '11:00', '12:00',
                    '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'
                ];
                const data = [12, 19, 8, 15, 22, 18, 25, 12, 9, 14, 7];
                
            <?php elseif ($periode === 'semaine'): ?>
                // Données pour semaine
                const labels = [];
                const data = [];
                <?php foreach ($donnees as $item): ?>
                    labels.push("<?php echo date('d/m', strtotime($item['jour'])); ?>");
                    data.push(<?php echo $item['chiffre_affaires']; ?>);
                <?php endforeach; ?>
                
            <?php elseif ($periode === 'mois'): ?>
                // Données pour mois
                const labels = [];
                const data = [];
                <?php foreach ($donnees as $item): ?>
                    labels.push("<?php echo date('d/m', strtotime($item['jour'])); ?>");
                    data.push(<?php echo $item['chiffre_affaires']; ?>);
                <?php endforeach; ?>
            <?php endif; ?>
            
            // Si pas de données, afficher message
            if (data.length === 0) {
                document.getElementById('ventesChart').parentElement.innerHTML = 
                    '<p style="text-align: center; color: var(--gray); padding: 40px;">Aucune donnée disponible pour le graphique</p>';
                return;
            }
            
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Chiffre d\'affaires (FCFA)',
                        data: data,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' FCFA';
                                }
                            }
                        }
                    }
                }
            });
        });
        
        // Export PDF (fonction de base)
        function exportPDF() {
            alert('Fonctionnalité d\'export PDF à implémenter');
        }
        
        // Export Excel (fonction de base)
        function exportExcel() {
            alert('Fonctionnalité d\'export Excel à implémenter');
        }
    </script>
</body>
</html>