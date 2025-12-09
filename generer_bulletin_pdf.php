<?php
// Génération d'un bulletin PDF amélioré selon le modèle fourni
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/fpdf.php';

Auth::requireLogin();

$eleve_id = isset($_GET['eleve_id']) ? intval($_GET['eleve_id']) : 0;
$periode = isset($_GET['periode']) ? $_GET['periode'] : '';
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

if (!$eleve_id || !$periode) {
    die('Paramètres manquants.');
}

$db = getDB();
// Récupération élève et sa classe
$stmt = $db->prepare("SELECT e.*, c.nom_classe FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = ?");
$stmt->execute([$eleve_id]);
$eleve = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$eleve) die('Élève introuvable.');

// Récupération des matières et notes pour la classe
$stmt = $db->prepare(
    "SELECT m.id AS matiere_id, m.nom_matiere, cm.coefficient, n.note
     FROM classe_matiere cm
     INNER JOIN matieres m ON cm.matiere_id = m.id
     LEFT JOIN notes n ON n.matiere_id = m.id AND n.eleve_id = ? AND n.periode = ? AND n.annee_scolaire = ?
     WHERE cm.classe_id = ?
     ORDER BY m.nom_matiere"
);
$stmt->execute([$eleve_id, $periode, $anneeScolaire, $eleve['classe_id']]);
$matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération moyenne générale / rang / mention si existant
$stmt = $db->prepare("SELECT moyenne_generale, rang, mention FROM moyennes WHERE eleve_id = ? AND periode = ? AND annee_scolaire = ? LIMIT 1");
$stmt->execute([$eleve_id, $periode, $anneeScolaire]);
$infoMoyenne = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Génération PDF ---
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// En-tête avec logo à gauche et informations à droite
if (file_exists('assets/logo.png')) {
    $pdf->Image('assets/logo.png', 12, 8, 28);
}
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 6, utf8_decode(getParam('nom_etablissement', 'Collège / Lycée')), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, utf8_decode(getParam('school_address', 'Adresse - Téléphone')), 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode('Année scolaire : ') . $anneeScolaire, 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(37, 99, 235);
$pdf->Cell(0, 10, utf8_decode('BULLETIN DE NOTES'), 0, 1, 'C');
$pdf->SetTextColor(0,0,0);
$pdf->Ln(2);

// Bloc informations élève (gauche) et synthèse (droite)
$leftX = 12;
$rightX = 115;
$yStart = $pdf->GetY();

$pdf->SetFont('Arial', '', 11);
$pdf->SetXY($leftX, $yStart);
$pdf->Cell(60, 6, 'Nom & Prénom :', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, utf8_decode($eleve['nom'] . ' ' . $eleve['prenom']), 0, 1);

$pdf->SetXY($leftX, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(60, 6, 'Matricule :', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, utf8_decode($eleve['matricule']), 0, 1);

$pdf->SetXY($leftX, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(60, 6, 'Classe :', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, utf8_decode($eleve['nom_classe']), 0, 1);

$pdf->SetXY($leftX, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(60, 6, 'Période :', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, utf8_decode(strtoupper(str_replace('_', ' ', $periode))), 0, 1);

// Synthèse à droite
$pdf->SetXY($rightX, $yStart);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 6, 'Moyenne générale :', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, isset($infoMoyenne['moyenne_generale']) ? number_format($infoMoyenne['moyenne_generale'],2) : '-', 0, 1);

$pdf->SetXY($rightX, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 6, 'Rang :', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, isset($infoMoyenne['rang']) ? $infoMoyenne['rang'] : '-', 0, 1);

$pdf->SetXY($rightX, $pdf->GetY());
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 6, 'Mention :', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, isset($infoMoyenne['mention']) ? utf8_decode($infoMoyenne['mention']) : '-', 0, 1);

$pdf->Ln(6);

// Tableau matières
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(37,99,235);
$pdf->SetTextColor(255,255,255);
$w = [10, 90, 20, 25, 35]; // N°, Matière, Coef, Note, Total
$pdf->Cell($w[0], 9, 'N°', 1, 0, 'C', true);
$pdf->Cell($w[1], 9, utf8_decode('Matière'), 1, 0, 'C', true);
$pdf->Cell($w[2], 9, 'Coef.', 1, 0, 'C', true);
$pdf->Cell($w[3], 9, 'Note /20', 1, 0, 'C', true);
$pdf->Cell($w[4], 9, 'Points', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0,0,0);

$i = 1;
$totalCoeffs = 0;
$totalPoints = 0;
foreach ($matieres as $m) {
    $noteVal = is_null($m['note']) || $m['note'] === '' ? null : floatval($m['note']);
    $coef = floatval($m['coefficient']);
    $points = $noteVal !== null ? ($noteVal * $coef) : 0;
    $totalCoeffs += $coef;
    $totalPoints += $points;

    $pdf->Cell($w[0], 8, $i, 1, 0, 'C');
    $pdf->Cell($w[1], 8, utf8_decode($m['nom_matiere']), 1);
    $pdf->Cell($w[2], 8, $coef, 1, 0, 'C');
    $pdf->Cell($w[3], 8, $noteVal !== null ? number_format($noteVal,2) : '-', 1, 0, 'C');
    $pdf->Cell($w[4], 8, $noteVal !== null ? number_format($points,2) : '-', 1, 1, 'C');
    $i++;
}

// Totaux et moyenne
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($w[0] + $w[1], 8, 'Totaux', 1, 0, 'R');
$pdf->Cell($w[2], 8, number_format($totalCoeffs,2), 1, 0, 'C');
$pdf->Cell($w[3], 8, '', 1, 0, 'C');
$pdf->Cell($w[4], 8, number_format($totalPoints,2), 1, 1, 'C');

$moyenneGeneraleCalc = $totalCoeffs > 0 ? $totalPoints / $totalCoeffs : 0;
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'Moyenne calculée : ' . number_format($moyenneGeneraleCalc,2) . ' / 20', 0, 1);

// Appréciation
$pdf->Ln(4);
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 6, utf8_decode('Appréciation générale : ' . (isset($infoMoyenne['mention']) ? $infoMoyenne['mention'] : '........................................................................')), 0, 'L');

// Signatures
$pdf->Ln(8);
$sigY = $pdf->GetY();
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(90, 6, 'Le parent/tuteur :', 0, 0, 'L');
$pdf->Cell(0, 6, 'Le Chef d\'établissement :', 0, 1, 'R');
$pdf->Ln(18);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(90, 6, utf8_decode('Signature : ______________________'), 0, 0, 'L');
$pdf->Cell(0, 6, utf8_decode('Signature : ______________________'), 0, 1, 'R');

$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(120,120,120);
$pdf->Cell(0, 6, utf8_decode(getParam('nom_etablissement', 'Établissement') . ' - Document généré automatiquement'), 0, 1, 'C');

$pdf->Output('I', 'bulletin_'.$eleve_id.'_'.$periode.'.pdf');
exit;
