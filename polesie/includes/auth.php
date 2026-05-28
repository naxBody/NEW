<?php
/**
 * Класс аутентификации и авторизации пользователей
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Аутентификация пользователя
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Обновляем время последнего входа
                $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Записываем данные в сессию
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['logged_in'] = true;
                
                // Логируем вход
                $this->logAction('login', 'auth', $user['id']);
                
                return ['success' => true, 'user' => $user];
            }
            
            return ['success' => false, 'message' => 'Неверное имя пользователя или пароль'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()];
        }
    }
    
    /**
     * Выход из системы
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAction('logout', 'auth', $_SESSION['user_id']);
        }
        
        session_destroy();
        session_start();
    }
    
    /**
     * Проверка авторизации
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Получение текущего пользователя
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Проверка роли пользователя
     */
    public function hasRole($roles) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return $this->isLoggedIn() && in_array($_SESSION['role'], $roles);
    }
    
    /**
     * Проверка прав доступа к модулю
     */
    public function canAccessModule($module) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $adminRoles = ['admin'];
        $rolePermissions = [
            'dashboard' => ['admin', 'manager', 'technologist', 'quality_controller', 'warehouse_worker', 'operator'],
            'orders' => ['admin', 'manager', 'technologist'],
            'production' => ['admin', 'manager', 'technologist', 'operator'],
            'quality' => ['admin', 'manager', 'quality_controller', 'technologist'],
            'warehouse' => ['admin', 'manager', 'warehouse_worker'],
            'employees' => ['admin', 'manager'],
            'reports' => ['admin', 'manager', 'technologist', 'quality_controller'],
        ];
        
        if (isset($rolePermissions[$module])) {
            return in_array($_SESSION['role'], array_merge($adminRoles, $rolePermissions[$module]));
        }
        
        return false;
    }
    
    /**
     * Логирование действий пользователя
     */
    private function logAction($action, $module, $userId = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO system_log (user_id, action, module, ip_address) VALUES (?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->execute([$userId ?? ($_SESSION['user_id'] ?? null), $action, $module, $ip]);
        } catch (PDOException $e) {
            error_log("Ошибка логирования: " . $e->getMessage());
        }
    }
    
    /**
     * Регистрация нового пользователя
     */
    public function register($data) {
        try {
            // Проверка существования пользователя
            $checkStmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$data['username']]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Пользователь с таким именем уже существует'];
            }
            
            // Хеширование пароля
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Вставка нового пользователя
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, full_name, email, phone, role, department, position) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['username'],
                $passwordHash,
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['role'],
                $data['department'] ?? null,
                $data['position'] ?? null
            ]);
            
            $this->logAction('user_created', 'employees', $this->db->lastInsertId());
            
            return ['success' => true, 'message' => 'Пользователь успешно зарегистрирован'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()];
        }
    }
    
    /**
     * Обновление профиля пользователя
     */
    public function updateProfile($userId, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $userId
            ]);
            
            $this->logAction('profile_updated', 'employees', $userId);
            
            return ['success' => true, 'message' => 'Профиль успешно обновлен'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()];
        }
    }
    
    /**
     * Смена пароля
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            // Проверка старого пароля
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Неверный текущий пароль'];
            }
            
            // Обновление пароля
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$passwordHash, $userId]);
            
            $this->logAction('password_changed', 'auth', $userId);
            
            return ['success' => true, 'message' => 'Пароль успешно изменен'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()];
        }
    }
}
