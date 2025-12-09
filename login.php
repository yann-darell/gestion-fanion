<?php
// ACTIVER LES ERREURS POUR LE DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

// Traitement de la connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // DEBUG: Afficher ce qui est reçu
        // echo "Username: $username<br>Password: $password<br>";
        
        $result = Auth::login($username, $password);
        
        // DEBUG: Afficher le résultat
        // echo "<pre>"; print_r($result); echo "</pre>";
        // echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NON DÉFINI') . "<br>";
        // echo "isLoggedIn: " . (isLoggedIn() ? 'OUI' : 'NON') . "<br>";
        // die(); // DÉCOMMENTER POUR STOPPER ET VOIR LE DEBUG
        
        if ($result['success']) {
            // Forcer la redirection avec header
            header("Location: dashboard.php", true, 302);
            exit();
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    } elseif ($action === 'logout') {
        Auth::logout();
        $successMessage = 'Vous avez été déconnecté avec succès';
    }
}

// Si déjà connecté, rediriger vers le dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php", true, 302);
    exit();
}

// Récupérer les messages d'erreur/succès
$errorMessage = $_SESSION['error_message'] ?? '';
$successMessage = $successMessage ?? '';

if (isset($_GET['success']) && $_GET['success'] === 'logged_out') {
    $successMessage = 'Vous avez été déconnecté avec succès';
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_expired':
            $errorMessage = 'Votre session a expiré. Veuillez vous reconnecter.';
            break;
        case 'login_failed':
            // Le message est déjà dans la session
            break;
        case 'access_denied':
            $errorMessage = 'Accès non autorisé. Veuillez vous connecter.';
            break;
    }
}

unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
        }
        
        .logo-icon i {
            font-size: 40px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .input-group-text {
            border: 2px solid #e5e7eb;
            border-right: none;
            background: white;
            border-radius: 10px 0 0 10px;
            padding: 12px 15px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .show-password {
            cursor: pointer;
            color: #6b7280;
            transition: color 0.2s;
        }
        
        .show-password:hover {
            color: var(--primary-color);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        
        .login-footer p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }
        
        .version-info {
            margin-top: 10px;
            font-size: 12px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h1>Collège Le Fanion</h1>
                <p>Système de Gestion des Notes et Paiements</p>
            </div>
            
            <div class="login-body">
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php" id="loginForm">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="bi bi-person-fill me-1"></i>
                            Nom d'utilisateur
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Entrez votre nom d'utilisateur"
                                   required 
                                   autofocus>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock-fill me-1"></i>
                            Mot de passe
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-key"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Entrez votre mot de passe"
                                   required>
                            <span class="input-group-text show-password" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Se connecter
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <p>
                    <i class="bi bi-shield-check me-1"></i>
                    Connexion sécurisée
                </p>
                <p class="version-info">Version <?php echo APP_VERSION; ?> • <?php echo date('Y'); ?></p>
            </div>
        </div>
        
        <div class="text-center mt-4 text-white">
            <small>
                <i class="bi bi-info-circle me-1"></i>
                Identifiants par défaut : <strong>admin</strong> / <strong>admin123</strong>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour afficher/masquer le mot de passe
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        }
        
        // Validation du formulaire
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (username === '' || password === '') {
                e.preventDefault();
                alert('Veuillez remplir tous les champs');
                return false;
            }
        });
    </script>
</body>
</html>