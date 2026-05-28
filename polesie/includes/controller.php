<?php
/**
 * Базовый класс контроллера
 */

class Controller {
    protected $db;
    protected $auth;
    protected $user;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->auth = new Auth();
        $this->user = $this->auth->getCurrentUser();
    }
    
    /**
     * Проверка авторизации
     */
    protected function requireAuth() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /polesie/index.php?page=login');
            exit;
        }
    }
    
    /**
     * Проверка прав доступа к модулю
     */
    protected function requireModuleAccess($module) {
        $this->requireAuth();
        if (!$this->auth->canAccessModule($module)) {
            http_response_code(403);
            die('Доступ запрещен');
        }
    }
    
    /**
     * Рендеринг представления
     */
    protected function render($view, $data = []) {
        extract($data);
        include BASE_PATH . '/includes/header.php';
        include BASE_PATH . '/modules/' . $view . '.php';
        include BASE_PATH . '/includes/footer.php';
    }
    
    /**
     * Возврат JSON ответа
     */
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Перенаправление
     */
    protected function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Получение и очистка GET параметра
     */
    protected function getParam($name, $default = null) {
        return isset($_GET[$name]) ? trim(htmlspecialchars($_GET[$name])) : $default;
    }
    
    /**
     * Получение и очистка POST параметра
     */
    protected function postParam($name, $default = null) {
        return isset($_POST[$name]) ? trim(htmlspecialchars($_POST[$name])) : $default;
    }
    
    /**
     * Форматирование даты
     */
    protected function formatDate($date, $format = 'd.m.Y H:i') {
        if (!$date) return '';
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        return date($format, $timestamp);
    }
    
    /**
     * Форматирование цены
     */
    protected function formatPrice($price, $currency = 'BYN') {
        return number_format((float)$price, 2, '.', ' ') . ' ' . $currency;
    }
    
    /**
     * Генерация уникального номера документа
     */
    protected function generateDocumentNumber($prefix) {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
