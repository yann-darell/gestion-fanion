<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('administrateur');

$user = getCurrentUser();
$db = getDB();

// Vérifier l'action
$action = $_POST['action'] ?? null;

if (!$action) {
    $_SESSION['error'] = "Action non spécifiée.";
    header('Location: utilisateurs.php');
    exit;
}

// Action : Créer un utilisateur
if ($action === 'create_user') {
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role = $_POST['role'] ?? '';
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');

    // Validation
    if (empty($nom_complet) || empty($nom_utilisateur) || empty($role) || empty($mot_de_passe)) {
        $_SESSION['error'] = "Veuillez remplir tous les champs obligatoires.";
        header('Location: utilisateurs.php');
        exit;
    }

    // Vérifier si le nom d'utilisateur existe déjà
    $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE nom_utilisateur = ?");
    $stmt->execute([$nom_utilisateur]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Ce nom d'utilisateur est déjà utilisé.";
        header('Location: utilisateurs.php');
        exit;
    }

    // Vérifier si l'email existe déjà (si fourni)
    if (!empty($email)) {
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Cet email est déjà utilisé.";
            header('Location: utilisateurs.php');
            exit;
        }
    }

    // Hasher le mot de passe
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    try {
        // Insérer l'utilisateur
        $stmt = $db->prepare("
            INSERT INTO utilisateurs (nom_complet, nom_utilisateur, email, telephone, role, mot_de_passe, actif, date_creation) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $nom_complet,
            $nom_utilisateur,
            $email,
            $telephone,
            $role,
            $mot_de_passe_hash
        ]);

        $_SESSION['success'] = "L'utilisateur <strong>" . htmlspecialchars($nom_complet) . "</strong> a été créé avec succès.";
        header('Location: utilisateurs.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la création : " . $e->getMessage();
        header('Location: utilisateurs.php');
        exit;
    }
}

// Action non reconnue
$_SESSION['error'] = "Action non reconnue.";
header('Location: utilisateurs.php');
exit;
?>