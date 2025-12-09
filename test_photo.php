<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();

try {
    $db = getDB();
    
    // Récupérer quelques élèves avec photos
    $stmt = $db->query("
        SELECT e.*, c.nom_classe 
        FROM eleves e
        LEFT JOIN classes c ON e.classe_id = c.id
        WHERE e.statut = 'actif'
        ORDER BY e.nom, e.prenom
        LIMIT 20
    ");
    $eleves = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trombinoscope - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        .eleve-card { transition: transform 0.3s; }
        .eleve-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .eleve-photo { width: 120px; height: 120px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h2 class="mb-4">
                    <i class="bi bi-image me-2"></i>
                    Trombinoscope des Élèves
                </h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <div class="row">
                    <?php foreach ($eleves as $eleve): ?>
                        <div class="col-md-3 col-sm-6 mb-4">
                            <div class="card eleve-card">
                                <div class="card-body text-center">
                                    <?php if ($eleve['photo']): ?>
                                        <img src="uploads/photos/<?php echo htmlspecialchars($eleve['photo']); ?>" 
                                             alt="Photo de <?php echo htmlspecialchars($eleve['nom']); ?>" 
                                             class="rounded-circle eleve-photo mb-3"
                                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Crect fill=%22%23ddd%22 width=%22120%22 height=%22120%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%23999%22 font-size=%2248%22%3E<?php echo strtoupper(substr($eleve['nom'], 0, 1)); ?>%3C/text%3E%3C/svg%3E'">
                                    <?php else: ?>
                                        <div class="rounded-circle eleve-photo bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="font-size: 48px;">
                                            <?php echo strtoupper(substr($eleve['nom'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                    </h5>
                                    <p class="text-muted small mb-2">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                                    </p>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-building me-1"></i>
                                        <?php echo htmlspecialchars($eleve['nom_classe'] ?? 'Sans classe'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($eleves)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun élève trouvé. Ajoutez des élèves avec leurs photos dans le module "Élèves".
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>