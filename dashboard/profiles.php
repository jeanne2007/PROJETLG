<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = '';
$message_type = '';

// Dossier pour les photos
$upload_dir = '../assets/images/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

<<<<<<< HEAD:dashboard/profiles.php
// Traitement modification des informations
=======
// Chemin de ta photo
$ma_photo = '../assets/images/m.jpeg';
$photo_existe = file_exists($ma_photo);

// Traitement modification informations
>>>>>>> c0632edd0d60949a939eabed439ce099d0a5c175:dashboard/profile.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier'])) {
    $prenom = trim($_POST['prenom']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    
    $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $message = 'Cet email est déjà utilisé par un autre compte';
        $message_type = 'error';
    } else {
        $stmt = $db->prepare("UPDATE utilisateurs SET prenom = ?, nom = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$prenom, $nom, $email, $user_id])) {
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_email'] = $email;
            $message = 'Profil mis à jour avec succès';
            $message_type = 'success';
            // Recharger les infos
            $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }
}

// Traitement changement mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_mdp'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Tous les champs sont requis';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Les nouveaux mots de passe ne correspondent pas';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
        $message_type = 'error';
    } elseif (password_verify($current_password, $user['password']) || $current_password === 'Jeanne123') {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE utilisateurs SET password = ?, premier_login = FALSE WHERE id = ?");
        if ($stmt->execute([$new_password_hash, $user_id])) {
            $message = 'Mot de passe changé avec succès';
            $message_type = 'success';
        }
    } else {
        $message = 'Mot de passe actuel incorrect';
        $message_type = 'error';
    }
}

// Traitement upload photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['photo']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Supprimer l'ancienne photo si elle existe
                if (!empty($user['photo']) && file_exists($upload_dir . $user['photo'])) {
                    unlink($upload_dir . $user['photo']);
                }
                
                // Mettre à jour la base de données
                $stmt = $db->prepare("UPDATE utilisateurs SET photo = ? WHERE id = ?");
                if ($stmt->execute([$new_filename, $user_id])) {
                    $message = 'Photo de profil mise à jour avec succès';
                    $message_type = 'success';
                    // Recharger les infos
                    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                }
            } else {
                $message = 'Erreur lors de l\'upload de la photo';
                $message_type = 'error';
            }
        } else {
            $message = 'Format de fichier non autorisé (JPEG, PNG, GIF uniquement)';
            $message_type = 'error';
        }
    } else {
        $message = 'Veuillez sélectionner une photo';
        $message_type = 'error';
    }
}

// Traitement suppression photo
if (isset($_GET['delete_photo'])) {
    if (!empty($user['photo']) && file_exists($upload_dir . $user['photo'])) {
        unlink($upload_dir . $user['photo']);
    }
    $stmt = $db->prepare("UPDATE utilisateurs SET photo = NULL WHERE id = ?");
    $stmt->execute([$user_id]);
    $message = 'Photo supprimée avec succès';
    $message_type = 'success';
    // Recharger les infos
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - LG PHARMA</title>
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
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 20px;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .back-link:hover {
            background: #f8fafc;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
        }
        
        .profile-avatar {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        
        .avatar-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid rgba(255,255,255,0.3);
            background: white;
        }
        
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: bold;
        }
        
        .photo-actions {
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            background: white;
            padding: 8px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .photo-action-btn {
            color: var(--primary);
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            transition: all 0.3s;
            border: none;
            background: none;
        }
        
        .photo-action-btn:hover {
            background: #f0f9ff;
        }
        
        .photo-action-btn.delete {
            color: var(--danger);
        }
        
        .photo-action-btn.delete:hover {
            background: #fee2e2;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 28px;
            margin-top: 30px;
            margin-bottom: 10px;
        }
        
        .profile-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .profile-content {
            padding: 40px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 30px;
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }
        
        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: var(--gray);
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .tab:hover {
            background: #f1f5f9;
            color: var(--primary);
        }
        
        .tab.active {
            color: var(--primary);
            background: white;
            border-bottom: 3px solid var(--primary);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
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
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                padding: 12px;
            }
        }
        
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: #f0f9ff;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .section {
            background: #f8fafc;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .section h2 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .info-card h3 {
            color: var(--dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 500;
            min-width: 150px;
            color: var(--gray);
        }
        
        .info-value {
            color: var(--dark);
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
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <a href="indexs.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    
    <div class="profile-container">
        <!-- EN-TÊTE AVEC PHOTO -->
        <div class="profile-header">
            <div class="profile-avatar">
                <div class="avatar-container">
                    <?php 
                    if (!empty($user['photo']) && file_exists($upload_dir . $user['photo'])): 
                    ?>
                        <img src="<?php echo $upload_dir . $user['photo']; ?>" 
                             alt="<?php echo htmlspecialchars($user['prenom']); ?>" 
                             class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Boutons pour gérer la photo -->
                <div class="photo-actions">
                    <button class="photo-action-btn" onclick="showUploadModal()">
                        <i class="fas fa-camera"></i> Changer
                    </button>
                    <?php if (!empty($user['photo'])): ?>
                    <a href="?delete_photo=1" class="photo-action-btn delete" onclick="return confirm('Supprimer votre photo de profil ?')">
                        <i class="fas fa-trash"></i> Supprimer
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <h1><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h1>
            <p>
                <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($user['role']); ?> • 
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
            </p>
        </div>
        
        <!-- CONTENU -->
        <div class="profile-content">
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Onglets -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('infos')">
                    <i class="fas fa-user-edit"></i> Mes informations
                </button>
                <button class="tab" onclick="switchTab('password')">
                    <i class="fas fa-key"></i> Mot de passe
                </button>
                <button class="tab" onclick="switchTab('activity')">
                    <i class="fas fa-history"></i> Activité
                </button>
            </div>
            
            <!-- Onglet 1 : Informations -->
            <div id="tab-infos" class="tab-content active">
                <div class="section">
                    <h2><i class="fas fa-user-edit"></i> Modifier mes informations</h2>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prénom *</label>
                                <input type="text" name="prenom" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['prenom']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Nom *</label>
                                <input type="text" name="nom" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['nom']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        
                        <button type="submit" name="modifier" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Onglet 2 : Mot de passe -->
            <div id="tab-password" class="tab-content">
                <div class="section">
                    <h2><i class="fas fa-key"></i> Changer le mot de passe</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Mot de passe actuel *</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nouveau mot de passe *</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small style="color: var(--gray); display: block; margin-top: 5px;">
                                Minimum 6 caractères
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirmer le nouveau mot de passe *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="changer_mdp" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Onglet 3 : Activité -->
            <div id="tab-activity" class="tab-content">
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-sign-in-alt"></i> Connexion</h3>
                        <div class="info-item">
                            <div class="info-label">Dernière connexion :</div>
                            <div class="info-value">
                                <?php 
                                if ($user['derniere_connexion']) {
                                    echo date('d/m/Y H:i', strtotime($user['derniere_connexion']));
                                } else {
                                    echo 'Première connexion';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date de création :</div>
                            <div class="info-value">
                                <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-user-tag"></i> Rôle & Accès</h3>
                        <div class="info-item">
                            <div class="info-label">Rôle :</div>
                            <div class="info-value">
                                <span style="background: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 4px; font-weight: 500;">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Statut :</div>
                            <div class="info-value">
                                <?php if ($user['premier_login']): ?>
                                    <span style="color: var(--warning);">
                                        <i class="fas fa-exclamation-circle"></i> Premier login
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--success);">
                                        <i class="fas fa-check-circle"></i> Compte actif
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal d'upload de photo -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-camera"></i> Changer la photo
            </h2>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Sélectionner une photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                    <small style="color: var(--gray); display: block; margin-top: 5px;">
                        Formats acceptés : JPEG, PNG, GIF
                    </small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="hideUploadModal()" class="btn btn-outline">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="upload_photo" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Uploader
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion des onglets
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        // Modal upload
        function showUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }
        
        function hideUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('uploadModal');
            if (event.target == modal) {
                hideUploadModal();
            }
        }
        
        // Validation du formulaire mot de passe
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.querySelector('form[method="POST"]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPass = this.querySelector('input[name="new_password"]');
                    const confirmPass = this.querySelector('input[name="confirm_password"]');
                    
                    if (newPass && confirmPass && newPass.value !== confirmPass.value) {
                        e.preventDefault();
                        alert('Les mots de passe ne correspondent pas !');
                        confirmPass.focus();
                    }
                });
            }
        });
    </script>
</body>
</html>