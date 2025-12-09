<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostic du système</h1>";
echo "<hr>";

// NOUVEAU : Bouton pour installer les rôles
if (isset($_GET['install_roles'])) {
    echo "<h2 style='color: #2563eb;'>🔧 Installation des rôles et permissions</h2>";
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=fanion_notes;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div style='background: #f0fdf4; padding: 15px; border-left: 4px solid #10b981; margin: 10px 0;'>";
        
        // Étape 1 : Modifier la colonne role
        try {
            $pdo->exec("ALTER TABLE utilisateurs MODIFY COLUMN role ENUM('administrateur', 'principale', 'directeur_etudes', 'secretaire') DEFAULT 'secretaire'");
            echo "✅ Colonne 'role' mise à jour<br>";
        } catch (PDOException $e) {
            echo "✅ Colonne 'role' déjà à jour<br>";
        }
        
        // Étape 2 : Créer l'utilisateur Principale
        $stmt = $pdo->prepare("
            INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, email, actif)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE nom_complet = VALUES(nom_complet), role = VALUES(role)
        ");
        $stmt->execute(['principale', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Principale de l\'établissement', 'principale', 'principale@lefanion.cm']);
        echo "✅ Utilisateur 'principale' créé<br>";
        
        // Étape 3 : Créer l'utilisateur Directeur
        $stmt->execute(['directeur', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Directeur des Études', 'directeur_etudes', 'directeur@lefanion.cm']);
        echo "✅ Utilisateur 'directeur' créé<br>";
        
        // Étape 4 : Créer la table permissions
        $pdo->exec("DROP TABLE IF EXISTS permissions");
        $pdo->exec("
            CREATE TABLE permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(50) NOT NULL,
                module VARCHAR(50) NOT NULL,
                can_view BOOLEAN DEFAULT FALSE,
                can_create BOOLEAN DEFAULT FALSE,
                can_edit BOOLEAN DEFAULT FALSE,
                can_delete BOOLEAN DEFAULT FALSE,
                can_print BOOLEAN DEFAULT FALSE,
                date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_role_module (role, module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ Table 'permissions' créée<br>";
        
        // Étape 5 : Insérer les permissions
        $permissions = [
            ['principale', 'dashboard', 1, 0, 0, 0, 1], ['principale', 'eleves', 1, 1, 1, 1, 1],
            ['principale', 'classes', 1, 1, 1, 1, 1], ['principale', 'matieres', 1, 1, 1, 1, 1],
            ['principale', 'notes', 1, 1, 1, 1, 1], ['principale', 'bordereaux', 1, 0, 0, 0, 1],
            ['principale', 'bulletins', 1, 0, 0, 0, 1], ['principale', 'paiements', 1, 1, 1, 1, 1],
            ['principale', 'utilisateurs', 1, 1, 1, 1, 0], ['principale', 'parametres', 1, 1, 1, 0, 0],
            
            ['directeur_etudes', 'dashboard', 1, 0, 0, 0, 1], ['directeur_etudes', 'eleves', 1, 1, 1, 1, 1],
            ['directeur_etudes', 'classes', 1, 1, 1, 1, 1], ['directeur_etudes', 'matieres', 1, 1, 1, 1, 1],
            ['directeur_etudes', 'notes', 1, 1, 1, 1, 1], ['directeur_etudes', 'bordereaux', 1, 0, 0, 0, 1],
            ['directeur_etudes', 'bulletins', 1, 0, 0, 0, 1], ['directeur_etudes', 'paiements', 0, 0, 0, 0, 0],
            ['directeur_etudes', 'utilisateurs', 0, 0, 0, 0, 0], ['directeur_etudes', 'parametres', 0, 0, 0, 0, 0],
            
            ['administrateur', 'dashboard', 1, 0, 0, 0, 1], ['administrateur', 'eleves', 1, 1, 1, 1, 1],
            ['administrateur', 'classes', 1, 1, 1, 1, 1], ['administrateur', 'matieres', 1, 1, 1, 1, 1],
            ['administrateur', 'notes', 1, 1, 1, 1, 1], ['administrateur', 'bordereaux', 1, 0, 0, 0, 1],
            ['administrateur', 'bulletins', 1, 0, 0, 0, 1], ['administrateur', 'paiements', 1, 1, 1, 1, 1],
            ['administrateur', 'utilisateurs', 1, 1, 1, 1, 0], ['administrateur', 'parametres', 1, 1, 1, 1, 0],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO permissions (role, module, can_view, can_create, can_edit, can_delete, can_print) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($permissions as $perm) { $stmt->execute($perm); }
        echo "✅ " . count($permissions) . " permissions insérées<br>";
        
        echo "</div>";
        
        echo "<div style='background: #dbeafe; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
        echo "<h3>🎉 Installation réussie !</h3>";
        echo "<h4>Comptes créés :</h4>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f3f4f6;'><th>Utilisateur</th><th>Mot de passe</th><th>Rôle</th><th>Accès Paiements</th></tr>";
        echo "<tr><td><strong>principale</strong></td><td><code>admin123</code></td><td>Principale</td><td style='color: green;'>✅ OUI</td></tr>";
        echo "<tr><td><strong>directeur</strong></td><td><code>admin123</code></td><td>Directeur des Études</td><td style='color: red;'>❌ NON</td></tr>";
        echo "<tr><td><strong>admin</strong></td><td><code>admin123</code></td><td>Administrateur</td><td style='color: green;'>✅ OUI</td></tr>";
        echo "</table>";
        echo "<br><a href='login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔐 Tester la connexion</a>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div style='background: #fef2f2; padding: 15px; border-left: 4px solid #ef4444; color: #ef4444;'>";
        echo "❌ Erreur : " . $e->getMessage();
        echo "</div>";
    }
    
    echo "<hr>";
}

// Test 1 : Connexion à la base de données
echo "<h3>1. Test de connexion à la base de données</h3>";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=fanion_notes;charset=utf8mb4", "root", "");
    echo "✅ <strong style='color:green'>Connexion MySQL réussie</strong><br>";
    
    // Vérifier si la table utilisateurs existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'utilisateurs'");
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong style='color:green'>Table 'utilisateurs' existe</strong><br>";
        
        // Vérifier si l'utilisateur admin existe
        $stmt = $pdo->query("SELECT * FROM utilisateurs WHERE nom_utilisateur = 'admin'");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "✅ <strong style='color:green'>Utilisateur 'admin' existe</strong><br>";
            echo "   - ID: " . $admin['id'] . "<br>";
            echo "   - Nom: " . $admin['nom_complet'] . "<br>";
            echo "   - Role: " . $admin['role'] . "<br>";
            echo "   - Actif: " . ($admin['actif'] ? 'Oui' : 'Non') . "<br>";
            
            // Tester le mot de passe
            if (password_verify('admin123', $admin['mot_de_passe'])) {
                echo "✅ <strong style='color:green'>Mot de passe 'admin123' correct</strong><br>";
            } else {
                echo "❌ <strong style='color:red'>Mot de passe incorrect - Recréation nécessaire</strong><br>";
                
                // Recréer l'utilisateur admin avec le bon mot de passe
                $newPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE nom_utilisateur = 'admin'");
                $stmt->execute([$newPassword]);
                echo "✅ <strong style='color:green'>Mot de passe réinitialisé</strong><br>";
            }
        } else {
            echo "❌ <strong style='color:red'>Utilisateur 'admin' n'existe pas</strong><br>";
            
            // Créer l'utilisateur admin
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role) VALUES ('admin', ?, 'Administrateur Principal', 'administrateur')");
            $stmt->execute([$password]);
            echo "✅ <strong style='color:green'>Utilisateur 'admin' créé</strong><br>";
        }
    } else {
        echo "❌ <strong style='color:red'>Table 'utilisateurs' n'existe pas - Importez database.sql</strong><br>";
    }
    
} catch (PDOException $e) {
    echo "❌ <strong style='color:red'>Erreur: " . $e->getMessage() . "</strong><br>";
}

echo "<hr>";

// Test 2 : Sessions PHP
echo "<h3>2. Test des sessions PHP</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✅ <strong style='color:green'>Session démarrée</strong><br>";
echo "   - Session ID: " . session_id() . "<br>";
echo "   - Session save path: " . session_save_path() . "<br>";

// Tester l'écriture en session
$_SESSION['test'] = 'valeur_test';
if (isset($_SESSION['test']) && $_SESSION['test'] === 'valeur_test') {
    echo "✅ <strong style='color:green'>Écriture/Lecture en session fonctionne</strong><br>";
} else {
    echo "❌ <strong style='color:red'>Problème avec les sessions</strong><br>";
}

echo "<hr>";

// Test 3 : Fichiers requis
echo "<h3>3. Test des fichiers requis</h3>";
$files = ['config.php', 'auth.php', 'login.php', 'dashboard.php', 'index.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ <strong style='color:green'>$file existe</strong><br>";
    } else {
        echo "❌ <strong style='color:red'>$file manquant</strong><br>";
    }
}

echo "<hr>";

// Test 4 : Dossiers requis
echo "<h3>4. Test des dossiers requis</h3>";
$dirs = ['includes', 'uploads', 'uploads/bulletins', 'uploads/bordereaux'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ <strong style='color:green'>$dir existe</strong>";
        if (is_writable($dir)) {
            echo " (✓ writable)";
        } else {
            echo " (⚠️ non writable)";
        }
        echo "<br>";
    } else {
        echo "❌ <strong style='color:red'>$dir manquant</strong><br>";
        if (mkdir($dir, 0755, true)) {
            echo "   ✅ Dossier créé<br>";
        }
    }
}

echo "<hr>";

// Test 5 : Test de connexion simulé
echo "<h3>5. Test de connexion simulé</h3>";

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

$result = Auth::login('admin', 'admin123');

echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "✅ <strong style='color:green'>Connexion simulée réussie</strong><br>";
    echo "   - User ID en session: " . ($_SESSION['user_id'] ?? 'NON DÉFINI') . "<br>";
    echo "   - Username en session: " . ($_SESSION['username'] ?? 'NON DÉFINI') . "<br>";
    
    if (isLoggedIn()) {
        echo "✅ <strong style='color:green'>isLoggedIn() retourne TRUE</strong><br>";
    } else {
        echo "❌ <strong style='color:red'>isLoggedIn() retourne FALSE</strong><br>";
    }
} else {
    echo "❌ <strong style='color:red'>Échec de connexion: " . $result['message'] . "</strong><br>";
}

echo "<hr>";

// Bouton pour installer les rôles
if (!isset($_GET['install_roles'])) {
    echo "<div style='background: #fef3c7; padding: 20px; border-radius: 10px; border: 2px solid #f59e0b; margin: 20px 0;'>";
    echo "<h3>🔧 Installer les nouveaux rôles et permissions</h3>";
    echo "<p>Cliquez sur le bouton ci-dessous pour créer les comptes <strong>Principale</strong> et <strong>Directeur des Études</strong> :</p>";
    echo "<a href='?install_roles=1' style='background: #f59e0b; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>";
    echo "⚡ INSTALLER LES RÔLES MAINTENANT";
    echo "</a>";
    echo "</div>";
}

echo "<hr>";

// Test 6 : Test de redirection
echo "<h3>6. Test de redirection</h3>";
echo "<a href='login.php' class='btn'>Retour au login</a> ";
echo "<a href='dashboard.php' class='btn'>Aller au dashboard</a>";

echo "<hr>";
echo "<h3>✅ Diagnostic terminé</h3>";
echo "<p><strong>Actions recommandées:</strong></p>";
echo "<ol>";
echo "<li>Si tous les tests sont verts, essayez de vous reconnecter</li>";
echo "<li>Si la table utilisateurs n'existe pas, importez database.sql dans phpMyAdmin</li>";
echo "<li>Si les sessions ne fonctionnent pas, vérifiez que le dossier session est writable</li>";
echo "</ol>";

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1 { color: #333; }
h3 { color: #666; margin-top: 20px; }
.btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
pre { background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
hr { margin: 30px 0; border: 1px solid #ddd; }
</style>";
?>