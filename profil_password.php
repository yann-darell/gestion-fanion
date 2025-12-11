<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
$currentUser = getCurrentUser();
$db = getDB();

$success = false;
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ancien_mot_de_passe = $_POST['ancien_mot_de_passe'] ?? '';
    $nouveau_mot_de_passe = $_POST['nouveau_mot_de_passe'] ?? '';
    $confirmer_mot_de_passe = $_POST['confirmer_mot_de_passe'] ?? '';

    // Validation
    if (empty($ancien_mot_de_passe) || empty($nouveau_mot_de_passe) || empty($confirmer_mot_de_passe)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif ($nouveau_mot_de_passe !== $confirmer_mot_de_passe) {
        $error = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($nouveau_mot_de_passe) < 6) {
        $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } else {
        // Vérifier l'ancien mot de passe
        $stmt = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $userData = $stmt->fetch();

        if (!password_verify($ancien_mot_de_passe, $userData['mot_de_passe'])) {
            $error = "L'ancien mot de passe est incorrect.";
        } else {
            // Mettre à jour le mot de passe
            $nouveau_hash = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$nouveau_hash, $currentUser['id']]);

                $_SESSION['success'] = "Votre mot de passe a été changé avec succès.";
                header('Location: profil.php');
                exit;

            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer mon mot de passe - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --warning-color: #f59e0b;
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

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
        }

        .form-control:focus {
            border-color: var(--warning-color);
            box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.1);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: white;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
        }

        .btn-warning:hover {
            opacity: .9;
            color: white;
        }

        .section-header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 0.5rem;
            transition: all 0.3s;
        }

        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }

        .security-tip {
            background: #fef3c7;
            border-left: 4px solid var(--warning-color);
            padding: 1rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-key-fill me-2"></i>
                        Changer mon mot de passe
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="profil.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Retour
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <form method="POST" class="card">
                            <div class="card-body">
                                <div class="section-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-shield-lock-fill me-2"></i>
                                        Sécurité du compte
                                    </h5>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">
                                            <i class="bi bi-lock me-1"></i>
                                            Ancien mot de passe *
                                        </label>
                                        <input type="password" 
                                               name="ancien_mot_de_passe" 
                                               class="form-control" 
                                               placeholder="Entrez votre mot de passe actuel"
                                               required>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">
                                            <i class="bi bi-key me-1"></i>
                                            Nouveau mot de passe *
                                        </label>
                                        <input type="password" 
                                               name="nouveau_mot_de_passe" 
                                               id="nouveau_mot_de_passe"
                                               class="form-control" 
                                               placeholder="Au moins 6 caractères"
                                               required
                                               minlength="6">
                                        <div id="password-strength" class="password-strength"></div>
                                        <small class="text-muted">Minimum 6 caractères recommandés</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Confirmer le nouveau mot de passe *
                                        </label>
                                        <input type="password" 
                                               name="confirmer_mot_de_passe" 
                                               id="confirmer_mot_de_passe"
                                               class="form-control" 
                                               placeholder="Retapez le nouveau mot de passe"
                                               required>
                                        <small id="match-message" class="text-muted"></small>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-white border-top">
                                <div class="d-flex justify-content-between">
                                    <a href="profil.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i> Annuler
                                    </a>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-shield-check me-1"></i> Changer le mot de passe
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-shield-check me-2 text-warning"></i>
                                    Conseils de sécurité
                                </h5>
                                <ul class="mb-0">
                                    <li class="mb-2">Utilisez au moins 8 caractères</li>
                                    <li class="mb-2">Combinez majuscules et minuscules</li>
                                    <li class="mb-2">Ajoutez des chiffres et symboles</li>
                                    <li class="mb-2">Ne réutilisez pas d'anciens mots de passe</li>
                                    <li>Évitez les informations personnelles</li>
                                </ul>
                            </div>
                        </div>

                        <div class="security-tip">
                            <h6 class="text-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Important
                            </h6>
                            <p class="mb-0 small">
                                Pour votre sécurité, vous serez déconnecté après le changement de mot de passe et devrez vous reconnecter avec le nouveau.
                            </p>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Indicateur de force du mot de passe
        const passwordInput = document.getElementById('nouveau_mot_de_passe');
        const strengthBar = document.getElementById('password-strength');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });

        // Vérification de correspondance des mots de passe
        const confirmInput = document.getElementById('confirmer_mot_de_passe');
        const matchMessage = document.getElementById('match-message');
        
        confirmInput.addEventListener('input', function() {
            if (this.value === passwordInput.value && this.value !== '') {
                matchMessage.textContent = '✓ Les mots de passe correspondent';
                matchMessage.className = 'text-success';
            } else if (this.value !== '') {
                matchMessage.textContent = '✗ Les mots de passe ne correspondent pas';
                matchMessage.className = 'text-danger';
            } else {
                matchMessage.textContent = '';
            }
        });
    </script>
</body>
</html>