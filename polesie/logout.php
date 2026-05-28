<?php
/**
 * Выход из системы
 */
session_start();
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->logout();

redirect(BASE_URL . '/login.php');
