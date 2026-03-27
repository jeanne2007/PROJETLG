<?php
session_start();

// Vérifier connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// 🔐 Vérifier que l'utilisateur est ADMIN
require_once '../includes/check_role.php';
checkAdmin(); // Seul l'admin peut gérer les utilisateurs

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$message = '';
$message_type = '';

// Récupérer les infos de l'admin connecté (pour la sidebar)
$stmt = $db->prepare("SELECT prenom, role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// --- AJOUTER un utilisateur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter'])) {
    $prenom = trim($_POST['prenom']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = password_hash('123456', PASSWORD_DEFAULT); // Mot de passe par défaut

    // Vérifier si l'email existe déjà
    $check = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $message = "Cet email est déjà utilisé";
        $message_type = "error";
    } else {
        $stmt = $db->prepare("INSERT INTO utilisateurs (prenom, nom, email, password, role, date_creation) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt->execute([$prenom, $nom, $email, $password, $role])) {
            $message = "Utilisateur ajouté avec succès (mot de passe par défaut : 123456)";
            $message_type = "success";
        } else {
            $message = "Erreur lors de l'ajout";
            $message_type = "error";
        }
    }
}

// --- MODIFIER un utilisateur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier'])) {
    $id = $_POST['id'];
    $prenom = trim($_POST['prenom']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Vérifier si l'email existe déjà pour un autre utilisateur
    $check = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        $message = "Cet email est déjà utilisé par un autre compte";
        $message_type = "error";
    } else {
        $stmt = $db->prepare("UPDATE utilisateurs SET prenom = ?, nom = ?, email = ?, role = ? WHERE id = ?");
        if ($stmt->execute([$prenom, $nom, $email, $role, $id])) {
            $message = "Utilisateur modifié avec succès";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la modification";
            $message_type = "error";
        }
    }
}

// --- SUPPRIMER un utilisateur ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Empêcher l'admin de se supprimer lui-même
    if ($id == $_SESSION['user_id']) {
        $message = "Vous ne pouvez pas supprimer votre propre compte !";
        $message_type = "error";
    } else {
        $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = "Utilisateur supprimé avec succès";
            $message_type = "success";
        }
    }
}

// --- RÉINITIALISER le mot de passe ---
if (isset($_GET['reset'])) {
    $id = $_GET['reset'];
    $new_password = password_hash('123456', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE utilisateurs SET password = ?, premier_login = TRUE WHERE id = ?");
    if ($stmt->execute([$new_password, $id])) {
        $message = "Mot de passe réinitialisé (nouveau mot de passe : 123456)";
        $message_type = "success";
    }
}

// Récupérer tous les utilisateurs
$users = $db->query("SELECT id, prenom, nom, email, role, premier_login, date_creation, derniere_connexion FROM utilisateurs ORDER BY role, nom")->fetchAll();

// Récupérer un utilisateur pour modification (si demandé)
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - LG PHARMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
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

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-outline {
            background: white;
            color: #3498db;
            border: 1px solid #3498db;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #34495e;
            color: white;
            padding: 15px;
            text-align: left;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-admin {
            background: #3498db;
            color: white;
        }

        .badge-vendeur {
            background: #2ecc71;
            color: white;
        }

        .badge-warning {
            background: #f39c12;
            color: white;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

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
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            td, th {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-users" style="color: #3498db;"></i>
                Gestion des utilisateurs
            </h1>
            <div>
                <a href="indexs.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> Retour
                </a>
                <button onclick="showAddModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvel utilisateur
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Liste des utilisateurs -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Liste des utilisateurs</h2>
            
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Date création</th>
                            <th>Dernière connexion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <br><small style="color: #3498db;">(vous)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo $user['role'] === 'admin' ? 'Administrateur' : 'Vendeur'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['premier_login']): ?>
                                        <span class="badge badge-warning">Première connexion</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #27ae60; color: white;">Actif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['date_creation'])); ?></td>
                                <td>
                                    <?php echo $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : '-'; ?>
                                </td>
                                <td class="actions">
                                    <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <a href="?reset=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" 
                                       onclick="return confirm('Réinitialiser le mot de passe de cet utilisateur ? (nouveau mot de passe : 123456)')"
                                       title="Réinitialiser mot de passe">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Supprimer cet utilisateur ? Cette action est irréversible.')"
                                           title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Formulaire de modification (si édition en cours) -->
        <?php if ($edit_user): ?>
        <div class="card">
            <h2><i class="fas fa-edit"></i> Modifier l'utilisateur</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" class="form-control" required
                               value="<?php echo htmlspecialchars($edit_user['prenom']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" class="form-control" required
                               value="<?php echo htmlspecialchars($edit_user['nom']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_user['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Rôle *</label>
                    <select name="role" class="form-control" required>
                        <option value="vendeur" <?php echo $edit_user['role'] === 'vendeur' ? 'selected' : ''; ?>>Vendeur</option>
                        <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="modifier" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="utilisateur.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal d'ajout d'utilisateur -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-user-plus" style="color: #27ae60;"></i> Ajouter un utilisateur
            </h2>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Rôle *</label>
                    <select name="role" class="form-control" required>
                        <option value="vendeur">Vendeur</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <small style="color: #7f8c8d;">
                        <i class="fas fa-info-circle"></i> Le mot de passe par défaut sera <strong>123456</strong>
                    </small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="hideAddModal()" class="btn btn-outline">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="ajouter" class="btn btn-success">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function hideAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) {
                hideAddModal();
            }
        }
    </script>
</body>
</html>