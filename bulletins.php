<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

// Récupération des paramètres
$classeId = $_GET['classe_id'] ?? '';
$eleveId = $_GET['eleve_id'] ?? '';
$periode = $_GET['periode'] ?? 'trimestre1';

try {
    $db = getDB();
    
    // Récupérer toutes les classes
    $stmt = $db->query("SELECT * FROM classes WHERE annee_scolaire = '$anneeScolaire' ORDER BY nom_classe");
    $classes = $stmt->fetchAll();
    
    // Récupérer les élèves de la classe sélectionnée
    $eleves = [];
    if ($classeId) {
        $stmt = $db->prepare("
            SELECT e.*, c.nom_classe 
            FROM eleves e 
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE e.classe_id = ? AND e.statut = 'actif' 
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$classeId]);
        $eleves = $stmt->fetchAll();
    }
    
    // Si un élève est sélectionné, générer son bulletin
    $bulletin = null;
    if ($eleveId && $classeId) {
        // Info élève
        $stmt = $db->prepare("
            SELECT e.*, c.nom_classe, c.niveau
            FROM eleves e
            INNER JOIN classes c ON e.classe_id = c.id
            WHERE e.id = ?
        ");
        $stmt->execute([$eleveId]);
        $eleveInfo = $stmt->fetch();
        
        if ($eleveInfo) {
            // Vérifier colonnes optionnelles
            $hasCompetences = $db->query("SHOW COLUMNS FROM matieres LIKE 'competences'")->fetch();
            $competencesCol = $hasCompetences ? 'm.competences' : "'' AS competences";
            
            // Vérifier groupes
            $groupCols = ['groupe', 'group_label', 'group_no', 'group_name'];
            $foundGroupCol = null;
            foreach ($groupCols as $col) {
                $check = $db->query("SHOW COLUMNS FROM classe_matiere LIKE " . $db->quote($col))->fetch();
                if ($check) { $foundGroupCol = $col; break; }
            }
            
            $groupeSelect = $foundGroupCol ? "cm.$foundGroupCol" : "'GROUPE I'";
            
            // Récupérer les notes groupées
            $stmt = $db->prepare("
                SELECT 
                    m.nom_matiere,
                    m.code_matiere,
                    $competencesCol,
                    cm.coefficient,
                    $groupeSelect as groupe,
                    n.note,
                    (n.note * cm.coefficient) as points
                FROM notes n
                INNER JOIN matieres m ON n.matiere_id = m.id
                INNER JOIN classe_matiere cm ON n.matiere_id = cm.matiere_id AND n.classe_id = cm.classe_id
                WHERE n.eleve_id = ? AND n.classe_id = ? AND n.periode = ? AND n.annee_scolaire = ?
                ORDER BY $groupeSelect, m.nom_matiere
            ");
            $stmt->execute([$eleveId, $classeId, $periode, $anneeScolaire]);
            $notes = $stmt->fetchAll();
            
            // Regrouper les notes
            $notesGroupees = [];
            foreach ($notes as $note) {
                $grp = trim($note['groupe'] ?? 'GROUPE I');
                if (empty($grp)) $grp = 'GROUPE I';
                $notesGroupees[$grp][] = $note;
            }
            
            // Récupérer la moyenne et le rang
            $stmt = $db->prepare("
                SELECT * FROM moyennes 
                WHERE eleve_id = ? AND classe_id = ? AND periode = ? AND annee_scolaire = ?
            ");
            $stmt->execute([$eleveId, $classeId, $periode, $anneeScolaire]);
            $moyenne = $stmt->fetch();
            
            // Récupérer l'effectif de la classe
            $stmt = $db->prepare("
                SELECT COUNT(*) as effectif 
                FROM eleves 
                WHERE classe_id = ? AND statut = 'actif'
            ");
            $stmt->execute([$classeId]);
            $effectif = $stmt->fetch()['effectif'];
            
            $bulletin = [
                'eleve' => $eleveInfo,
                'notes' => $notes,
                'notesGroupees' => $notesGroupees,
                'moyenne' => $moyenne,
                'effectif' => $effectif
            ];
        }
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
    <title>Bulletins scolaires - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f3f4f6; }
        .navbar { background: linear-gradient(135deg, #2563eb, #1e40af); }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 70px 0 0; background: white; }
        .sidebar .nav-link { color: #6b7280; padding: 0.75rem 1rem; margin-bottom: 0.25rem; border-radius: 10px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: #2563eb; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; }
        main { padding-top: 70px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        
        @media print {
            @page { size: A4 portrait; margin: 1cm; }
            .no-print { display: none !important; }
            body { background: white; margin: 0; padding: 0; }
            .sidebar { display: none !important; }
            main { padding-top: 0; margin-left: 0 !important; width: 100% !important; }
            .bulletin-container { 
                border: none !important; 
                box-shadow: none !important; 
                padding: 0 !important;
                max-width: 100% !important;
                page-break-after: always;
            }
        }
        
        .bulletin-container {
            background: white;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* En-tête officiel */
        .bulletin-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1F4F88;
        }
        
        .header-left, .header-right {
            font-size: 9px;
            line-height: 1.4;
            text-align: center;
            flex: 1;
        }
        
        .header-center {
            flex: 0 0 100px;
            text-align: center;
            padding: 0 10px;
        }
        
        .logo-bulletin {
            width: 90px;
            height: 90px;
            object-fit: contain;
        }
        
        .photo-eleve {
            width: 90px;
            height: 110px;
            object-fit: cover;
            border: 2px solid #1F4F88;
            position: absolute;
            top: 10px;
            right: 20px;
        }
        
        .bulletin-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin: 15px 0;
            color: #1F4F88;
        }
        
        /* Informations élève */
        .info-eleve {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 20px 0;
        }
        
        .info-row {
            display: flex;
            padding: 5px 0;
            font-size: 11px;
        }
        
        .info-row strong {
            min-width: 150px;
        }
        
        /* Tableaux par groupe */
        .groupe-section {
            margin: 20px 0;
        }
        
        .groupe-title {
            background: #1F4F88;
            color: white;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .bulletin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 15px;
        }
        
        .bulletin-table th {
            background: #1F4F88;
            color: white;
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #1F4F88;
        }
        
        .bulletin-table td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .matiere-cell {
            text-align: left !important;
            padding-left: 10px !important;
        }
        
        .total-row {
            background: #e8f4f8;
            font-weight: bold;
        }
        
        .moyenne-row {
            background: #f0f0f0;
            font-style: italic;
        }
        
        /* Résumé général */
        .resume-general {
            border: 2px solid #1F4F88;
            padding: 15px;
            margin: 20px 0;
        }
        
        .resume-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12px;
        }
        
        .resume-row.highlight {
            font-size: 14px;
            font-weight: bold;
            color: #1F4F88;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        /* Observations */
        .observations-section {
            border: 1px solid #ddd;
            margin: 20px 0;
        }
        
        .obs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .obs-table td {
            border: 1px solid #ddd;
            padding: 10px;
            vertical-align: top;
            font-size: 10px;
        }
        
        .obs-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        /* Signatures */
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            padding-top: 20px;
        }
        
        .signature-box {
            text-align: center;
            font-size: 11px;
        }
        
        .signature-line {
            margin-top: 40px;
            padding-top: 5px;
            border-top: 1px solid #000;
        }
        
        .footer-note {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-dark fixed-top no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i>
                Collège Le Fanion
            </a>
            <div class="d-flex">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <?php echo htmlspecialchars($user['nom_complet']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <form method="POST" action="login.php">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse no-print">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column px-2">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="eleves.php">
                                <i class="bi bi-people-fill me-2"></i>Élèves
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notes.php">
                                <i class="bi bi-journal-text me-2"></i>Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bordereaux.php">
                                <i class="bi bi-table me-2"></i>Bordereaux
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bulletins.php">
                                <i class="bi bi-file-earmark-text me-2"></i>Bulletins
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2">
                        <i class="bi bi-file-earmark-text me-2"></i>
                        Bulletins scolaires
                    </h1>
                    <?php if ($bulletin): ?>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Imprimer
                            </button>
                            <a href="bulletins_word.php?classe_id=<?php echo $classeId; ?>&eleve_id=<?php echo $eleveId; ?>&periode=<?php echo $periode; ?>" 
                               class="btn btn-success">
                                <i class="bi bi-file-earmark-word"></i> Exporter Word
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Filtres -->
                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form method="GET" action="bulletins.php">
                            <div class="row g-3">
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <label class="form-label">Élève *</label>
                                    <select class="form-select" name="eleve_id" required 
                                            <?php echo empty($eleves) ? 'disabled' : ''; ?>>
                                        <option value="">Sélectionner un élève...</option>
                                        <?php foreach ($eleves as $eleve): ?>
                                            <option value="<?php echo $eleve['id']; ?>" 
                                                    <?php echo ($eleveId == $eleve['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Période *</label>
                                    <select class="form-select" name="periode" required>
                                        <option value="trimestre1" <?php echo ($periode == 'trimestre1') ? 'selected' : ''; ?>>Trimestre 1</option>
                                        <option value="trimestre2" <?php echo ($periode == 'trimestre2') ? 'selected' : ''; ?>>Trimestre 2</option>
                                        <option value="trimestre3" <?php echo ($periode == 'trimestre3') ? 'selected' : ''; ?>>Trimestre 3</option>
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger no-print"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($bulletin): ?>
                    <!-- Bulletin -->
                    <div class="bulletin-container position-relative">
                        <!-- Photo élève (en haut à droite) -->
                        <?php 
                        $photoPath = null;
                        if (!empty($bulletin['eleve']['photo']) && file_exists(__DIR__ . '/' . $bulletin['eleve']['photo'])) {
                            $photoPath = $bulletin['eleve']['photo'];
                        } else {
                            $possible = [
                                "uploads/photos/{$bulletin['eleve']['matricule']}.jpg",
                                "uploads/photos/{$bulletin['eleve']['matricule']}.jpeg",
                                "uploads/photos/{$bulletin['eleve']['matricule']}.png"
                            ];
                            foreach ($possible as $p) {
                                if (file_exists(__DIR__ . '/' . $p)) {
                                    $photoPath = $p;
                                    break;
                                }
                            }
                        }
                        if ($photoPath): ?>
                            <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Photo" class="photo-eleve">
                        <?php endif; ?>
                        
                        <!-- En-tête officiel -->
                        <div class="bulletin-header">
                            <div class="header-left">
                                <strong>REPUBLIQUE DU CAMEROUN</strong><br>
                                <em>Paix - Travail - Patrie</em><br>
                                ___________<br>
                                <strong>MINISTERE DES ENSEIGNEMENTS SECONDAIRES</strong><br>
                                Collège Privé Laïc<br>
                                <strong>LE FANION</strong>
                            </div>
                            
                            <div class="header-center">
                                <img src="assets/images/logo.png" alt="Logo" class="logo-bulletin" onerror="this.style.display='none'">
                            </div>
                            
                            <div class="header-right">
                                <strong>REPUBLIC OF CAMEROON</strong><br>
                                <em>Peace - Work - Fatherland</em><br>
                                ___________<br>
                                <strong>MINISTRY OF SECONDARY EDUCATION</strong><br>
                                Private Institute<br>
                                <strong>LE FANION</strong>
                            </div>
                        </div>
                        
                        <!-- Titre -->
                        <div class="bulletin-title">
                            BULLETIN DE NOTES - <?php echo strtoupper(str_replace('_', ' ', $periode)); ?>
                        </div>
                        
                        <!-- Informations élève -->
                        <div class="info-eleve">
                            <div class="info-row">
                                <strong>Nom & Prénom :</strong>
                                <span><?php echo strtoupper($bulletin['eleve']['nom'] . ' ' . $bulletin['eleve']['prenom']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Matricule :</strong>
                                <span><?php echo htmlspecialchars($bulletin['eleve']['matricule']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Classe :</strong>
                                <span><?php echo htmlspecialchars($bulletin['eleve']['nom_classe']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Date de naissance :</strong>
                                <span><?php echo !empty($bulletin['eleve']['date_naissance']) ? date('d/m/Y', strtotime($bulletin['eleve']['date_naissance'])) : '-'; ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Effectif :</strong>
                                <span><?php echo $bulletin['effectif']; ?> élèves</span>
                            </div>
                        </div>
                        
                        <!-- Tableaux par groupe -->
                        <?php 
                        $grandCoef = 0;
                        $grandPoints = 0;
                        
                        foreach ($bulletin['notesGroupees'] as $groupe => $notesGroupe): 
                            $gCoef = 0;
                            $gPoints = 0;
                        ?>
                            <div class="groupe-section">
                                <div class="groupe-title"><?php echo strtoupper($groupe); ?></div>
                                
                                <table class="bulletin-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">MATIERES</th>
                                            <th style="width: 10%;">Coef</th>
                                            <th style="width: 10%;">Note</th>
                                            <th style="width: 10%;">Points</th>
                                            <th style="width: 20%;">Appréciation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notesGroupe as $note): 
                                            $noteVal = floatval($note['note']);
                                            $coefVal = floatval($note['coefficient']);
                                            $pointsVal = $noteVal * $coefVal;
                                            
                                            $gCoef += $coefVal;
                                            $gPoints += $pointsVal;
                                            
                                            // Appréciation
                                            if ($noteVal >= 16) $apr = "Excellent";
                                            elseif ($noteVal >= 14) $apr = "Très bien";
                                            elseif ($noteVal >= 12) $apr = "Bien";
                                            elseif ($noteVal >= 10) $apr = "Assez bien";
                                            else $apr = "Passable";
                                        ?>
                                            <tr>
                                                <td class="matiere-cell"><?php echo htmlspecialchars($note['nom_matiere']); ?></td>
                                                <td><?php echo number_format($coefVal, 0); ?></td>
                                                <td><strong><?php echo number_format($noteVal, 2); ?></strong></td>
                                                <td><?php echo number_format($pointsVal, 2); ?></td>
                                                <td><?php echo $apr; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <!-- Total du groupe -->
                                        <tr class="total-row">
                                            <td class="matiere-cell">TOTAL <?php echo strtoupper($groupe); ?></td>
                                            <td><?php echo number_format($gCoef, 0); ?></td>
                                            <td>-</td>
                                            <td><?php echo number_format($gPoints, 2); ?></td>
                                            <td>-</td>
                                        </tr>
                                        
                                        <!-- Moyenne du groupe -->
                                        <tr class="moyenne-row">
                                            <td class="matiere-cell">Moyenne du <?php echo $groupe; ?></td>
                                            <td>-</td>
                                            <td><strong><?php echo $gCoef > 0 ? number_format($gPoints / $gCoef, 2) : '0.00'; ?></strong></td>
                                            <td>-</td>
                                            <td>-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php 
                            $grandCoef += $gCoef;
                            $grandPoints += $gPoints;
                        endforeach; 
                        ?>
                        
                        <!-- Résumé général -->
                        <div class="resume-general">
                            <div class="resume-row">
                                <strong>Total Général</strong>
                                <span><?php echo number_format($grandPoints, 2); ?></span>
                            </div>
                            <div class="resume-row highlight">
                                <strong>Moyenne Générale</strong>
                                <span><?php echo $grandCoef > 0 ? number_format($grandPoints / $grandCoef, 2) : '0.00'; ?> / 20</span>
                            </div>
                            <?php if ($bulletin['moyenne']): ?>
                                <div class="resume-row">
                                    <strong>Rang</strong>
                                    <span><?php echo $bulletin['moyenne']['rang']; ?> / <?php echo $bulletin['effectif']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Observations et Discipline -->
                        <div class="observations-section">
                            <div class="obs-title" style="background: #1F4F88; color: white; padding: 5px 10px;">
                                DECISIONS ET OBSERVATIONS / Discipline
                            </div>
                            <table class="obs-table">
                                <tr>
                                    <td style="width: 50%;">
                                        <div class="obs-title">Observations du professeur principal :</div>
                                        <div style="min-height: 60px;"></div>
                                    </td>
                                    <td style="width: 50%;">
                                        <div class="obs-title">Appréciation du travail :</div>
                                        <div style="min-height: 60px;">
                                            <?php
                                            // Safety checks: ensure $bulletin and moyenne exist and contain a numeric moyenne_generale
                                            if (!empty($bulletin['moyenne']['moyenne_generale'])) {
                                                $moy = floatval($bulletin['moyenne']['moyenne_generale']);
                                                if ($moy >= 16) echo "Excellent travail. Continuez ainsi !";
                                                elseif ($moy >= 14) echo "Très bon travail. Félicitations !";
                                                elseif ($moy >= 12) echo "Bon travail. Peut mieux faire.";
                                                elseif ($moy >= 10) echo "Travail satisfaisant. Doit fournir plus d'efforts.";
                                                else echo "Résultats insuffisants. Doit redoubler d'efforts.";
                                            } else {
                                                echo "Aucune moyenne disponible pour cette période.";
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Signatures -->
                        <div class="signatures">
                            <div class="signature-box">
                                <div>Parent / Tuteur</div>
                                <div class="signature-line">_______________________</div>
                            </div>
                            <div class="signature-box">
                                <div>Le Directeur</div>
                                <div class="signature-line">_______________________</div>
                            </div>
                        </div>
                        
                        <div class="footer-note">
                            Bulletin généré le <?php echo date('d/m/Y H:i'); ?>
                        </div>
                    </div>
                <?php elseif ($classeId): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mb-0">Sélectionnez un élève pour afficher son bulletin</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-arrow-up-circle fs-1 text-muted"></i>
                            <p class="text-muted mb-0">Sélectionnez une classe, un élève et une période pour générer le bulletin</p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>