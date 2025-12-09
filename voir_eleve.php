<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

if (!isset($_GET['id'])) {
    die("ID élève manquant");
}

$eleveId = intval($_GET['id']);
$db = getDB();

// Récupérer les infos de l'élève
$stmt = $db->prepare("
    SELECT e.*, c.nom_classe 
    FROM eleves e
    LEFT JOIN classes c ON e.classe_id = c.id
    WHERE e.id = ?
");
$stmt->execute([$eleveId]);
$eleve = $stmt->fetch();

if (!$eleve) {
    die("Élève introuvable");
}

// Détection de la photo dans assets/images/photos
$photoPath = null;
if (!empty($eleve['photo']) && file_exists(__DIR__ . '/' . $eleve['photo'])) {
    $photoPath = $eleve['photo'];
} else {
    // Essayer de trouver par matricule dans assets/images/photos
    foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $p = 'assets/images/photos/' . $eleve['matricule'] . '.' . $ext;
        if (file_exists(__DIR__ . '/' . $p)) {
            $photoPath = $p;
            break;
        }
    }
}

// Récupérer les statistiques de l'élève
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

// Nombre de notes
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notes WHERE eleve_id = ? AND annee_scolaire = ?");
$stmt->execute([$eleveId, $anneeScolaire]);
$nbNotes = $stmt->fetch()['total'] ?? 0;

// Moyenne générale
$stmt = $db->prepare("
    SELECT AVG(moyenne_generale) as moyenne 
    FROM moyennes 
    WHERE eleve_id = ? AND annee_scolaire = ?
");
$stmt->execute([$eleveId, $anneeScolaire]);
$moyenneGenerale = $stmt->fetch()['moyenne'] ?? null;

// Dernières notes
$stmt = $db->prepare("
    SELECT m.nom_matiere, n.note, n.periode
    FROM notes n
    INNER JOIN matieres m ON n.matiere_id = m.id
    WHERE n.eleve_id = ? AND n.annee_scolaire = ?
    ORDER BY n.id DESC
    LIMIT 5
");
$stmt->execute([$eleveId, $anneeScolaire]);
$dernieresNotes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Détails élève - <?php echo APP_NAME; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<?php include 'includes/styles.php'; ?>
<style>
.info-box { 
    padding: 20px; 
    border: 1px solid #e5e7eb; 
    border-radius: 10px; 
    background: #fff; 
    margin-bottom: 20px;
}
.info-row { 
    display: flex; 
    padding: 8px 0; 
    font-size: 14px; 
    border-bottom: 1px solid #f3f4f6;
}
.info-row:last-child {
    border-bottom: none;
}
.info-row strong { 
    min-width: 200px; 
    color: #6b7280;
}
.info-row span {
    color: #1f2937;
    font-weight: 500;
}
.photo-eleve { 
    width: 180px; 
    height: 220px; 
    object-fit: cover; 
    border: 3px solid #2563eb; 
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.no-photo {
    width: 180px; 
    height: 220px;
    border: 3px dashed #d1d5db;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    color: #9ca3af;
}
.stat-card {
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.stat-card .value {
    font-size: 32px;
    font-weight: bold;
    margin: 10px 0;
}
.stat-card .label {
    font-size: 14px;
    opacity: 0.9;
}
</style>
</head>

<body>
<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>
                    <i class="bi bi-person-circle me-2"></i>
                    Détails de l'élève
                </h2>
                <div>
                    <a href="modifier_eleve.php?id=<?php echo $eleveId; ?>" class="btn btn-warning me-2">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                    <a href="eleves.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Colonne gauche : Photo et stats -->
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body text-center">
                            <?php if ($photoPath): ?>
                                <img src="<?php echo $photoPath; ?>" class="photo-eleve mb-3" alt="Photo élève">
                            <?php else: ?>
                                <div class="no-photo mb-3">
                                    <div>
                                        <i class="bi bi-person-circle" style="font-size: 80px;"></i>
                                        <p class="small mt-2 mb-0">Pas de photo</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mb-1"><?php echo htmlspecialchars($eleve['nom'].' '.$eleve['prenom']); ?></h4>
                            <p class="text-muted mb-3">
                                <i class="bi bi-credit-card me-1"></i>
                                <?php echo htmlspecialchars($eleve['matricule']); ?>
                            </p>
                            
                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <?php if ($eleve['sexe'] == 'M'): ?>
                                    <span class="badge bg-primary">Masculin</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Féminin</span>
                                <?php endif; ?>
                                
                                <?php
                                $badgeClass = match($eleve['statut']) {
                                    'actif' => 'bg-success',
                                    'inactif' => 'bg-secondary',
                                    'transfere' => 'bg-warning',
                                    default => 'bg-dark'
                                };
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo ucfirst($eleve['statut']); ?>
                                </span>
                            </div>
                            
                            <?php if ($eleve['nom_classe']): ?>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-building me-1"></i>
                                    <strong><?php echo htmlspecialchars($eleve['nom_classe']); ?></strong>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Non affecté à une classe
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Statistiques -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="stat-card">
                                <i class="bi bi-journal-text" style="font-size: 24px;"></i>
                                <div class="value"><?php echo $nbNotes; ?></div>
                                <div class="label">Notes enregistrées</div>
                            </div>
                        </div>
                        <?php if ($moyenneGenerale): ?>
                        <div class="col-12">
                            <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="bi bi-graph-up" style="font-size: 24px;"></i>
                                <div class="value"><?php echo number_format($moyenneGenerale, 2); ?>/20</div>
                                <div class="label">Moyenne générale</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Colonne droite : Informations détaillées -->
                <div class="col-md-8">
                    <!-- Informations personnelles -->
                    <div class="info-box">
                        <h5 class="mb-3">
                            <i class="bi bi-person-vcard me-2 text-primary"></i>
                            Informations personnelles
                        </h5>

                        <div class="info-row">
                            <strong>Nom complet :</strong>
                            <span><?php echo htmlspecialchars($eleve['nom'].' '.$eleve['prenom']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Matricule :</strong>
                            <span><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Sexe :</strong>
                            <span><?php echo $eleve['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Date de naissance :</strong>
                            <span><?php echo formatDate($eleve['date_naissance']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Lieu de naissance :</strong>
                            <span><?php echo htmlspecialchars($eleve['lieu_naissance'] ?: '-'); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Adresse :</strong>
                            <span><?php echo htmlspecialchars($eleve['adresse'] ?: '-'); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Téléphone élève :</strong>
                            <span><?php echo htmlspecialchars($eleve['telephone'] ?: '-'); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Email :</strong>
                            <span><?php echo htmlspecialchars($eleve['email'] ?: '-'); ?></span>
                        </div>
                    </div>

                    <!-- Informations scolaires -->
                    <div class="info-box">
                        <h5 class="mb-3">
                            <i class="bi bi-book me-2 text-success"></i>
                            Informations scolaires
                        </h5>

                        <div class="info-row">
                            <strong>Classe actuelle :</strong>
                            <span><?php echo htmlspecialchars($eleve['nom_classe'] ?: 'Non affecté'); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Date d'inscription :</strong>
                            <span><?php echo formatDate($eleve['date_inscription']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Année scolaire :</strong>
                            <span><?php echo htmlspecialchars($eleve['annee_scolaire']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Statut :</strong>
                            <span><?php echo ucfirst($eleve['statut']); ?></span>
                        </div>
                    </div>

                    <!-- Informations parent -->
                    <div class="info-box">
                        <h5 class="mb-3">
                            <i class="bi bi-people me-2 text-warning"></i>
                            Parent / Tuteur
                        </h5>

                        <div class="info-row">
                            <strong>Nom du parent :</strong>
                            <span><?php echo htmlspecialchars($eleve['nom_parent'] ?: '-'); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Téléphone :</strong>
                            <span><?php echo htmlspecialchars($eleve['telephone_parent'] ?: '-'); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Profession :</strong>
                            <span><?php echo htmlspecialchars($eleve['profession_parent'] ?: '-'); ?></span>
                        </div>
                    </div>

                    <!-- Dernières notes -->
                    <?php if (!empty($dernieresNotes)): ?>
                    <div class="info-box">
                        <h5 class="mb-3">
                            <i class="bi bi-clipboard-data me-2 text-danger"></i>
                            Dernières notes
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Matière</th>
                                        <th>Note</th>
                                        <th>Période</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dernieresNotes as $note): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($note['nom_matiere']); ?></td>
                                        <td>
                                            <strong class="<?php echo $note['note'] >= 10 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo number_format($note['note'], 2); ?>/20
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo strtoupper(str_replace('_', ' ', $note['periode'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            -
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="notes.php?eleve_id=<?php echo $eleveId; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Voir toutes les notes
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Actions rapides -->
                    <div class="mt-4">
                        <div class="d-flex gap-2">
                            <a href="bulletins.php?eleve_id=<?php echo $eleveId; ?>&classe_id=<?php echo $eleve['classe_id']; ?>" 
                               class="btn btn-primary flex-fill">
                                <i class="bi bi-file-earmark-text"></i> Voir les bulletins
                            </a>
                            <a href="notes.php?eleve_id=<?php echo $eleveId; ?>" 
                               class="btn btn-success flex-fill">
                                <i class="bi bi-plus-circle"></i> Ajouter une note
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