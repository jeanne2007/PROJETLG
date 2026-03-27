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
    header("Location: index.php");
    exit();
}

// Filtres
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';

// Construction de la requête
$query = "SELECT j.* FROM journal j WHERE DATE(j.date_action) BETWEEN ? AND ?";
$params = [$date_debut, $date_fin];

if (!empty($user_filter)) {
    $query .= " AND j.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($action_filter)) {
    $query .= " AND j.action = ?";
    $params[] = $action_filter;
}

$query .= " ORDER BY j.date_action DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Récupérer la liste des utilisateurs pour le filtre
$users = $db->query("SELECT id, prenom, nom FROM utilisateurs ORDER BY prenom")->fetchAll();

// Récupérer les types d'actions uniques
$actions = $db->query("SELECT DISTINCT action FROM journal ORDER BY action")->fetchAll();

// Récupérer infos utilisateur pour sidebar
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_info = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
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
        
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .pharmacie-info h2 {
            color: var(--primary);
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
        
        .filtres-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        
        .filtres-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
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
            font-size: 13px;
            color: var(--gray);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: auto;
            border: 1px solid var(--border);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th {
            background: #f8fafc;
            color: var(--gray);
            font-weight: 500;
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
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
        
        .ip-address {
            color: var(--gray);
            font-family: monospace;
            font-size: 12px;
        }
        
        @media (max-width: 1024px) {
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
                <a href="alerte.php" class="nav-item">
                    <i class="fas fa-bell"></i> <span class="nav-text">Alertes</span>
                </a>
                <a href="journals.php" class="nav-item active">
                    <i class="fas fa-history"></i> <span class="nav-text">Journal</span>
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
                    <h1><i class="fas fa-history" style="color: var(--primary);"></i> Journal des actions</h1>
                </div>
            </div>
            
            <!-- FILTRES -->
            <div class="filtres-box">
                <form method="GET" class="filtres-form">
                    <div class="form-group">
                        <label>Date début</label>
                        <input type="date" name="date_debut" class="form-control" value="<?php echo $date_debut; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Date fin</label>
                        <input type="date" name="date_fin" class="form-control" value="<?php echo $date_fin; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Utilisateur</label>
                        <select name="user" class="form-control">
                            <option value="">Tous</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Action</label>
                        <select name="action" class="form-control">
                            <option value="">Toutes</option>
                            <?php foreach ($actions as $a): ?>
                                <option value="<?php echo $a['action']; ?>" <?php echo $action_filter == $a['action'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a['action']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- TABLEAU DU JOURNAL -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-history" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                    <p>Aucune action enregistrée pour cette période</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['date_action'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['user_nom']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'badge-info';
                                        if (strpos($log['action'], 'suppression') !== false) {
                                            $badge_class = 'badge-danger';
                                        } elseif (strpos($log['action'], 'ajout') !== false || strpos($log['action'], 'vente') !== false) {
                                            $badge_class = 'badge-success';
                                        } elseif (strpos($log['action'], 'modification') !== false) {
                                            $badge_class = 'badge-warning';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                                    <td><span class="ip-address"><?php echo htmlspecialchars($log['ip_address']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>