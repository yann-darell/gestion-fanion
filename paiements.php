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
                // Générer un numéro de reçu unique
                $numeroRecu = 'REC' . date('Y') . date('m') . sprintf('%04d', rand(1, 9999));
                
                $stmt = $db->prepare("
                    INSERT INTO paiements (eleve_id, type_paiement, montant, mode_paiement, 
                                          reference_paiement, date_paiement, periode_concernee, 
                                          observation, recu_par, numero_recu)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['eleve_id'],
                    $_POST['type_paiement'],
                    $_POST['montant'],
                    $_POST['mode_paiement'],
                    securiser($_POST['reference_paiement'] ?? ''),
                    $_POST['date_paiement'],
                    securiser($_POST['periode_concernee'] ?? ''),
                    securiser($_POST['observation'] ?? ''),
                    $user['id'],
                    $numeroRecu
                ]);
                
                $paiementId = $db->lastInsertId();
                
                logActivity('Enregistrement d\'un paiement', 'paiements', $paiementId, $numeroRecu);
                $_SESSION['success_message'] = 'Paiement enregistré avec succès';
                $_SESSION['dernier_recu'] = $numeroRecu;
                redirect('paiements.php?success=added&recu=' . $numeroRecu);
                break;
                
            case 'supprimer':
                $paiementId = $_POST['paiement_id'];
                
                $stmt = $db->prepare("DELETE FROM paiements WHERE id = ?");
                $stmt->execute([$paiementId]);
                
                logActivity('Suppression d\'un paiement', 'paiements', $paiementId);
                $_SESSION['success_message'] = 'Paiement supprimé avec succès';
                redirect('paiements.php?success=deleted');
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Erreur : ' . $e->getMessage();
        redirect('paiements.php?error=operation_failed');
    }
}

// Récupération des données
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

try {
    $db = getDB();
    
    // Récupérer tous les élèves pour le formulaire
    $stmt = $db->query("
        SELECT e.*, c.nom_classe 
        FROM eleves e 
        LEFT JOIN classes c ON e.classe_id = c.id 
        WHERE e.statut = 'actif' 
        ORDER BY e.nom, e.prenom
    ");
    $eleves = $stmt->fetchAll();
    
    // Construire la requête de recherche des paiements
    $query = "
        SELECT 
            p.*,
            CONCAT(e.nom, ' ', e.prenom) as eleve_nom,
            e.matricule,
            c.nom_classe,
            u.nom_complet as recu_par_nom
        FROM paiements p
        INNER JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN classes c ON e.classe_id = c.id
        LEFT JOIN utilisateurs u ON p.recu_par = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR p.numero_recu LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_fill(0, 4, $searchTerm);
    }
    
    if (!empty($type_filter)) {
        $query .= " AND p.type_paiement = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($date_debut)) {
        $query .= " AND p.date_paiement >= ?";
        $params[] = $date_debut;
    }
    
    if (!empty($date_fin)) {
        $query .= " AND p.date_paiement <= ?";
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY p.date_paiement DESC, p.id DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $paiements = $stmt->fetchAll();
    
    // Statistiques
    $stmtStats = $db->query("
        SELECT 
            COUNT(*) as total_paiements,
            SUM(montant) as total_montant,
            SUM(CASE WHEN MONTH(date_paiement) = MONTH(CURDATE()) THEN montant ELSE 0 END) as montant_mois
        FROM paiements
        WHERE YEAR(date_paiement) = YEAR(CURDATE())
    ");
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des paiements - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; margin-bottom: 0.25rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; }
        main { padding-top: 70px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        .stat-card .card-body { display: flex; align-items: center; justify-content: space-between; padding: 1.5rem; }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: white; }
        .stat-success .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
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
                        <i class="bi bi-cash-stack me-2"></i>
                        Gestion des paiements
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjoutPaiement">
                        <i class="bi bi-plus-circle me-1"></i>
                        Nouveau paiement
                    </button>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <?php if (isset($_GET['recu'])): ?>
                            <a href="generer_recu.php?numero=<?php echo $_GET['recu']; ?>" class="btn btn-sm btn-success ms-3" target="_blank">
                                <i class="bi bi-printer me-1"></i>Imprimer le reçu
                            </a>
                        <?php endif; ?>
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

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card stat-success">
                            <div class="card-body">
                                <div class="stat-icon">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="mb-0"><?php echo formatMontant($stats['total_montant'] ?? 0); ?></h3>
                                    <p class="text-muted mb-0">Total cette année</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card stat-warning">
                            <div class="card-body">
                                <div class="stat-icon">
                                    <i class="bi bi-calendar-month"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="mb-0"><?php echo formatMontant($stats['montant_mois'] ?? 0); ?></h3>
                                    <p class="text-muted mb-0">Ce mois</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                            <div class="card-body">
                                <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                                    <i class="bi bi-receipt"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="mb-0 text-white"><?php echo $stats['total_paiements'] ?? 0; ?></h3>
                                    <p class="text-white mb-0" style="opacity: 0.9;">Paiements</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="paiements.php" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Rechercher</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nom, matricule, n° reçu..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type">
                                    <option value="">Tous</option>
                                    <option value="inscription" <?php echo ($type_filter == 'inscription') ? 'selected' : ''; ?>>Inscription</option>
                                    <option value="scolarite" <?php echo ($type_filter == 'scolarite') ? 'selected' : ''; ?>>Scolarité</option>
                                    <option value="examen" <?php echo ($type_filter == 'examen') ? 'selected' : ''; ?>>Examen</option>
                                    <option value="autre" <?php echo ($type_filter == 'autre') ? 'selected' : ''; ?>>Autre</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date début</label>
                                <input type="date" class="form-control" name="date_debut" value="<?php echo $date_debut; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date fin</label>
                                <input type="date" class="form-control" name="date_fin" value="<?php echo $date_fin; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                                <a href="paiements.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des paiements -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Liste des paiements récents (<?php echo count($paiements); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>N° Reçu</th>
                                        <th>Date</th>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Type</th>
                                        <th>Mode</th>
                                        <th class="text-end">Montant</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($paiements)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                                <p class="text-muted mb-0">Aucun paiement trouvé</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($paiements as $paiement): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($paiement['numero_recu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($paiement['date_paiement']); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($paiement['eleve_nom']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($paiement['matricule']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($paiement['nom_classe'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst($paiement['type_paiement']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo ucfirst(str_replace('_', ' ', $paiement['mode_paiement'])); ?></small>
                                                </td>
                                                <td class="text-end">
                                                    <strong class="text-success">
                                                        <?php echo formatMontant($paiement['montant']); ?>
                                                    </strong>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="generer_recu.php?id=<?php echo $paiement['id']; ?>" 
                                                           class="btn btn-outline-primary" target="_blank" 
                                                           title="Imprimer le reçu">
                                                            <i class="bi bi-printer"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="supprimerPaiement(<?php echo $paiement['id']; ?>, '<?php echo htmlspecialchars($paiement['numero_recu']); ?>')">
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

    <!-- Modal Ajout Paiement -->
    <div class="modal fade" id="modalAjoutPaiement" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>
                        Enregistrer un paiement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="paiements.php">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Élève *</label>
                                <select class="form-select" name="eleve_id" id="eleve_id" required>
                                    <option value="">Sélectionner un élève...</option>
                                    <?php foreach ($eleves as $eleve): ?>
                                        <option value="<?php echo $eleve['id']; ?>">
                                            <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?> 
                                            - <?php echo htmlspecialchars($eleve['nom_classe'] ?? 'Sans classe'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de paiement *</label>
                                <input type="date" class="form-control" name="date_paiement" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Type de paiement *</label>
                                <select class="form-select" name="type_paiement" required>
                                    <option value="inscription">Inscription</option>
                                    <option value="scolarite" selected>Scolarité</option>
                                    <option value="examen">Examen</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Montant (FCFA) *</label>
                                <input type="number" class="form-control" name="montant" 
                                       min="0" step="1" required placeholder="Ex: 50000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mode de paiement *</label>
                                <select class="form-select" name="mode_paiement" required>
                                    <option value="especes" selected>Espèces</option>
                                    <option value="virement">Virement</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Référence (optionnel)</label>
                                <input type="text" class="form-control" name="reference_paiement" 
                                       placeholder="N° chèque, transaction...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Période concernée</label>
                                <input type="text" class="form-control" name="periode_concernee" 
                                       placeholder="Ex: Trimestre 1">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observation</label>
                                <textarea class="form-control" name="observation" rows="2" 
                                          placeholder="Remarques éventuelles..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>
                            Enregistrer et imprimer le reçu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function supprimerPaiement(id, numero) {
            if (confirm('Êtes-vous sûr de vouloir supprimer le paiement ' + numero + ' ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'paiements.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'supprimer';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'paiement_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Améliorer le select d'élève avec recherche
        const eleveSelect = document.getElementById('eleve_id');
        if (eleveSelect) {
            eleveSelect.addEventListener('keyup', function(e) {
                const filter = e.target.value.toLowerCase();
                const options = eleveSelect.options;
                
                for (let i = 0; i < options.length; i++) {
                    const text = options[i].text.toLowerCase();
                    options[i].style.display = text.includes(filter) ? '' : 'none';
                }
            });
        }
    </script>
</body>
</html>