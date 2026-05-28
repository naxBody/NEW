<?php
/**
 * Страница входа в систему
 */

// Подключаем конфигурацию
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

// Запускаем сессию только если она ещё не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Если уже авторизован - перенаправляем на главную
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    redirect(BASE_URL . '/index.php?page=dashboard');
}

$error = '';
$success = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Введите имя пользователя и пароль';
    } else {
        $auth = new Auth();
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            redirect(BASE_URL . '/index.php?page=dashboard');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему | <?php echo getSetting('company_name', 'Полесьеэлектромаш'); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        
        .login-header {
            background: #1e293b;
            color: #f1f5f9;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .login-header p {
            margin: 0.5rem 0 0;
            color: #94a3b8;
            font-size: 0.875rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.1);
        }
        
        .btn-login {
            background: #475569;
            border: none;
            padding: 0.625rem;
            font-weight: 500;
        }
        
        .btn-login:hover {
            background: #334155;
        }
        
        .company-info {
            text-align: center;
            color: #64748b;
            margin-top: 2rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h2><i class="fas fa-industry me-2"></i>Полесьеэлектромаш</h2>
            <p>Система управления производством</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo e($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>Имя пользователя
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Введите имя пользователя" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Пароль
                    </label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Введите пароль" required>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Запомнить меня</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Войти
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <small class="text-muted">
                    Тестовые учетные данные:<br>
                    Логин: <strong>admin</strong> | Пароль: <strong>admin123</strong>
                </small>
            </div>
        </div>
    </div>
    
    <div class="company-info">
        <p>&copy; <?php echo date('Y'); ?> ОАО "Полесьеэлектромаш"</p>
        <p class="small">Республика Беларусь, Гомельская обл., г. Мозырь</p>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
