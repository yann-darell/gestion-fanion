<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('administrateur');

$user = getCurrentUser();
$db = getDB();

// Récupérer l'ID de l'utilisateur
$userId = $_GET['id'] ?? null;

if (!$userId) {
    header('Location: utilisateurs.php');
    exit;
}

// Récupérer les informations de l'utilisateur
$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    header('Location: utilisateurs.php');
    exit;
}

// Icône selon le rôle
$roleIcons = [
    'administrateur' => 'shield-fill-check',
    'secretaire' => 'person-badge-fill',
    'censeur' => 'clipboard-check-fill',
    'enseignant' => 'mortarboard-fill'
];

$roleColors = [
    'administrateur' => 'danger',
    'secretaire' => 'primary',
    'censeur' => 'warning',
    'enseignant' => 'success'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails utilisateur - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
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
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            border: 5px solid rgba(255, 255, 255, 0.3);
        }

        .info-row {
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1.1rem;
            color: #1f2937;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            font-weight: 500;
        }

        .btn-primary:hover {
            opacity: .9;
        }

        .badge-large {
            padding: 0.5rem 1rem;
            font-size: 1rem;
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
                    <i class="bi bi-person-fill me-2"></i>
                    Détails de l'utilisateur
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="utilisateur_edit.php?id=<?php echo $userData['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil-square me-1"></i> Modifier
                        </a>
                        <a href="utilisateurs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Retour
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Colonne principale -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <h3 class="mb-1"><?php echo htmlspecialchars($userData['nom_complet']); ?></h3>
                            <p class="mb-2 opacity-75">@<?php echo htmlspecialchars($userData['nom_utilisateur']); ?></p>
                            <span class="badge badge-large bg-<?php echo $roleColors[$userData['role']] ?? 'secondary'; ?>">
                                <i class="bi bi-<?php echo $roleIcons[$userData['role']] ?? 'person'; ?> me-1"></i>
                                <?php echo ucfirst($userData['role']); ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title mb-4">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Informations générales
                            </h5>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-person me-1"></i>
                                    Nom complet
                                </div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($userData['nom_complet']); ?>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-at me-1"></i>
                                    Nom d'utilisateur
                                </div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($userData['nom_utilisateur']); ?>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-envelope me-1"></i>
                                    Email
                                </div>
                                <div class="info-value">
                                    <?php echo $userData['email'] ? htmlspecialchars($userData['email']) : '<span class="text-muted">Non renseigné</span>'; ?>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-telephone me-1"></i>
                                    Téléphone
                                </div>
                                <div class="info-value">
                                    <?php echo $userData['telephone'] ? htmlspecialchars($userData['telephone']) : '<span class="text-muted">Non renseigné</span>'; ?>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Rôle
                                </div>
                                <div class="info-value">
                                    <span class="badge bg-<?php echo $roleColors[$userData['role']] ?? 'secondary'; ?>">
                                        <i class="bi bi-<?php echo $roleIcons[$userData['role']] ?? 'person'; ?> me-1"></i>
                                        <?php echo ucfirst($userData['role']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-toggle-on me-1"></i>
                                    Statut
                                </div>
                                <div class="info-value">
                                    <?php if ($userData['actif']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Actif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle me-1"></i>Désactivé
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Date de création
                                </div>
                                <div class="info-value">
                                    <?php 
                                    if ($userData['date_creation']) {
                                        echo date('d/m/Y à H:i', strtotime($userData['date_creation']));
                                    } else {
                                        echo '<span class="text-muted">Non disponible</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne latérale -->
                <div class="col-lg-4">
                    <!-- Actions rapides -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-lightning-fill me-2"></i>
                                Actions rapides
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="utilisateur_edit.php?id=<?php echo $userData['id']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-pencil-square me-2"></i>
                                    Modifier l'utilisateur
                                </a>

                                <?php if ($userData['actif']): ?>
                                    <a href="utilisateur_toggle.php?action=disable&id=<?php echo $userData['id']; ?>"
                                       class="btn btn-outline-warning">
                                        <i class="bi bi-x-circle me-2"></i>
                                        Désactiver le compte
                                    </a>
                                <?php else: ?>
                                    <a href="utilisateur_toggle.php?action=enable&id=<?php echo $userData['id']; ?>"
                                       class="btn btn-outline-success">
                                        <i class="bi bi-check-circle me-2"></i>
                                        Activer le compte
                                    </a>
                                <?php endif; ?>

                                <a href="utilisateur_delete.php?id=<?php echo $userData['id']; ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.');">
                                    <i class="bi bi-trash me-2"></i>
                                    Supprimer l'utilisateur
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Informations système -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-gear-fill me-2"></i>
                                Informations système
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">ID Utilisateur</small>
                                <strong>#<?php echo $userData['id']; ?></strong>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block">Dernière connexion</small>
                                <strong>
                                    <?php 
                                    if (isset($userData['derniere_connexion']) && $userData['derniere_connexion']) {
                                        echo date('d/m/Y à H:i', strtotime($userData['derniere_connexion']));
                                    } else {
                                        echo '<span class="text-muted">Jamais connecté</span>';
                                    }
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <small class="text-muted d-block">Créé le</small>
                                <strong>
                                    <?php 
                                    if ($userData['date_creation']) {
                                        echo date('d/m/Y', strtotime($userData['date_creation']));
                                    } else {
                                        echo '<span class="text-muted">N/A</span>';
                                    }
                                    ?>
                                </strong>
                            </div>
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