<?php
// assets/app/Auth.php
require_once __DIR__ . '/db_connect.php';

class Auth {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function register($email, $password, $full_name, $phone = null, $dob = null) {
        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32));
        
        // Insert user
        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, password_hash, full_name, phone, date_of_birth, verification_token)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([$email, $password_hash, $full_name, $phone, $dob, $verification_token]);
            $user_id = $this->pdo->lastInsertId();
            
            // Create session
            $this->createSession($user_id, $email, $full_name);
            
            // Send verification email (optional)
            // $this->sendVerificationEmail($email, $verification_token);
            
            return ['success' => true, 'user_id' => $user_id];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        $stmt = $this->pdo->prepare("
            SELECT id, email, password_hash, full_name 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Update last login
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Create session
        $this->createSession($user['id'], $user['email'], $user['full_name']);
        
        return ['success' => true, 'user' => $user];
    }
    
private function createSession($user_id, $email, $full_name) {
    // 👇 NEW: Clear any existing admin session
    if (isset($_SESSION['admin_id'])) {
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
    }

    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $full_name;
    $_SESSION['logged_in'] = true;
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}
    
    public function logout() {
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, email, full_name, phone, date_of_birth 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateProfile($user_id, $data) {
        $allowed_fields = ['full_name', 'phone', 'date_of_birth'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    public function changePassword($user_id, $current_password, $new_password) {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        
        if ($stmt->execute([$new_hash, $user_id])) {
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Failed to update password'];
    }
    
    public function requestPasswordReset($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        $reset_token = bin2hex(random_bytes(32));
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET reset_token = ?, reset_expires = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$reset_token, $reset_expires, $user['id']])) {
            // In production, send email with reset link
            return [
                'success' => true, 
                'reset_token' => $reset_token, // For testing only
                'message' => 'Password reset instructions sent to your email'
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to process reset request'];
    }
    
    public function resetPassword($token, $new_password) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM users 
            WHERE reset_token = ? AND reset_expires > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, reset_token = NULL, reset_expires = NULL 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$new_hash, $user['id']])) {
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Failed to reset password'];
    }
}