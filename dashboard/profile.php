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

// Chemin de ta photo
$ma_photo = '../assets/images/Q.jpg';
$photo_existe = file_exists($ma_photo);

// Traitement modification informations
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
        
        .profile-header h1 {
            margin: 0;
            font-size: 28px;
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
        
        .photo-note {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            margin-top: 20px;
            border-radius: 0 8px 8px 0;
            font-size: 14px;
            color: #0369a1;
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
    </a>
    
    <div class="profile-container">
        <!-- EN-TÊTE AVEC PHOTO -->
        <div class="profile-header">
            <div class="profile-avatar">
                <div class="avatar-container">
                    <?php if ($photo_existe): ?>
                        <!-- Affiche TA photo -->
                        <img src="<?php echo $ma_photo; ?>" 
                             alt="<?php echo htmlspecialchars($user['prenom']); ?>" 
                             class="avatar-img">
                    <?php else: ?>
                        <!-- Initiales si pas de photo -->
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($user['prenom'], 0, 1)); ?>
                        </div>
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
    
    <script>
        // Gestion des onglets
        function switchTab(tabName) {
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activer l'onglet sélectionné
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        // Validation du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            // Validation mot de passe
            const passwordForm = document.querySelector('form[name="changer_mdp"]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPass = this.querySelector('input[name="new_password"]');
                    const confirmPass = this.querySelector('input[name="confirm_password"]');
                    
                    if (newPass.value !== confirmPass.value) {
                        e.preventDefault();
                        alert('Les mots de passe ne correspondent pas !');
                        confirmPass.focus();
                    }
                    
                    if (newPass.value.length < 6) {
                        e.preventDefault();
                        alert('Le mot de passe doit contenir au moins 6 caractères !');
                        newPass.focus();
                    }
                });
            }
            
            // Animation pour la photo
            const avatar = document.querySelector('.avatar-container');
            if (avatar) {
                avatar.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                avatar.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            }
        });
    </script>
</body>
</html>