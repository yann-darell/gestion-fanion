<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('administrateur');

$user = getCurrentUser();
$db = getDB();

// Récupérer les paramètres
$userId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;
$redirect = $_GET['redirect'] ?? 'utilisateurs.php';

// Vérifier les paramètres
if (!$userId || !in_array($action, ['enable', 'disable'])) {
    $_SESSION['error'] = "Paramètres invalides.";
    header('Location: ' . $redirect);
    exit;
}

// Récupérer l'utilisateur
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    $_SESSION['error'] = "Utilisateur introuvable.";
    header('Location: ' . $redirect);
    exit;
}

// Empêcher l'utilisateur de se désactiver lui-même
if ($userData['id'] == $user['id']) {
    $_SESSION['error'] = "Vous ne pouvez pas désactiver votre propre compte.";
    header('Location: ' . $redirect);
    exit;
}

try {
    // Effectuer l'action
    if ($action === 'disable') {
        // Désactiver l'utilisateur
        $stmt = $db->prepare("UPDATE utilisateurs SET actif = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        $_SESSION['success'] = "L'utilisateur <strong>" . htmlspecialchars($userData['nom_complet']) . "</strong> a été désactivé avec succès.";
    } else {
        // Activer l'utilisateur
        $stmt = $db->prepare("UPDATE utilisateurs SET actif = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        $_SESSION['success'] = "L'utilisateur <strong>" . htmlspecialchars($userData['nom_complet']) . "</strong> a été activé avec succès.";
    }
    
    // Redirection
    header('Location: ' . $redirect);
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
    header('Location: ' . $redirect);
    exit;
}
?>