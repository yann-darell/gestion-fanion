<?php
// Vue pour l'affichage de la saisie des notes
// Ce fichier sera inclus par le contrôleur NotesController

// Variables attendues :
// $classes, $matieres, $eleves, $classeId, $matiereId, $periode, $error
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des notes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="mb-4"><i class="bi bi-journal-text me-2"></i>Saisie des notes</h1>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <!-- Formulaire de sélection et de saisie des notes -->
                <!-- ... (reprendre le code HTML du formulaire depuis notes.php) ... -->
            </main>
        </div>
    </div>
</body>
</html>
