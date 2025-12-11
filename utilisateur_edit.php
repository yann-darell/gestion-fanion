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

// Traitement du formulaire
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role = $_POST['role'] ?? '';
    $actif = isset($_POST['actif']) ? 1 : 0;
    $nouveau_mot_de_passe = trim($_POST['nouveau_mot_de_passe'] ?? '');

    if (empty($nom_complet) || empty($nom_utilisateur) || empty($role)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        // Vérifier si le nom d'utilisateur n'est pas déjà pris par un autre utilisateur
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND id != ?");
        $stmt->execute([$nom_utilisateur, $userId]);
        
        if ($stmt->fetch()) {
            $error = "Ce nom d'utilisateur est déjà utilisé par un autre utilisateur.";
        } else {
            // Mise à jour de l'utilisateur
            if (!empty($nouveau_mot_de_passe)) {
                // Avec nouveau mot de passe
                $mot_de_passe_hash = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE utilisateurs 
                    SET nom_complet = ?, nom_utilisateur = ?, email = ?, telephone = ?, 
                        role = ?, actif = ?, mot_de_passe = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nom_complet, $nom_utilisateur, $email, $telephone, 
                    $role, $actif, $mot_de_passe_hash, $userId
                ]);
            } else {
                // Sans changer le mot de passe
                $stmt = $db->prepare("
                    UPDATE utilisateurs 
                    SET nom_complet = ?, nom_utilisateur = ?, email = ?, telephone = ?, 
                        role = ?, actif = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nom_complet, $nom_utilisateur, $email, $telephone, 
                    $role, $actif, $userId
                ]);
            }
            
            $success = true;
            
            // Recharger les données
            $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier utilisateur - <?php echo APP_NAME; ?></title>
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
            padding: 0.625rem 1.5rem;
            border-radius: 10px;
        }

        .btn-primary:hover {
            opacity: .9;
        }

        .alert {
            border: none;
            border-radius: 10px;
        }

        .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .required-field::after {
            content: " *";
            color: var(--danger-color);
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
                    <i class="bi bi-pencil-square me-2"></i>
                    Modifier l'utilisateur
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <a href="utilisateur_view.php?id=<?php echo $userData['id']; ?>" class="btn btn-outline-info">
                            <i class="bi bi-eye me-1"></i> Voir
                        </a>
                        <a href="utilisateurs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Retour
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Succès !</strong> L'utilisateur a été modifié avec succès.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Erreur !</strong> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <form method="POST" class="card">
                        <div class="card-body">
                            <!-- En-tête -->
                            <div class="section-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    Informations de l'utilisateur
                                </h5>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Nom complet</label>
                                    <input type="text" 
                                           name="nom_complet" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($userData['nom_complet']); ?>" 
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required-field">Nom d'utilisateur</label>
                                    <input type="text" 
                                           name="nom_utilisateur" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($userData['nom_utilisateur']); ?>" 
                                           required>
                                    <small class="text-muted">Utilisé pour la connexion</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>">
                                    <small class="text-muted">Facultatif</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" 
                                           name="telephone" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($userData['telephone'] ?? ''); ?>">
                                    <small class="text-muted">Facultatif</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required-field">Rôle</label>
                                    <select name="role" class="form-select" required>
                                        <option value="administrateur" <?php echo $userData['role'] === 'administrateur' ? 'selected' : ''; ?>>
                                            Administrateur
                                        </option>
                                        <option value="secretaire" <?php echo $userData['role'] === 'secretaire' ? 'selected' : ''; ?>>
                                            Secrétaire
                                        </option>
                                        <option value="censeur" <?php echo $userData['role'] === 'censeur' ? 'selected' : ''; ?>>
                                            Censeur
                                        </option>
                                        <option value="enseignant" <?php echo $userData['role'] === 'enseignant' ? 'selected' : ''; ?>>
                                            Enseignant
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Statut du compte</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="actif" 
                                               id="actif" 
                                               <?php echo $userData['actif'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="actif">
                                            Compte actif
                                        </label>
                                    </div>
                                    <small class="text-muted">Décochez pour désactiver le compte</small>
                                </div>
                            </div>

                            <!-- Section mot de passe -->
                            <div class="section-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-key-fill me-2"></i>
                                    Modifier le mot de passe (optionnel)
                                </h5>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Nouveau mot de passe</label>
                                    <input type="password" 
                                           name="nouveau_mot_de_passe" 
                                           class="form-control"
                                           placeholder="Laissez vide pour conserver l'actuel">
                                    <small class="text-muted">
                                        Laissez ce champ vide si vous ne souhaitez pas changer le mot de passe
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="utilisateurs.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Colonne latérale -->
                <div class="col-lg-4">
                    <!-- Informations -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Informations
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-lightbulb-fill me-2"></i>
                                <strong>Conseil :</strong> Les champs marqués d'un astérisque (*) sont obligatoires.
                            </div>
                        </div>
                    </div>

                    <!-- Informations système -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-gear-fill me-2"></i>
                                Détails du compte
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">ID Utilisateur</small>
                                <strong>#<?php echo $userData['id']; ?></strong>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block">Date de création</small>
                                <strong>
                                    <?php 
                                    if ($userData['date_creation']) {
                                        echo date('d/m/Y à H:i', strtotime($userData['date_creation']));
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
                                    if (isset($userData['derniere_connexion']) && $userData['derniere_connexion']) {
                                        echo date('d/m/Y à H:i', strtotime($userData['derniere_connexion']));
                                    } else {
                                        echo 'Jamais connecté';
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