<?php
define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();

$user = getCurrentUser();
$anneeScolaire = getParam('annee_scolaire_actuelle', date('Y') . '-' . (date('Y') + 1));


// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        switch ($action) {
            case 'ajouter':
                $matricule = securiser($_POST['matricule']);
                
                // Vérifier que le matricule n'existe pas déjà
                $stmt = $db->prepare("SELECT id FROM eleves WHERE matricule = ?");
                $stmt->execute([$matricule]);
                if ($stmt->fetch()) {
                    throw new Exception("Ce matricule existe déjà");
                }
                
                // Gestion de l'upload de la photo
                $photoFileName = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['photo']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        // Vérifier la taille du fichier (max 5Mo)
                        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                            throw new Exception("La photo ne doit pas dépasser 5 Mo");
                        }
                        
                        // Utiliser le matricule comme nom de fichier
                        $photoFileName = $matricule . '.' . $ext;
                        $destination = PHOTOS_DIR . '/' . $photoFileName;
                        
                        // Déplacer le fichier uploadé
                        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                            throw new Exception("Erreur lors de l'upload de la photo");
                        }
                        
                        // Stocker le chemin relatif dans la base
                        $photoFileName = 'assets/images/photos/' . $photoFileName;
                    } else {
                        throw new Exception("Format de photo non autorisé. Utilisez JPG, PNG ou GIF");
                    }
                }
                
                $stmt = $db->prepare("
                    INSERT INTO eleves (matricule, nom, prenom, sexe, date_naissance, lieu_naissance, 
                                       adresse, telephone, email, nom_parent, telephone_parent, 
                                       profession_parent, classe_id, photo, date_inscription, annee_scolaire, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')
                ");
                
                $classeId = !empty($_POST['classe_id']) ? $_POST['classe_id'] : null;
                
                $stmt->execute([
                    $matricule,
                    securiser($_POST['nom']),
                    securiser($_POST['prenom']),
                    $_POST['sexe'],
                    $_POST['date_naissance'],
                    securiser($_POST['lieu_naissance'] ?? ''),
                    securiser($_POST['adresse'] ?? ''),
                    securiser($_POST['telephone'] ?? ''),
                    securiser($_POST['email'] ?? ''),
                    securiser($_POST['nom_parent'] ?? ''),
                    securiser($_POST['telephone_parent'] ?? ''),
                    securiser($_POST['profession_parent'] ?? ''),
                    $classeId,
                    $photoFileName,
                    $_POST['date_inscription'],
                    $anneeScolaire
                ]);
                
                $eleveId = $db->lastInsertId();
                
                // Mettre à jour l'effectif de la classe
                if (!empty($_POST['classe_id'])) {
                    $stmt = $db->prepare("UPDATE classes SET effectif = effectif + 1 WHERE id = ?");
                    $stmt->execute([$_POST['classe_id']]);
                }
                
                logActivity('Ajout d\'un élève', 'eleves', $eleveId, $matricule);
                $_SESSION['success_message'] = 'Élève ajouté avec succès';
                redirect('eleves.php?success=added');
                break;
                
            case 'modifier':
                $eleveId = $_POST['eleve_id'];
                
                // Récupérer l'ancienne classe et photo
                $stmt = $db->prepare("SELECT classe_id, photo FROM eleves WHERE id = ?");
                $stmt->execute([$eleveId]);
                $oldData = $stmt->fetch();
                $oldClasseId = $oldData['classe_id'];
                $oldPhoto = $oldData['photo'];
                
                // Gestion de l'upload de la nouvelle photo
                $photoFileName = $oldPhoto; // Garder l'ancienne par défaut
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['photo']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        // Vérifier la taille
                        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                            throw new Exception("La photo ne doit pas dépasser 5 Mo");
                        }
                        
                        // Supprimer l'ancienne photo si elle existe
                        if ($oldPhoto && file_exists(__DIR__ . '/' . $oldPhoto)) {
                            unlink(__DIR__ . '/' . $oldPhoto);
                        }
                        
                        // Utiliser le matricule comme nom de fichier
                        $photoFileName = $_POST['matricule'] . '.' . $ext;
                        $destination = PHOTOS_DIR . '/' . $photoFileName;
                        
                        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                            throw new Exception("Erreur lors de l'upload de la photo");
                        }
                        
                        $photoFileName = 'assets/images/photos/' . $photoFileName;
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE eleves SET 
                        matricule = ?, nom = ?, prenom = ?, sexe = ?, date_naissance = ?, lieu_naissance = ?,
                        adresse = ?, telephone = ?, email = ?, nom_parent = ?, telephone_parent = ?,
                        profession_parent = ?, classe_id = ?, photo = ?, date_inscription = ?
                    WHERE id = ?
                ");
                
                $classeId = !empty($_POST['classe_id']) ? $_POST['classe_id'] : null;
                
                $stmt->execute([
                    securiser($_POST['matricule']),
                    securiser($_POST['nom']),
                    securiser($_POST['prenom']),
                    $_POST['sexe'],
                    $_POST['date_naissance'],
                    securiser($_POST['lieu_naissance'] ?? ''),
                    securiser($_POST['adresse'] ?? ''),
                    securiser($_POST['telephone'] ?? ''),
                    securiser($_POST['email'] ?? ''),
                    securiser($_POST['nom_parent'] ?? ''),
                    securiser($_POST['telephone_parent'] ?? ''),
                    securiser($_POST['profession_parent'] ?? ''),
                    $classeId,
                    $photoFileName,
                    $_POST['date_inscription'],
                    $eleveId
                ]);
                
                // Mettre à jour les effectifs si changement de classe
                $newClasseId = $classeId;
                if ($oldClasseId != $newClasseId) {
                    if ($oldClasseId) {
                        $stmt = $db->prepare("UPDATE classes SET effectif = effectif - 1 WHERE id = ?");
                        $stmt->execute([$oldClasseId]);
                    }
                    if ($newClasseId) {
                        $stmt = $db->prepare("UPDATE classes SET effectif = effectif + 1 WHERE id = ?");
                        $stmt->execute([$newClasseId]);
                    }
                }
                
                logActivity('Modification d\'un élève', 'eleves', $eleveId);
                $_SESSION['success_message'] = 'Élève modifié avec succès';
                redirect('eleves.php?success=updated');
                break;
                
            case 'supprimer':
                $eleveId = $_POST['eleve_id'];
                
                // Récupérer la classe et la photo
                $stmt = $db->prepare("SELECT classe_id, photo FROM eleves WHERE id = ?");
                $stmt->execute([$eleveId]);
                $data = $stmt->fetch();
                $classeId = $data['classe_id'];
                $photo = $data['photo'];
                
                // Supprimer la photo si elle existe
                if ($photo && file_exists(__DIR__ . '/' . $photo)) {
                    unlink(__DIR__ . '/' . $photo);
                }
                
                // Supprimer l'élève
                $stmt = $db->prepare("DELETE FROM eleves WHERE id = ?");
                $stmt->execute([$eleveId]);
                
                // Mettre à jour l'effectif
                if ($classeId) {
                    $stmt = $db->prepare("UPDATE classes SET effectif = effectif - 1 WHERE id = ?");
                    $stmt->execute([$classeId]);
                }
                
                logActivity('Suppression d\'un élève', 'eleves', $eleveId);
                $_SESSION['success_message'] = 'Élève supprimé avec succès';
                redirect('eleves.php?success=deleted');
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Erreur : ' . $e->getMessage();
        redirect('eleves.php?error=operation_failed');
    }
}

// Récupération des données
$search = $_GET['search'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$statut_filter = $_GET['statut'] ?? 'actif';

try {
    $db = getDB();
    
    // Récupérer les classes pour le filtre
    $stmt = $db->query("SELECT * FROM classes WHERE annee_scolaire = '$anneeScolaire' ORDER BY nom_classe");
    $classes = $stmt->fetchAll();
    
    // Construire la requête de recherche
    $query = "
        SELECT e.*, c.nom_classe 
        FROM eleves e
        LEFT JOIN classes c ON e.classe_id = c.id
        WHERE e.annee_scolaire = '$anneeScolaire'
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    if (!empty($classe_filter)) {
        $query .= " AND e.classe_id = ?";
        $params[] = $classe_filter;
    }
    
    if (!empty($statut_filter)) {
        $query .= " AND e.statut = ?";
        $params[] = $statut_filter;
    }
    
    $query .= " ORDER BY e.nom, e.prenom";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $eleves = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des élèves - <?php echo APP_NAME; ?></title>
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-people-fill me-2"></i>
                        Gestion des élèves
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjoutEleve">
                            <i class="bi bi-plus-circle me-1"></i>
                            Nouvel élève
                        </button>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtres de recherche -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="eleves.php" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Rechercher</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nom, prénom ou matricule..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Classe</label>
                                <select class="form-select" name="classe">
                                    <option value="">Toutes les classes</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>" 
                                                <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut">
                                    <option value="">Tous</option>
                                    <option value="actif" <?php echo ($statut_filter == 'actif') ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo ($statut_filter == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                    <option value="transfere" <?php echo ($statut_filter == 'transfere') ? 'selected' : ''; ?>>Transféré</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des élèves -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            Liste des élèves (<?php echo count($eleves); ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Matricule</th>
                                        <th>Nom complet</th>
                                        <th>Sexe</th>
                                        <th>Date naissance</th>
                                        <th>Classe</th>
                                        <th>Parent</th>
                                        <th>Téléphone</th>
                                        <th>Statut</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($eleves)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                                <p class="text-muted mb-0">Aucun élève trouvé</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($eleves as $eleve): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $photoPath = null;
                                                    if (!empty($eleve['photo']) && file_exists(__DIR__ . '/' . $eleve['photo'])) {
                                                        $photoPath = $eleve['photo'];
                                                    } else {
                                                        // Chercher par matricule
                                                        foreach (['jpg', 'jpeg', 'png'] as $ext) {
                                                            $p = 'assets/images/photos/' . $eleve['matricule'] . '.' . $ext;
                                                            if (file_exists(__DIR__ . '/' . $p)) {
                                                                $photoPath = $p;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($photoPath): ?>
                                                        <img src="<?php echo $photoPath; ?>" width="40" height="40" 
                                                             class="rounded-circle" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" 
                                                             style="width:40px; height:40px;">
                                                            <i class="bi bi-person"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($eleve['sexe'] == 'M'): ?>
                                                        <span class="badge bg-primary">M</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">F</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($eleve['date_naissance']); ?></td>
                                                <td>
                                                    <?php if ($eleve['nom_classe']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($eleve['nom_classe']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Non affecté</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($eleve['nom_parent']); ?></td>
                                                <td><?php echo htmlspecialchars($eleve['telephone_parent']); ?></td>
                                                <td>
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
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="voirEleve(<?php echo $eleve['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="modifierEleve(<?php echo $eleve['id']; ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="supprimerEleve(<?php echo $eleve['id']; ?>, '<?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Ajout Élève -->
    <div class="modal fade" id="modalAjoutEleve" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i>
                        Ajouter un nouvel élève
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="eleves.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Matricule *</label>
                                <input type="text" class="form-control" name="matricule" 
                                       placeholder="Ex: 241518491" required
                                       pattern="[0-9]{9,12}"
                                       title="Le matricule doit contenir 9 à 12 chiffres">
                                <small class="text-muted">Saisissez le matricule de l'élève (9-12 chiffres)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Photo de l'élève</label>
                                <input type="file" class="form-control" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif">
                                <small class="text-muted">Formats: JPG, PNG, GIF. Max: 5 Mo. Sera enregistrée dans assets/images/photos/</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="nom" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prénom *</label>
                                <input type="text" class="form-control" name="prenom" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sexe *</label>
                                <select class="form-select" name="sexe" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date de naissance *</label>
                                <input type="date" class="form-control" name="date_naissance" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lieu de naissance</label>
                                <input type="text" class="form-control" name="lieu_naissance">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Classe</label>
                                <select class="form-select" name="classe_id">
                                    <option value="">Aucune (à affecter plus tard)</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date d'inscription *</label>
                                <input type="date" class="form-control" name="date_inscription" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="adresse" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone élève</label>
                                <input type="tel" class="form-control" name="telephone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email élève</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom du parent/tuteur</label>
                                <input type="text" class="form-control" name="nom_parent">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone parent *</label>
                                <input type="tel" class="form-control" name="telephone_parent" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Profession parent</label>
                                <input type="text" class="form-control" name="profession_parent">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function voirEleve(id) {
            window.location.href = "voir_eleve.php?id=" + id;
        }

        function modifierEleve(id) {
            window.location.href = "modifier_eleve.php?id=" + id;
        }

        function supprimerEleve(id, nom) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'élève ' + nom + ' ?\n\nToutes ses notes et paiements seront également supprimés.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'eleves.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'supprimer';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'eleve_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>