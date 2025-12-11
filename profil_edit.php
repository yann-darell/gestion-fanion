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
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    if (empty($nom_complet)) {
        $error = "Le nom complet est obligatoire.";
    } else {
        // Vérifier si l'email n'est pas déjà utilisé par un autre utilisateur
        if (!empty($email)) {
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            
            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé par un autre utilisateur.";
            }
        }

        if (empty($error)) {
            try {
                $stmt = $db->prepare("
                    UPDATE utilisateurs 
                    SET nom_complet = ?, email = ?, telephone = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom_complet, $email, $telephone, $currentUser['id']]);

                $_SESSION['success'] = "Votre profil a été mis à jour avec succès.";
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
    <title>Modifier mon profil - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1);
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
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
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
                        <i class="bi bi-pencil-square me-2"></i>
                        Modifier mon profil
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
                                        <i class="bi bi-person-fill me-2"></i>
                                        Informations personnelles
                                    </h5>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nom d'utilisateur</label>
                                        <input type="text" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($currentUser['nom_utilisateur']); ?>" 
                                               disabled>
                                        <small class="text-muted">Le nom d'utilisateur ne peut pas être modifié</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Rôle</label>
                                        <input type="text" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?>" 
                                               disabled>
                                        <small class="text-muted">Le rôle est géré par l'administrateur</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Nom complet *</label>
                                        <input type="text" 
                                               name="nom_complet" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($currentUser['nom_complet']); ?>" 
                                               required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" 
                                               name="email" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>">
                                        <small class="text-muted">Facultatif</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Téléphone</label>
                                        <input type="text" 
                                               name="telephone" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($currentUser['telephone'] ?? ''); ?>">
                                        <small class="text-muted">Facultatif</small>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-white border-top">
                                <div class="d-flex justify-content-between">
                                    <a href="profil.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i> Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i> Enregistrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Informations
                                </h5>
                                <p class="card-text">
                                    Vous pouvez modifier vos informations personnelles. Le nom d'utilisateur et le rôle ne peuvent être modifiés que par un administrateur.
                                </p>
                                <hr>
                                <a href="profil_password.php" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-key me-2"></i>
                                    Changer mon mot de passe
                                </a>
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