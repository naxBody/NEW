<?php
/**
 * Функции-помощники
 */

/**
 * Подключение к базе данных
 */
function getDb() {
    return Database::getInstance()->getConnection();
}

/**
 * Проверка авторизации
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Получение текущего пользователя
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Перенаправление с учетом базового пути
 */
function redirect($url) {
    // Если URL начинается с http, /polesie или содержит BASE_URL, оставляем как есть
    if (strpos($url, 'http') !== 0 && strpos($url, BASE_URL) !== 0) {
        $url = BASE_URL . $url;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Безопасный вывод
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Форматирование даты
 */
function formatDate($date, $format = 'd.m.Y H:i') {
    if (!$date) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Форматирование цены
 */
function formatPrice($price, $currency = 'BYN') {
    return number_format((float)$price, 2, '.', ' ') . ' ' . $currency;
}

/**
 * Получение статуса заказа (текстовое представление)
 */
function getOrderStatusText($status) {
    $statuses = [
        'new' => ['text' => 'Новый', 'class' => 'badge-info'],
        'confirmed' => ['text' => 'Подтвержден', 'class' => 'badge-success'],
        'in_production' => ['text' => 'В производстве', 'class' => 'badge-warning'],
        'quality_check' => ['text' => 'Проверка качества', 'class' => 'badge-primary'],
        'ready' => ['text' => 'Готов', 'class' => 'badge-success'],
        'shipped' => ['text' => 'Отгружен', 'class' => 'badge-info'],
        'completed' => ['text' => 'Завершен', 'class' => 'badge-secondary'],
        'cancelled' => ['text' => 'Отменен', 'class' => 'badge-danger']
    ];
    
    return $statuses[$status] ?? ['text' => $status, 'class' => 'badge-secondary'];
}

/**
 * Получение статуса задания (текстовое представление)
 */
function getTaskStatusText($status) {
    $statuses = [
        'planned' => ['text' => 'Запланировано', 'class' => 'badge-info'],
        'released' => ['text' => 'Выпущено', 'class' => 'badge-success'],
        'in_progress' => ['text' => 'В работе', 'class' => 'badge-warning'],
        'paused' => ['text' => 'Приостановлено', 'class' => 'badge-secondary'],
        'completed' => ['text' => 'Завершено', 'class' => 'badge-success'],
        'rejected' => ['text' => 'Отклонено', 'class' => 'badge-danger']
    ];
    
    return $statuses[$status] ?? ['text' => $status, 'class' => 'badge-secondary'];
}

/**
 * Получение роли (текстовое представление)
 */
function getRoleText($role) {
    $roles = [
        'admin' => 'Администратор',
        'manager' => 'Менеджер',
        'technologist' => 'Технолог',
        'quality_controller' => 'Контролер ОТК',
        'warehouse_worker' => 'Кладовщик',
        'operator' => 'Оператор'
    ];
    
    return $roles[$role] ?? $role;
}

/**
 * Генерация CSRF токена
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверка CSRF токена
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Логирование действий
 */
function logAction($action, $module, $recordId = null) {
    $db = getDb();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $db->prepare("INSERT INTO system_log (user_id, action, module, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $module, $recordId, $ip]);
    } catch (PDOException $e) {
        error_log("Ошибка логирования: " . $e->getMessage());
    }
}

/**
 * Загрузка настроек системы
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $db = getDb();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Получение списка пользователей для выпадающего списка
 */
function getUsersList($roleFilter = null) {
    $db = getDb();
    
    if ($roleFilter) {
        $stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE is_active = TRUE AND role = ? ORDER BY full_name");
        $stmt->execute([$roleFilter]);
    } else {
        $stmt = $db->query("SELECT id, full_name, role FROM users WHERE is_active = TRUE ORDER BY full_name");
    }
    
    return $stmt->fetchAll();
}

/**
 * Получение списка клиентов
 */
function getCustomersList($activeOnly = true) {
    $db = getDb();
    
    $where = $activeOnly ? "WHERE is_active = TRUE" : "";
    $stmt = $db->query("SELECT id, name, inn, city FROM customers $where ORDER BY name");
    
    return $stmt->fetchAll();
}

/**
 * Получение списка продукции
 */
function getProductsList($categoryId = null, $activeOnly = true) {
    $db = getDb();
    
    $where = $activeOnly ? "WHERE p.is_active = TRUE" : "";
    $categoryFilter = $categoryId ? "AND p.category_id = ?" : "";
    
    $sql = "SELECT p.id, p.article, p.name, p.base_price, p.unit, c.name as category_name 
            FROM products p 
            LEFT JOIN product_categories c ON p.category_id = c.id 
            $where $categoryFilter 
            ORDER BY p.name";
    
    if ($categoryId) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $db->query($sql);
    }
    
    return $stmt->fetchAll();
}
