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
    
    // AJOUTER (retour à la liste) et AJOUTER + CONTINUER
    if (isset($_POST['ajouter']) || isset($_POST['ajouter_continuer'])) {
        
        // Vérifier si le code barre existe déjà (s'il est fourni)
        if (!empty($_POST['code_barre'])) {
            $check = $db->prepare("SELECT id FROM medicaments WHERE code_barre = ?");
            $check->execute([$_POST['code_barre']]);
            if ($check->fetch()) {
                $message = "❌ Ce code barre existe déjà pour un autre médicament !";
                $message_type = "error";
                // Aller directement à l'affichage sans essayer d'insérer
                goto afficher_formulaire;
            }
        }
        
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
            logAction($db, $_SESSION['user_id'], 'ajout', 'Ajout du médicament : ' . $_POST['nom']);
            
            if (isset($_POST['ajouter_continuer'])) {
                $message = "✅ Médicament ajouté avec succès. Vous pouvez en ajouter un autre.";
                $message_type = 'success';
            } else {
                header("Location: medicament.php?message=Médicament ajouté avec succès&type=success");
                exit();
            }
        }
    }
    
    if (isset($_POST['modifier'])) {
        
        // Vérifier si le code barre existe déjà (sauf pour ce médicament)
        if (!empty($_POST['code_barre'])) {
            $check = $db->prepare("SELECT id FROM medicaments WHERE code_barre = ? AND id != ?");
            $check->execute([$_POST['code_barre'], $_POST['id']]);
            if ($check->fetch()) {
                $message = "❌ Ce code barre existe déjà pour un autre médicament !";
                $message_type = "error";
                goto afficher_formulaire;
            }
        }
        
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
            logAction($db, $_SESSION['user_id'], 'modification', 'Modification du médicament : ' . $_POST['nom'] . ' (ID: ' . $_POST['id'] . ')');
            
            header("Location: medicament.php?message=Médicament modifié avec succès&type=success");
            exit();
        }
    }
    
    if (isset($_POST['supprimer'])) {
        // Récupérer le nom avant suppression pour le log
        $stmt = $db->prepare("SELECT nom FROM medicaments WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $med_nom = $stmt->fetch()['nom'];
        
        $stmt = $db->prepare("DELETE FROM medicaments WHERE id = ?");
        $success = $stmt->execute([$_POST['id']]);
        
        if ($success) {
            logAction($db, $_SESSION['user_id'], 'suppression', 'Suppression du médicament : ' . $med_nom . ' (ID: ' . $_POST['id'] . ')');
            
            header("Location: medicament.php?message=Médicament supprimé avec succès&type=success");
            exit();
        }
    }
}

afficher_formulaire:

// Récupérer médicament pour modification
$medicament = null;
if ($id > 0 && ($action === 'modifier' || $action === 'voir')) {
    $stmt = $db->prepare("SELECT * FROM medicaments WHERE id = ?");
    $stmt->execute([$id]);
    $medicament = $stmt->fetch();
    
    if (!$medicament && $action !== 'ajouter') {
        header("Location: medicament.php?message=Médicament non trouvé&type=error");
        exit();
    }
}

// Récupérer tous les médicaments pour la liste
if ($action === 'liste') {
    $recherche = $_GET['recherche'] ?? '';
    
    $query = "SELECT m.* FROM medicaments m WHERE 1=1";
    $params = [];
    
    if (!empty($recherche)) {
        $query .= " AND (m.nom LIKE ? OR m.dci LIKE ?)";
        $search_term = "%$recherche%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY m.nom ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $medicaments = $stmt->fetchAll();
}

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT prenom, nom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer la liste des fournisseurs existants
$fournisseurs = $db->query("SELECT DISTINCT fournisseur FROM medicaments WHERE fournisseur IS NOT NULL AND fournisseur != '' ORDER BY fournisseur")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Médicaments - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
        }

        .container {
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            min-height: 100vh;
        }

        .sidebar-header {
            padding: 20px;
            background: #1e2b3a;
            text-align: center;
        }

        .sidebar-header h2 {
            color: #fff;
            font-size: 20px;
        }

        .sidebar-header p {
            color: #95a5a6;
            font-size: 12px;
            margin-top: 5px;
        }

        .sidebar-nav a {
            display: block;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: #34495e;
            border-left-color: #3498db;
        }

        .sidebar-nav i {
            width: 25px;
        }

        .user-info {
            position: absolute;
            bottom: 0;
            width: 250px;
            padding: 20px;
            background: #1e2b3a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .user-details h4 {
            font-size: 14px;
        }

        .user-details p {
            font-size: 12px;
            color: #95a5a6;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .page-title h1 {
            font-size: 24px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Alertes */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Boutons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-outline {
            background: white;
            color: #3498db;
            border: 1px solid #3498db;
        }

        .btn-outline:hover {
            background: #3498db;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* Recherche */
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .search-form {
            display: flex;
            gap: 15px;
        }

        .search-form input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-form button {
            padding: 12px 25px;
        }

        /* Tableau */
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #34495e;
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f5f5f5;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Formulaire simplifié */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group label i {
            margin-right: 8px;
            color: #3498db;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Champs requis */
        .required label:after {
            content: " *";
            color: #e74c3c;
        }

        /* Aide */
        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        /* Vue détail simplifiée */
        .detail-view {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: #2c3e50;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                min-height: auto;
            }
            .user-info {
                position: relative;
                width: 100%;
            }
            .main-content {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .search-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>LG PHARMA</h2>
                <p>Gestion de pharmacie</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="indexs.php">
                    <i class="fas fa-home"></i> Tableau de bord
                </a>
                <a href="medicament.php" class="active">
                    <i class="fas fa-capsules"></i> Médicaments
                </a>
                <a href="vente.php">
                    <i class="fas fa-shopping-cart"></i> Ventes
                </a>
                <a href="rapport.php">
                    <i class="fas fa-chart-bar"></i> Rapports
                </a>
                <a href="profiles.php">
                    <i class="fas fa-user"></i> Mon profil
                </a>
                <a href="parametre.php">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
                <a href="utilisateur.php">
                    <i class="fas fa-users"></i> Utilisateurs
                </a>
                <a href="journals.php">
                    <i class="fas fa-history"></i> Journal
                </a>
                <a href="alerte.php">
                    <i class="fas fa-bell"></i> Alertes
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
                <a href="../deconnexion.php" style="margin-left: auto; color: #95a5a6;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- En-tête -->
            <div class="page-header">
                <div class="page-title">
                    <h1>
                        <i class="fas fa-capsules" style="color: #3498db;"></i>
                        <?php 
                        if ($action === 'ajouter') echo "Ajouter un médicament";
                        elseif ($action === 'modifier') echo "Modifier un médicament";
                        elseif ($action === 'voir') echo "Détail du médicament";
                        else echo "Gestion des médicaments";
                        ?>
                    </h1>
                </div>
                
                <div class="page-actions">
                    <?php if ($action === 'liste'): ?>
                        <a href="medicament.php?action=ajouter" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouveau médicament
                        </a>
                    <?php else: ?>
                        <a href="medicament.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Retour
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
                <!-- Recherche simple -->
                <div class="search-bar">
                    <form method="GET" class="search-form">
                        <input type="text" name="recherche" 
                               placeholder="Rechercher par nom ou DCI..."
                               value="<?php echo htmlspecialchars($_GET['recherche'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>
                </div>
                
                <!-- Liste des médicaments -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Forme/Dosage</th>
                                <th>Stock</th>
                                <th>Prix vente</th>
                                <th>Fournisseur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($medicaments)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-capsules" style="font-size: 40px; color: #ccc; margin-bottom: 15px;"></i>
                                        <p>Aucun médicament trouvé</p>
                                        <a href="medicament.php?action=ajouter" class="btn btn-primary" style="margin-top: 15px;">
                                            <i class="fas fa-plus"></i> Ajouter un médicament
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($medicaments as $med): ?>
                                    <?php 
                                    $stock_class = 'badge-success';
                                    $stock_text = 'Disponible';
                                    if ($med['stock'] <= 0) {
                                        $stock_class = 'badge-danger';
                                        $stock_text = 'Rupture';
                                    } elseif ($med['stock'] <= $med['seuil_alerte']) {
                                        $stock_class = 'badge-warning';
                                        $stock_text = 'Stock bas';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($med['nom']); ?></strong>
                                            <?php if ($med['code_barre']): ?>
                                                <br><small style="color: #666;">Code: <?php echo $med['code_barre']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $med['forme']; ?> <?php echo $med['dosage']; ?><br>
                                            <small style="color: #666;"><?php echo $med['dci']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $stock_class; ?>">
                                                <?php echo $stock_text; ?>
                                            </span><br>
                                            <small><?php echo $med['stock']; ?> unités</small>
                                        </td>
                                        <td><strong><?php echo number_format($med['prix_vente'], 0, ',', ' '); ?> FCFA</strong></td>
                                        <td><?php echo $med['fournisseur'] ?: '-'; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="medicament.php?action=voir&id=<?php echo $med['id']; ?>" 
                                                   class="btn btn-sm btn-outline" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="medicament.php?action=modifier&id=<?php echo $med['id']; ?>" 
                                                   class="btn btn-sm btn-outline" style="color: #27ae60; border-color: #27ae60;" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="showDeleteModal(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars(addslashes($med['nom'])); ?>')" 
                                                        class="btn btn-sm btn-outline" style="color: #e74c3c; border-color: #e74c3c;" title="Supprimer">
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
                
            <?php elseif ($action === 'ajouter'): ?>
                <!-- Formulaire d'ajout simplifié -->
                <div class="form-container">
                    <form method="POST" action="">
                        <div class="form-group required">
                            <label><i class="fas fa-tag"></i> Nom du médicament</label>
                            <input type="text" name="nom" class="form-control" required 
                                   placeholder="Ex: Doliprane, Paracétamol...">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> DCI</label>
                            <input type="text" name="dci" class="form-control" 
                                   placeholder="Ex: Paracétamol">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-shapes"></i> Forme</label>
                                <select name="forme" class="form-control">
                                    <option value="">Choisir...</option>
                                    <option value="Comprimé">Comprimé</option>
                                    <option value="Gélule">Gélule</option>
                                    <option value="Sirop">Sirop</option>
                                    <option value="Injectable">Injectable</option>
                                    <option value="Crème">Crème</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-weight"></i> Dosage</label>
                                <input type="text" name="dosage" class="form-control" 
                                       placeholder="Ex: 500mg, 1g...">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-dollar-sign"></i> Prix d'achat</label>
                                <input type="number" name="prix_achat" class="form-control" min="0"
                                       placeholder="0">
                            </div>
                            
                            <div class="form-group required">
                                <label><i class="fas fa-dollar-sign"></i> Prix de vente</label>
                                <input type="number" name="prix_vente" class="form-control" required min="0"
                                       placeholder="Prix de vente">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required">
                                <label><i class="fas fa-boxes"></i> Stock initial</label>
                                <input type="number" name="stock" class="form-control" required min="0"
                                       value="0">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-exclamation-triangle"></i> Seuil d'alerte</label>
                                <input type="number" name="seuil_alerte" class="form-control" min="1"
                                       value="10">
                                <div class="help-text">Alerte quand stock est bas</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-truck"></i> Fournisseur</label>
                            <input type="text" name="fournisseur" class="form-control" 
                                   placeholder="Nom du fournisseur">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Date péremption</label>
                                <input type="date" name="date_peremption" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-hashtag"></i> N° de lot</label>
                                <input type="text" name="numero_lot" class="form-control" 
                                       placeholder="Numéro de lot">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-barcode"></i> Code barre (optionnel)</label>
                            <input type="text" name="code_barre" class="form-control" 
                                   placeholder="Code barre">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-flask"></i> Laboratoire</label>
                            <input type="text" name="laboratoire" class="form-control" 
                                   placeholder="Laboratoire fabricant">
                        </div>
                        
                        <div class="form-actions">
                            <a href="medicament.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" name="ajouter" class="btn btn-success">
                                <i class="fas fa-save"></i> Ajouter
                            </button>
                            <button type="submit" name="ajouter_continuer" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Ajouter + Nouveau
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($action === 'modifier' && $medicament): ?>
                <!-- Formulaire de modification -->
                <div class="form-container">
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?php echo $medicament['id']; ?>">
                        
                        <div class="form-group required">
                            <label><i class="fas fa-tag"></i> Nom du médicament</label>
                            <input type="text" name="nom" class="form-control" required 
                                   value="<?php echo htmlspecialchars($medicament['nom']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> DCI</label>
                            <input type="text" name="dci" class="form-control" 
                                   value="<?php echo htmlspecialchars($medicament['dci']); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-shapes"></i> Forme</label>
                                <select name="forme" class="form-control">
                                    <option value="">Choisir...</option>
                                    <option value="Comprimé" <?php echo $medicament['forme'] == 'Comprimé' ? 'selected' : ''; ?>>Comprimé</option>
                                    <option value="Gélule" <?php echo $medicament['forme'] == 'Gélule' ? 'selected' : ''; ?>>Gélule</option>
                                    <option value="Sirop" <?php echo $medicament['forme'] == 'Sirop' ? 'selected' : ''; ?>>Sirop</option>
                                    <option value="Injectable" <?php echo $medicament['forme'] == 'Injectable' ? 'selected' : ''; ?>>Injectable</option>
                                    <option value="Crème" <?php echo $medicament['forme'] == 'Crème' ? 'selected' : ''; ?>>Crème</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-weight"></i> Dosage</label>
                                <input type="text" name="dosage" class="form-control" 
                                       value="<?php echo htmlspecialchars($medicament['dosage']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-dollar-sign"></i> Prix d'achat</label>
                                <input type="number" name="prix_achat" class="form-control" min="0"
                                       value="<?php echo $medicament['prix_achat']; ?>">
                            </div>
                            
                            <div class="form-group required">
                                <label><i class="fas fa-dollar-sign"></i> Prix de vente</label>
                                <input type="number" name="prix_vente" class="form-control" required min="0"
                                       value="<?php echo $medicament['prix_vente']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required">
                                <label><i class="fas fa-boxes"></i> Stock actuel</label>
                                <input type="number" name="stock" class="form-control" required min="0"
                                       value="<?php echo $medicament['stock']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-exclamation-triangle"></i> Seuil d'alerte</label>
                                <input type="number" name="seuil_alerte" class="form-control" min="1"
                                       value="<?php echo $medicament['seuil_alerte']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-truck"></i> Fournisseur</label>
                            <input type="text" name="fournisseur" class="form-control" 
                                   value="<?php echo htmlspecialchars($medicament['fournisseur']); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Date péremption</label>
                                <input type="date" name="date_peremption" class="form-control"
                                       value="<?php echo $medicament['date_peremption']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-hashtag"></i> N° de lot</label>
                                <input type="text" name="numero_lot" class="form-control" 
                                       value="<?php echo htmlspecialchars($medicament['numero_lot']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-barcode"></i> Code barre</label>
                            <input type="text" name="code_barre" class="form-control" 
                                   value="<?php echo htmlspecialchars($medicament['code_barre']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-flask"></i> Laboratoire</label>
                            <input type="text" name="laboratoire" class="form-control" 
                                   value="<?php echo htmlspecialchars($medicament['laboratoire']); ?>">
                        </div>
                        
                        <div class="form-actions">
                            <a href="medicament.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" name="modifier" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($action === 'voir' && $medicament): ?>
                <!-- Vue détail simplifiée -->
                <div class="detail-view">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Nom</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['nom']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">DCI</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['dci']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Forme / Dosage</div>
                            <div class="detail-value"><?php echo $medicament['forme']; ?> <?php echo $medicament['dosage']; ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Laboratoire</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['laboratoire']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Prix d'achat</div>
                            <div class="detail-value"><?php echo number_format($medicament['prix_achat'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Prix de vente</div>
                            <div class="detail-value"><?php echo number_format($medicament['prix_vente'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Stock</div>
                            <div class="detail-value"><?php echo $medicament['stock']; ?> unités</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Seuil d'alerte</div>
                            <div class="detail-value"><?php echo $medicament['seuil_alerte']; ?> unités</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Fournisseur</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['fournisseur']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">N° de lot</div>
                            <div class="detail-value"><?php echo htmlspecialchars($medicament['numero_lot']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Date péremption</div>
                            <div class="detail-value">
                                <?php if ($medicament['date_peremption']): ?>
                                    <?php echo date('d/m/Y', strtotime($medicament['date_peremption'])); ?>
                                <?php else: ?>
                                    Non précisée
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Code barre</div>
                            <div class="detail-value"><?php echo $medicament['code_barre'] ?: 'Non défini'; ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <a href="medicament.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <a href="medicament.php?action=modifier&id=<?php echo $medicament['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Modal de confirmation suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; color: #e74c3c;">
                <i class="fas fa-exclamation-triangle"></i> Confirmer la suppression
            </h3>
            <p id="deleteMessage" style="margin-bottom: 20px;"></p>
            <form id="deleteForm" method="POST" action="">
                <input type="hidden" name="id" id="deleteId">
                <div class="modal-actions">
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
            document.getElementById('deleteMessage').innerHTML = 
                'Êtes-vous sûr de vouloir supprimer le médicament <strong>"' + nom + '"</strong> ?<br>Cette action est irréversible.';
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
        
        // Raccourci clavier : Ctrl + Entrée pour "Ajouter + Nouveau"
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                var btn = document.querySelector('button[name="ajouter_continuer"]');
                if (btn) {
                    btn.click();
                }
            }
        });
    </script>
</body>
</html>