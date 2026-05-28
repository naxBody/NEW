<?php
/**
 * Главный файл приложения (роутер)
 */

// Определяем базовый путь
define('BASE_PATH', __DIR__);

// Запускаем сессию только если она ещё не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/controller.php';
require_once BASE_PATH . '/includes/functions.php';

// Создаем экземпляр Auth для проверки прав доступа
$auth = new Auth();
$user = $auth->getCurrentUser();

// Получаем запрошенную страницу
$page = $_GET['page'] ?? 'login';

// Маршрутизация
$publicPages = ['login'];
$protectedPages = [
    'dashboard',
    'orders',
    'order_view',
    'order_create',
    'order_edit',
    'production',
    'task_view',
    'task_create',
    'task_edit',
    'quality',
    'quality_check',
    'quality_create',
    'warehouse',
    'inventory',
    'inventory_transaction',
    'employees',
    'employee_view',
    'employee_create',
    'employee_edit',
    'reports',
    'settings',
    'profile',
    'change_password'
];

// Если страница публичная
if (in_array($page, $publicPages)) {
    include BASE_PATH . '/login.php';
    exit;
}

// Проверка авторизации для защищенных страниц
if (!$auth->isLoggedIn()) {
    redirect('/polesie/login.php');
    exit;
}

// Проверка прав доступа к модулю
$moduleMap = [
    'dashboard' => 'dashboard',
    'orders' => 'orders',
    'order_view' => 'orders',
    'order_create' => 'orders',
    'order_edit' => 'orders',
    'production' => 'production',
    'task_view' => 'production',
    'task_create' => 'production',
    'task_edit' => 'production',
    'quality' => 'quality',
    'quality_check' => 'quality',
    'quality_create' => 'quality',
    'warehouse' => 'warehouse',
    'inventory' => 'warehouse',
    'inventory_transaction' => 'warehouse',
    'employees' => 'employees',
    'employee_view' => 'employees',
    'employee_create' => 'employees',
    'employee_edit' => 'employees',
    'reports' => 'reports',
    'settings' => 'dashboard',
    'profile' => 'dashboard',
    'change_password' => 'dashboard'
];

$requiredModule = $moduleMap[$page] ?? 'dashboard';
if (!$auth->canAccessModule($requiredModule)) {
    http_response_code(403);
    die('<h1>Доступ запрещен</h1><p>У вас нет прав для доступа к этой странице.</p><a href="/polesie/index.php?page=dashboard">Вернуться на главную</a>');
}

// Заголовок страницы
$pageTitles = [
    'dashboard' => 'Панель управления',
    'orders' => 'Заказы клиентов',
    'order_view' => 'Просмотр заказа',
    'order_create' => 'Создание заказа',
    'order_edit' => 'Редактирование заказа',
    'production' => 'Производство',
    'task_view' => 'Просмотр задания',
    'task_create' => 'Создание задания',
    'task_edit' => 'Редактирование задания',
    'quality' => 'Контроль качества',
    'quality_check' => 'Проверка качества',
    'quality_create' => 'Создание проверки',
    'warehouse' => 'Склад',
    'inventory' => 'Остатки на складе',
    'inventory_transaction' => 'Движение товаров',
    'employees' => 'Сотрудники',
    'employee_view' => 'Просмотр сотрудника',
    'employee_create' => 'Добавление сотрудника',
    'employee_edit' => 'Редактирование сотрудника',
    'reports' => 'Отчеты',
    'settings' => 'Настройки системы',
    'profile' => 'Профиль пользователя',
    'change_password' => 'Смена пароля'
];

$pageTitle = $pageTitles[$page] ?? 'Полесьеэлектромаш';
$activePage = explode('_', $page)[0];

// Подключаем соответствующий модуль
switch ($page) {
    case 'dashboard':
        include BASE_PATH . '/modules/dashboard/index.php';
        break;
    
    case 'orders':
    case 'order_view':
    case 'order_create':
    case 'order_edit':
        include BASE_PATH . '/modules/orders/index.php';
        break;
    
    case 'production':
    case 'task_view':
    case 'task_create':
    case 'task_edit':
        include BASE_PATH . '/modules/production/index.php';
        break;
    
    case 'quality':
    case 'quality_check':
    case 'quality_create':
        include BASE_PATH . '/modules/quality/index.php';
        break;
    
    case 'warehouse':
    case 'inventory':
    case 'inventory_transaction':
        include BASE_PATH . '/modules/warehouse/index.php';
        break;
    
    case 'employees':
    case 'employee_view':
    case 'employee_create':
    case 'employee_edit':
        include BASE_PATH . '/modules/employees/index.php';
        break;
    
    case 'reports':
        include BASE_PATH . '/modules/reports/index.php';
        break;
    
    case 'settings':
        include BASE_PATH . '/modules/dashboard/settings.php';
        break;
    
    case 'profile':
        include BASE_PATH . '/modules/dashboard/profile.php';
        break;
    
    case 'change_password':
        include BASE_PATH . '/modules/dashboard/change_password.php';
        break;
    
    default:
        include BASE_PATH . '/modules/dashboard/index.php';
        break;
}
