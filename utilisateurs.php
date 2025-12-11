<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('administrateur');

$user = getCurrentUser();
$db = getDB();

// Récupérer utilisateurs
$stmt = $db->query("SELECT * FROM utilisateurs ORDER BY id DESC");
$users = $stmt->fetchAll();

// Statistiques
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['actif']));
$adminUsers = count(array_filter($users, fn($u) => $u['role'] === 'administrateur'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - <?php echo APP_NAME; ?></title>
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

        .stat-card .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-primary .stat-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-success .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-warning .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            font-weight: 500;
        }

        .btn-primary:hover {
            opacity: .9;
        }

        table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        table thead th {
            border: none;
            padding: 1rem;
        }

        table tbody tr {
            transition: background-color 0.2s;
        }

        table tbody tr:hover {
            background: #eef2ff;
        }

        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
        }

        .alert {
            border: none;
            border-radius: 10px;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
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
                    <i class="bi bi-people-fill me-2"></i>
                    Gestion des utilisateurs
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-circle me-1"></i> Nouvel utilisateur
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Cartes de statistiques -->
            <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card stat-primary">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-content text-end">
                                <h3 class="stat-number"><?php echo $totalUsers; ?></h3>
                                <p class="stat-label">Total utilisateurs</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card stat-success">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div class="stat-content text-end">
                                <h3 class="stat-number"><?php echo $activeUsers; ?></h3>
                                <p class="stat-label">Utilisateurs actifs</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card stat-warning">
                        <div class="card-body">
                            <div class="stat-icon">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>
                            <div class="stat-content text-end">
                                <h3 class="stat-number"><?php echo $adminUsers; ?></h3>
                                <p class="stat-label">Administrateurs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLE UTILISATEURS -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Liste des utilisateurs
                    </h5>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nom complet</th>
                                <th>Nom utilisateur</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Aucun utilisateur trouvé</p>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="ps-4"><?php echo $u['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($u['nom_complet']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($u['nom_utilisateur']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo ucfirst($u['role']); ?></span>
                                </td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Actif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle me-1"></i>Désactivé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="utilisateur_view.php?id=<?php echo $u['id']; ?>" 
                                           class="btn btn-sm btn-outline-info"
                                           title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <a href="utilisateur_edit.php?id=<?php echo $u['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           title="Modifier">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <?php if ($u['actif']): ?>
                                            <a href="utilisateur_toggle.php?action=disable&id=<?php echo $u['id']; ?>"
                                               class="btn btn-sm btn-outline-warning"
                                               title="Désactiver">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="utilisateur_toggle.php?action=enable&id=<?php echo $u['id']; ?>"
                                               class="btn btn-sm btn-outline-success"
                                               title="Activer">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="utilisateur_delete.php?id=<?php echo $u['id']; ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                           <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- MODAL AJOUT -->
<div class="modal fade" id="addUserModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="utilisateurs_action.php" class="modal-content">
            
            <input type="hidden" name="action" value="create_user">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Ajouter un utilisateur
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom complet *</label>
                        <input type="text" name="nom_complet" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nom utilisateur *</label>
                        <input type="text" name="nom_utilisateur" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Rôle *</label>
                        <select name="role" class="form-select">
                            <option value="administrateur">Administrateur</option>
                            <option value="secretaire">Secrétaire</option>
                            <option value="censeur">Censeur</option>
                            <option value="enseignant">Enseignant</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mot de passe *</label>
                        <input type="password" name="mot_de_passe" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Créer
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>