<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('administrateur'); // Seuls les admins peuvent accéder aux paramètres

$user = getCurrentUser();
$db = getDB();

// Récupérer les paramètres actuels
$params = [];
$stmt = $db->query("SELECT * FROM parametres");
while ($row = $stmt->fetch()) {
    $params[$row['cle_param']] = $row['valeur_param'];
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $error = '';

    try {
        // Paramètres de l'établissement
        if (isset($_POST['nom_etablissement'])) {
            updateParam('nom_etablissement', $_POST['nom_etablissement']);
        }
        if (isset($_POST['adresse_etablissement'])) {
            updateParam('adresse_etablissement', $_POST['adresse_etablissement']);
        }
        if (isset($_POST['telephone_etablissement'])) {
            updateParam('telephone_etablissement', $_POST['telephone_etablissement']);
        }
        if (isset($_POST['email_etablissement'])) {
            updateParam('email_etablissement', $_POST['email_etablissement']);
        }
        if (isset($_POST['devise'])) {
            updateParam('devise', $_POST['devise']);
        }

        // Paramètres de l'année scolaire
        if (isset($_POST['annee_scolaire_actuelle'])) {
            updateParam('annee_scolaire_actuelle', $_POST['annee_scolaire_actuelle']);
        }

        // Paramètres des notes
        if (isset($_POST['note_maximale'])) {
            updateParam('note_maximale', $_POST['note_maximale']);
        }
        if (isset($_POST['moyenne_passage'])) {
            updateParam('note_passage', $_POST['moyenne_passage']);
        }
        
        // Paramètres des mentions
        if (isset($_POST['mention_excellent'])) {
            updateParam('mention_excellent', $_POST['mention_excellent']);
        }
        if (isset($_POST['mention_tres_bien'])) {
            updateParam('mention_tres_bien', $_POST['mention_tres_bien']);
        }
        if (isset($_POST['mention_bien'])) {
            updateParam('mention_bien', $_POST['mention_bien']);
        }
        if (isset($_POST['mention_assez_bien'])) {
            updateParam('mention_assez_bien', $_POST['mention_assez_bien']);
        }

        // Paramètres des frais
        if (isset($_POST['frais_inscription'])) {
            updateParam('frais_inscription', $_POST['frais_inscription']);
        }
        if (isset($_POST['frais_scolarite_mensuel'])) {
            updateParam('frais_scolarite_mensuel', $_POST['frais_scolarite_mensuel']);
        }

        $success = true;
        $_SESSION['success'] = "Les paramètres ont été enregistrés avec succès.";
        
        // Recharger les paramètres
        $params = [];
        $stmt = $db->query("SELECT * FROM parametres");
        while ($row = $stmt->fetch()) {
            $params[$row['cle_param']] = $row['valeur_param'];
        }

    } catch (Exception $e) {
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// Fonction helper pour mettre à jour un paramètre
function updateParam($cle, $valeur) {
    global $db;
    $stmt = $db->prepare("
        INSERT INTO parametres (cle_param, valeur_param, date_modification) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE valeur_param = ?, date_modification = NOW()
    ");
    $stmt->execute([$cle, $valeur, $valeur]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - <?php echo APP_NAME; ?></title>
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
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.25rem 1.5rem;
            border: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            font-weight: 500;
            padding: 0.75rem 2rem;
            border-radius: 10px;
        }

        .btn-primary:hover {
            opacity: .9;
            transform: translateY(-2px);
        }

        .alert {
            border: none;
            border-radius: 10px;
        }

        .settings-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .icon-primary {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: var(--primary-color);
        }

        .icon-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: var(--success-color);
        }

        .icon-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: var(--warning-color);
        }

        .icon-info {
            background: linear-gradient(135deg, #cffafe, #a5f3fc);
            color: var(--info-color);
        }

        .setting-section {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .input-group-text {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 10px 0 0 10px;
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            color: #6b7280;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .nav-pills .nav-link:hover:not(.active) {
            background: #f3f4f6;
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
                        <i class="bi bi-gear-fill me-2"></i>
                        Paramètres du système
                    </h1>
                </div>

                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Succès !</strong> Les paramètres ont été enregistrés avec succès.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error) && $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Erreur !</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">
                        <!-- Colonne principale -->
                        <div class="col-lg-8">
                            
                            <!-- Informations de l'établissement -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>
                                        <i class="bi bi-building me-2"></i>
                                        Informations de l'établissement
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="setting-section">
                                        <div class="settings-icon icon-primary">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-label">Nom de l'établissement</label>
                                            <input type="text" 
                                                   name="nom_etablissement" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars($params['nom_etablissement'] ?? ''); ?>"
                                                   placeholder="Ex: Collège/Lycée...">
                                        </div>
                                    </div>

                                    <div class="setting-section">
                                        <div class="settings-icon icon-info">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-label">Adresse</label>
                                            <input type="text" 
                                                   name="adresse_etablissement" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars($params['adresse_etablissement'] ?? ''); ?>"
                                                   placeholder="Adresse complète">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-success">
                                                    <i class="bi bi-telephone-fill"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Téléphone</label>
                                                    <input type="text" 
                                                           name="telephone_etablissement" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['telephone_etablissement'] ?? ''); ?>"
                                                           placeholder="+237...">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-warning">
                                                    <i class="bi bi-envelope-fill"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" 
                                                           name="email_etablissement" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['email_etablissement'] ?? ''); ?>"
                                                           placeholder="contact@etablissement.cm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Année scolaire -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>
                                        <i class="bi bi-calendar-event me-2"></i>
                                        Année scolaire
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="setting-section">
                                        <div class="settings-icon icon-warning">
                                            <i class="bi bi-calendar3"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-label">Année scolaire actuelle</label>
                                            <input type="text" 
                                                   name="annee_scolaire_actuelle" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars($params['annee_scolaire_actuelle'] ?? date('Y').'-'.(date('Y')+1)); ?>"
                                                   placeholder="Ex: 2024-2025">
                                            <small class="text-muted">Format: AAAA-AAAA</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paramètres des notes -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>
                                        <i class="bi bi-journal-text me-2"></i>
                                        Paramètres des notes et mentions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-success">
                                                    <i class="bi bi-star-fill"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Note maximale</label>
                                                    <input type="number" 
                                                           name="note_maximale" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['note_maximale'] ?? '20'); ?>"
                                                           min="1" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-info">
                                                    <i class="bi bi-trophy-fill"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Note de passage</label>
                                                    <input type="number" 
                                                           name="moyenne_passage" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['note_passage'] ?? '10'); ?>"
                                                           min="0" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">
                                    <h6 class="mb-3">
                                        <i class="bi bi-award-fill me-2"></i>
                                        Seuils des mentions
                                    </h6>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #f59e0b;">
                                                    <i class="bi bi-award"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Mention Excellent (≥)</label>
                                                    <input type="number" 
                                                           name="mention_excellent" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['mention_excellent'] ?? '16'); ?>"
                                                           min="0" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #10b981;">
                                                    <i class="bi bi-award"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Mention Très Bien (≥)</label>
                                                    <input type="number" 
                                                           name="mention_tres_bien" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['mention_tres_bien'] ?? '14'); ?>"
                                                           min="0" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-primary">
                                                    <i class="bi bi-award"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Mention Bien (≥)</label>
                                                    <input type="number" 
                                                           name="mention_bien" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['mention_bien'] ?? '12'); ?>"
                                                           min="0" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-info">
                                                    <i class="bi bi-award"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Mention Assez Bien (≥)</label>
                                                    <input type="number" 
                                                           name="mention_assez_bien" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($params['mention_assez_bien'] ?? '10'); ?>"
                                                           min="0" 
                                                           step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paramètres financiers -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>
                                        <i class="bi bi-cash-stack me-2"></i>
                                        Paramètres financiers
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="setting-section mb-3">
                                        <div class="settings-icon icon-warning">
                                            <i class="bi bi-currency-exchange"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-label">Devise</label>
                                            <input type="text" 
                                                   name="devise" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars($params['devise'] ?? 'FCFA'); ?>"
                                                   placeholder="FCFA, EUR, USD...">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-primary">
                                                    <i class="bi bi-coin"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Frais d'inscription</label>
                                                    <div class="input-group">
                                                        <input type="number" 
                                                               name="frais_inscription" 
                                                               class="form-control" 
                                                               value="<?php echo htmlspecialchars($params['frais_inscription'] ?? '50000'); ?>"
                                                               min="0">
                                                        <span class="input-group-text"><?php echo $params['devise'] ?? 'FCFA'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="setting-section">
                                                <div class="settings-icon icon-success">
                                                    <i class="bi bi-calendar-check"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label">Scolarité mensuelle</label>
                                                    <div class="input-group">
                                                        <input type="number" 
                                                               name="frais_scolarite_mensuel" 
                                                               class="form-control" 
                                                               value="<?php echo htmlspecialchars($params['frais_scolarite_mensuel'] ?? '25000'); ?>"
                                                               min="0">
                                                        <span class="input-group-text"><?php echo $params['devise'] ?? 'FCFA'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bouton de sauvegarde -->
                            <div class="text-end mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Enregistrer les paramètres
                                </button>
                            </div>

                        </div>

                        <!-- Colonne latérale -->
                        <div class="col-lg-4">
                            <!-- Informations -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        Informations
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-lightbulb-fill me-2"></i>
                                        <strong>Conseil :</strong> Ces paramètres affectent tout le système. Modifiez-les avec précaution.
                                    </div>
                                    
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <strong>Attention :</strong> Certaines modifications peuvent nécessiter un rechargement de la page.
                                    </div>
                                </div>
                            </div>

                            <!-- Actions système -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>
                                        <i class="bi bi-tools me-2"></i>
                                        Actions système
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary">
                                            <i class="bi bi-arrow-clockwise me-2"></i>
                                            Réinitialiser le cache
                                        </button>
                                        <button type="button" class="btn btn-outline-success">
                                            <i class="bi bi-download me-2"></i>
                                            Sauvegarder la base
                                        </button>
                                        <button type="button" class="btn btn-outline-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Voir les logs système
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Version -->
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-server" style="font-size: 2rem; color: var(--primary-color);"></i>
                                    <h6 class="mt-2 mb-1">Système de gestion</h6>
                                    <p class="text-muted mb-0">Version 1.0.0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>