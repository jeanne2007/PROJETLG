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
    header("Location: index.php?message=Accès réservé aux administrateurs&type=error");
    exit();
}

// Récupérer tous les paramètres
$parametres = $db->query("SELECT * FROM parametres ORDER BY cle")->fetchAll();

$message = '';
$message_type = '';

// Traitement des modifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['modifier_parametres'])) {
        foreach ($_POST['parametres'] as $cle => $valeur) {
            $stmt = $db->prepare("UPDATE parametres SET valeur = ? WHERE cle = ?");
            $stmt->execute([trim($valeur), $cle]);
        }
        $message = 'Paramètres mis à jour avec succès';
        $message_type = 'success';
        
        // Recharger les paramètres
        $parametres = $db->query("SELECT * FROM parametres ORDER BY cle")->fetchAll();
    }
    
    if (isset($_POST['ajouter_categorie'])) {
        $nom = trim($_POST['nouvelle_categorie']);
        if (!empty($nom)) {
            try {
                $stmt = $db->prepare("INSERT INTO categories (nom) VALUES (?)");
                $stmt->execute([$nom]);
                $message = 'Catégorie ajoutée avec succès';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Cette catégorie existe déjà';
                $message_type = 'error';
            }
        }
    }
    
    if (isset($_POST['supprimer_categorie'])) {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$_POST['categorie_id']]);
        $message = 'Catégorie supprimée avec succès';
        $message_type = 'success';
    }
}

// Récupérer les catégories
$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

// Récupérer info utilisateur pour sidebar
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_info = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - LG PHARMA</title>
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
        
        /* SECTIONS */
        .settings-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .section-header h2 {
            color: var(--dark);
            font-size: 22px;
        }
        
        .section-header i {
            color: var(--primary);
            font-size: 24px;
        }
        
        /* FORMULAIRES */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        /* BOUTONS */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 15px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        /* TABLE DES CATÉGORIES */
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .categories-table th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            color: var(--gray);
            font-weight: 500;
            border-bottom: 1px solid var(--border);
        }
        
        .categories-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .categories-table tr:last-child td {
            border-bottom: none;
        }
        
        /* INFOS PHARMACIE */
        .pharmacy-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-card h3 {
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-card p {
            color: var(--gray);
            line-height: 1.6;
        }
        
        /* RESPONSIVE */
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* NOTES */
        .notes {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            margin-top: 20px;
            border-radius: 0 8px 8px 0;
        }
        
        .notes h4 {
            color: #0369a1;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notes ul {
            margin-left: 20px;
            color: #0c4a6e;
        }
        
        .notes li {
            margin-bottom: 5px;
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
                <a href="rapports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span class="nav-text">Rapports</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    <span class="nav-text">Mon profil</span>
                </a>
                <a href="parametres.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Paramètres</span>
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
                        <i class="fas fa-cog"></i>
                        Paramètres de la pharmacie
                    </h1>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
                    <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Informations pharmacie actuelle -->
            <div class="pharmacy-info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-hospital"></i> Pharmacie</h3>
                    <p><strong><?php echo getParametre('nom_pharmacie'); ?></strong></p>
                    <p><?php echo getParametre('adresse'); ?></p>
                    <p><?php echo getParametre('ville'); ?>, <?php echo getParametre('pays'); ?></p>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-user-md"></i> Responsable</h3>
                    <p><strong><?php echo getParametre('pharmacienne'); ?></strong></p>
                    <p>📞 <?php echo getParametre('telephone'); ?></p>
                    <p>📧 <?php echo getParametre('email_contact'); ?></p>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-chart-line"></i> Configuration système</h3>
                    <p>📊 Seuil stock bas : <?php echo getParametre('seuil_stock_bas'); ?> unités</p>
                    <p>⏰ Alerte péremption : <?php echo getParametre('jours_alerte_peremption'); ?> jours avant</p>
                    <p>💰 Devise : <?php echo getParametre('devise'); ?></p>
                </div>
            </div>
            
            <!-- Formulaire des paramètres -->
            <div class="settings-section">
                <div class="section-header">
                    <i class="fas fa-edit"></i>
                    <h2>Modifier les paramètres</h2>
                </div>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <?php foreach ($parametres as $param): ?>
                            <?php if (!in_array($param['cle'], ['timezone', 'pays', 'ville'])): ?>
                                <div class="form-group">
                                    <label>
                                        <?php 
                                        $labels = [
                                            'nom_pharmacie' => 'Nom de la pharmacie',
                                            'pharmacienne' => 'Nom de la pharmacienne',
                                            'adresse' => 'Adresse',
                                            'telephone' => 'Téléphone',
                                            'email_contact' => 'Email de contact',
                                            'seuil_stock_bas' => 'Seuil stock bas (unités)',
                                            'jours_alerte_peremption' => 'Alerte péremption (jours avant)',
                                            'devise' => 'Devise',
                                            'timezone' => 'Fuseau horaire',
                                            'pays' => 'Pays',
                                            'ville' => 'Ville'
                                        ];
                                        echo $labels[$param['cle']] ?? ucfirst(str_replace('_', ' ', $param['cle']));
                                        ?>
                                    </label>
                                    
                                    <?php if (in_array($param['cle'], ['seuil_stock_bas', 'jours_alerte_peremption'])): ?>
                                        <input type="number" 
                                               name="parametres[<?php echo $param['cle']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($param['valeur']); ?>"
                                               min="1" required>
                                    <?php elseif ($param['cle'] === 'devise'): ?>
                                        <select name="parametres[<?php echo $param['cle']; ?>]" class="form-control" required>
                                            <option value="FCFA" <?php echo $param['valeur'] === 'FCFA' ? 'selected' : ''; ?>>FCFA (Franc CFA)</option>
                                            <option value="$" <?php echo $param['valeur'] === '$' ? 'selected' : ''; ?>>$ (Dollar)</option>
                                            <option value="€" <?php echo $param['valeur'] === '€' ? 'selected' : ''; ?>>€ (Euro)</option>
                                            <option value="CDF" <?php echo $param['valeur'] === 'CDF' ? 'selected' : ''; ?>>CDF (Franc Congolais)</option>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" 
                                               name="parametres[<?php echo $param['cle']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($param['valeur']); ?>"
                                               <?php echo in_array($param['cle'], ['nom_pharmacie', 'pharmacienne', 'adresse', 'telephone', 'email_contact']) ? 'required' : ''; ?>>
                                    <?php endif; ?>
                                    
                                    <?php if ($param['description']): ?>
                                        <small style="color: var(--gray); margin-top: 5px; display: block;">
                                            <?php echo htmlspecialchars($param['description']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" name="modifier_parametres" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer tous les paramètres
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Gestion des catégories -->
            <div class="settings-section">
                <div class="section-header">
                    <i class="fas fa-tags"></i>
                    <h2>Gestion des catégories</h2>
                </div>
                
                <!-- Ajouter une catégorie -->
                <form method="POST" action="" style="margin-bottom: 25px;">
                    <div class="form-row" style="align-items: flex-end;">
                        <div class="form-group">
                            <label>Nouvelle catégorie</label>
                            <input type="text" name="nouvelle_categorie" class="form-control" 
                                   placeholder="ex: Antibiotiques, Analgésiques..." required>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="ajouter_categorie" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter la catégorie
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Liste des catégories -->
                <?php if (!empty($categories)): ?>
                    <table class="categories-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom de la catégorie</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $categorie): ?>
                                <tr>
                                    <td><?php echo $categorie['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($categorie['nom']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($categorie['description'] ?? 'Aucune description'); ?></td>
                                    <td>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="categorie_id" value="<?php echo $categorie['id']; ?>">
                                            <button type="submit" name="supprimer_categorie" 
                                                    class="btn btn-danger" 
                                                    onclick="return confirm('Supprimer cette catégorie ?')"
                                                    style="padding: 6px 12px; font-size: 13px;">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: var(--gray); text-align: center; padding: 20px;">
                        <i class="fas fa-tags" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                        Aucune catégorie créée
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Informations système -->
            <div class="settings-section">
                <div class="section-header">
                    <i class="fas fa-info-circle"></i>
                    <h2>Informations système</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Version du système</label>
                        <input type="text" class="form-control" value="LG PHARMA v1.0" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Date d'installation</label>
                        <input type="text" class="form-control" 
                               value="<?php echo date('d/m/Y'); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Base de données</label>
                        <input type="text" class="form-control" value="MySQL/MariaDB" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Serveur web</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>PHP Version</label>
                        <input type="text" class="form-control" value="<?php echo phpversion(); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Heure serveur</label>
                        <input type="text" class="form-control" 
                               value="<?php echo date('d/m/Y H:i:s'); ?> - <?php echo getParametre('timezone'); ?>" readonly>
                    </div>
                </div>
                
                <div class="notes">
                    <h4><i class="fas fa-lightbulb"></i> Notes importantes</h4>
                    <ul>
                        <li>Les modifications prennent effet immédiatement</li>
                        <li>Le seuil de stock bas déclenche des alertes automatiques</li>
                        <li>Les catégories sont utilisables immédiatement après création</li>
                        <li>Sauvegardez régulièrement votre base de données</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Confirmation avant suppression
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier la longueur des champs
            const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value.length > 200) {
                        this.style.borderColor = 'var(--danger)';
                        this.style.backgroundColor = '#fee2e2';
                    } else {
                        this.style.borderColor = '';
                        this.style.backgroundColor = '';
                    }
                });
            });
            
            // Avertissement avant soumission
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir modifier les paramètres du système ?')) {
                        e.preventDefault();
                    }
                });
            }
            
            // Afficher/masquer l'aide
            const helpButton = document.createElement('button');
            helpButton.innerHTML = '<i class="fas fa-question-circle"></i> Aide';
            helpButton.style.cssText = 'position: fixed; bottom: 20px; right: 20px; padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 50px; cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.2);';
            helpButton.onclick = function() {
                alert('📋 AIDE PARAMÈTRES\n\n1. Nom de la pharmacie : Apparaît sur toutes les pages\n2. Seuil stock bas : Nombre minimum avant alerte\n3. Alerte péremption : Jours avant expiration pour alerte\n4. Catégories : Classifiez vos médicaments\n\nToutes modifications sont immédiates.');
            };
            document.body.appendChild(helpButton);
        });
    </script>
</body>
</html>