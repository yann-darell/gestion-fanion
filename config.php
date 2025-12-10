<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * =============================================
 * FICHIER DE CONFIGURATION - COLLÈGE LE FANION
 * =============================================
 */

// Empêcher l'accès direct (seulement si pas déjà défini)
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// =============================================
// CONFIGURATION BASE DE DONNÉES
// =============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'fanion_notes');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =============================================
// CONFIGURATION APPLICATION
// =============================================
define('APP_NAME', 'Collège Le Fanion - Gestion des Notes');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/fanion_notes');
define('APP_PATH', __DIR__);

// =============================================
// CHEMINS DES DOSSIERS
// =============================================
define('UPLOADS_DIR', APP_PATH . '/uploads');
define('BULLETINS_DIR', UPLOADS_DIR . '/bulletins');
define('BORDEREAUX_DIR', UPLOADS_DIR . '/bordereaux');
define('RECU_DIR', UPLOADS_DIR . '/recus');

// IMPORTANT: Photos stockées dans assets/images/photos
if (!defined('PHOTOS_DIR')) {
    define('PHOTOS_DIR', APP_PATH . '/assets/images/photos');
}

// =============================================
// PARAMÈTRES DE SESSION
// =============================================
define('SESSION_NAME', 'fanion_session');
define('SESSION_LIFETIME', 3600); // 1 heure

// =============================================
// PARAMÈTRES DE SÉCURITÉ
// =============================================
define('HASH_ALGO', PASSWORD_DEFAULT);
define('HASH_COST', 10);

// =============================================
// PARAMÈTRES D'UPLOAD DE FICHIERS
// =============================================
define('MAX_FILE_SIZE', 5242880); // 5 Mo en octets (augmenté pour les photos)
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);

// =============================================
// CLASSE DE CONNEXION À LA BASE DE DONNÉES
// =============================================
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Empêcher le clonage
    private function __clone() {}
    
    // Empêcher la désérialisation
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// =============================================
// FONCTIONS UTILITAIRES
// =============================================

/**
 * Obtenir la connexion à la base de données
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Sécuriser les chaînes de caractères
 */
function securiser($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Rediriger vers une page
 */
function redirect($url) {
    // Si l'URL ne commence pas par http, ajouter le chemin de base
    if (!preg_match('/^https?:\/\//', $url)) {
        // Vérifier si on est dans un sous-dossier
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && !str_starts_with($url, $scriptName)) {
            $url = $scriptName . '/' . ltrim($url, '/');
        }
    }
    
    // Nettoyer les buffers de sortie
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    header("Location: " . $url);
    exit();
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtenir l'utilisateur connecté
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nom_utilisateur, nom_complet, role, email FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Vérifier si l'utilisateur a une permission spécifique
 */
function hasPermission($module, $action = 'view') {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // L'administrateur a tous les droits
    if ($user['role'] === 'administrateur') return true;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT can_view, can_create, can_edit, can_delete, can_print
            FROM permissions
            WHERE role = ? AND module = ?
        ");
        $stmt->execute([$user['role'], $module]);
        $perm = $stmt->fetch();
        
        if (!$perm) return false;
        
        switch ($action) {
            case 'view': return $perm['can_view'];
            case 'create': return $perm['can_create'];
            case 'edit': return $perm['can_edit'];
            case 'delete': return $perm['can_delete'];
            case 'print': return $perm['can_print'];
            default: return false;
        }
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifier le rôle de l'utilisateur
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Vérifier si l'utilisateur peut accéder à un module
 */
function canAccessModule($module) {
    return hasPermission($module, 'view');
}

/**
 * Bloquer l'accès si l'utilisateur n'a pas la permission
 */
function requirePermission($module, $action = 'view') {
    if (!hasPermission($module, $action)) {
        $_SESSION['error_message'] = "Vous n'avez pas l'autorisation d'accéder à cette fonctionnalité.";
        redirect('dashboard.php?error=permission_denied');
        exit();
    }
}

/**
 * Logger une activité
 */
function logActivity($action, $table = null, $id = null, $details = null) {
    if (!isLoggedIn()) return;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO logs_activites (utilisateur_id, action, table_concernee, id_enregistrement, details, adresse_ip)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $table,
            $id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // Ignorer les erreurs de log
    }
}

/**
 * Obtenir un paramètre système
 */
function getParam($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valeur_param FROM parametres WHERE cle_param = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['valeur_param'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Définir un paramètre système
 */
function setParam($key, $value, $description = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO parametres (cle_param, valeur_param, description_param)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valeur_param = ?, description_param = COALESCE(?, description_param)
        ");
        $stmt->execute([$key, $value, $description, $value, $description]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Calculer la mention selon la moyenne
 */
function getMention($moyenne) {
    if ($moyenne >= floatval(getParam('mention_excellent', 16))) {
        return 'Excellent';
    } elseif ($moyenne >= floatval(getParam('mention_tres_bien', 14))) {
        return 'Très Bien';
    } elseif ($moyenne >= floatval(getParam('mention_bien', 12))) {
        return 'Bien';
    } elseif ($moyenne >= floatval(getParam('mention_assez_bien', 10))) {
        return 'Assez Bien';
    } else {
        return 'Passable';
    }
}

/**
 * Formater une date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Formater un montant
 */
function formatMontant($montant) {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

/**
 * Générer un matricule unique
 */
function genererMatricule($annee_scolaire = null) {
    if (!$annee_scolaire) {
        $annee_scolaire = getParam('annee_scolaire_actuelle', date('Y'));
    }
    
    $annee = explode('-', $annee_scolaire)[0];
    $random = strtoupper(substr(uniqid(), -6));
    
    return "LF{$annee}{$random}";
}

/**
 * Créer les dossiers nécessaires
 */
function createDirectories() {
    $dirs = [
        UPLOADS_DIR, 
        BULLETINS_DIR, 
        BORDEREAUX_DIR, 
        RECU_DIR, 
        PHOTOS_DIR,
        APP_PATH . '/assets',
        APP_PATH . '/assets/images'
    ];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

// Créer les dossiers au démarrage
createDirectories();

// =============================================
// GESTION DES ERREURS
// =============================================
// En développement : afficher les erreurs (déjà défini au début du fichier)
// Les lignes ci-dessous sont commentées car déjà définies en haut
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// En production, décommenter ces lignes :
// error_reporting(0);
// ini_set('display_errors', 0);

// =============================================
// FUSEAU HORAIRE
// =============================================
date_default_timezone_set('Africa/Douala');

// =============================================
// FIN DE LA CONFIGURATION
// ========================================