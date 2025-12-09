<?php
/**
 * =============================================
 * SYSTÈME D'AUTHENTIFICATION - COLLÈGE LE FANION
 * =============================================
 */

define('APP_INIT', true);
require_once 'config.php';

// Démarrer la session
session_name(SESSION_NAME);
session_start();

// =============================================
// CLASSE D'AUTHENTIFICATION
// =============================================
class Auth {
    
    /**
     * Connexion de l'utilisateur
     */
    public static function login($username, $password) {
        try {
            $db = getDB();
            
            // Récupérer l'utilisateur
            $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE nom_utilisateur = ? AND actif = 1");
            $stmt->execute([securiser($username)]);
            $user = $stmt->fetch();
            
            // Vérifier si l'utilisateur existe et le mot de passe est correct
            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nom_utilisateur'];
                $_SESSION['nom_complet'] = $user['nom_complet'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Mettre à jour la dernière connexion
                $stmt = $db->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Logger l'activité
                logActivity('Connexion réussie');
                
                return [
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'user' => $user
                ];
            } else {
                // Logger la tentative échouée
                if ($user) {
                    logActivity('Tentative de connexion échouée - Mot de passe incorrect');
                } else {
                    logActivity('Tentative de connexion échouée - Utilisateur inexistant');
                }
                
                return [
                    'success' => false,
                    'message' => 'Nom d\'utilisateur ou mot de passe incorrect'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Déconnexion de l'utilisateur
     */
    public static function logout() {
        // Logger l'activité
        logActivity('Déconnexion');
        
        // Détruire la session
        session_unset();
        session_destroy();
        
        // Démarrer une nouvelle session
        session_start();
        
        return [
            'success' => true,
            'message' => 'Déconnexion réussie'
        ];
    }
    
    /**
     * Vérifier si la session est valide
     */
    public static function checkSession() {
        if (!isLoggedIn()) {
            return false;
        }
        
        // Vérifier le timeout de session
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            self::logout();
            return false;
        }
        
        // Rafraîchir le temps de connexion
        $_SESSION['login_time'] = time();
        
        return true;
    }
    
    /**
     * Protéger une page (redirection si non connecté)
     */
    public static function requireLogin() {
        if (!self::checkSession()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            redirect('login.php?error=session_expired');
        }
    }
    
    /**
     * Vérifier le rôle requis
     */
    public static function requireRole($role) {
        self::requireLogin();
        
        if (!hasRole($role)) {
            redirect('index.php?error=access_denied');
        }
    }
    
    /**
     * Créer un nouvel utilisateur
     */
    public static function createUser($data) {
        try {
            $db = getDB();
            
            // Vérifier si l'utilisateur existe déjà
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE nom_utilisateur = ?");
            $stmt->execute([$data['nom_utilisateur']]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Ce nom d\'utilisateur existe déjà'
                ];
            }
            
            // Hasher le mot de passe
            $hashedPassword = password_hash($data['mot_de_passe'], HASH_ALGO, ['cost' => HASH_COST]);
            
            // Insérer l'utilisateur
            $stmt = $db->prepare("
                INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role, email, telephone)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                securiser($data['nom_utilisateur']),
                $hashedPassword,
                securiser($data['nom_complet']),
                $data['role'] ?? 'secretaire',
                securiser($data['email'] ?? ''),
                securiser($data['telephone'] ?? '')
            ]);
            
            $userId = $db->lastInsertId();
            
            // Logger l'activité
            logActivity('Création d\'utilisateur', 'utilisateurs', $userId, $data['nom_utilisateur']);
            
            return [
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'user_id' => $userId
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Changer le mot de passe
     */
    public static function changePassword($userId, $oldPassword, $newPassword) {
        try {
            $db = getDB();
            
            // Récupérer l'utilisateur
            $stmt = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
            
            // Vérifier l'ancien mot de passe
            if (!password_verify($oldPassword, $user['mot_de_passe'])) {
                return [
                    'success' => false,
                    'message' => 'Ancien mot de passe incorrect'
                ];
            }
            
            // Hasher le nouveau mot de passe
            $hashedPassword = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
            
            // Mettre à jour
            $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            // Logger l'activité
            logActivity('Changement de mot de passe', 'utilisateurs', $userId);
            
            return [
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du changement : ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Réinitialiser le mot de passe (pour administrateur)
     */
    public static function resetPassword($userId, $newPassword) {
        try {
            $db = getDB();
            
            // Hasher le nouveau mot de passe
            $hashedPassword = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
            
            // Mettre à jour
            $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            // Logger l'activité
            logActivity('Réinitialisation de mot de passe', 'utilisateurs', $userId);
            
            return [
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation : ' . $e->getMessage()
            ];
        }
    }
}

// =============================================
// TRAITEMENT DES ACTIONS
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $result = Auth::login($username, $password);
            
            if ($result['success']) {
                // Rediriger vers la page demandée ou le dashboard
                $redirectUrl = $_SESSION['redirect_url'] ?? 'dashboard.php';
                unset($_SESSION['redirect_url']);
                redirect($redirectUrl);
            } else {
                $_SESSION['error_message'] = $result['message'];
                redirect('login.php?error=login_failed');
            }
            break;
            
        case 'logout':
            Auth::logout();
            redirect('login.php?success=logged_out');
            break;
            
        case 'change_password':
            Auth::requireLogin();
            
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if ($newPassword !== $confirmPassword) {
                $_SESSION['error_message'] = 'Les mots de passe ne correspondent pas';
                redirect('profile.php?error=password_mismatch');
            }
            
            $result = Auth::changePassword($_SESSION['user_id'], $oldPassword, $newPassword);
            
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
                redirect('profile.php?success=password_changed');
            } else {
                $_SESSION['error_message'] = $result['message'];
                redirect('profile.php?error=password_change_failed');
            }
            break;
    }
}

// =============================================
// FIN DU FICHIER D'AUTHENTIFICATION
// =============================================