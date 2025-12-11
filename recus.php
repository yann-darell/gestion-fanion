<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

// Filtres
$search = $_GET['search'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

try {
    $db = getDB();
    
    // Classes pour le filtre
    $stmt = $db->prepare("SELECT * FROM classes WHERE annee_scolaire = ? ORDER BY nom_classe");
    $stmt->execute([$anneeScolaire]);
    $classes = $stmt->fetchAll();

    // Requête des reçus (basée sur la table paiements)
    $query = "
        SELECT 
            p.*,
            e.nom AS eleve_nom,
            e.prenom AS eleve_prenom,
            e.matricule,
            c.nom_classe,
            u.nom_complet AS emetteur
        FROM paiements p
        INNER JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN classes c ON e.classe_id = c.id
        LEFT JOIN utilisateurs u ON p.recu_par = u.id
        WHERE p.annee_scolaire = ?
    ";

    $params = [$anneeScolaire];

    // Rechercher
    if (!empty($search)) {
        $query .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR p.numero_recu LIKE ?)";
        $s = "%$search%";
        array_push($params, $s, $s, $s, $s);
    }

    // Filtre classe
    if (!empty($classe_filter)) {
        $query .= " AND e.classe_id = ?";
        $params[] = $classe_filter;
    }

    // Filtre type
    if (!empty($type_filter)) {
        $query .= " AND p.type_paiement = ?";
        $params[] = $type_filter;
    }

    // Dates
    if (!empty($date_debut)) {
        $query .= " AND DATE(p.date_paiement) >= ?";
        $params[] = $date_debut;
    }
    if (!empty($date_fin)) {
        $query .= " AND DATE(p.date_paiement) <= ?";
        $params[] = $date_fin;
    }

    $query .= " ORDER BY p.date_paiement DESC, p.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $recus = $stmt->fetchAll();

    // Statistiques
    $totalRecus = count($recus);
    $totalMontant = array_sum(array_column($recus, 'montant'));

    // Statistiques par type
    $statsParType = [];
    foreach ($recus as $recu) {
        $type = $recu['type_paiement'];
        if (!isset($statsParType[$type])) {
            $statsParType[$type] = ['nombre' => 0, 'montant' => 0];
        }
        $statsParType[$type]['nombre']++;
        $statsParType[$type]['montant'] += $recu['montant'];
    }

} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des reçus - <?php echo APP_NAME; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'includes/styles.php'; ?>

    <style>
        .stat-card {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card.success { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.info    { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

<?php include "includes/header.php"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include "includes/sidebar.php"; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom no-print">
                <h1 class="h2"><i class="bi bi-receipt me-2"></i> Historique des reçus</h1>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Imprimer
                </button>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="row no-print mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <small><i class="bi bi-receipt"></i> Total reçus</small>
                        <h3><?php echo $totalRecus; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <small><i class="bi bi-cash-stack"></i> Montant total</small>
                        <h3><?php echo number_format($totalMontant, 0, ',', ' '); ?> FCFA</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <small><i class="bi bi-calendar"></i> Année scolaire</small>
                        <h3><?php echo $anneeScolaire; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <small><i class="bi bi-cash-coin"></i> Moyenne</small>
                        <h3>
                            <?php echo $totalRecus > 0 ? number_format($totalMontant / $totalRecus, 0, ',', ' ') : '0'; ?>
                            FCFA
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card mb-4 no-print">
                <div class="card-body">
                    <form class="row g-3" method="GET">

                        <div class="col-md-3">
                            <label>Rechercher</label>
                            <input type="text" name="search" class="form-control"
                                   placeholder="Nom, matricule, reçu..."
                                   value="<?php echo $search; ?>">
                        </div>

                        <div class="col-md-2">
                            <label>Classe</label>
                            <select name="classe" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        <?php echo ($classe_filter == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo $c['nom_classe']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Type</label>
                            <select class="form-select" name="type">
                                <option value="">Tous</option>
                                <option value="inscription" <?php echo $type_filter == 'inscription' ? 'selected' : ''; ?>>Inscription</option>
                                <option value="scolarite" <?php echo $type_filter == 'scolarite' ? 'selected' : ''; ?>>Scolarité</option>
                                <option value="examen" <?php echo $type_filter == 'examen' ? 'selected' : ''; ?>>Examen</option>
                                <option value="autre" <?php echo $type_filter == 'autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Date début</label>
                            <input type="date" name="date_debut" class="form-control" value="<?php echo $date_debut; ?>">
                        </div>

                        <div class="col-md-2">
                            <label>Date fin</label>
                            <input type="date" name="date_fin" class="form-control" value="<?php echo $date_fin; ?>">
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Liste des reçus -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Liste des reçus (<?php echo $totalRecus; ?>)</h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>N° Reçu</th>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Matricule</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Émis par</th>
                        </tr>
                        </thead>
                        <tbody>

                        <?php if (empty($recus)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-3">
                                    <i class="bi bi-inbox text-muted fs-1"></i><br>
                                    <small class="text-muted">Aucun reçu trouvé</small>
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($recus as $r): ?>
                                <tr>
                                    <td><strong class="text-primary"><?php echo $r['numero_recu']; ?></strong></td>
                                    <td><?php echo formatDate($r['date_paiement']); ?></td>
                                    <td><strong><?php echo $r['eleve_nom']." ".$r['eleve_prenom']; ?></strong></td>
                                    <td><?php echo $r['matricule']; ?></td>
                                    <td><span class="badge bg-info"><?php echo $r['nom_classe']; ?></span></td>

                                    <td>
                                        <span class="badge 
                                            <?php echo match($r['type_paiement']) {
                                                'scolarite' => 'bg-primary',
                                                'inscription' => 'bg-success',
                                                'examen' => 'bg-warning',
                                                default => 'bg-secondary'
                                            }; ?>">
                                            <?php echo ucfirst($r['type_paiement']); ?>
                                        </span>
                                    </td>

                                    <td><strong class="text-success">
                                        <?php echo number_format($r['montant'], 0, ',', ' '); ?> FCFA
                                    </strong></td>

                                    <td><small><?php echo ucfirst($r['mode_paiement']); ?></small></td>

                                    <td><small><?php echo $r['emetteur'] ?? '-'; ?></small></td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>
                    </table>
                </div>

            </div>

        </main>

    </div>
</div>

</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>