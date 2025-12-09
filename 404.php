<?php
// PAGE 404 - NON TROUVÉE
http_response_code(404);
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';
$user = getCurrentUser();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Page non trouvée - <?php echo APP_NAME; ?></title>
    <?php include 'includes/styles.php'; ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5 text-center">
        <h1 class="display-1">404</h1>
        <p class="lead">Oups ! La page demandée est introuvable.</p>
        <a href="dashboard.php" class="btn btn-primary">Retour au tableau de bord</a>
    </div>
</body>
</html>
