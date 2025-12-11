<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
$user = getCurrentUser();
$db = getDB();

// Récupérer des statistiques utilisateur
$stats = [];

// Si enseignant : nombre de classes enseignées
if ($user['role'] === 'enseignant') {
    // À adapter selon votre structure de base de données
    $stmt = $db->prepare("SELECT COUNT(DISTINCT classe_id) as total FROM notes WHERE enseignant_id = ?");
    $stmt->execute([$user['id']]);
    $stats['classes'] = $stmt->fetch()['total'] ?? 0;
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
    <title>Mon Profil - <?php echo APP_NAME; ?></title>
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
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin: 0 auto 1.5rem;
            border: 6px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .profile-username {
            opacity: 0.9;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .badge-large {
            padding: 0.6rem 1.2rem;
            font-size: 1rem;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .info-card {
            padding: 1.5rem;
        }

        .info-item {
            padding: 1.25rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 1rem;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            color: var(--primary-color);
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1.1rem;
            color: #1f2937;
            font-weight: 600;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .stat-primary .stat-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-success .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-warning .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
        }

        .btn-primary:hover {
            opacity: .9;
            transform: translateY(-2px);
        }

        .action-card {
            cursor: pointer;
            transition: all 0.3s;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- En-tête de page -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-person-circle me-2"></i>
                        Mon Profil
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="profil_edit.php" class="btn btn-primary">
                            <i class="bi bi-pencil-square me-1"></i> Modifier mon profil
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Colonne principale -->
                    <div class="col-lg-8 mb-4">
                        <!-- Carte profil principal -->
                        <div class="card mb-4">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <h2 class="profile-name"><?php echo htmlspecialchars($user['nom_complet']); ?></h2>
                                <p class="profile-username">@<?php echo htmlspecialchars($user['nom_utilisateur']); ?></p>
                                <span class="badge badge-large bg-<?php echo $roleColors[$user['role']] ?? 'secondary'; ?>">
                                    <i class="bi bi-<?php echo $roleIcons[$user['role']] ?? 'person'; ?> me-1"></i>
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>

                            <div class="info-card">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-person-badge"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Nom complet</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['nom_complet']); ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-at"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Nom d'utilisateur</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['nom_utilisateur']); ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-envelope"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Email</div>
                                        <div class="info-value">
                                            <?php echo $user['email'] ? htmlspecialchars($user['email']) : '<span class="text-muted">Non renseigné</span>'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Téléphone</div>
                                        <div class="info-value">
                                            <?php echo $user['telephone'] ? htmlspecialchars($user['telephone']) : '<span class="text-muted">Non renseigné</span>'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Rôle dans le système</div>
                                        <div class="info-value">
                                            <span class="badge bg-<?php echo $roleColors[$user['role']] ?? 'secondary'; ?>">
                                                <i class="bi bi-<?php echo $roleIcons[$user['role']] ?? 'person'; ?> me-1"></i>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions rapides -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="profil_edit.php" class="text-decoration-none">
                                    <div class="card action-card stat-primary">
                                        <div class="card-body text-center">
                                            <i class="bi bi-pencil-square action-icon text-primary"></i>
                                            <h5>Modifier mon profil</h5>
                                            <p class="text-muted mb-0">Mettre à jour mes informations</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="profil_password.php" class="text-decoration-none">
                                    <div class="card action-card stat-warning">
                                        <div class="card-body text-center">
                                            <i class="bi bi-key-fill action-icon text-warning"></i>
                                            <h5>Changer mot de passe</h5>
                                            <p class="text-muted mb-0">Sécuriser mon compte</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne latérale -->
                    <div class="col-lg-4">
                        <!-- Informations de compte -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    Informations du compte
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Statut</small>
                                    <strong>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Actif
                                        </span>
                                    </strong>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Membre depuis</small>
                                    <strong>
                                        <?php 
                                        if (isset($user['date_creation']) && $user['date_creation']) {
                                            echo date('d/m/Y', strtotime($user['date_creation']));
                                        } else {
                                            echo 'Non disponible';
                                        }
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <small class="text-muted d-block">Dernière connexion</small>
                                    <strong>
                                        <?php 
                                        if (isset($user['derniere_connexion']) && $user['derniere_connexion']) {
                                            echo date('d/m/Y à H:i', strtotime($user['derniere_connexion']));
                                        } else {
                                            echo 'Première connexion';
                                        }
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Sécurité -->
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-shield-lock-fill me-2"></i>
                                    Sécurité
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="profil_password.php" class="btn btn-outline-warning">
                                        <i class="bi bi-key me-2"></i>
                                        Changer mon mot de passe
                                    </a>
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