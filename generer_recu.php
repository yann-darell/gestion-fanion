<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

// Récupérer le paiement
$paiementId = $_GET['id'] ?? null;
$numeroRecu = $_GET['numero'] ?? null;

try {
    $db = getDB();
    
    if ($paiementId) {
        $stmt = $db->prepare("
            SELECT 
                p.*,
                CONCAT(e.nom, ' ', e.prenom) as eleve_nom,
                e.matricule,
                e.nom_parent,
                e.telephone_parent,
                c.nom_classe,
                u.nom_complet as recu_par_nom
            FROM paiements p
            INNER JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            LEFT JOIN utilisateurs u ON p.recu_par = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$paiementId]);
    } else if ($numeroRecu) {
        $stmt = $db->prepare("
            SELECT 
                p.*,
                CONCAT(e.nom, ' ', e.prenom) as eleve_nom,
                e.matricule,
                e.nom_parent,
                e.telephone_parent,
                c.nom_classe,
                u.nom_complet as recu_par_nom
            FROM paiements p
            INNER JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            LEFT JOIN utilisateurs u ON p.recu_par = u.id
            WHERE p.numero_recu = ?
        ");
        $stmt->execute([$numeroRecu]);
    } else {
        die('Paramètres manquants');
    }
    
    $paiement = $stmt->fetch();
    
    if (!$paiement) {
        die('Paiement non trouvé');
    }
    
    // Paramètres de l'établissement
    $nomEtablissement = getParam('nom_etablissement', 'Collège Le Fanion');
    $adresseEtablissement = getParam('adresse_etablissement', 'Douala, Cameroun');
    $telephoneEtablissement = getParam('telephone_etablissement', '+237 XXX XXX XXX');
    
} catch (PDOException $e) {
    die('Erreur : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de paiement - <?php echo $paiement['numero_recu']; ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .recu-container {
            background: white;
            padding: 40px;
            border: 2px solid #2563eb;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 28px;
            margin: 10px 0;
            text-transform: uppercase;
        }
        
        .header .etablissement {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
        }
        
        .header .info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .numero-recu {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 20px;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-row:nth-child(even) {
            background: #f9fafb;
        }
        
        .info-label {
            font-weight: bold;
            width: 200px;
            color: #374151;
        }
        
        .info-value {
            flex: 1;
            color: #1f2937;
        }
        
        .montant-section {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: center;
        }
        
        .montant-section .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .montant-section .montant {
            font-size: 36px;
            font-weight: bold;
        }
        
        .montant-section .lettres {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 10px;
            font-style: italic;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .signature-zone {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }
        
        .signature {
            text-align: center;
            width: 40%;
        }
        
        .signature-line {
            border-top: 2px solid #374151;
            margin-top: 60px;
            padding-top: 10px;
            font-size: 12px;
            color: #6b7280;
        }
        
        .btn-imprimer {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            display: block;
            margin: 20px auto;
        }
        
        .btn-imprimer:hover {
            background: #1e40af;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(37, 99, 235, 0.05);
            font-weight: bold;
            pointer-events: none;
            z-index: 0;
        }
        
        .content {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
    <button class="btn-imprimer no-print" onclick="window.print()">
        🖨️ Imprimer le reçu
    </button>
    
    <div class="recu-container">
        <div class="watermark">PAYÉ</div>
        
        <div class="content">
            <div class="header">
                <div class="etablissement"><?php echo htmlspecialchars($nomEtablissement); ?></div>
                <div class="info"><?php echo htmlspecialchars($adresseEtablissement); ?></div>
                <div class="info">Tél: <?php echo htmlspecialchars($telephoneEtablissement); ?></div>
                <h1>Reçu de Paiement</h1>
            </div>
            
            <div class="numero-recu">
                N° <?php echo htmlspecialchars($paiement['numero_recu']); ?>
            </div>
            
            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">Date de paiement :</div>
                    <div class="info-value"><?php echo formatDate($paiement['date_paiement'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Élève :</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($paiement['eleve_nom']); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Matricule :</div>
                    <div class="info-value"><?php echo htmlspecialchars($paiement['matricule']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Classe :</div>
                    <div class="info-value"><?php echo htmlspecialchars($paiement['nom_classe'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Parent/Tuteur :</div>
                    <div class="info-value"><?php echo htmlspecialchars($paiement['nom_parent']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type de paiement :</div>
                    <div class="info-value"><?php echo ucfirst($paiement['type_paiement']); ?></div>
                </div>
                <?php if ($paiement['periode_concernee']): ?>
                <div class="info-row">
                    <div class="info-label">Période concernée :</div>
                    <div class="info-value"><?php echo htmlspecialchars($paiement['periode_concernee']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Mode de paiement :</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $paiement['mode_paiement'])); ?></div>
                </div>
                <?php if ($paiement['reference_paiement']): ?>
                <div class="info-row">
                    <div class="info-label">Référence :</div>
                    <div class="info-value"><?php echo htmlspecialchars($paiement['reference_paiement']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="montant-section">
                <div class="label">Montant payé</div>
                <div class="montant"><?php echo formatMontant($paiement['montant']); ?></div>
                <div class="lettres">
                    <?php
                    // Convertir le montant en lettres (version simplifiée)
                    $montant = $paiement['montant'];
                    echo "Arrêté le présent reçu à la somme de " . number_format($montant, 0, ',', ' ') . " francs CFA";
                    ?>
                </div>
            </div>
            
            <?php if ($paiement['observation']): ?>
            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">Observation :</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($paiement['observation'])); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <div style="text-align: center; margin-bottom: 20px; font-size: 12px; color: #6b7280;">
                    Reçu établi le <?php echo formatDate($paiement['date_creation'], 'd/m/Y à H:i'); ?>
                    <?php if ($paiement['recu_par_nom']): ?>
                        par <?php echo htmlspecialchars($paiement['recu_par_nom']); ?>
                    <?php endif; ?>
                </div>
                
                <div class="signature-zone">
                    <div class="signature">
                        <div>L'Élève / Parent</div>
                        <div class="signature-line">Signature</div>
                    </div>
                    <div class="signature">
                        <div>Le Secrétariat</div>
                        <div class="signature-line">Signature et Cachet</div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #9ca3af;">
                    Ce reçu est généré électroniquement et fait foi de paiement.
                </div>
            </div>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <a href="paiements.php" style="color: #2563eb; text-decoration: none;">
            ← Retour à la liste des paiements
        </a>
    </div>
</body>
</html>