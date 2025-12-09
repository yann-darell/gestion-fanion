<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';
Auth::requireLogin();
$user = getCurrentUser();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres - <?php echo APP_NAME; ?></title>
    <?php include 'includes/styles.php'; ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1>Paramètres</h1>
                <p>Page de gestion des paramètres de l'application (à personnaliser selon vos besoins).</p>
            </main>
        </div>
    </div>
</body>
</html>
