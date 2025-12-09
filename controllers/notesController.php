<?php
// Controller for managing notes


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

class NotesController {
    public function index() {
        Auth::requireLogin();
        $user = getCurrentUser();
        $anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));

        $classeId = $_GET['classe_id'] ?? '';
        $matiereId = $_GET['matiere_id'] ?? '';
        $periode = $_GET['periode'] ?? 'sequence1';
        $error = null;

        try {
            $db = getDB();
            $stmt = $db->query("SELECT * FROM classes WHERE annee_scolaire = '$anneeScolaire' ORDER BY nom_classe");
            $classes = $stmt->fetchAll();

            $matieres = [];
            if ($classeId) {
                $stmt = $db->prepare("
                    SELECT m.*, cm.coefficient
                    FROM matieres m
                    INNER JOIN classe_matiere cm ON m.id = cm.matiere_id
                    WHERE cm.classe_id = ?
                    ORDER BY m.nom_matiere
                ");
                $stmt->execute([$classeId]);
                $matieres = $stmt->fetchAll();
            }

            $eleves = [];
            if ($classeId && $matiereId) {
                $stmt = $db->prepare("
                    SELECT 
                        e.*, n.note, n.date_saisie
                    FROM eleves e
                    LEFT JOIN notes n ON e.id = n.eleve_id 
                        AND n.matiere_id = ? 
                        AND n.periode = ? 
                        AND n.annee_scolaire = ?
                    WHERE e.classe_id = ? AND e.statut = 'actif'
                    ORDER BY e.nom, e.prenom
                ");
                $stmt->execute([$matiereId, $periode, $anneeScolaire, $classeId]);
                $eleves = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }

        include __DIR__ . '/../views/notes_view.php';
    }

    public function store() {
        Auth::requireLogin();
        $user = getCurrentUser();
        $anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sauvegarder_notes') {
            try {
                $db = getDB();
                $db->beginTransaction();
                $classeId = $_POST['classe_id'];
                $matiereId = $_POST['matiere_id'];
                $periode = $_POST['periode'];
                $notesData = $_POST['notes'] ?? [];
                foreach ($notesData as $eleveId => $note) {
                    $note = trim($note);
                    if ($note === '') continue;
                    $note = floatval($note);
                    if ($note < 0 || $note > 20) {
                        throw new Exception("Note invalide pour l'élève ID $eleveId : $note");
                    }
                    $stmt = $db->prepare("
                        INSERT INTO notes (eleve_id, matiere_id, classe_id, periode, annee_scolaire, note, saisi_par)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            note = VALUES(note),
                            date_saisie = CURRENT_TIMESTAMP,
                            saisi_par = VALUES(saisi_par)
                    ");
                    $stmt->execute([
                        $eleveId,
                        $matiereId,
                        $classeId,
                        $periode,
                        $anneeScolaire,
                        $note,
                        $user['id']
                    ]);
                }
                $this->calculerMoyennes($db, $classeId, $periode, $anneeScolaire);
                $db->commit();
                logActivity('Saisie de notes', 'notes', null, "Classe: $classeId, Matière: $matiereId, Période: $periode");
                $_SESSION['success_message'] = 'Notes enregistrées avec succès';
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_message'] = 'Erreur : ' . $e->getMessage();
            }
        }
        header('Location: notes.php?classe_id=' . urlencode($_POST['classe_id']) . '&matiere_id=' . urlencode($_POST['matiere_id']) . '&periode=' . urlencode($_POST['periode']));
        exit;
    }

    private function calculerMoyennes($db, $classeId, $periode, $anneeScolaire) {
        $stmtEleves = $db->prepare("SELECT id FROM eleves WHERE classe_id = ? AND statut = 'actif'");
        $stmtEleves->execute([$classeId]);
        $eleves = $stmtEleves->fetchAll(PDO::FETCH_COLUMN);
        foreach ($eleves as $eleveId) {
            $stmt = $db->prepare("
                SELECT n.note, cm.coefficient
                FROM notes n
                INNER JOIN classe_matiere cm ON n.matiere_id = cm.matiere_id AND n.classe_id = cm.classe_id
                WHERE n.eleve_id = ? AND n.classe_id = ? AND n.periode = ? AND n.annee_scolaire = ?
            ");
            $stmt->execute([$eleveId, $classeId, $periode, $anneeScolaire]);
            $notes = $stmt->fetchAll();
            if (empty($notes)) continue;
            $totalPoints = 0;
            $totalCoefficients = 0;
            foreach ($notes as $note) {
                $totalPoints += $note['note'] * $note['coefficient'];
                $totalCoefficients += $note['coefficient'];
            }
            $moyenneGenerale = $totalCoefficients > 0 ? $totalPoints / $totalCoefficients : 0;
            $mention = function_exists('getMention') ? getMention($moyenneGenerale) : '';
            $stmt = $db->prepare("
                INSERT INTO moyennes (eleve_id, classe_id, periode, annee_scolaire, moyenne_generale, total_points, total_coefficients, mention)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    moyenne_generale = VALUES(moyenne_generale),
                    total_points = VALUES(total_points),
                    total_coefficients = VALUES(total_coefficients),
                    mention = VALUES(mention),
                    date_calcul = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $eleveId,
                $classeId,
                $periode,
                $anneeScolaire,
                $moyenneGenerale,
                $totalPoints,
                $totalCoefficients,
                $mention
            ]);
        }
        $stmt = $db->prepare("
            SELECT id, eleve_id, moyenne_generale
            FROM moyennes
            WHERE classe_id = ? AND periode = ? AND annee_scolaire = ?
            ORDER BY moyenne_generale DESC
        ");
        $stmt->execute([$classeId, $periode, $anneeScolaire]);
        $moyennes = $stmt->fetchAll();
        $rang = 1;
        foreach ($moyennes as $moyenne) {
            $stmtRang = $db->prepare("UPDATE moyennes SET rang = ? WHERE id = ?");
            $stmtRang->execute([$rang, $moyenne['id']]);
            $rang++;
        }
    }
}
