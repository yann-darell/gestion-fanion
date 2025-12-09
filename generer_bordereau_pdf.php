<?php
// Génération d'un bordereau PDF fidèle au modèle fourni
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/fpdf.php';

Auth::requireLogin();

// Récupération des données (exemple, à adapter selon votre base)
$classe_id = isset($_GET['classe_id']) ? intval($_GET['classe_id']) : 0;
$periode = isset($_GET['periode']) ? $_GET['periode'] : '';

if (!$classe_id || !$periode) {
    die('Paramètres manquants.');
}

$db = getDB();
$classe = $db->prepare("SELECT * FROM classes WHERE id = ?");
$classe->execute([$classe_id]);
$classe = $classe->fetch();
if (!$classe) die('Classe introuvable.');

// Récupérer les élèves et leurs notes (à adapter)
$eleves = $db->prepare("SELECT e.id, e.nom, e.prenom FROM eleves e WHERE e.classe_id = ? AND e.statut = 'actif'");
$eleves->execute([$classe_id]);
$eleves = $eleves->fetchAll();

// --- Génération PDF ---
require_once('includes/fpdf.php');
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'BORDEREAU DE NOTES', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Classe: ' . $classe['nom_classe'], 0, 1);
$pdf->Cell(0, 8, 'Période: ' . htmlspecialchars($periode), 0, 1);
$pdf->Ln(5);

// En-tête du tableau
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(10, 8, 'N°', 1);
$pdf->Cell(60, 8, 'Nom & Prénom', 1);
// Ajouter les matières dynamiquement (exemple)
$matieres = $db->query("SELECT nom_matiere FROM matieres")->fetchAll(PDO::FETCH_COLUMN);
foreach ($matieres as $matiere) {
    $pdf->Cell(25, 8, $matiere, 1);
}
$pdf->Ln();

// Remplir le tableau
$pdf->SetFont('Arial', '', 10);
$num = 1;
foreach ($eleves as $eleve) {
    $pdf->Cell(10, 8, $num++, 1);
    $pdf->Cell(60, 8, $eleve['nom'] . ' ' . $eleve['prenom'], 1);
    foreach ($matieres as $matiere) {
        // Récupérer la note de l'élève pour la matière et la période (à adapter)
        $stmt = $db->prepare("SELECT note FROM moyennes m INNER JOIN matieres mat ON m.matiere_id = mat.id WHERE m.eleve_id = ? AND mat.nom_matiere = ? AND m.periode = ?");
        $stmt->execute([$eleve['id'], $matiere, $periode]);
        $note = $stmt->fetchColumn();
        $pdf->Cell(25, 8, $note !== false ? $note : '-', 1);
    }
    $pdf->Ln();
}

$pdf->Output('I', 'bordereau_'.$classe_id.'_'.$periode.'.pdf');
exit;
