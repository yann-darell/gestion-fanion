<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

// Récupération des paramètres
$classeId = $_GET['classe_id'] ?? '';
$periode = $_GET['periode'] ?? 'sequence1';

try {
    $db = getDB();
    
    // Récupérer toutes les classes
    $stmt = $db->query("SELECT * FROM classes WHERE annee_scolaire = '$anneeScolaire' ORDER BY nom_classe");
    $classes = $stmt->fetchAll();
    
    // Si une classe est sélectionnée, récupérer le bordereau
    $bordereau = [];
    $matieres = [];
    $classeInfo = null;
    
    if ($classeId) {
        // Info de la classe
        $stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$classeId]);
        $classeInfo = $stmt->fetch();
        
        // Récupérer les matières groupées
        $groupCols = ['groupe', 'group_label', 'group_no', 'group_name'];
        $foundGroupCol = null;
        foreach ($groupCols as $col) {
            $check = $db->query("SHOW COLUMNS FROM classe_matiere LIKE " . $db->quote($col))->fetch();
            if ($check) { $foundGroupCol = $col; break; }
        }
        
        if ($foundGroupCol) {
            $stmt = $db->prepare("
                SELECT m.*, cm.coefficient, cm.$foundGroupCol as groupe
                FROM matieres m
                INNER JOIN classe_matiere cm ON m.id = cm.matiere_id
                WHERE cm.classe_id = ?
                ORDER BY cm.$foundGroupCol, cm.ordre ASC, m.nom_matiere
            ");
        } else {
            $stmt = $db->prepare("
                SELECT m.*, cm.coefficient, 'GROUPE I' as groupe
                FROM matieres m
                INNER JOIN classe_matiere cm ON m.id = cm.matiere_id
                WHERE cm.classe_id = ?
                ORDER BY m.nom_matiere
            ");
        }
        $stmt->execute([$classeId]);
        $matieres = $stmt->fetchAll();
        
        // Récupérer les moyennes avec rang
        $stmt = $db->prepare("
            SELECT 
                m.*,
                CONCAT(e.nom, ' ', e.prenom) as eleve_nom,
                e.matricule,
                e.sexe
            FROM moyennes m
            INNER JOIN eleves e ON m.eleve_id = e.id
            WHERE m.classe_id = ? AND m.periode = ? AND m.annee_scolaire = ?
            ORDER BY m.rang ASC
        ");
        $stmt->execute([$classeId, $periode, $anneeScolaire]);
        $moyennesClassees = $stmt->fetchAll();
        
        // Pour chaque élève, récupérer ses notes par matière
        foreach ($moyennesClassees as &$moyenne) {
            $stmt = $db->prepare("
                SELECT n.note, n.matiere_id
                FROM notes n
                WHERE n.eleve_id = ? AND n.classe_id = ? AND n.periode = ? AND n.annee_scolaire = ?
            ");
            $stmt->execute([$moyenne['eleve_id'], $classeId, $periode, $anneeScolaire]);
            $notes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $moyenne['notes'] = $notes;
        }
        
        $bordereau = $moyennesClassees;
        
        // Calculer la moyenne générale de la classe
        if (!empty($bordereau)) {
            $sommeMoyennes = array_sum(array_column($bordereau, 'moyenne_generale'));
            $moyenneClasse = $sommeMoyennes / count($bordereau);
            
            // Calculer moyennes par matière
            $moyennesParMatiere = [];
            foreach ($matieres as $matiere) {
                $sommeNotes = 0;
                $compteur = 0;
                foreach ($bordereau as $ligne) {
                    if (isset($ligne['notes'][$matiere['id']]) && is_numeric($ligne['notes'][$matiere['id']])) {
                        $sommeNotes += $ligne['notes'][$matiere['id']];
                        $compteur++;
                    }
                }
                $moyennesParMatiere[$matiere['id']] = $compteur > 0 ? $sommeNotes / $compteur : 0;
            }
            
            // Meilleure et pire moyenne
            $moyennes = array_column($bordereau, 'moyenne_generale');
            $moyennePremier = max($moyennes);
            $moyenneDernier = min($moyennes);
        }
    }
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

// Traduction période
function getPeriodeLabel($periode) {
    $labels = [
        'sequence1' => "SÉQUENCE N°1",
        'sequence2' => "SÉQUENCE N°2", 
        'trimestre1' => "TRIMESTRE N°1",
        'sequence3' => "SÉQUENCE N°3",
        'sequence4' => "SÉQUENCE N°4",
        'trimestre2' => "TRIMESTRE N°2",
        'sequence5' => "SÉQUENCE N°5",
        'sequence6' => "SÉQUENCE N°6",
        'trimestre3' => "TRIMESTRE N°3"
    ];
    return $labels[$periode] ?? strtoupper($periode);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bordereau - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        /* .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; margin-bottom: 0.25rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; } */
        main { padding-top: 70px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        
        /* STYLES IMPRESSION */
        @media print {
            @page { 
                size: A4 landscape; 
                margin: 0.5cm;
            }
            .no-print { display: none !important; }
            body { 
                background: white; 
                margin: 0;
                padding: 0;
            }
            .sidebar { display: none !important; }
            main { 
                padding-top: 0; 
                margin-left: 0 !important; 
                width: 100% !important;
                max-width: 100% !important;
            }
            .card { 
                box-shadow: none; 
                border: none;
                margin: 0;
                padding: 0;
            }
            .card-body {
                padding: 0.3cm !important;
            }
            
            .bordereau-header {
                margin-bottom: 0.3cm;
            }
            
            .bordereau-table {
                font-size: 8px !important;
                width: 100%;
            }
            
            .bordereau-table th,
            .bordereau-table td {
                padding: 2px 1px !important;
                line-height: 1.1 !important;
            }
            
            .logo-header {
                width: 60px !important;
                height: 60px !important;
            }
        }
        
        /* STYLES TABLEAU */
        .bordereau-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 2px solid #000;
        }
        
        .header-left, .header-right {
            font-size: 9px;
            line-height: 1.3;
            text-align: center;
            flex: 1;
        }
        
        .header-center {
            flex: 0 0 100px;
            text-align: center;
        }
        
        .logo-header {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        .bordereau-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
            text-decoration: underline;
        }
        
        .classe-info {
            text-align: center;
            font-size: 11px;
            margin-bottom: 15px;
        }
        
        .bordereau-table {
            font-size: 9px;
            width: 100%;
            border-collapse: collapse;
        }
        
        .bordereau-table th {
            background: #000080;
            color: white;
            font-weight: bold;
            padding: 4px 2px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000;
            line-height: 1.2;
        }
        
        .bordereau-table td {
            padding: 3px 2px;
            text-align: center;
            border: 1px solid #000;
            vertical-align: middle;
        }
        
        .rang-col { width: 30px; background: #fff3cd; font-weight: bold; }
        .nom-col { width: 150px; text-align: left !important; font-weight: bold; }
        .sexe-col { width: 25px; }
        .matiere-col { width: 35px; }
        .moyenne-col { width: 50px; background: #d1ecf1; font-weight: bold; }
        .appreciation-col { width: 80px; font-size: 8px; }
        
        .rang-1 { background: #ffd700 !important; }
        .rang-2 { background: #c0c0c0 !important; }
        .rang-3 { background: #cd7f32 !important; }
        
        .note-rouge { color: #dc3545; font-weight: bold; }
        .note-verte { color: #198754; font-weight: bold; }
        
        .moyenne-row {
            background: #f8f9fa;
            font-weight: bold;
            font-style: italic;
        }
        
        .footer-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 10px;
        }
        
        .footer-signatures div {
            text-align: center;
            flex: 1;
        }
    </style>
</head>
<body>
   <?php include "includes/header.php"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include "includes/sidebar.php"; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2">
                        <i class="bi bi-table me-2"></i>
                        Bordereaux de notes
                    </h1>
                    <?php if (!empty($bordereau)): ?>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>
                            Imprimer
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Filtres -->
                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form method="GET" action="bordereaux.php">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Classe *</label>
                                    <select class="form-select" name="classe_id" required onchange="this.form.submit()">
                                        <option value="">Sélectionner une classe...</option>
                                        <?php foreach ($classes as $classe): ?>
                                            <option value="<?php echo $classe['id']; ?>" 
                                                    <?php echo ($classeId == $classe['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Période *</label>
                                    <select class="form-select" name="periode" required onchange="this.form.submit()">
                                        <option value="sequence1" <?php echo ($periode == 'sequence1') ? 'selected' : ''; ?>>Séquence 1</option>
                                        <option value="sequence2" <?php echo ($periode == 'sequence2') ? 'selected' : ''; ?>>Séquence 2</option>
                                        <option value="trimestre1" <?php echo ($periode == 'trimestre1') ? 'selected' : ''; ?>>Trimestre 1</option>
                                        <option value="sequence3" <?php echo ($periode == 'sequence3') ? 'selected' : ''; ?>>Séquence 3</option>
                                        <option value="sequence4" <?php echo ($periode == 'sequence4') ? 'selected' : ''; ?>>Séquence 4</option>
                                        <option value="trimestre2" <?php echo ($periode == 'trimestre2') ? 'selected' : ''; ?>>Trimestre 2</option>
                                        <option value="sequence5" <?php echo ($periode == 'sequence5') ? 'selected' : ''; ?>>Séquence 5</option>
                                        <option value="sequence6" <?php echo ($periode == 'sequence6') ? 'selected' : ''; ?>>Séquence 6</option>
                                        <option value="trimestre3" <?php echo ($periode == 'trimestre3') ? 'selected' : ''; ?>>Trimestre 3</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($bordereau)): ?>
                    <!-- Bordereau -->
                    <div class="card">
                        <div class="card-body">
                            <!-- En-tête officiel -->
                            <div class="bordereau-header">
                                <div class="header-left">
                                    <strong>REPUBLIQUE DU CAMEROUN</strong><br>
                                    <em>Paix - Travail - Patrie</em><br>
                                    ___________<br>
                                    <strong>MINISTERE DES ENSEIGNEMENTS SECONDAIRES</strong><br>
                                    Collège Privé Laïc<br>
                                    <strong>LE FANION</strong><br>
                                    BP 7052 Yaoundé<br>
                                    TEL: +237 675 26 / 696 84 07 22
                                </div>
                                
                                <div class="header-center">
                                    <img src="assets/images/logo.jpg" alt="Logo" class="logo-header" onerror="this.style.display='none'">
                                </div>
                                
                                <div class="header-right">
                                    <strong>REPUBLIC OF CAMEROON</strong><br>
                                    <em>Peace - Work - Fatherland</em><br>
                                    ___________<br>
                                    <strong>MINISTRY OF SECONDARY EDUCATION</strong><br>
                                    Private Institute<br>
                                    <strong>LE FANION</strong><br>
                                    PO BOX 7052 Yaoundé<br>
                                    TEL: +237 675 26 / 696 84 07 22
                                </div>
                            </div>
                            
                            <!-- Titre -->
                            <div class="bordereau-title">
                                BORDEREAUX DE NOTES DE L'EVALUATION <?php echo getPeriodeLabel($periode); ?> DE LA CLASSE DE <?php echo strtoupper($classeInfo['nom_classe']); ?>
                            </div>
                            
                            <!-- Info classe -->
                            <div class="classe-info">
                                <strong>Année Scolaire:</strong> <?php echo $anneeScolaire; ?> | 
                                <strong>Effectif:</strong> <?php echo count($bordereau); ?> élèves
                            </div>
                            
                            <!-- Tableau -->
                            <div class="table-responsive">
                                <table class="bordereau-table">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="rang-col">Rang</th>
                                            <th rowspan="2" class="nom-col">Noms et<br>Prénoms</th>
                                            <th rowspan="2" class="sexe-col">Sexe</th>
                                            <th rowspan="2" style="width:60px;">Matricule</th>
                                            
                                            <?php 
                                            // Grouper les matières
                                            $groupes = [];
                                            foreach ($matieres as $m) {
                                                $g = $m['groupe'] ?? 'GROUPE I';
                                                $groupes[$g][] = $m;
                                            }
                                            
                                            foreach ($groupes as $groupe => $matieresGroupe): 
                                                $colspan = count($matieresGroupe);
                                            ?>
                                                <th colspan="<?php echo $colspan; ?>" style="background:#4169E1;">
                                                    <?php echo htmlspecialchars($groupe); ?>
                                                </th>
                                            <?php endforeach; ?>
                                            
                                            <th rowspan="2" class="moyenne-col">Math</th>
                                            <th rowspan="2" class="moyenne-col">SVT</th>
                                            <th rowspan="2" class="moyenne-col">ECM</th>
                                            <th rowspan="2" class="moyenne-col">Géo</th>
                                            <th rowspan="2" class="moyenne-col">Hist</th>
                                            <th rowspan="2" class="moyenne-col">EAC</th>
                                            <th rowspan="2" class="moyenne-col">EPS</th>
                                            <th rowspan="2" class="moyenne-col">ESF</th>
                                            <th rowspan="2" class="moyenne-col">ICN</th>
                                            <th rowspan="2" class="moyenne-col">TM</th>
                                            <th rowspan="2" class="moyenne-col">MOY</th>
                                            <th rowspan="2" class="appreciation-col">Appréciation</th>
                                        </tr>
                                        <tr>
                                            <?php foreach ($matieres as $matiere): ?>
                                                <th class="matiere-col" title="<?php echo htmlspecialchars($matiere['nom_matiere']); ?>">
                                                    <?php 
                                                    $code = $matiere['code_matiere'] ?? substr($matiere['nom_matiere'], 0, 3);
                                                    echo htmlspecialchars(strtoupper($code)); 
                                                    ?>
                                                    <br><small>(<?php echo $matiere['coefficient']; ?>)</small>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bordereau as $index => $ligne): 
                                            $rangClass = '';
                                            if ($ligne['rang'] == 1) $rangClass = 'rang-1';
                                            elseif ($ligne['rang'] == 2) $rangClass = 'rang-2';
                                            elseif ($ligne['rang'] == 3) $rangClass = 'rang-3';
                                        ?>
                                            <tr class="<?php echo $rangClass; ?>">
                                                <td class="rang-col"><?php echo $ligne['rang']; ?></td>
                                                <td class="nom-col">
                                                    <?php echo strtoupper(htmlspecialchars($ligne['eleve_nom'])); ?>
                                                </td>
                                                <td class="sexe-col">
                                                    <?php echo strtoupper(substr($ligne['sexe'] ?? 'M', 0, 1)); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($ligne['matricule']); ?></td>
                                                
                                                <?php foreach ($matieres as $matiere): 
                                                    $note = $ligne['notes'][$matiere['id']] ?? null;
                                                    $noteClass = '';
                                                    if (is_numeric($note)) {
                                                        if ($note < 10) $noteClass = 'note-rouge';
                                                        elseif ($note >= 15) $noteClass = 'note-verte';
                                                    }
                                                ?>
                                                    <td class="<?php echo $noteClass; ?>">
                                                        <?php 
                                                        echo is_numeric($note) ? number_format($note, 2) : '-';
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                
                                                <!-- Colonnes supplémentaires vides pour correspondre au format -->
                                                <td>-</td><td>-</td><td>-</td><td>-</td>
                                                <td>-</td><td>-</td><td>-</td><td>-</td>
                                                <td>-</td><td>-</td>
                                                
                                                <td class="moyenne-col">
                                                    <?php echo number_format($ligne['moyenne_generale'], 2); ?>
                                                </td>
                                                <td class="appreciation-col">
                                                    <?php echo htmlspecialchars($ligne['mention'] ?? '-'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <!-- Ligne des moyennes -->
                                        <tr class="moyenne-row">
                                            <td colspan="4" style="text-align:right; padding-right:10px;">
                                                <strong>Moyenne générale</strong>
                                            </td>
                                            <?php foreach ($matieres as $matiere): ?>
                                                <td>
                                                    <?php 
                                                    echo number_format($moyennesParMatiere[$matiere['id']] ?? 0, 2);
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td colspan="10">-</td>
                                            <td class="moyenne-col">
                                                <?php echo number_format($moyenneClasse, 2); ?>
                                            </td>
                                            <td>-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pied de page -->
                            <div class="footer-signatures">
                                <div>
                                    <strong>LA DIRECTION DES ETUDES</strong>
                                </div>
                                <div>
                                    <strong>LE PRINCIPAL</strong>
                                </div>
                            </div>
                            
                            <!-- Info impression (non imprimée) -->
                            <div class="mt-3 no-print text-end">
                                <small class="text-muted">
                                    Édité le <?php echo date('d/m/Y à H:i'); ?> par <?php echo htmlspecialchars($user['nom_complet']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php elseif ($classeId): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mb-0">Aucune note disponible pour cette période</p>
                            <small class="text-muted">Assurez-vous d'avoir saisi les notes dans le module "Notes"</small>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-arrow-up-circle fs-1 text-muted"></i>
                            <p class="text-muted mb-0">Sélectionnez une classe et une période pour afficher le bordereau</p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>