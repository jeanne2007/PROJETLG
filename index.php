<?php
session_start();

// Si déjà connecté, aller au dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/indexs.php');
    exit();
}

// Inclure la base de données
require_once 'config/database.php';
$db = getDB();

$message = '';
$message_type = ''; // 'success' ou 'error'

// Si formulaire envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $message = 'Tous les champs sont requis';
        $message_type = 'error';
    } else {
        try {
            // Chercher l'utilisateur
            $stmt = $db->prepare("SELECT id, prenom, password, role FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Vérifier le mot de passe
                if (password_verify($password, $user['password'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = $user['role'];
                    
                    // AJOUT DU LOG DE CONNEXION
                    require_once 'includes/functions.php';
                    logAction($db, $user['id'], 'connexion', 'Connexion à l\'application');
                    
                    // Rediriger vers le dashboard
                    header('Location: dashboard/indexs.php');
                    exit();
                } else {
                    $message = 'Mot de passe incorrect';
                    $message_type = 'error';
                }
            } else {
                $message = 'Aucun compte avec cet email';
                $message_type = 'error';
            }
            
        } catch (Exception $e) {
            $message = 'Erreur technique : ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion - LG Pharma</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #b83f6d;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dddddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn:hover {
            background-color: #8b7639;
        }
        
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .test-credentials {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            color: #0369a1;
            border-left: 4px solid #0ea5e9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>LG Pharma</h1>
            <p>Gestion Pharmaceutique</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" required 
                       placeholder="jeannengbo3@gmail.com">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe </label>
                <input type="password" id="password" name="password" required 
                       placeholder="Votre mot de passe">
            </div>
            
            <button type="submit" class="btn">Se connecter</button>
        </form>
    </div>
</body>
</html>