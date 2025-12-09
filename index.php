<?php
/**
 * =============================================
 * POINT D'ENTRÉE - COLLÈGE LE FANION
 * =============================================
 */

define('APP_INIT', true);
require_once 'config.php';
session_start();

// Si l'utilisateur est connecté, rediriger vers le dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    // Sinon, rediriger vers la page de connexion
    redirect('login.php');
}
?>