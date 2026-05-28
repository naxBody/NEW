<?php
/**
 * Конфигурационный файл системы управления производством "Полесьеэлектромаш"
 * Версия: 1.0
 */

// Параметры подключения к базе данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_polesie');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('APP_NAME', 'Полесьеэлектромаш - Система управления производством');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/polesie');
define('BASE_URL', '/polesie');
define('TIMEZONE', 'Europe/Minsk');

// Пути к директориям (определяем только если ещё не определены)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Настройки сессии
define('SESSION_LIFETIME', 3600); // 1 час
define('SESSION_NAME', 'POLESIE_SESSION');

// Настройки безопасности
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 минут

// Настройки_pagination
define('DEFAULT_PAGE_SIZE', 20);

// Локаль
define('DEFAULT_LOCALE', 'ru_BY');
define('CURRENCY_SYMBOL', 'BYN');

// Время установки
date_default_timezone_set(TIMEZONE);

// Ошибки (в продакшене отключить)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');

// Создание директорий если не существуют
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(BASE_PATH . '/logs')) {
    mkdir(BASE_PATH . '/logs', 0755, true);
}
