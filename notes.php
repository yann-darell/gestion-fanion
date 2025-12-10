<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date("Y") . "-" . (date("Y") + 1));

// ------------------------------------------------------------
// SAUVEGARDE DES NOTES
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "save_notes") {

    $classeId = $_POST["classe_id"];
    $eleveId  = $_POST["eleve_id"];
    $periode  = $_POST["periode"];
    $notes    = $_POST["notes"] ?? [];

    try {
        $db = getDB();
        $db->beginTransaction();

        foreach ($notes as $matiereId => $val) {
            $val = trim($val);
            if ($val === "") continue;

            $note = floatval($val);
            if ($note < 0 || $note > 20) {
                throw new Exception("Note invalide : $note");
            }

            $stmt = $db->prepare("
                INSERT INTO notes (eleve_id, matiere_id, classe_id, periode, annee_scolaire, note, saisi_par)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    note = VALUES(note),
                    date_saisie = NOW(),
                    saisi_par = VALUES(saisi_par)
            ");

            $stmt->execute([
                $eleveId,
                $matiereId,
                $classeId,
                $periode,
                $anneeScolaire,
                $note,
                $user["id"]
            ]);
        }

        $db->commit();
        $_SESSION["success_message"] = "Notes enregistrées avec succès !";

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION["error_message"] = "Erreur : " . $e->getMessage();
    }

    header("Location: notes.php?classe_id=$classeId&eleve_id=$eleveId&period=$periode");
    exit;
}

// ------------------------------------------------------------
// CHARGEMENT DES DONNÉES
// ------------------------------------------------------------
$classeId = $_GET["classe_id"] ?? "";
$eleveId  = $_GET["eleve_id"] ?? "";
$periode  = $_GET["periode"] ?? "sequence1";

$db = getDB();

$classes = $db->query("SELECT * FROM classes ORDER BY nom_classe")->fetchAll();

$eleves = [];
if ($classeId) {
    $stmt = $db->prepare("SELECT * FROM eleves WHERE classe_id = ? ORDER BY nom, prenom");
    $stmt->execute([$classeId]);
    $eleves = $stmt->fetchAll();
}

$matieres = [];
if ($classeId) {
    $stmt = $db->prepare("
        SELECT m.*, cm.coefficient 
        FROM matieres m
        INNER JOIN classe_matiere cm ON cm.matiere_id = m.id
        WHERE cm.classe_id = ?
        ORDER BY m.nom_matiere
    ");
    $stmt->execute([$classeId]);
    $matieres = $stmt->fetchAll();
}

// Notes déjà enregistrées
$notesMap = [];
if ($classeId && $eleveId) {
    $stmt = $db->prepare("
        SELECT matiere_id, note 
        FROM notes 
        WHERE eleve_id = ? AND classe_id = ? AND periode = ? AND annee_scolaire = ?
    ");
    $stmt->execute([$eleveId, $classeId, $periode, $anneeScolaire]);
    foreach ($stmt->fetchAll() as $n) {
        $notesMap[$n["matiere_id"]] = $n["note"];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisie des notes</title>

    <!-- TON STYLE ORIGINEL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        /* .sidebar { position: fixed; top: 0; bottom: 0; left: 0; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; } */
        main { padding-top: 70px; }

        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }

        .note-input { width: 80px; text-align:center; font-weight:bold; }
        .note-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.2); }

        .table-notes th { position: sticky; top: 0; background: white; z-index: 10; }
    </style>
</head>

<body>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">

        <?php include 'includes/sidebar.php'; ?>


        

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <h1 class="mb-4"><i class="bi bi-journal-text me-2"></i>Saisie des notes</h1>

            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <!-- FILTRES -->
            <div class="card mb-4">
                <div class="card-body">

                    <form method="GET" class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Classe</label>
                            <select name="classe_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($classeId == $c['id']) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($c['nom_classe']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Élève</label>
                            <select name="eleve_id" class="form-select" <?= empty($eleves) ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= ($eleveId == $e['id']) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($e['nom'] . " " . $e['prenom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Période</label>
                            <select name="periode" class="form-select" onchange="this.form.submit()">
                                <?php
                                $periodes = [
                                    "sequence1"=>"Séquence 1", "sequence2"=>"Séquence 2",
                                    "trimestre1"=>"Trimestre 1", "sequence3"=>"Séquence 3",
                                    "sequence4"=>"Séquence 4", "trimestre2"=>"Trimestre 2",
                                    "sequence5"=>"Séquence 5", "sequence6"=>"Séquence 6",
                                    "trimestre3"=>"Trimestre 3"
                                ];
                                foreach ($periodes as $key => $label):
                                ?>
                                    <option value="<?= $key ?>" <?= $periode == $key ? "selected" : "" ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </form>

                </div>
            </div>

            <!-- TABLEAU DE SAISIE -->
            <?php if ($classeId && $eleveId): ?>

                <form method="POST">
                    <input type="hidden" name="action" value="save_notes">
                    <input type="hidden" name="classe_id" value="<?= $classeId ?>">
                    <input type="hidden" name="eleve_id" value="<?= $eleveId ?>">
                    <input type="hidden" name="periode" value="<?= $periode ?>">

                    <div class="card">
                        <div class="card-header bg-white">
                            <strong>Notes de l’élève</strong>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 600px;">
                                <table class="table table-striped table-notes mb-0">
                                    <thead>
                                        <tr>
                                            <th>Matière</th>
                                            <th>Coef</th>
                                            <th style="width:150px;">Note</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($matieres as $m): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($m["nom_matiere"]) ?></td>
                                            <td><?= $m["coefficient"] ?></td>
                                            <td>
                                                <input type="number"
                                                       class="form-control note-input"
                                                       name="notes[<?= $m['id'] ?>]"
                                                       min="0" max="20" step="0.01"
                                                       value="<?= $notesMap[$m['id']] ?? '' ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>

                                </table>
                            </div>
                        </div>

                        <div class="card-footer bg-white text-end">
                            <button class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-2"></i>Enregistrer
                            </button>
                        </div>

                    </div>

                </form>

            <?php endif; ?>

        </main>
    </div>
</div>

</body>
</html>
