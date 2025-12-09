<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';
Auth::requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil - <?php echo APP_NAME; ?></title>
    <?php include 'includes/styles.php'; ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h3 mb-3">Mon profil</h1>

                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p><strong>Nom d'utilisateur :</strong> <?php echo htmlspecialchars($user['nom_utilisateur'] ?? '-'); ?></p>
                                <p><strong>Nom complet :</strong> <?php echo htmlspecialchars($user['nom_complet'] ?? '-'); ?></p>
                                <p><strong>Email :</strong> <?php echo htmlspecialchars($user['email'] ?? '-'); ?></p>
                                <p><strong>Rôle :</strong> <?php echo htmlspecialchars($user['role'] ?? '-'); ?></p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="avatar-placeholder mb-3">
                                    <i class="bi bi-person-circle" style="font-size:64px;color:#6b7280"></i>
                                </div>
                                <a href="profil_edit.php" class="btn btn-outline-primary">Modifier mon profil</a>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>
