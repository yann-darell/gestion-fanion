<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';
$user = getCurrentUser();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contact - <?php echo APP_NAME; ?></title>
    <?php include 'includes/styles.php'; ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5">
        <h1>Contact</h1>
        <p>Pour toute question, contactez l'administration du Collège Le Fanion à <a href="mailto:admin@lefanion.cm">admin@lefanion.cm</a>.</p>
    </div>
</body>
</html>
