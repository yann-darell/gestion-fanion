<?php
if (!isset($user)) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : ['nom_complet' => 'Utilisateur', 'role' => ''];
}
?>
<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-mortarboard-fill me-2"></i>
            <?php echo htmlspecialchars(getParam('nom_etablissement', 'Collège Le Fanion')); ?>
        </a>

        <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="d-flex align-items-center">
            <!-- Theme selector -->
            <div class="dropdown me-3">
                <a class="nav-link dropdown-toggle" href="#" id="themeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-palette-fill fs-5"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="themeDropdown">
                    <li><button class="dropdown-item" onclick="setTheme('light')"><i class="bi bi-brightness-high me-2"></i>Thème clair</button></li>
                    <li><button class="dropdown-item" onclick="setTheme('dark')"><i class="bi bi-moon-stars me-2"></i>Thème sombre</button></li>
                    <li><button class="dropdown-item" onclick="setTheme('auto')"><i class="bi bi-circle-half me-2"></i>Système</button></li>
                </ul>
            </div>

            <!-- Notifications -->
            <div class="dropdown me-3">
                <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">3</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle text-info me-2"></i>Nouveau bulletin généré<br><small class="text-muted">Il y a 5 min</small></a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-cash text-success me-2"></i>Paiement reçu<br><small class="text-muted">Il y a 1 heure</small></a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Notes en attente de saisie<br><small class="text-muted">Il y a 2 heures</small></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center" href="#">Voir toutes les notifications</a></li>
                </ul>
            </div>

            <!-- User menu -->
            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="me-2 text-end d-none d-md-block">
                        <div class="fw-bold"><?php echo htmlspecialchars($user['nom_complet'] ?? 'Utilisateur'); ?></div>
                        <small class="text-white-50"><?php echo htmlspecialchars(ucfirst($user['role'] ?? '')); ?></small>
                    </div>
                    <div class="user-avatar">
                        <i class="bi bi-person-circle fs-3"></i>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><h6 class="dropdown-header"><?php echo htmlspecialchars($user['nom_complet'] ?? 'Utilisateur'); ?></h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                    <li><a class="dropdown-item" href="parametres.php"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="login.php" class="d-inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Theme script: keep lightweight and robust -->
<script>
function setTheme(theme) {
    localStorage.setItem('theme', theme);
    applyTheme();
}
function applyTheme() {
    let theme = localStorage.getItem('theme') || 'auto';
    if (theme === 'dark' || (theme === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        document.body.classList.add('bg-dark', 'text-light');
        document.body.classList.remove('bg-light', 'text-dark');
    } else {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        document.body.classList.add('bg-light', 'text-dark');
        document.body.classList.remove('bg-dark', 'text-light');
    }
}
applyTheme();
if (window.matchMedia) {
    try { window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme); } catch(e) { /* older browsers */ }
}
</script>
