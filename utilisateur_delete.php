<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('administrateur');

$user = getCurrentUser();
$db = getDB();

// Récupérer l'ID
$userId = $_GET['id'] ?? null;
$redirect = $_GET['redirect'] ?? 'utilisateurs.php';

if (!$userId) {
    $_SESSION['error'] = "ID utilisateur manquant.";
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

// Empêcher l'utilisateur de se supprimer lui-même
if ($userData['id'] == $user['id']) {
    $_SESSION['error'] = "Vous ne pouvez pas supprimer votre propre compte.";
    header('Location: ' . $redirect);
    exit;
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Supprimer l'utilisateur
        $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$userId]);
        
        $_SESSION['success'] = "L'utilisateur <strong>" . htmlspecialchars($userData['nom_complet']) . "</strong> a été supprimé avec succès.";
        header('Location: utilisateurs.php');
        exit;
        
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer utilisateur - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --danger-color: #ef4444;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
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

        .alert-danger {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            font-weight: 500;
        }

        .btn-danger:hover {
            opacity: .9;
        }

        .danger-icon {
            font-size: 5rem;
            color: var(--danger-color);
        }

        .user-info {
            background: #fef2f2;
            border-left: 4px solid var(--danger-color);
            padding: 1rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<?php include "includes/header.php"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include "includes/sidebar.php"; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- En-tête de page -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                    Supprimer l'utilisateur
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo $redirect; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Retour
                    </a>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Erreur !</strong> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-exclamation-triangle-fill danger-icon mb-4"></i>
                            
                            <h2 class="text-danger mb-4">Attention : Action irréversible !</h2>
                            
                            <p class="lead mb-4">
                                Vous êtes sur le point de supprimer définitivement cet utilisateur.
                            </p>

                            <div class="user-info mb-4 text-start">
                                <h5 class="text-danger mb-3">
                                    <i class="bi bi-person-fill me-2"></i>
                                    Utilisateur à supprimer :
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <strong>Nom complet :</strong><br>
                                        <?php echo htmlspecialchars($userData['nom_complet']); ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>Nom d'utilisateur :</strong><br>
                                        <?php echo htmlspecialchars($userData['nom_utilisateur']); ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>Email :</strong><br>
                                        <?php echo htmlspecialchars($userData['email'] ?: 'Non renseigné'); ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>Rôle :</strong><br>
                                        <span class="badge bg-primary"><?php echo ucfirst($userData['role']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-danger mb-4">
                                <i class="bi bi-shield-exclamation me-2"></i>
                                <strong>Avertissement :</strong> Cette action supprimera définitivement cet utilisateur et toutes les données associées. Cette opération ne peut pas être annulée.
                            </div>

                            <form method="POST" class="d-inline">
                                <input type="hidden" name="confirm_delete" value="1">
                                
                                <div class="d-flex gap-3 justify-content-center">
                                    <a href="<?php echo $redirect; ?>" class="btn btn-secondary btn-lg">
                                        <i class="bi bi-x-circle me-2"></i>
                                        Non, annuler
                                    </a>
                                    
                                    <button type="submit" class="btn btn-danger btn-lg">
                                        <i class="bi bi-trash-fill me-2"></i>
                                        Oui, supprimer définitivement
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Informations supplémentaires -->
                    <div class="card mt-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Que se passe-t-il lors de la suppression ?
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li class="mb-2">L'utilisateur sera définitivement supprimé de la base de données</li>
                                <li class="mb-2">Il ne pourra plus se connecter au système</li>
                                <li class="mb-2">Toutes ses données personnelles seront effacées</li>
                                <li class="mb-2">Les actions effectuées par cet utilisateur resteront dans l'historique</li>
                                <li>Cette action ne peut pas être annulée</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>