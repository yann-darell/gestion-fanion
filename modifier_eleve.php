<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

if (!isset($_GET['id'])) die("ID manquant");

$eleveId = intval($_GET['id']);
$db = getDB();

// Récupérer l'élève
$stmt = $db->prepare("SELECT * FROM eleves WHERE id = ?");
$stmt->execute([$eleveId]);
$eleve = $stmt->fetch();

if (!$eleve) die("Élève introuvable");

// Classes disponibles
$classes = $db->query("SELECT id, nom_classe FROM classes ORDER BY nom_classe")->fetchAll();

// Photo - chercher dans assets/images/photos
$photoPath = null;
if (!empty($eleve['photo']) && file_exists(__DIR__ . '/' . $eleve['photo'])) {
    $photoPath = $eleve['photo'];
} else {
    // Essayer de trouver par matricule
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $p = 'assets/images/photos/' . $eleve['matricule'] . '.' . $ext;
        if (file_exists(__DIR__ . '/' . $p)) {
            $photoPath = $p;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Modifier élève - <?php echo APP_NAME; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<?php include 'includes/styles.php'; ?>
</head>

<body>
<?php include 'includes/header.php'; ?>

<div class="container-fluid">
<div class="row">
<?php include 'includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h2><i class="bi bi-pencil me-2"></i>Modifier l'élève</h2>
        <a href="eleves.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
    </div>


    <div class="card shadow-sm">
        <div class="card-body">

            <form method="POST" action="eleves.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="modifier">
            <input type="hidden" name="eleve_id" value="<?php echo $eleveId; ?>">

            <div class="row g-3">

                <div class="col-md-3 text-center">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" class="img-thumbnail mb-3" width="160" style="height:200px; object-fit:cover;">
                    <?php else: ?>
                        <div class="border rounded p-5 mb-3 text-muted">
                            <i class="bi bi-person-circle" style="font-size: 100px;"></i>
                            <p class="small">Pas de photo</p>
                        </div>
                    <?php endif; ?>
                    
                    <label class="form-label">Changer la photo</label>
                    <input type="file" class="form-control" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif">
                    <small class="text-muted d-block mt-1">Enregistrée dans: assets/images/photos/</small>
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Matricule *</label>
                            <input type="text" class="form-control" name="matricule"
                                   value="<?php echo htmlspecialchars($eleve['matricule']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo htmlspecialchars($eleve['nom']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Prénom *</label>
                            <input type="text" class="form-control" name="prenom"
                                   value="<?php echo htmlspecialchars($eleve['prenom']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Sexe *</label>
                            <select class="form-select" name="sexe" required>
                                <option value="M" <?php if($eleve['sexe']=='M') echo 'selected'; ?>>Masculin</option>
                                <option value="F" <?php if($eleve['sexe']=='F') echo 'selected'; ?>>Féminin</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date naissance *</label>
                            <input type="date" class="form-control" name="date_naissance"
                                   value="<?php echo $eleve['date_naissance']; ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Lieu naissance</label>
                            <input type="text" class="form-control" name="lieu_naissance"
                                   value="<?php echo htmlspecialchars($eleve['lieu_naissance']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Classe</label>
                            <select class="form-select" name="classe_id">
                                <option value="">Aucune</option>
                                <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"
                                   <?php if ($eleve['classe_id']==$c['id']) echo 'selected'; ?>>
                                   <?php echo htmlspecialchars($c['nom_classe']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date inscription *</label>
                            <input type="date" class="form-control" name="date_inscription"
                                   value="<?php echo $eleve['date_inscription']; ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="adresse" rows="2"><?php echo htmlspecialchars($eleve['adresse']); ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Téléphone élève</label>
                            <input type="text" class="form-control" name="telephone"
                                   value="<?php echo htmlspecialchars($eleve['telephone']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?php echo htmlspecialchars($eleve['email']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nom parent</label>
                            <input type="text" class="form-control" name="nom_parent"
                                   value="<?php echo htmlspecialchars($eleve['nom_parent']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Téléphone parent *</label>
                            <input type="text" class="form-control" name="telephone_parent"
                                   value="<?php echo htmlspecialchars($eleve['telephone_parent']); ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Profession parent</label>
                            <input type="text" class="form-control" name="profession_parent"
                                   value="<?php echo htmlspecialchars($eleve['profession_parent']); ?>">
                        </div>
                    </div>
                </div>

            </div>

            <div class="text-end mt-4">
                <a href="eleves.php" class="btn btn-secondary me-2">
                    <i class="bi bi-x-circle"></i> Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Enregistrer les modifications
                </button>
            </div>

            </form>

        </div>
    </div>

</main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>