<?php
/**
 * =============================================
 * SCRIPT D'INSTALLATION - COLLÈGE LE FANION
 * =============================================
 * Ce fichier configure automatiquement l'application
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'fanion_notes');
define('DB_USER', 'root');
define('DB_PASS', '');

$success = [];
$errors = [];

// Étape 1 : Créer les dossiers nécessaires
$directories = [
    'includes',
    'uploads',
    'uploads/bulletins',
    'uploads/bordereaux',
    'uploads/recus',
    'uploads/photos'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            $success[] = "✓ Dossier créé : $dir";
        } else {
            $errors[] = "✗ Impossible de créer : $dir";
        }
    } else {
        $success[] = "✓ Dossier existe déjà : $dir";
    }
}

// Étape 2 : Vérifier la connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $success[] = "✓ Connexion à MySQL réussie";
    
    // Créer la base de données si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $success[] = "✓ Base de données " . DB_NAME . " créée/vérifiée";
    
    // Se connecter à la base de données
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Vérifier si les tables existent déjà
    $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
    if ($stmt->rowCount() == 0) {
        $success[] = "✓ Tables à créer...";
        $needsSetup = true;
    } else {
        $success[] = "✓ Tables existent déjà";
        $needsSetup = false;
    }
    
} catch (PDOException $e) {
    $errors[] = "✗ Erreur de connexion MySQL : " . $e->getMessage();
    $errors[] = "Vérifiez que MySQL est démarré et que les paramètres de connexion sont corrects";
}

// Étape 3 : Créer le fichier includes/styles.php
$stylesContent = <<<'STYLES'
<style>
:root {
    --primary-color: #2563eb;
    --secondary-color: #1e40af;
    --success-color: #10b981;
    --danger-color: #ef4444;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f3f4f6;
}
.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 70px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    background: white;
}
.navbar {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}
main {
    padding-top: 70px;
}
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}
.stat-card .card-body {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}
.stat-primary .stat-icon {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}
</style>
STYLES;

if (file_put_contents('includes/styles.php', $stylesContent)) {
    $success[] = "✓ Fichier includes/styles.php créé";
} else {
    $errors[] = "✗ Impossible de créer includes/styles.php";
}

// Étape 4 : Créer le fichier includes/header.php (version simplifiée)
$headerContent = <<<'HEADER'
<?php
if (!isset($user)) {
    $user = getCurrentUser();
}
?>
<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-mortarboard-fill me-2"></i>
            Collège Le Fanion
        </a>
        <div class="d-flex">
            <div class="dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <?php echo htmlspecialchars($user['nom_complet'] ?? 'Utilisateur'); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <form method="POST" action="auth.php">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Déconnexion
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
HEADER;

if (file_put_contents('includes/header.php', $headerContent)) {
    $success[] = "✓ Fichier includes/header.php créé";
} else {
    $errors[] = "✗ Impossible de créer includes/header.php";
}

// Étape 5 : Créer le fichier includes/sidebar.php (version simplifiée)
$sidebarContent = <<<'SIDEBAR'
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="sidebar-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'eleves.php') ? 'active' : ''; ?>" href="eleves.php">
                    <i class="bi bi-people-fill"></i> Élèves
                </a>
            </li>
        </ul>
    </div>
</nav>
SIDEBAR;

if (file_put_contents('includes/sidebar.php', $sidebarContent)) {
    $success[] = "✓ Fichier includes/sidebar.php créé";
} else {
    $errors[] = "✗ Impossible de créer includes/sidebar.php";
}

// Étape 6 : Vérifier le fichier .htaccess
if (!file_exists('.htaccess')) {
    $htaccessContent = <<<'HTACCESS'
RewriteEngine On
DirectoryIndex index.php
Options -Indexes

<FilesMatch "^(config\.php|auth\.php)$">
    Order allow,deny
    Deny from all
</FilesMatch>

php_flag display_errors On
php_value error_reporting E_ALL
HTACCESS;
    
    if (file_put_contents('.htaccess', $htaccessContent)) {
        $success[] = "✓ Fichier .htaccess créé";
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Collège Le Fanion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .install-container {
            max-width: 800px;
            width: 100%;
            padding: 20px;
        }
        .install-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .install-body {
            padding: 30px;
        }
        .success-item {
            color: #10b981;
            margin-bottom: 10px;
        }
        .error-item {
            color: #ef4444;
            margin-bottom: 10px;
        }
        .btn-install {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><i class="bi bi-gear-fill me-2"></i>Installation</h1>
                <p class="mb-0">Collège Le Fanion - Système de Gestion des Notes</p>
            </div>
            
            <div class="install-body">
                <h4 class="mb-4">État de l'installation</h4>
                
                <?php if (!empty($success)): ?>
                    <div class="mb-4">
                        <h6 class="text-success">✓ Opérations réussies :</h6>
                        <?php foreach ($success as $msg): ?>
                            <div class="success-item"><?php echo $msg; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6 class="text-danger">✗ Erreurs détectées :</h6>
                        <?php foreach ($errors as $error): ?>
                            <div class="error-item"><?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($errors)): ?>
                    <div class="alert alert-success mt-4">
                        <h5><i class="bi bi-check-circle-fill me-2"></i>Installation réussie !</h5>
                        <p class="mb-3">L'application est maintenant configurée.</p>
                        
                        <hr>
                        
                        <h6>Prochaines étapes :</h6>
                        <ol>
                            <li>Importez le fichier <strong>database.sql</strong> dans phpMyAdmin</li>
                            <li>Connectez-vous avec les identifiants par défaut :
                                <ul>
                                    <li>Utilisateur : <strong>admin</strong></li>
                                    <li>Mot de passe : <strong>admin123</strong></li>
                                </ul>
                            </li>
                        </ol>
                        
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-install">
                                <i class="bi bi-arrow-right-circle me-2"></i>
                                Accéder à la page de connexion
                            </a>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <strong><i class="bi bi-info-circle me-2"></i>Important :</strong>
                        Pour des raisons de sécurité, supprimez le fichier <code>install.php</code> après l'installation.
                    </div>
                <?php else: ?>
                    <div class="mt-4">
                        <button onclick="location.reload()" class="btn btn-install">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Réessayer l'installation
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>