-- =============================================
-- MISE A JOUR DES ROLES ET PERMISSIONS
-- =============================================

USE fanion_notes;

-- Etape 1: Modifier la table utilisateurs pour supporter les nouveaux roles
ALTER TABLE utilisateurs 
MODIFY COLUMN role ENUM('administrateur', 'principale', 'directeur_etudes', 'secretaire') DEFAULT 'secretaire';

-- Etape 2: Ajouter un utilisateur Principale
INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, email, actif)
VALUES ('principale', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Principale de l etablissement', 'principale', 'principale@lefanion.cm', 1)
ON DUPLICATE KEY UPDATE nom_complet = 'Principale de l etablissement';

-- Etape 3: Ajouter un utilisateur Directeur des etudes
INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, email, actif)
VALUES ('directeur', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Directeur des Etudes', 'directeur_etudes', 'directeur@lefanion.cm', 1)
ON DUPLICATE KEY UPDATE nom_complet = 'Directeur des Etudes';

-- Etape 4: Creer une table de permissions
DROP TABLE IF EXISTS permissions;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    can_view BOOLEAN DEFAULT FALSE,
    can_create BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_print BOOLEAN DEFAULT FALSE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_module (role, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DEFINIR LES PERMISSIONS PAR ROLE
-- =============================================

-- Permissions pour PRINCIPALE (Acces total)
INSERT INTO permissions (role, module, can_view, can_create, can_edit, can_delete, can_print) VALUES
('principale', 'dashboard', 1, 0, 0, 0, 1),
('principale', 'eleves', 1, 1, 1, 1, 1),
('principale', 'classes', 1, 1, 1, 1, 1),
('principale', 'matieres', 1, 1, 1, 1, 1),
('principale', 'notes', 1, 1, 1, 1, 1),
('principale', 'bordereaux', 1, 0, 0, 0, 1),
('principale', 'bulletins', 1, 0, 0, 0, 1),
('principale', 'paiements', 1, 1, 1, 1, 1),
('principale', 'utilisateurs', 1, 1, 1, 1, 0),
('principale', 'parametres', 1, 1, 1, 0, 0);

-- Permissions pour DIRECTEUR DES ETUDES (Tout sauf paiements)
INSERT INTO permissions (role, module, can_view, can_create, can_edit, can_delete, can_print) VALUES
('directeur_etudes', 'dashboard', 1, 0, 0, 0, 1),
('directeur_etudes', 'eleves', 1, 1, 1, 1, 1),
('directeur_etudes', 'classes', 1, 1, 1, 1, 1),
('directeur_etudes', 'matieres', 1, 1, 1, 1, 1),
('directeur_etudes', 'notes', 1, 1, 1, 1, 1),
('directeur_etudes', 'bordereaux', 1, 0, 0, 0, 1),
('directeur_etudes', 'bulletins', 1, 0, 0, 0, 1),
('directeur_etudes', 'paiements', 0, 0, 0, 0, 0),
('directeur_etudes', 'utilisateurs', 0, 0, 0, 0, 0),
('directeur_etudes', 'parametres', 0, 0, 0, 0, 0);

-- Permissions pour ADMINISTRATEUR (Acces total)
INSERT INTO permissions (role, module, can_view, can_create, can_edit, can_delete, can_print) VALUES
('administrateur', 'dashboard', 1, 0, 0, 0, 1),
('administrateur', 'eleves', 1, 1, 1, 1, 1),
('administrateur', 'classes', 1, 1, 1, 1, 1),
('administrateur', 'matieres', 1, 1, 1, 1, 1),
('administrateur', 'notes', 1, 1, 1, 1, 1),
('administrateur', 'bordereaux', 1, 0, 0, 0, 1),
('administrateur', 'bulletins', 1, 0, 0, 0, 1),
('administrateur', 'paiements', 1, 1, 1, 1, 1),
('administrateur', 'utilisateurs', 1, 1, 1, 1, 0),
('administrateur', 'parametres', 1, 1, 1, 1, 0);

-- Permissions pour SECRETAIRE (Acces limite)
INSERT INTO permissions (role, module, can_view, can_create, can_edit, can_delete, can_print) VALUES
('secretaire', 'dashboard', 1, 0, 0, 0, 1),
('secretaire', 'eleves', 1, 1, 1, 0, 1),
('secretaire', 'classes', 1, 0, 0, 0, 1),
('secretaire', 'matieres', 1, 0, 0, 0, 1),
('secretaire', 'notes', 1, 1, 0, 0, 1),
('secretaire', 'bordereaux', 1, 0, 0, 0, 1),
('secretaire', 'bulletins', 1, 0, 0, 0, 1),
('secretaire', 'paiements', 1, 1, 0, 0, 1),
('secretaire', 'utilisateurs', 0, 0, 0, 0, 0),
('secretaire', 'parametres', 0, 0, 0, 0, 0);

-- =============================================
-- VERIFICATION : Afficher les utilisateurs crees
-- =============================================
SELECT 
    '=== UTILISATEURS CREES ===' as info,
    nom_utilisateur as utilisateur, 
    'admin123' as mot_de_passe,
    role,
    email
FROM utilisateurs 
WHERE nom_utilisateur IN ('admin', 'principale', 'directeur')
ORDER BY 
    CASE 
        WHEN nom_utilisateur = 'principale' THEN 1
        WHEN nom_utilisateur = 'directeur' THEN 2
        WHEN nom_utilisateur = 'admin' THEN 3
    END;

-- =============================================
-- VERIFICATION : Afficher les permissions cles
-- =============================================
SELECT 
    '=== VERIFICATION DES PERMISSIONS ===' as info,
    role as Role,
    module as Module,
    CASE WHEN can_view = 1 THEN 'OUI' ELSE 'NON' END as Voir,
    CASE WHEN can_create = 1 THEN 'OUI' ELSE 'NON' END as Creer,
    CASE WHEN can_edit = 1 THEN 'OUI' ELSE 'NON' END as Modifier,
    CASE WHEN can_delete = 1 THEN 'OUI' ELSE 'NON' END as Supprimer
FROM permissions
WHERE module IN ('paiements', 'eleves', 'notes')
ORDER BY 
    CASE 
        WHEN role = 'principale' THEN 1
        WHEN role = 'directeur_etudes' THEN 2
        WHEN role = 'administrateur' THEN 3
    END,
    module;

-- =============================================
-- MESSAGE DE SUCCES
-- =============================================
SELECT '✓ Mise a jour terminee avec succes !' as MESSAGE,
       'Vous pouvez maintenant vous connecter avec les nouveaux comptes' as INFORMATION;