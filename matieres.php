<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        switch ($action) {
            case 'ajouter':
                $stmt = $db->prepare("
                    INSERT INTO matieres (nom_matiere, code_matiere, coefficient, categorie)
                    VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    securiser($_POST['nom_matiere']),
                    securiser($_POST['code_matiere']),
                    $_POST['coefficient'] ?? 1,
                    securiser($_POST['categorie'] ?? '')
                ]);
                
                $matiereId = $db->lastInsertId();
                
                logActivity('Ajout d\'une matière', 'matieres', $matiereId, $_POST['nom_matiere']);
                $_SESSION['success_message'] = 'Matière ajoutée avec succès';
                redirect('matieres.php?success=added');
                break;
                
            case 'modifier':
                $matiereId = $_POST['matiere_id'];
                
                $stmt = $db->prepare("
                    UPDATE matieres SET 
                        nom_matiere = ?, code_matiere = ?, coefficient = ?, categorie = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    securiser($_POST['nom_matiere']),
                    securiser($_POST['code_matiere']),
                    $_POST['coefficient'] ?? 1,
                    securiser($_POST['categorie'] ?? ''),
                    $matiereId
                ]);
                
                logActivity('Modification d\'une matière', 'matieres', $matiereId);
                $_SESSION['success_message'] = 'Matière modifiée avec succès';
                redirect('matieres.php?success=updated');
                break;
                
            case 'supprimer':
                $matiereId = $_POST['matiere_id'];
                
                // Vérifier si la matière est utilisée
                $stmt = $db->prepare("SELECT COUNT(*) as nb FROM classe_matiere WHERE matiere_id = ?");
                $stmt->execute([$matiereId]);
                $nbClasses = $stmt->fetch()['nb'];
                
                if ($nbClasses > 0) {
                    $_SESSION['error_message'] = "Impossible de supprimer cette matière car elle est associée à $nbClasses classe(s)";
                    redirect('matieres.php?error=in_use');
                }
                
                $stmt = $db->prepare("DELETE FROM matieres WHERE id = ?");
                $stmt->execute([$matiereId]);
                
                logActivity('Suppression d\'une matière', 'matieres', $matiereId);
                $_SESSION['success_message'] = 'Matière supprimée avec succès';
                redirect('matieres.php?success=deleted');
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Erreur : ' . $e->getMessage();
        redirect('matieres.php?error=operation_failed');
    }
}

// Récupération des données
$search = $_GET['search'] ?? '';
$categorie_filter = $_GET['categorie'] ?? '';

try {
    $db = getDB();
    
    $query = "
        SELECT 
            m.*,
            COUNT(DISTINCT cm.classe_id) as nb_classes
        FROM matieres m
        LEFT JOIN classe_matiere cm ON m.id = cm.matiere_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (m.nom_matiere LIKE ? OR m.code_matiere LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm];
    }
    
    if (!empty($categorie_filter)) {
        $query .= " AND m.categorie = ?";
        $params[] = $categorie_filter;
    }
    
    $query .= " GROUP BY m.id ORDER BY m.categorie, m.nom_matiere";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $matieres = $stmt->fetchAll();
    
    // Récupérer les catégories distinctes
    $stmt = $db->query("SELECT DISTINCT categorie FROM matieres WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des matières - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        /* .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; margin-bottom: 0.25rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; }
        main { padding-top: 70px; } */
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        .table thead th { background: #f9fafb; font-weight: 600; }
        .badge-coef { background: linear-gradient(135deg, #f59e0b, #d97706); }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
        
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-book-fill me-2"></i>
                        Gestion des matières
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjoutMatiere">
                        <i class="bi bi-plus-circle me-1"></i>
                        Nouvelle matière
                    </button>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="matieres.php" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Rechercher</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nom ou code de la matière..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Catégorie</label>
                                <select class="form-select" name="categorie">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo ($categorie_filter == $cat) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des matières -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Liste des matières (<?php echo count($matieres); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Nom de la matière</th>
                                        <th>Catégorie</th>
                                        <th class="text-center">Coefficient</th>
                                        <th class="text-center">Classes associées</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($matieres)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                                <p class="text-muted mb-0">Aucune matière trouvée</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($matieres as $matiere): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($matiere['code_matiere']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($matiere['nom_matiere']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($matiere['categorie']): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($matiere['categorie']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-coef">
                                                        Coef. <?php echo $matiere['coefficient']; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-success">
                                                        <?php echo $matiere['nb_classes']; ?> classe(s)
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick='modifierMatiere(<?php echo json_encode($matiere); ?>)'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="supprimerMatiere(<?php echo $matiere['id']; ?>, '<?php echo htmlspecialchars($matiere['nom_matiere']); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Ajout/Modification Matière -->
    <div class="modal fade" id="modalAjoutMatiere" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMatiereTitle">
                        <i class="bi bi-plus-circle me-2"></i>
                        Ajouter une matière
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="matieres.php" id="formMatiere">
                    <input type="hidden" name="action" value="ajouter" id="action_matiere">
                    <input type="hidden" name="matiere_id" id="matiere_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom de la matière *</label>
                            <input type="text" class="form-control" name="nom_matiere" id="nom_matiere" 
                                   placeholder="Ex: Mathématiques" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Code matière *</label>
                            <input type="text" class="form-control" name="code_matiere" id="code_matiere" 
                                   placeholder="Ex: MATHS" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coefficient *</label>
                                <input type="number" class="form-control" name="coefficient" id="coefficient" 
                                       value="1" min="1" max="10" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Catégorie</label>
                                <select class="form-select" name="categorie" id="categorie">
                                    <option value="">Aucune</option>
                                    <option value="Langues">Langues</option>
                                    <option value="Français">Français</option>
                                    <option value="Sciences">Sciences</option>
                                    <option value="Sciences Sociales">Sciences Sociales</option>
                                    <option value="Arts">Arts</option>
                                    <option value="Sport">Sport</option>
                                    <option value="Pratique">Pratique</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function modifierMatiere(matiere) {
            document.getElementById('modalMatiereTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Modifier la matière';
            document.getElementById('action_matiere').value = 'modifier';
            document.getElementById('matiere_id').value = matiere.id;
            document.getElementById('nom_matiere').value = matiere.nom_matiere;
            document.getElementById('code_matiere').value = matiere.code_matiere;
            document.getElementById('coefficient').value = matiere.coefficient;
            document.getElementById('categorie').value = matiere.categorie || '';
            
            new bootstrap.Modal(document.getElementById('modalAjoutMatiere')).show();
        }

        function supprimerMatiere(id, nom) {
            if (confirm('Êtes-vous sûr de vouloir supprimer la matière "' + nom + '" ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'matieres.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'supprimer';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'matiere_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Réinitialiser le formulaire quand on ferme le modal
        document.getElementById('modalAjoutMatiere').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formMatiere').reset();
            document.getElementById('modalMatiereTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Ajouter une matière';
            document.getElementById('action_matiere').value = 'ajouter';
            document.getElementById('matiere_id').value = '';
        });
    </script>
</body>
</html>