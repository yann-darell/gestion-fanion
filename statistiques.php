<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

try {
    $db = getDB();
    
    // Statistiques générales
    $stmt = $db->query("SELECT COUNT(*) as total FROM eleves WHERE statut = 'actif'");
    $totalEleves = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM classes WHERE annee_scolaire = '$anneeScolaire'");
    $totalClasses = $stmt->fetch()['total'];
    
    // Répartition par sexe
    $stmt = $db->query("
        SELECT sexe, COUNT(*) as nb
        FROM eleves
        WHERE statut = 'actif'
        GROUP BY sexe
    ");
    $repartitionSexe = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Effectif par classe
    $stmt = $db->query("
        SELECT c.nom_classe, COUNT(e.id) as effectif
        FROM classes c
        LEFT JOIN eleves e ON c.id = e.classe_id AND e.statut = 'actif'
        WHERE c.annee_scolaire = '$anneeScolaire'
        GROUP BY c.id, c.nom_classe
        ORDER BY c.nom_classe
    ");
    $effectifClasses = $stmt->fetchAll();
    
    // Moyennes par classe (dernière période)
    $stmt = $db->query("
        SELECT 
            c.nom_classe,
            AVG(m.moyenne_generale) as moyenne_classe,
            COUNT(DISTINCT m.eleve_id) as nb_eleves
        FROM moyennes m
        INNER JOIN classes c ON m.classe_id = c.id
        WHERE m.annee_scolaire = '$anneeScolaire'
        GROUP BY c.id, c.nom_classe
        ORDER BY c.nom_classe
    ");
    $moyennesClasses = $stmt->fetchAll();
    
    // Top 10 élèves
    $stmt = $db->query("
        SELECT 
            CONCAT(e.nom, ' ', e.prenom) as nom_complet,
            c.nom_classe,
            m.moyenne_generale,
            m.mention
        FROM moyennes m
        INNER JOIN eleves e ON m.eleve_id = e.id
        INNER JOIN classes c ON m.classe_id = c.id
        WHERE m.annee_scolaire = '$anneeScolaire'
        ORDER BY m.moyenne_generale DESC
        LIMIT 10
    ");
    $topEleves = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        /* .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; margin-bottom: 0.25rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; } */
        main { padding-top: 70px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
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
                        <i class="bi bi-bar-chart-fill me-2"></i>
                        Statistiques et Analyses
                    </h1>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>
                        Imprimer
                    </button>
                </div>

                <!-- Graphiques -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Répartition par sexe</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartSexe" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Effectif par classe</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartClasses" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Moyennes par classe</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartMoyennes" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top 10 -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-trophy-fill text-warning me-2"></i>
                                    Top 10 des meilleurs élèves
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Rang</th>
                                            <th>Nom complet</th>
                                            <th>Classe</th>
                                            <th>Moyenne</th>
                                            <th>Mention</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rang = 1; foreach ($topEleves as $eleve): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($rang == 1): ?>
                                                        <i class="bi bi-trophy-fill text-warning"></i>
                                                    <?php elseif ($rang == 2): ?>
                                                        <i class="bi bi-trophy-fill text-secondary"></i>
                                                    <?php elseif ($rang == 3): ?>
                                                        <i class="bi bi-trophy-fill" style="color: #cd7f32;"></i>
                                                    <?php else: ?>
                                                        <?php echo $rang; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($eleve['nom_complet']); ?></strong></td>
                                                <td><span class="badge bg-info"><?php echo htmlspecialchars($eleve['nom_classe']); ?></span></td>
                                                <td><strong class="text-success"><?php echo number_format($eleve['moyenne_generale'], 2); ?>/20</strong></td>
                                                <td><span class="badge bg-success"><?php echo htmlspecialchars($eleve['mention']); ?></span></td>
                                            </tr>
                                        <?php $rang++; endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graphique sexe
        new Chart(document.getElementById('chartSexe'), {
            type: 'doughnut',
            data: {
                labels: ['Garçons', 'Filles'],
                datasets: [{
                    data: [<?php echo $repartitionSexe['M'] ?? 0; ?>, <?php echo $repartitionSexe['F'] ?? 0; ?>],
                    backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(236, 72, 153, 0.8)']
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

        // Graphique classes
        new Chart(document.getElementById('chartClasses'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($effectifClasses, 'nom_classe')); ?>,
                datasets: [{
                    label: 'Effectif',
                    data: <?php echo json_encode(array_column($effectifClasses, 'effectif')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)'
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });

        // Graphique moyennes
        new Chart(document.getElementById('chartMoyennes'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($moyennesClasses, 'nom_classe')); ?>,
                datasets: [{
                    label: 'Moyenne de classe',
                    data: <?php echo json_encode(array_map(function($m) { return round($m['moyenne_classe'], 2); }, $moyennesClasses)); ?>,
                    borderColor: 'rgba(16, 185, 129, 0.8)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20
                    }
                }
            }
        });
    </script>
</body>
</html>