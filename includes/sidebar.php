<?php
// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="sidebar-sticky">
        <ul class="nav flex-column nav-sidebar">
            <!-- Dashboard - Visible pour tous -->
            <?php if (canAccessModule('dashboard')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span class="ms-2">Tableau de bord</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <h6 class="nav-heading">
            Gestion Académique
        </h6>
        <ul class="nav flex-column nav-sidebar">
            <!-- Élèves -->
            <?php if (canAccessModule('eleves')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'eleves.php') ? 'active' : ''; ?>" href="eleves.php">
                    <i class="bi bi-people-fill"></i>
                    <span class="ms-2">Élèves</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Classes -->
            <?php if (canAccessModule('classes')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'classes.php') ? 'active' : ''; ?>" href="classes.php">
                    <i class="bi bi-building"></i>
                    <span class="ms-2">Classes</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Matières -->
            <?php if (canAccessModule('matieres')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'matieres.php') ? 'active' : ''; ?>" href="matieres.php">
                    <i class="bi bi-book-fill"></i>
                    <span class="ms-2">Matières</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <h6 class="nav-heading">
            Notes & Évaluations
        </h6>
        <ul class="nav flex-column nav-sidebar">
            <!-- Notes -->
            <?php if (canAccessModule('notes')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'notes.php') ? 'active' : ''; ?>" href="notes.php">
                    <i class="bi bi-journal-text"></i>
                    <span class="ms-2">Saisie des notes</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Bordereaux -->
            <?php if (canAccessModule('bordereaux')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'bordereaux.php') ? 'active' : ''; ?>" href="bordereaux.php">
                    <i class="bi bi-table"></i>
                    <span class="ms-2">Bordereaux</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Bulletins -->
            <?php if (canAccessModule('bulletins')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'bulletins.php') ? 'active' : ''; ?>" href="bulletins.php">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span class="ms-2">Bulletins</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <?php if (canAccessModule('dashboard')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'statistiques.php') ? 'active' : ''; ?>" href="statistiques.php">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span class="ms-2">Statistiques</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Finance - UNIQUEMENT pour Principale et Administrateur -->
        <?php if (canAccessModule('paiements')): ?>
        <h6 class="nav-heading">
            Finance
        </h6>
        <ul class="nav flex-column nav-sidebar">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'paiements.php') ? 'active' : ''; ?>" href="paiements.php">
                    <i class="bi bi-cash-stack"></i>
                    <span class="ms-2">Paiements</span>
                    <span class="badge bg-warning text-dark ms-2" style="font-size: 0.7em;">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'recus.php') ? 'active' : ''; ?>" href="recus.php">
                    <i class="bi bi-receipt"></i>
                    <span class="ms-2">Reçus</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- Administration - UNIQUEMENT pour Principale et Administrateur -->
        <?php if (canAccessModule('utilisateurs') || canAccessModule('parametres')): ?>
        <h6 class="nav-heading">
            Administration
        </h6>
        <ul class="nav flex-column nav-sidebar">
            <?php if (canAccessModule('utilisateurs')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'utilisateurs.php') ? 'active' : ''; ?>" href="utilisateurs.php">
                    <i class="bi bi-person-gear"></i>
                    <span class="ms-2">Utilisateurs</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (canAccessModule('parametres')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'parametres.php') ? 'active' : ''; ?>" href="parametres.php">
                    <i class="bi bi-gear-fill"></i>
                    <span class="ms-2">Paramètres</span>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'logs.php') ? 'active' : ''; ?>" href="logs.php">
                    <i class="bi bi-clock-history"></i>
                    <span class="ms-2">Historique</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <hr class="my-3">

        <ul class="nav flex-column nav-sidebar">
            <li class="nav-item">
                <a class="nav-link" href="profil.php">
                    <i class="bi bi-person-circle"></i>
                    <span class="ms-2">Mon profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contact.php">
                    <i class="bi bi-envelope-fill"></i>
                    <span class="ms-2">Contact</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="a_propos.php">
                    <i class="bi bi-info-circle-fill"></i>
                    <span class="ms-2">À propos</span>
                </a>
            </li>
        </ul>
        
        <!-- Informations utilisateur -->
        <div class="mt-4 px-3 py-2" style="background: #f9fafb; border-radius: 10px; font-size: 0.85em;">
            <div class="text-muted mb-1"><i class="bi bi-person-circle me-1"></i> Connecté en tant que :</div>
            <div class="fw-bold"><?php echo htmlspecialchars(getCurrentUser()['nom_complet'] ?? 'Utilisateur'); ?></div>
            <div class="text-muted">
                <i class="bi bi-shield-check me-1"></i>
                <?php 
                $role = getCurrentUser()['role'] ?? '';
                echo match($role) {
                    'principale' => 'Principale',
                    'directeur_etudes' => 'Directeur des Études',
                    'administrateur' => 'Administrateur',
                    'secretaire' => 'Secrétaire',
                    default => ucfirst($role)
                };
                ?>
            </div>
        </div>
    </div>
</nav>