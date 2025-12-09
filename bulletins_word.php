<?php
// bulletins_word.php - Format officiel camerounais
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';
Auth::requireLogin();

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo "Erreur serveur : l'extension PHP <strong>zip</strong> n'est pas active.";
    exit;
}

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
require_once __DIR__ . '/vendor/autoload.php';

$classeId = $_GET['classe_id'] ?? null;
$eleveId  = $_GET['eleve_id']  ?? null;
$periode  = $_GET['periode']   ?? null;

if (!$classeId || !$eleveId || !$periode) {
    die("Paramètre manquant : classe_id, eleve_id et periode sont requis.");
}

try {
    $db = getDB();

    // Récupérer élève
    $stmt = $db->prepare("
        SELECT e.*, c.nom_classe, c.niveau
        FROM eleves e
        LEFT JOIN classes c ON e.classe_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$eleveId]);
    $eleve = $stmt->fetch();
    if (!$eleve) die("Élève introuvable.");

    // Récupérer matières groupées
    $groupCols = ['groupe', 'group_label', 'group_no', 'group_name'];
    $foundGroupCol = null;
    foreach ($groupCols as $col) {
        $check = $db->query("SHOW COLUMNS FROM classe_matiere LIKE " . $db->quote($col))->fetch();
        if ($check) { $foundGroupCol = $col; break; }
    }

    // Vérifier si les colonnes existent
    $hasCompetences = $db->query("SHOW COLUMNS FROM matieres LIKE 'competences'")->fetch();
    $hasSeq1 = $db->query("SHOW COLUMNS FROM notes LIKE 'note_seq1'")->fetch();
    $hasSeq2 = $db->query("SHOW COLUMNS FROM notes LIKE 'note_seq2'")->fetch();
    
    $competencesCol = $hasCompetences ? 'm.competences' : "'' AS competences";
    $seq1Col = $hasSeq1 ? 'n.note_seq1' : '0 AS note_seq1';
    $seq2Col = $hasSeq2 ? 'n.note_seq2' : '0 AS note_seq2';

    if ($foundGroupCol) {
        $stmt = $db->prepare("
            SELECT cm.$foundGroupCol AS grp, m.nom_matiere, $competencesCol, 
                   cm.coefficient, n.note, $seq1Col, $seq2Col
            FROM classe_matiere cm
            INNER JOIN matieres m ON cm.matiere_id = m.id
            LEFT JOIN notes n ON n.matiere_id = m.id AND n.classe_id = cm.classe_id 
                AND n.eleve_id = ? AND n.periode = ?
            WHERE cm.classe_id = ?
            ORDER BY cm.$foundGroupCol, cm.ordre ASC, m.nom_matiere
        ");
        $stmt->execute([$eleveId, $periode, $classeId]);
    } else {
        $stmt = $db->prepare("
            SELECT 'GROUPE I' AS grp, m.nom_matiere, $competencesCol,
                   cm.coefficient, n.note, $seq1Col, $seq2Col
            FROM classe_matiere cm
            INNER JOIN matieres m ON cm.matiere_id = m.id
            LEFT JOIN notes n ON n.matiere_id = m.id AND n.classe_id = cm.classe_id 
                AND n.eleve_id = ? AND n.periode = ?
            WHERE cm.classe_id = ?
            ORDER BY m.nom_matiere
        ");
        $stmt->execute([$eleveId, $periode, $classeId]);
    }

    $rows = $stmt->fetchAll();

    // Regroupement
    $groups = [];
    foreach ($rows as $r) {
        $g = trim($r['grp'] ?: 'GROUPE I');
        $groups[$g][] = $r;
    }

    // Moyenne + rang
    $stmt = $db->prepare("SELECT * FROM moyennes WHERE eleve_id=? AND classe_id=? AND periode=?");
    $stmt->execute([$eleveId,$classeId,$periode]);
    $moyenne = $stmt->fetch();

    // Effectif
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM eleves WHERE classe_id=? AND statut='actif'");
    $stmt->execute([$classeId]);
    $effectif = $stmt->fetch()['total'] ?? 0;

    // Discipline
    $stmt = $db->prepare("SELECT * FROM discipline WHERE eleve_id=? AND periode=?");
    $stmt->execute([$eleveId, $periode]);
    $discipline = $stmt->fetch();

    // PHOTO
    $photoFile = null;
    if (!empty($eleve['photo']) && file_exists(__DIR__ . '/' . $eleve['photo'])) {
        $photoFile = __DIR__ . '/' . $eleve['photo'];
    } else {
        $possible = [
            __DIR__ . "/uploads/photos/{$eleve['matricule']}.jpg",
            __DIR__ . "/uploads/photos/{$eleve['matricule']}.jpeg",
            __DIR__ . "/uploads/photos/{$eleve['matricule']}.png"
        ];
        foreach ($possible as $p) if (file_exists($p)) { $photoFile = $p; break; }
    }

    // Logo établissement
    $logoPath = __DIR__ . '/assets/images/logo.jpg';
    if (!file_exists($logoPath)) {
        // Essayer d'autres formats
        $alternatives = [
            __DIR__ . '/assets/images/logo.jpg',
            __DIR__ . '/assets/images/logo.jpeg',
            __DIR__ . '/uploads/logo.png'
        ];
        foreach ($alternatives as $alt) {
            if (file_exists($alt)) {
                $logoPath = $alt;
                break;
            }
        }
    }

    // CRÉATION DU DOCUMENT
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName('Arial');
    $phpWord->setDefaultFontSize(9);

    $section = $phpWord->addSection([
        'marginTop'    => Converter::cmToTwip(1.0),
        'marginBottom' => Converter::cmToTwip(1.0),
        'marginLeft'   => Converter::cmToTwip(1.0),
        'marginRight'  => Converter::cmToTwip(1.0),
        'orientation'  => 'portrait'
    ]);

    // Watermark - Logo en arrière-plan
    if ($logoPath && file_exists($logoPath)) {
        $section->addWatermark($logoPath, [
            'width'     => 400,
            'height'    => 400,
            'rotation'  => 0,
            'marginTop' => 150,
            'marginLeft' => 100
        ]);
    }

    // ==== EN-TÊTE OFFICIEL ====
    $headerTable = $section->addTable(['borderSize'=>0, 'width'=>100*50]);
    $headerTable->addRow();
    
    // Colonne gauche - République
    $cellLeft = $headerTable->addCell(3500);
    $cellLeft->addText("REPUBLIQUE DU CAMEROUN", ['size'=>8, 'bold'=>true], ['alignment'=>Jc::CENTER]);
    $cellLeft->addText("Paix - Travail - Patrie", ['size'=>7, 'italic'=>true], ['alignment'=>Jc::CENTER]);
    $cellLeft->addText("___________", ['size'=>7], ['alignment'=>Jc::CENTER]);
    $cellLeft->addText("MINISTERE DES ENSEIGNEMENTS SECONDAIRES", ['size'=>7, 'bold'=>true], ['alignment'=>Jc::CENTER]);
    $cellLeft->addText("___________", ['size'=>7], ['alignment'=>Jc::CENTER]);
    $cellLeft->addText("Collège bilingue privé laïc", ['size'=>7], ['alignment'=>Jc::CENTER]);
    $cellLeft->addText("le Fanion", ['size'=>8, 'bold'=>true], ['alignment'=>Jc::CENTER]);

    // Colonne centre - Logo
    $cellCenter = $headerTable->addCell(2000);
    if ($logoPath && file_exists($logoPath)) {
        $cellCenter->addImage($logoPath, [
            'width'  => 80,
            'height' => 80,
            'alignment' => Jc::CENTER
        ]);
    } else {
        $cellCenter->addText("", ['size'=>8], ['alignment'=>Jc::CENTER]);
    }

    // Colonne droite - Republic + Photo
    $cellRight = $headerTable->addCell(3500);
    $cellRight->addText("REPUBLIC OF CAMEROON", ['size'=>8, 'bold'=>true], ['alignment'=>Jc::CENTER]);
    $cellRight->addText("Peace - Work - Fatherland", ['size'=>7, 'italic'=>true], ['alignment'=>Jc::CENTER]);
    $cellRight->addText("___________", ['size'=>7], ['alignment'=>Jc::CENTER]);
    $cellRight->addText("MINISTRY OF SECONDARY EDUCATION", ['size'=>7, 'bold'=>true], ['alignment'=>Jc::CENTER]);
    $cellRight->addText("___________", ['size'=>7], ['alignment'=>Jc::CENTER]);
    $cellRight->addText("bilingual private institute", ['size'=>7], ['alignment'=>Jc::CENTER]);
    $cellRight->addText("le Fanion", ['size'=>8, 'bold'=>true], ['alignment'=>Jc::CENTER]);
    
    $cellRight->addTextBreak(0.3);
    
    // Photo élève à droite
    if ($photoFile) {
        $cellRight->addImage($photoFile, [
            'width'  => 70,
            'height' => 85,
            'alignment' => Jc::END
        ]);
    }

    $section->addTextBreak(1);

    // ==== TITRE DU BULLETIN ====
    $periodeLabel = strtoupper(str_replace('_', ' ', $periode));
    if (strpos($periode, 'trim') !== false) {
        $periodeLabel = str_replace('TRIMESTRE', 'TRIMESTRE', $periodeLabel);
    }
    
    $section->addText(
        "BULLETIN DU " . $periodeLabel,
        ['bold'=>true, 'size'=>12],
        ['alignment'=>Jc::CENTER]
    );

    $section->addTextBreak(0.5);

    // ==== INFORMATIONS ÉLÈVE ====
    $phpWord->addTableStyle('InfoStyle', [
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 40
    ]);

    $infoTable = $section->addTable('InfoStyle');
    $infoTable->addRow();
    
    $infoTable->addCell(2000)->addText("CLASSE :", ['bold'=>true, 'size'=>8]);
    $infoTable->addCell(2500)->addText($eleve['nom_classe'] ?? '-', ['size'=>8]);
    $infoTable->addCell(2000)->addText("EFFECTIF :", ['bold'=>true, 'size'=>8]);
    $infoTable->addCell(1500)->addText($effectif, ['size'=>8]);
    $infoTable->addCell(2500)->addText("PROFESSEUR PRINCIPAL :", ['bold'=>true, 'size'=>8]);
    $infoTable->addCell(2500)->addText("[Nom du professeur]", ['size'=>8]);

    $infoTable->addRow();
    $infoTable->addCell(2000)->addText("NOM ET PRENOM(S) :", ['bold'=>true, 'size'=>8]);
    $nomComplet = strtoupper($eleve['nom'] . " " . $eleve['prenom']);
    $infoTable->addCell(4500, ['gridSpan'=>2])->addText($nomComplet, ['size'=>8, 'bold'=>true]);
    $infoTable->addCell(2000)->addText("MATRICULE :", ['bold'=>true, 'size'=>8]);
    $infoTable->addCell(1500)->addText($eleve['matricule'] ?? '-', ['size'=>8]);
    $infoTable->addCell(2500)->addText("Date/lieu Naissance :", ['bold'=>true, 'size'=>8]);
    $dateNaiss = !empty($eleve['date_naissance']) ? date('d/m/Y', strtotime($eleve['date_naissance'])) : '-';
    $infoTable->addCell(2500)->addText($dateNaiss, ['size'=>8]);

    $section->addTextBreak(0.5);

    // ==== STYLE TABLEAU NOTES ====
    $phpWord->addTableStyle('NotesTable', [
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 30,
        'alignment' => Jc::CENTER
    ]);

    // Variables pour calculs
    $grandCoef = 0;
    $grandPoints = 0;

    // ==== TABLEAUX PAR GROUPE ====
    foreach ($groups as $gLabel => $items) {
        
        $section->addText(strtoupper($gLabel), ['bold'=>true, 'size'=>10, 'underline'=>'single']);
        
        $notesTable = $section->addTable('NotesTable');
        
        // En-tête
        $notesTable->addRow(400);
        $notesTable->addCell(4500)->addText("MATIERES", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(3000)->addText("Compétences Evaluées", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(800)->addText("TRIM 1", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(800)->addText("SEQ 1", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(800)->addText("SEQ 2", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(600)->addText("Coef", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(800)->addText("Moy Coef", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(1000)->addText("Moy Gpe Classe", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(1200)->addText("Rang", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(1500)->addText("Appréciation", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);

        $gCoef = 0;
        $gPoints = 0;

        foreach ($items as $m) {
            $trim1 = floatval($m['note'] ?? 0);
            $seq1 = floatval($m['note_seq1'] ?? 0);
            $seq2 = floatval($m['note_seq2'] ?? 0);
            $coef = floatval($m['coefficient'] ?? 0);
            $moyCoef = $trim1 * $coef;
            
            $competences = $m['competences'] ?? '';

            $gCoef += $coef;
            $gPoints += $moyCoef;

            // Appréciation
            if ($trim1 >= 16) $apr = "Excellent";
            elseif ($trim1 >= 14) $apr = "Très bien";
            elseif ($trim1 >= 12) $apr = "Bien";
            elseif ($trim1 >= 10) $apr = "Assez bien";
            else $apr = "Passable";

            $notesTable->addRow();
            $notesTable->addCell(4500)->addText($m['nom_matiere'], ['size'=>8]);
            $notesTable->addCell(3000)->addText($competences, ['size'=>7]);
            $notesTable->addCell(800)->addText(number_format($trim1, 2), ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(800)->addText(number_format($seq1, 2), ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(800)->addText(number_format($seq2, 2), ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(600)->addText($coef, ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(800)->addText(number_format($moyCoef, 2), ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(1000)->addText("-", ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(1200)->addText("-", ['size'=>8], ['alignment'=>Jc::CENTER]);
            $notesTable->addCell(1500)->addText($apr, ['size'=>7]);
        }

        // Total du groupe
        $notesTable->addRow();
        $notesTable->addCell(7500, ['gridSpan'=>2])->addText("Total du $gLabel", ['bold'=>true, 'size'=>8]);
        $notesTable->addCell(800)->addText("", ['size'=>8]);
        $notesTable->addCell(800)->addText("", ['size'=>8]);
        $notesTable->addCell(800)->addText("", ['size'=>8]);
        $notesTable->addCell(600)->addText(number_format($gCoef, 0), ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(800)->addText(number_format($gPoints, 2), ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(1000)->addText("", ['size'=>8]);
        $notesTable->addCell(1200)->addText("", ['size'=>8]);
        $notesTable->addCell(1500)->addText("", ['size'=>8]);

        // Moyenne du groupe
        $moyGrp = $gCoef > 0 ? $gPoints / $gCoef : 0;
        $notesTable->addRow();
        $notesTable->addCell(7500, ['gridSpan'=>2])->addText("Moyenne du Groupe :", ['italic'=>true, 'size'=>8]);
        $notesTable->addCell(2400, ['gridSpan'=>3])->addText(number_format($moyGrp, 2), ['bold'=>true, 'size'=>9], ['alignment'=>Jc::CENTER]);
        $notesTable->addCell(3100, ['gridSpan'=>5])->addText("", ['size'=>8]);

        $grandCoef += $gCoef;
        $grandPoints += $gPoints;

        $section->addTextBreak(0.5);
    }

    // ==== RÉSUMÉ GÉNÉRAL ====
    $moyGen = $grandCoef > 0 ? $grandPoints / $grandCoef : 0;
    $rangEleve = $moyenne['rang'] ?? '-';
    $totalEleves = $moyenne['total_students'] ?? $effectif;

    $resumeTable = $section->addTable('NotesTable');
    $resumeTable->addRow(350);
    $resumeTable->addCell(7000)->addText("Total Général (G1 + G2 + G3 + G4 + G5)", ['bold'=>true, 'size'=>9]);
    $resumeTable->addCell(2000)->addText(number_format($grandPoints, 2), ['bold'=>true, 'size'=>10], ['alignment'=>Jc::CENTER]);
    $resumeTable->addCell(3000)->addText("Moy Gén de la Classe :", ['bold'=>true, 'size'=>8]);
    $resumeTable->addCell(3000)->addText("[XX.XX]", ['bold'=>true, 'size'=>9], ['alignment'=>Jc::CENTER]);

    $resumeTable->addRow(350);
    $resumeTable->addCell(7000)->addText("Moyenne Générale :", ['bold'=>true, 'size'=>10]);
    $resumeTable->addCell(2000)->addText(number_format($moyGen, 2), ['bold'=>true, 'size'=>11, 'color'=>'FF0000'], ['alignment'=>Jc::CENTER]);
    $resumeTable->addCell(3000)->addText("Moy 1er :", ['bold'=>true, 'size'=>8]);
    $resumeTable->addCell(3000)->addText("[XX.XX]", ['size'=>9], ['alignment'=>Jc::CENTER]);

    $resumeTable->addRow(350);
    $resumeTable->addCell(9000, ['gridSpan'=>2])->addText("", ['size'=>8]);
    $resumeTable->addCell(3000)->addText("Moy du dernier :", ['bold'=>true, 'size'=>8]);
    $resumeTable->addCell(3000)->addText("[XX.XX]", ['size'=>9], ['alignment'=>Jc::CENTER]);

    $section->addTextBreak(0.5);

    // ==== MOYENNES DE L'ÉLÈVE ====
    $moyTable = $section->addTable('InfoStyle');
    $moyTable->addRow();
    $moyTable->addCell(5000)->addText("Moyennes de l'Elève :", ['bold'=>true, 'size'=>9]);
    $moyTable->addCell(3000)->addText("SEQ 1", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
    $moyTable->addCell(3000)->addText("SEQ 2", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);
    $moyTable->addCell(4000)->addText("Trimestre", ['bold'=>true, 'size'=>8], ['alignment'=>Jc::CENTER]);

    $moyTable->addRow();
    $moyTable->addCell(5000)->addText("", ['size'=>8]);
    $moyTable->addCell(3000)->addText("[XX.XX]", ['size'=>9], ['alignment'=>Jc::CENTER]);
    $moyTable->addCell(3000)->addText("[XX.XX]", ['size'=>9], ['alignment'=>Jc::CENTER]);
    $moyTable->addCell(4000)->addText(number_format($moyGen, 2), ['bold'=>true, 'size'=>10], ['alignment'=>Jc::CENTER]);

    $section->addTextBreak(0.3);
    $section->addText(
        "❖ La moyenne trimestrielle n'est pas égale à la moyenne des 2 séquences ❖",
        ['size'=>7, 'italic'=>true, 'color'=>'FF0000'],
        ['alignment'=>Jc::CENTER]
    );

    $section->addTextBreak(0.5);

    // ==== DISCIPLINE ET OBSERVATIONS ====
    $discTable = $section->addTable('InfoStyle');
    $discTable->addRow();
    $discTable->addCell(5000)->addText("DISCIPLINE / Disciplin", ['bold'=>true, 'size'=>9]);
    $discTable->addCell(10000, ['gridSpan'=>2])->addText("Appréciation du travail de l'élève", ['bold'=>true, 'size'=>9], ['alignment'=>Jc::CENTER]);

    $discTable->addRow(800);
    $cellDisc = $discTable->addCell(5000);
    $cellDisc->addText("Nb d'Heures :", ['size'=>8]);
    $cellDisc->addText("Exclusions / Appointments : " . ($discipline['exclusions'] ?? 'CA'), ['size'=>8]);
    $cellDisc->addText("Blâme / Blames :", ['size'=>8]);
    $cellDisc->addText("Avertissements / Warnings :", ['size'=>8]);
    
    $discTable->addCell(10000, ['gridSpan'=>2, 'valign'=>'top'])->addText(
        "Observations et Remarques\nProfesseur Principal",
        ['size'=>8],
        ['alignment'=>Jc::CENTER]
    );

    $discTable->addRow(600);
    $discTable->addCell(5000)->addText("Observation et Remarques du parent\nde l'élève", ['size'=>8, 'bold'=>true]);
    $discTable->addCell(5000)->addText("Décisions du Conseil de Classe", ['size'=>8, 'bold'=>true], ['alignment'=>Jc::CENTER]);
    $discTable->addCell(5000)->addText("Signature du Censeur ou l'élève\nPrincipal", ['size'=>8, 'bold'=>true], ['alignment'=>Jc::CENTER]);

    $section->addTextBreak(0.5);

    // ==== NOTE FINALE ====
    $section->addText(
        "NB : Les élèves ont un délai de 15 jours pour toutes revendications non reception du bulletin.",
        ['size'=>7, 'bold'=>true],
        ['alignment'=>Jc::CENTER]
    );

    // Envoi du fichier
    $fileName = "Bulletin_{$eleve['nom']}_{$eleve['prenom']}_{$periode}.docx";
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save("php://output");
    exit;

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}