-- =============================================
-- BASE DE DONNÉES - COLLÈGE LE FANION
-- Système de gestion des notes et paiements
-- =============================================

CREATE DATABASE IF NOT EXISTS fanion_notes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fanion_notes;

-- =============================================
-- TABLE: utilisateurs (Authentification)
-- =============================================
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur VARCHAR(50) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom_complet VARCHAR(100) NOT NULL,
    role ENUM('administrateur', 'principale', 'directeur_etudes', 'secretaire') DEFAULT 'secretaire',
    email VARCHAR(100),
    telephone VARCHAR(20),
    actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL,
    INDEX idx_nom_utilisateur (nom_utilisateur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: classes
-- =============================================
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_classe VARCHAR(50) NOT NULL,
    niveau VARCHAR(20) NOT NULL,
    annee_scolaire VARCHAR(20) NOT NULL,
    effectif INT DEFAULT 0,
    frais_scolarite DECIMAL(10,2) DEFAULT 0,
    frais_inscription DECIMAL(10,2) DEFAULT 0,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_classe (nom_classe, annee_scolaire),
    INDEX idx_niveau (niveau),
    INDEX idx_annee (annee_scolaire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: matieres
-- =============================================
CREATE TABLE matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_matiere VARCHAR(100) NOT NULL,
    code_matiere VARCHAR(20),
    coefficient INT DEFAULT 1,
    categorie VARCHAR(50),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code_matiere)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: classe_matiere (Association)
-- =============================================
CREATE TABLE classe_matiere (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classe_id INT NOT NULL,
    matiere_id INT NOT NULL,
    coefficient INT DEFAULT 1,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    UNIQUE KEY unique_classe_matiere (classe_id, matiere_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: eleves
-- =============================================
CREATE TABLE eleves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    sexe ENUM('M', 'F') NOT NULL,
    date_naissance DATE NOT NULL,
    lieu_naissance VARCHAR(100),
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    nom_parent VARCHAR(100),
    telephone_parent VARCHAR(20),
    profession_parent VARCHAR(100),
    classe_id INT,
    photo VARCHAR(255),
    statut ENUM('actif', 'inactif', 'transfere', 'abandonne') DEFAULT 'actif',
    date_inscription DATE NOT NULL,
    annee_scolaire VARCHAR(20) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
    INDEX idx_matricule (matricule),
    INDEX idx_nom (nom, prenom),
    INDEX idx_classe (classe_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: notes
-- =============================================
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    matiere_id INT NOT NULL,
    classe_id INT NOT NULL,
    periode ENUM('sequence1', 'sequence2', 'trimestre1', 'sequence3', 'sequence4', 'trimestre2', 'sequence5', 'sequence6', 'trimestre3') NOT NULL,
    annee_scolaire VARCHAR(20) NOT NULL,
    note DECIMAL(5,2),
    appreciation TEXT,
    date_saisie TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    saisi_par INT,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (saisi_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    UNIQUE KEY unique_note (eleve_id, matiere_id, periode, annee_scolaire),
    INDEX idx_eleve_periode (eleve_id, periode),
    INDEX idx_classe_periode (classe_id, periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: moyennes (Calculs automatiques)
-- =============================================
CREATE TABLE moyennes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    classe_id INT NOT NULL,
    periode ENUM('sequence1', 'sequence2', 'trimestre1', 'sequence3', 'sequence4', 'trimestre2', 'sequence5', 'sequence6', 'trimestre3', 'annuelle') NOT NULL,
    annee_scolaire VARCHAR(20) NOT NULL,
    moyenne_generale DECIMAL(5,2),
    total_points DECIMAL(10,2),
    total_coefficients INT,
    rang INT,
    mention VARCHAR(50),
    appreciation TEXT,
    date_calcul TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_moyenne (eleve_id, periode, annee_scolaire),
    INDEX idx_classe_periode (classe_id, periode),
    INDEX idx_rang (rang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: paiements
-- =============================================
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    type_paiement ENUM('inscription', 'scolarite', 'examen', 'autre') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    mode_paiement ENUM('especes', 'virement', 'cheque', 'mobile_money') DEFAULT 'especes',
    reference_paiement VARCHAR(100),
    date_paiement DATE NOT NULL,
    periode_concernee VARCHAR(50),
    observation TEXT,
    recu_par INT,
    numero_recu VARCHAR(50) UNIQUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (recu_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_eleve (eleve_id),
    INDEX idx_date (date_paiement),
    INDEX idx_numero_recu (numero_recu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: bulletins_generes (Historique)
-- =============================================
CREATE TABLE bulletins_generes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleve_id INT NOT NULL,
    classe_id INT NOT NULL,
    periode VARCHAR(50) NOT NULL,
    annee_scolaire VARCHAR(20) NOT NULL,
    fichier_pdf VARCHAR(255),
    date_generation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    genere_par INT,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (genere_par) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_eleve_periode (eleve_id, periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: parametres (Configuration système)
-- =============================================
CREATE TABLE parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle_param VARCHAR(100) UNIQUE NOT NULL,
    valeur_param TEXT,
    description_param TEXT,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: logs_activites (Traçabilité)
-- =============================================
CREATE TABLE logs_activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(100) NOT NULL,
    table_concernee VARCHAR(50),
    id_enregistrement INT,
    details TEXT,
    adresse_ip VARCHAR(45),
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
('Expression Écrite', 'EXP_ECR', 2, 'Français'),
('Expression Orale', 'EXP_ORA', 1, 'Français'),
('Informatique', 'INFO', 2, 'Sciences'),
('Mathématiques', 'MATHS', 4, 'Sciences'),
('SVT/EEHB', 'SVT', 2, 'Sciences'),
('ECM', 'ECM', 2, 'Sciences Sociales'),
('Géographie', 'GEO', 2, 'Sciences Sociales'),
('Histoire', 'HIST', 2, 'Sciences Sociales'),
('EAC', 'EAC', 2, 'Arts'),
('EPS', 'EPS', 1, 'Sport'),
('ESF', 'ESF', 1, 'Pratique'),
('LCN', 'LCN', 1, 'Langues'),
('TM', 'TM', 1, 'Pratique');

-- Paramètres du système
INSERT INTO parametres (cle_param, valeur_param, description_param) VALUES
('nom_etablissement', 'Collège Le Fanion', 'Nom de l\'établissement'),
('annee_scolaire_actuelle', '2024-2025', 'Année scolaire en cours'),
('adresse_etablissement', 'Douala, Cameroun', 'Adresse de l\'établissement'),
('telephone_etablissement', '+237 XXX XXX XXX', 'Téléphone de l\'établissement'),
('email_etablissement', 'contact@lefanion.cm', 'Email de l\'établissement'),
('mention_excellent', '16', 'Note minimale pour mention Excellent'),
('mention_tres_bien', '14', 'Note minimale pour mention Très Bien'),
('mention_bien', '12', 'Note minimale pour mention Bien'),
('mention_assez_bien', '10', 'Note minimale pour mention Assez Bien'),
('note_passage', '10', 'Note minimale de passage');

-- =============================================
-- VUES UTILES
-- =============================================

-- Vue: Liste des élèves avec leur classe
CREATE VIEW vue_eleves_classes AS
SELECT 
    e.id,
    e.matricule,
    CONCAT(e.nom, ' ', e.prenom) AS nom_complet,
    e.sexe,
    e.date_naissance,
    e.telephone_parent,
    c.nom_classe,
    c.niveau,
    e.statut,
    e.annee_scolaire
FROM eleves e
LEFT JOIN classes c ON e.classe_id = c.id;

-- Vue: Statistiques par classe
CREATE VIEW vue_statistiques_classes AS
SELECT 
    c.id AS classe_id,
    c.nom_classe,
    c.niveau,
    COUNT(e.id) AS nombre_eleves,
    COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) AS garcons,
    COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) AS filles,
    c.annee_scolaire
FROM classes c
LEFT JOIN eleves e ON c.id = e.classe_id AND e.statut = 'actif'
GROUP BY c.id, c.nom_classe, c.niveau, c.annee_scolaire;

-- =============================================
-- FIN DE LA STRUCTURE
-- =============================================