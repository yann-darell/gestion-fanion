<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>À propos - <?php echo APP_NAME; ?></title>
    <?php include 'includes/styles.php'; ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h3 mb-3">À propos</h1>
                <div class="card">
                    <div class="card-body">
                        <p>Application de gestion scolaire pour le <?php echo htmlspecialchars(getParam('nom_etablissement', 'Collège Le Fanion')); ?>.<br>Développée pour faciliter la gestion des élèves, notes, paiements et bulletins.</p>
                        <p class="text-muted small">Version: <?php echo defined('APP_VERSION') ? APP_VERSION : '1.0'; ?></p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
