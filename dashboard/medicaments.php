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
$id = $_GET['id'] ?? 0;
$message = $_GET['message'] ?? '';
$type = $_GET['type'] ?? '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter'])) {
        // Ajouter un médicament
        $stmt = $db->prepare("
            INSERT INTO medicaments (
                nom, code_barre, dci, forme, dosage, laboratoire,
                prix_achat, prix_vente, stock, seuil_alerte,
                date_peremption, numero_lot, fournisseur, ajoute_par
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $_POST['nom'],
            !empty($_POST['code_barre']) ? $_POST['code_barre'] : null,
            $_POST['dci'] ?? '',
            $_POST['forme'] ?? '',
            $_POST['dosage'] ?? '',
            $_POST['laboratoire'] ?? '',
            floatval($_POST['prix_achat']) ?? 0,
            floatval($_POST['prix_vente']),
            intval($_POST['stock']) ?? 0,
            intval($_POST['seuil_alerte']) ?? 10,
            !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null,
            $_POST['numero_lot'] ?? '',
            $_POST['fournisseur'] ?? '',
            $_SESSION['user_id']
        ]);
        
        if ($success) {
            header("Location: medicaments.php?message=Médicament ajouté avec succès&type=success");
            exit();
        }
    }
    
    if (isset($_POST['modifier'])) {
        // Modifier un médicament
        $stmt = $db->prepare("
            UPDATE medicaments SET
                nom = ?, code_barre = ?, dci = ?, forme = ?, dosage = ?,
                laboratoire = ?, prix_achat = ?, prix_vente = ?, stock = ?,
                seuil_alerte = ?, date_peremption = ?, numero_lot = ?,
                fournisseur = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $_POST['nom'],
            !empty($_POST['code_barre']) ? $_POST['code_barre'] : null,
            $_POST['dci'] ?? '',
            $_POST['forme'] ?? '',
            $_POST['dosage'] ?? '',
            $_POST['laboratoire'] ?? '',
            floatval($_POST['prix_achat']) ?? 0,
            floatval($_POST['prix_vente']),
            intval($_POST['stock']) ?? 0,
            intval($_POST['seuil_alerte']) ?? 10,
            !empty($_POST['date_peremption']) ? $_POST['date_peremption'] : null,
            $_POST['numero_lot'] ?? '',
            $_POST['fournisseur'] ?? '',
            $_POST['id']
        ]);
        
        if ($success) {
            header("Location: medicaments.php?message=Médicament modifié avec succès&type=success");
            exit();
        }
    }
    
    if (isset($_POST['supprimer'])) {
        // Supprimer un médicament
        $stmt = $db->prepare("DELETE FROM medicaments WHERE id = ?");
        $success = $stmt->execute([$_POST['id']]);
        
        if ($success) {
            header("Location: medicaments.php?message=Médicament supprimé avec succès&type=success");
            exit();
        }
    }
}

// Récupérer les catégories
$categories = $db->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll();

// Récupérer médicament pour modification
$medicament = null;
if ($id > 0 && ($action === 'modifier' || $action === 'voir')) {
    $stmt = $db->prepare("SELECT * FROM medicaments WHERE id = ?");
    $stmt->execute([$id]);
    $medicament = $stmt->fetch();
    
    if (!$medicament && $action !== 'ajouter') {
        header("Location: medicaments.php?message=Médicament non trouvé&type=error");
        exit();
    }
}

// Récupérer tous les médicaments pour la liste
if ($action === 'liste') {
    $recherche = $_GET['recherche'] ?? '';
    $categorie_id = $_GET['categorie'] ?? '';
    $stock_filter = $_GET['stock'] ?? '';
    
    $query = "SELECT m.* FROM medicaments m WHERE 1=1";
    $params = [];
    
    if (!empty($recherche)) {
        $query .= " AND (m.nom LIKE ? OR m.dci LIKE ? OR m.code_barre LIKE ?)";
        $search_term = "%$recherche%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($categorie_id)) {
        $query .= " AND m.id IN (SELECT medicament_id FROM medicaments_categories WHERE categorie_id = ?)";
        $params[] = $categorie_id;
    }
    
    if ($stock_filter === 'bas') {
        $query .= " AND m.stock <= m.seuil_alerte AND m.stock > 0";
    } elseif ($stock_filter === 'rupture') {
        $query .= " AND m.stock = 0";
    } elseif ($stock_filter === 'perime') {
        $query .= " AND m.date_peremption IS NOT NULL AND m.date_peremption < CURDATE()";
    }
    
    $query .= " ORDER BY m.nom ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $medicaments = $stmt->fetchAll();
}

// Récupérer l'utilisateur pour l'en-tête
$stmt = $db->prepare("SELECT prenom, nom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Médicaments - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* VARIABLES */
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
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* SIDEBAR (identique au dashboard) */
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
        
        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
        }
        
        /* HEADER */
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
        
        /* MESSAGES */
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
        
        /* BOUTONS */
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
            background: #8a13a4;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        /* BARRE DE RECHERCHE */
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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
        
        /* FORMULAIRE */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-full {
            grid-column: 1 / -1;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        /* MODAL DE CONFIRMATION */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .search-form {
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
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                flex-wrap: wrap;
            }
        }
        
        /* VUE DÉTAIL */
        .detail-view {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
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
                <a href="medicaments.php" class="nav-item active">
                    <i class="fas fa-capsules"></i>
                    <span class="nav-text">Médicaments</span>
                </a>
                <a href="ventes.php" class="nav-item">
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
                <a href="parametres.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Paramètres</span>
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
                    <h1>
                        <i class="fas fa-capsules"></i>
                        <?php 
                        if ($action === 'ajouter') echo "Ajouter un médicament";
                        elseif ($action === 'modifier') echo "Modifier le médicament";
                        elseif ($action === 'voir') echo "Détail du médicament";
                        else echo "Gestion des médicaments";
                        ?>
                    </h1>
                </div>
                
                <div class="page-actions">
                    <?php if ($action === 'liste'): ?>
                        <a href="medicaments.php?action=ajouter" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouveau médicament
                        </a>
                    <?php else: ?>
                        <a href="medicaments.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $type === 'error' ? 'error' : 'success'; ?>">
                    <i class="fas <?php echo $type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'liste'): ?>
                <!-- BARRE DE RECHERCHE -->
                <div class="search-bar">
                    <form method="GET" class="search-form">
                        <div class="form-group">
                            <label>Rechercher</label>
                            <input type="text" name="recherche" class="form-control" 
                                   placeholder="Nom, DCI ou code barre..."
                                   value="<?php echo htmlspecialchars($_GET['recherche'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Catégorie</label>
                            <select name="categorie" class="form-control">
                                <option value="">Toutes</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                        <?php echo ($_GET['categorie'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>État du stock</label>
                            <select name="stock" class="form-control">
                                <option value="">Tous</option>
                                <option value="bas" <?php echo ($_GET['stock'] ?? '') === 'bas' ? 'selected' : ''; ?>>Stock bas</option>
                                <option value="rupture" <?php echo ($_GET['stock'] ?? '') === 'rupture' ? 'selected' : ''; ?>>Rupture</option>
                                <option value="perime" <?php echo ($_GET['stock'] ?? '') === 'perime' ? 'selected' : ''; ?>>Périmés</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="height: 40px;">
                                <i class="fas fa-search"></i> Rechercher
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- LISTE DES MÉDICAMENTS -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>DCI</th>
                                    <th>Stock</th>
                                    <th>Prix</th>
                                    <th>Fournisseur</th>
                                    <th>Péremption</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($medicaments)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px;">
                                            <i class="fas fa-capsules" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                            <p style="color: var(--gray);">Aucun médicament trouvé</p>
                                            <a href="medicaments.php?action=ajouter" class="btn btn-primary" style="margin-top: 15px;">
                                                <i class="fas fa-plus"></i> Ajouter le premier médicament
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($medicaments as $med): ?>
                                        <?php 
                                        // Déterminer le statut du stock
                                        $statut = '';
                                        $badge_class = '';
                                        if ($med['stock'] <= 0) {
                                            $statut = 'Rupture';
                                            $badge_class = 'badge-danger';
                                        } elseif ($med['stock'] <= $med['seuil_alerte']) {
                                            $statut = 'Stock bas';
                                            $badge_class = 'badge-warning';
                                        } else {
                                            $statut = 'Disponible';
                                            $badge_class = 'badge-success';
                                        }
                                        
                                        // Vérifier péremption
                                        $is_perime = false;
                                        if ($med['date_peremption']) {
                                            $today = new DateTime();
                                            $peremption = new DateTime($med['date_peremption']);
                                            $is_perime = $peremption < $today;
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($med['nom']); ?></strong><br>
                                                <small style="color: var(--gray);"><?php echo htmlspecialchars($med['forme'] . ' - ' . $med['dosage']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($med['dci']); ?></td>
                                            <td>
                                                <div class="badge <?php echo $badge_class; ?>" style="margin-bottom: 5px;">
                                                    <?php echo $statut; ?>
                                                </div><br>
                                                <small><?php echo $med['stock']; ?> unités</small>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($med['prix_vente'], 0, ',', ' '); ?> FCFA</strong><br>
                                                <small>Achat: <?php echo number_format($med['prix_achat'], 0, ',', ' '); ?> FCFA</small>
                                            </td>
                                            <td><?php echo htmlspecialchars($med['fournisseur']); ?></td>
                                            <td>
                                                <?php if ($med['date_peremption']): ?>
                                                    <?php if ($is_perime): ?>
                                                        <span style="color: var(--danger);">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                            <?php echo date('d/m/Y', strtotime($med['date_peremption'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?php echo date('d/m/Y', strtotime($med['date_peremption'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">Non précisée</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 5px;">
                                                    <a href="medicaments.php?action=voir&id=<?php echo $med['id']; ?>" 
                                                       class="btn" style="padding: 5px 10px; background: #9811b0;">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="medicaments.php?action=modifier&id=<?php echo $med['id']; ?>" 
                                                       class="btn" style="padding: 5px 10px; background: #53a30c;">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="showDeleteModal(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars(addslashes($med['nom'])); ?>')" 
                                                            class="btn" style="padding: 5px 10px; background: #fee2e2; color: #991b1b;">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif ($action === 'ajouter' || $action === 'modifier'): ?>
                <!-- FORMULAIRE AJOUT/MODIFICATION -->
                <div class="form-container">
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?php echo $medicament['id'] ?? ''; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom commercial *</label>
                                <input type="text" name="nom" class="form-control" required
                                       value="<?php echo htmlspecialchars($medicament['nom'] ?? ''); ?>"
                                       placeholder="ex: Doliprane 500mg">
                            </div>
                            
                            <div class="form-group">
                                <label>Code barre</label>
                                <input type="text" name="code_barre" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['code_barre'] ?? ''); ?>"
                                       placeholder="123456789012">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>DCI (Dénomination Commune Internationale)</label>
                                <input type="text" name="dci" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['dci'] ?? ''); ?>"
                                       placeholder="ex: Paracétamol">
                            </div>
                            
                            <div class="form-group">
                                <label>Forme galénique</label>
                                <select name="forme" class="form-control">
                                    <option value="">Sélectionner...</option>
                                    <option value="Comprimé" <?php echo ($medicament['forme'] ?? '') === 'Comprimé' ? 'selected' : ''; ?>>Comprimé</option>
                                    <option value="Carton" <?php echo ($medicament['forme'] ?? '') === 'cartons' ? 'selected' : ''; ?>>cartons</option>
                                    <option value="Sirop" <?php echo ($medicament['forme'] ?? '') === 'Sirop' ? 'selected' : ''; ?>>Sirop</option>
                                    <option value="Injectable" <?php echo ($medicament['forme'] ?? '') === 'Injectable' ? 'selected' : ''; ?>>Injectable</option>
                                    <option value="Crème" <?php echo ($medicament['forme'] ?? '') === 'Crème' ? 'selected' : ''; ?>>Crème</option>
                                    <option value="Pommade" <?php echo ($medicament['forme'] ?? '') === 'Pommade' ? 'selected' : ''; ?>>Pommade</option>
                                    <option value="Suppositoire" <?php echo ($medicament['forme'] ?? '') === 'Suppositoire' ? 'selected' : ''; ?>>Suppositoire</option>
                                    <option value="Collyre" <?php echo ($medicament['forme'] ?? '') === 'Collyre' ? 'selected' : ''; ?>>Collyre</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Dosage</label>
                                <input type="text" name="dosage" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['dosage'] ?? ''); ?>"
                                       placeholder="ex: 500mg, 1g, 2%...">
                            </div>
                            
                            <div class="form-group">
                                <label>Laboratoire</label>
                                <input type="text" name="laboratoire" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['laboratoire'] ?? ''); ?>"
                                       placeholder="ex: Sanofi, Bayer...">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prix d'achat ($)</label>
                                <input type="number" name="prix_achat" class="form-control" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($medicament['prix_achat'] ?? '0'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Prix de vente ($) </label>
                                <input type="number" name="prix_vente" class="form-control" required step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($medicament['prix_vente'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stock actuel </label>
                                <input type="number" name="stock" class="form-control" required min="0"
                                       value="<?php echo htmlspecialchars($medicament['stock'] ?? '0'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Seuil d'alerte stock</label>
                                <input type="number" name="seuil_alerte" class="form-control" min="1"
                                       value="<?php echo htmlspecialchars($medicament['seuil_alerte'] ?? '10'); ?>">
                                <small style="color: var(--gray);">Alerte quand stock ≤ cette valeur</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date de péremption</label>
                                <input type="date" name="date_peremption" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['date_peremption'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Numéro de lot</label>
                                <input type="text" name="numero_lot" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['numero_lot'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row form-full">
                            <div class="form-group">
                                <label>Fournisseur</label>
                                <input type="text" name="fournisseur" class="form-control"
                                       value="<?php echo htmlspecialchars($medicament['fournisseur'] ?? ''); ?>"
                                       placeholder="ex: Pharmacie Centrale, Grossiste X...">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="medicaments.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" name="<?php echo $action === 'ajouter' ? 'ajouter' : 'modifier'; ?>" 
                                    class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $action === 'ajouter' ? 'Ajouter le médicament' : 'Enregistrer les modifications'; ?>
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($action === 'voir' && $medicament): ?>
                <!-- VUE DÉTAIL -->
                <div class="detail-view">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Nom commercial</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['nom']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">DCI</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['dci']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Forme / Dosage</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['forme'] . ' - ' . $medicament['dosage']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Laboratoire</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['laboratoire']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Prix d'achat</div>
                            <div class="detail-value"><?php echo number_format($medicament['prix_achat'], 0, ',', ' '); ?> $</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Prix de vente</div>
                            <div class="detail-value"><?php echo number_format($medicament['prix_vente'], 0, ',', ' '); ?> $</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Stock actuel</div>
                            <div class="detail-value">
                                <span class="badge <?php 
                                    if ($medicament['stock'] <= 0) echo 'badge-danger';
                                    elseif ($medicament['stock'] <= $medicament['seuil_alerte']) echo 'badge-warning';
                                    else echo 'badge-success';
                                ?>">
                                    <?php echo $medicament['stock']; ?> unités
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Seuil d'alerte</div>
                            <div class="detail-value"><?php echo $medicament['seuil_alerte']; ?> unités</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Date de péremption</div>
                            <div class="detail-value">
                                <?php if ($medicament['date_peremption']): ?>
                                    <?php 
                                    $date_peremption = new DateTime($medicament['date_peremption']);
                                    $today = new DateTime();
                                    if ($date_peremption < $today): ?>
                                        <span style="color: var(--danger);">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?php echo $date_peremption->format('d/m/Y'); ?> (Périmé)
                                        </span>
                                    <?php else: ?>
                                        <?php echo $date_peremption->format('d/m/Y'); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Non précisée
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Numéro de lot</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['numero_lot']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Fournisseur</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['fournisseur']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Date d'ajout</div>
                            <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($medicament['date_ajout'])); ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                        <a href="medicaments.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                        <a href="medicaments.php?action=modifier&id=<?php echo $medicament['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- MODAL DE SUPPRESSION -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Confirmer la suppression</h3>
            <p id="deleteMessage" style="margin-bottom: 20px;"></p>
            <form id="deleteForm" method="POST" action="">
                <input type="hidden" name="id" id="deleteId">
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="hideDeleteModal()" class="btn btn-outline">
                        Annuler
                    </button>
                    <button type="submit" name="supprimer" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal de suppression
        function showDeleteModal(id, nom) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').textContent = 
                'Êtes-vous sûr de vouloir supprimer le médicament "' + nom + '" ? Cette action est irréversible.';
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fermer modal en cliquant à l'extérieur
        window.onclick = function(event) {
            var modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                hideDeleteModal();
            }
        }
        
        // Calcul automatique du prix de vente
        document.addEventListener('DOMContentLoaded', function() {
            var prixAchat = document.querySelector('input[name="prix_achat"]');
            var prixVente = document.querySelector('input[name="prix_vente"]');
            
            if (prixAchat && prixVente) {
                prixAchat.addEventListener('input', function() {
                    if (!prixVente.value && this.value) {
                        // Suggérer un prix de vente avec 30% de marge
                        var prix = parseFloat(this.value) || 0;
                        var suggestion = prix * 1.3;
                        prixVente.placeholder = 'Suggestion: ' + suggestion.toFixed(2) + ' FCFA';
                    }
                });
            }
            
            // Alertes pour stock bas
            var stock = document.querySelector('input[name="stock"]');
            var seuil = document.querySelector('input[name="seuil_alerte"]');
            
            if (stock && seuil) {
                stock.addEventListener('input', checkStockAlert);
                seuil.addEventListener('input', checkStockAlert);
                
                function checkStockAlert() {
                    var stockValue = parseInt(stock.value) || 0;
                    var seuilValue = parseInt(seuil.value) || 10;
                    
                    if (stockValue <= seuilValue) {
                        stock.style.borderColor = 'var(--warning)';
                        stock.style.backgroundColor = '#fef3c7';
                    } else {
                        stock.style.borderColor = '';
                        stock.style.backgroundColor = '';
                    }
                }
            }
        });
    </script>
</body>
</html>