<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

// Protéger la page
Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

// Récupérer les statistiques
try {
    $db = getDB();
    
    // Nombre total d'élèves actifs
    $stmt = $db->query("SELECT COUNT(*) as total FROM eleves WHERE statut = 'actif'");
    $totalEleves = $stmt->fetch()['total'] ?? 0;
    
    // Nombre de classes (Sécurisée avec requête préparée)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM classes WHERE annee_scolaire = ?");
    $stmt->execute([$anneeScolaire]);
    $totalClasses = $stmt->fetch()['total'] ?? 0;
    
    // Montant total des paiements
    $stmt = $db->query("SELECT COALESCE(SUM(montant), 0) as total FROM paiements WHERE YEAR(date_paiement) = YEAR(CURDATE())");
    $totalPaiements = $stmt->fetch()['total'] ?? 0;
    
    // Nombre de bulletins générés ce mois
    $stmt = $db->query("SELECT COUNT(*) as total FROM bulletins_generes WHERE MONTH(date_generation) = MONTH(CURDATE())");
    $totalBulletins = $stmt->fetch()['total'] ?? 0;
    
    // Statistiques par classe (Sécurisée avec requête préparée)
    $stmt = $db->prepare("
        SELECT 
            c.nom_classe,
            COUNT(e.id) as effectif,
            COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) as garcons,
            COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) as filles
        FROM classes c
        LEFT JOIN eleves e ON c.id = e.classe_id AND e.statut = 'actif'
        WHERE c.annee_scolaire = ?
        GROUP BY c.id, c.nom_classe
        ORDER BY c.nom_classe
        LIMIT 10
    ");
    $stmt->execute([$anneeScolaire]);
    $statsClasses = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données : " . $e->getMessage();
    $totalEleves = 0;
    $totalClasses = 0;
    $totalPaiements = 0;
    $totalBulletins = 0;
    $statsClasses = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 70px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background: white;
        }
        .sidebar .nav-link {
            color: #6b7280;
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 10px;
        }
        .sidebar .nav-link:hover {
            background: #f3f4f6;
            color: var(--primary-color);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
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
        .stat-info .stat-icon {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
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
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
             <?php include 'includes/sidebar.php'; ?>
             <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Tableau de bord
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-calendar-event me-1"></i>
                                <?php echo $anneeScolaire; ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Cartes de statistiques -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-primary">
                            <div class="card-body">
                                <div class="stat-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="stat-number"><?php echo $totalEleves; ?></h3>
                                    <p class="stat-label">Élèves actifs</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-success">
                            <div class="card-body">
                                <div class="stat-icon">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="stat-number"><?php echo $totalClasses; ?></h3>
                                    <p class="stat-label">Classes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-warning">
                            <div class="card-body">
                                <div class="stat-icon">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="stat-number"><?php echo number_format($totalPaiements, 0, ',', ' '); ?></h3>
                                    <p class="stat-label">FCFA (<?php echo date('Y'); ?>)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-info">
                            <div class="card-body">
                                <div class="stat-icon">
                                    <i class="bi bi-file-earmark-text-fill"></i>
                                </div>
                                <div class="stat-content text-end">
                                    <h3 class="stat-number"><?php echo $totalBulletins; ?></h3>
                                    <p class="stat-label">Bulletins ce mois</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message de bienvenue -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                                <h2 class="mt-3">Bienvenue sur le système de gestion !</h2>
                                <p class="text-muted mb-4">
                                    Connecté en tant que <strong><?php echo htmlspecialchars($user['nom_complet']); ?></strong>
                                </p>
                                <div class="row mt-4">
                                    <div class="col-md-3">
                                        <a href="eleves.php" class="btn btn-outline-primary w-100 mb-2">
                                            <i class="bi bi-people-fill d-block mb-2" style="font-size: 2rem;"></i>
                                            Gérer les élèves
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="classes.php" class="btn btn-outline-success w-100 mb-2">
                                            <i class="bi bi-building d-block mb-2" style="font-size: 2rem;"></i>
                                            Gérer les classes
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="notes.php" class="btn btn-outline-warning w-100 mb-2">
                                            <i class="bi bi-journal-text d-block mb-2" style="font-size: 2rem;"></i>
                                            Saisir les notes
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="paiements.php" class="btn btn-outline-info w-100 mb-2">
                                            <i class="bi bi-cash-stack d-block mb-2" style="font-size: 2rem;"></i>
                                            Gérer paiements
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($statsClasses)): ?>
                <!-- Graphique des classes -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-bar-chart-fill me-2"></i>
                                    Répartition des élèves par classe
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartClasses" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($statsClasses)): ?>
    <script>
        const ctxClasses = document.getElementById('chartClasses').getContext('2d');
        const chartClasses = new Chart(ctxClasses, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($statsClasses, 'nom_classe')); ?>,
                datasets: [{
                    label: 'Garçons',
                    data: <?php echo json_encode(array_column($statsClasses, 'garcons')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                }, {
                    label: 'Filles',
                    data: <?php echo json_encode(array_column($statsClasses, 'filles')); ?>,
                    backgroundColor: 'rgba(236, 72, 153, 0.8)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>