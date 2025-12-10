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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des utilisateurs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --bg-light: #f3f4f6;
            --text-muted: #6b7280;
        }

        body {
            background: var(--bg-light);
            font-family: 'Segoe UI';
        }

        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: .3s;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }

        .title-bar {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            margin-bottom: 25px;
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

        table tbody tr:hover {
            background: #eef2ff;
        }
    </style>

</head>
<body>

<?php include "includes/header.php"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include "includes/sidebar.php"; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-4">

            <!-- HEADER PAGE -->
            <div class="d-flex justify-content-between align-items-center title-bar">
                <h2 class="h3">
                    <i class="bi bi-people-fill me-2"></i>
                    Gestion des utilisateurs
                </h2>

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-circle me-1"></i> Nouvel utilisateur
                </button>
            </div>

            <!-- TABLE UTILISATEURS -->
            <div class="card">
                <div class="card-body table-responsive">

                    <table class="table align-middle">
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
                                    Aucun utilisateur trouvé
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['nom_complet']); ?></td>
                                <td><?php echo htmlspecialchars($u['nom_utilisateur']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $u['role']; ?></span>
                                </td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Désactivé</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">

                                        <a href="utilisateur_edit.php?id=<?php echo $u['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <?php if ($u['actif']): ?>
                                            <a href="utilisateur_toggle.php?action=disable&id=<?php echo $u['id']; ?>"
                                               class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="utilisateur_toggle.php?action=enable&id=<?php echo $u['id']; ?>"
                                               class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="utilisateur_delete.php?id=<?php echo $u['id']; ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Supprimer cet utilisateur ?');">
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
                <h5 class="modal-title">Ajouter un utilisateur</h5>
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
                <button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button class="btn btn-primary">Créer</button>
            </div>

        </form>
    </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
