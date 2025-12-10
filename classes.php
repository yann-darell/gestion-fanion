<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        switch ($action) {
            case 'ajouter':
                $stmt = $db->prepare("
                    INSERT INTO classes (nom_classe, niveau, annee_scolaire, frais_scolarite, frais_inscription)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    securiser($_POST['nom_classe']),
                    securiser($_POST['niveau']),
                    $anneeScolaire,
                    $_POST['frais_scolarite'] ?? 0,
                    $_POST['frais_inscription'] ?? 0
                ]);
                
                $classeId = $db->lastInsertId();
                
                // Associer les matières sélectionnées
                if (!empty($_POST['matieres'])) {
                    $stmtMatiere = $db->prepare("
                        INSERT INTO classe_matiere (classe_id, matiere_id, coefficient)
                        VALUES (?, ?, ?)
                    ");
                    
                    foreach ($_POST['matieres'] as $matiereId) {
                        $coefficient = $_POST['coefficient_' . $matiereId] ?? 1;
                        $stmtMatiere->execute([$classeId, $matiereId, $coefficient]);
                    }
                }
                
                logActivity('Ajout d\'une classe', 'classes', $classeId, $_POST['nom_classe']);
                $_SESSION['success_message'] = 'Classe ajoutée avec succès';
                redirect('classes.php?success=added');
                break;
                
            case 'modifier':
                $classeId = $_POST['classe_id'];
                
                $stmt = $db->prepare("
                    UPDATE classes SET 
                        nom_classe = ?, niveau = ?, frais_scolarite = ?, frais_inscription = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    securiser($_POST['nom_classe']),
                    securiser($_POST['niveau']),
                    $_POST['frais_scolarite'] ?? 0,
                    $_POST['frais_inscription'] ?? 0,
                    $classeId
                ]);
                
                logActivity('Modification d\'une classe', 'classes', $classeId);
                $_SESSION['success_message'] = 'Classe modifiée avec succès';
                redirect('classes.php?success=updated');
                break;
                
            case 'supprimer':
                $classeId = $_POST['classe_id'];
                
                // Vérifier s'il y a des élèves dans cette classe
                $stmt = $db->prepare("SELECT COUNT(*) as nb FROM eleves WHERE classe_id = ?");
                $stmt->execute([$classeId]);
                $nbEleves = $stmt->fetch()['nb'];
                
                if ($nbEleves > 0) {
                    $_SESSION['error_message'] = "Impossible de supprimer cette classe car elle contient $nbEleves élève(s)";
                    redirect('classes.php?error=has_students');
                }
                
                $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$classeId]);
                
                logActivity('Suppression d\'une classe', 'classes', $classeId);
                $_SESSION['success_message'] = 'Classe supprimée avec succès';
                redirect('classes.php?success=deleted');
                break;
                
            case 'gerer_matieres':
                $classeId = $_POST['classe_id'];
                
                // Supprimer les associations existantes
                $stmt = $db->prepare("DELETE FROM classe_matiere WHERE classe_id = ?");
                $stmt->execute([$classeId]);
                
                // Ajouter les nouvelles associations
                if (!empty($_POST['matieres'])) {
                    $stmtMatiere = $db->prepare("
                        INSERT INTO classe_matiere (classe_id, matiere_id, coefficient)
                        VALUES (?, ?, ?)
                    ");
                    
                    foreach ($_POST['matieres'] as $matiereId) {
                        $coefficient = $_POST['coefficient_' . $matiereId] ?? 1;
                        $stmtMatiere->execute([$classeId, $matiereId, $coefficient]);
                    }
                }
                
                logActivity('Modification des matières d\'une classe', 'classe_matiere', $classeId);
                $_SESSION['success_message'] = 'Matières mises à jour avec succès';
                redirect('classes.php?success=matieres_updated');
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Erreur : ' . $e->getMessage();
        redirect('classes.php?error=operation_failed');
    }
}

// Récupération des données
try {
    $db = getDB();
    
    // Récupérer les classes avec statistiques
    $stmt = $db->query("
        SELECT 
            c.*,
            COUNT(DISTINCT e.id) as nb_eleves,
            COUNT(DISTINCT cm.matiere_id) as nb_matieres,
            COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) as nb_garcons,
            COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) as nb_filles
        FROM classes c
        LEFT JOIN eleves e ON c.id = e.classe_id AND e.statut = 'actif'
        LEFT JOIN classe_matiere cm ON c.id = cm.classe_id
        WHERE c.annee_scolaire = '$anneeScolaire'
        GROUP BY c.id
        ORDER BY c.niveau, c.nom_classe
    ");
    $classes = $stmt->fetchAll();
    
    // Récupérer toutes les matières disponibles
    $stmt = $db->query("SELECT * FROM matieres ORDER BY nom_matiere");
    $matieres = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des classes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        /* .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; margin-bottom: 0.25rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; } */
        main { padding-top: 70px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); transition: transform 0.3s; }
        .card:hover { transform: translateY(-3px); }
        .classe-card { cursor: pointer; }
        .classe-badge { display: inline-block; padding: 0.35rem 0.65rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; }
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
                        <i class="bi bi-building me-2"></i>
                        Gestion des classes
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjoutClasse">
                        <i class="bi bi-plus-circle me-1"></i>
                        Nouvelle classe
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

                <!-- Liste des classes -->
                <div class="row">
                    <?php if (empty($classes)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <p class="text-muted mb-3">Aucune classe créée pour cette année scolaire</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjoutClasse">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Créer la première classe
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($classes as $classe): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card classe-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <i class="bi bi-building text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                                </h5>
                                                <span class="classe-badge bg-info text-white">
                                                    <?php echo htmlspecialchars($classe['niveau']); ?>
                                                </span>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="gererMatieres(<?php echo $classe['id']; ?>, '<?php echo htmlspecialchars($classe['nom_classe']); ?>')">
                                                            <i class="bi bi-book me-2"></i>Gérer les matières
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="modifierClasse(<?php echo $classe['id']; ?>)">
                                                            <i class="bi bi-pencil me-2"></i>Modifier
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="supprimerClasse(<?php echo $classe['id']; ?>, '<?php echo htmlspecialchars($classe['nom_classe']); ?>')">
                                                            <i class="bi bi-trash me-2"></i>Supprimer
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <h4 class="mb-0 text-primary"><?php echo $classe['nb_eleves']; ?></h4>
                                                    <small class="text-muted">Élèves</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <h4 class="mb-0 text-success"><?php echo $classe['nb_matieres']; ?></h4>
                                                    <small class="text-muted">Matières</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">
                                                <i class="bi bi-gender-male text-primary me-1"></i>
                                                <?php echo $classe['nb_garcons']; ?> G
                                            </span>
                                            <span class="text-muted">
                                                <i class="bi bi-gender-female text-danger me-1"></i>
                                                <?php echo $classe['nb_filles']; ?> F
                                            </span>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="bi bi-cash me-1"></i>
                                                Scolarité: <?php echo number_format($classe['frais_scolarite'], 0, ',', ' '); ?> FCFA
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Ajout Classe -->
    <div class="modal fade" id="modalAjoutClasse" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>
                        Ajouter une nouvelle classe
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="classes.php">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom de la classe *</label>
                                <input type="text" class="form-control" name="nom_classe" placeholder="Ex: 6ème A" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Niveau *</label>
                                <select class="form-select" name="niveau" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="6ème">6ème</option>
                                    <option value="5ème">5ème</option>
                                    <option value="4ème">4ème</option>
                                    <option value="3ème">3ème</option>
                                    <option value="2nde">2nde</option>
                                    <option value="1ère">1ère</option>
                                    <option value="Tle">Terminale</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Frais de scolarité (FCFA)</label>
                                <input type="number" class="form-control" name="frais_scolarite" value="0" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Frais d'inscription (FCFA)</label>
                                <input type="number" class="form-control" name="frais_inscription" value="0" min="0">
                            </div>
                            
                            <div class="col-12">
                                <hr>
                                <h6>Matières associées (optionnel)</h6>
                                <p class="text-muted small">Vous pouvez associer les matières maintenant ou plus tard</p>
                                
                                <div class="row">
                                    <?php foreach ($matieres as $matiere): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="matieres[]" 
                                                       value="<?php echo $matiere['id']; ?>" 
                                                       id="matiere_<?php echo $matiere['id']; ?>">
                                                <label class="form-check-label" for="matiere_<?php echo $matiere['id']; ?>">
                                                    <?php echo htmlspecialchars($matiere['nom_matiere']); ?>
                                                    <input type="number" class="form-control form-control-sm d-inline-block ms-2" 
                                                           name="coefficient_<?php echo $matiere['id']; ?>" 
                                                           value="<?php echo $matiere['coefficient']; ?>" 
                                                           min="1" max="10" style="width: 60px;" placeholder="Coef">
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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

    <!-- Modal Gérer Matières -->
    <div class="modal fade" id="modalGererMatieres" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGererMatieresTitle">Gérer les matières</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="classes.php" id="formGererMatieres">
                    <input type="hidden" name="action" value="gerer_matieres">
                    <input type="hidden" name="classe_id" id="gerer_classe_id">
                    <div class="modal-body">
                        <div id="listeMatieres"></div>
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
        function gererMatieres(classeId, nomClasse) {
            document.getElementById('gerer_classe_id').value = classeId;
            document.getElementById('modalGererMatieresTitle').textContent = 'Gérer les matières - ' + nomClasse;
            
            // Charger les matières actuelles via AJAX (simplifié ici)
            const listeMatieres = document.getElementById('listeMatieres');
            listeMatieres.innerHTML = `
                <div class="row">
                    <?php foreach ($matieres as $matiere): ?>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="matieres[]" 
                                       value="<?php echo $matiere['id']; ?>" 
                                       id="gerer_matiere_<?php echo $matiere['id']; ?>">
                                <label class="form-check-label" for="gerer_matiere_<?php echo $matiere['id']; ?>">
                                    <?php echo htmlspecialchars($matiere['nom_matiere']); ?>
                                    <input type="number" class="form-control form-control-sm d-inline-block ms-2" 
                                           name="coefficient_<?php echo $matiere['id']; ?>" 
                                           value="<?php echo $matiere['coefficient']; ?>" 
                                           min="1" max="10" style="width: 60px;">
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('modalGererMatieres')).show();
        }

        function modifierClasse(id) {
            alert('Fonctionnalité en cours de développement');
        }

        function supprimerClasse(id, nom) {
            if (confirm('Êtes-vous sûr de vouloir supprimer la classe ' + nom + ' ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'classes.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'supprimer';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'classe_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>