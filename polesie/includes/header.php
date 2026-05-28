<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? getSetting('company_name', 'Полесьеэлектромаш')); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #475569;
            --secondary-color: #64748b;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --light-bg: #f1f5f9;
            --dark-text: #1e293b;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8fafc;
            color: var(--dark-text);
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #1e293b;
            color: #cbd5e1;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid #334155;
            text-align: center;
            background: #0f172a;
        }
        
        .sidebar-brand h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
            color: #f1f5f9;
        }
        
        .sidebar-brand small {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: #334155;
            color: #f1f5f9;
            border-left: 3px solid var(--accent-color);
        }
        
        .sidebar-menu a i {
            width: 24px;
            margin-right: 12px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 0.875rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .content-area {
            padding: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-industry me-2"></i>Полесьеэлектромаш</h4>
            <small>Система управления производством</small>
        </div>
        
        <nav class="sidebar-menu">
            <a href="<?php echo BASE_URL; ?>/index.php?page=dashboard" class="<?php echo ($activePage ?? '') == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Панель управления</span>
            </a>
            
            <?php if ($auth->canAccessModule('orders')): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?page=orders" class="<?php echo ($activePage ?? '') == 'orders' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Заказы</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('production')): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?page=production" class="<?php echo ($activePage ?? '') == 'production' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Производство</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('quality')): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?page=quality" class="<?php echo ($activePage ?? '') == 'quality' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Контроль качества</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('warehouse')): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?page=warehouse" class="<?php echo ($activePage ?? '') == 'warehouse' ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i>
                <span>Склад</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('employees')): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?page=employees" class="<?php echo ($activePage ?? '') == 'employees' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Сотрудники</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('reports')): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?page=reports" class="<?php echo ($activePage ?? '') == 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Отчеты</span>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo BASE_URL; ?>/index.php?page=settings">
                <i class="fas fa-cog"></i>
                <span>Настройки</span>
            </a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0 ms-3"><?php echo e($pageTitle ?? 'Полесьеэлектромаш'); ?></h5>
            </div>
            
            <div class="user-menu">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle text-decoration-none" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar me-2">
                            <?php echo mb_substr($user['full_name'] ?? 'U', 0, 1); ?>
                        </div>
                        <span class="d-none d-md-inline"><?php echo e($user['full_name'] ?? 'Гость'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=profile"><i class="fas fa-user me-2"></i>Профиль</a></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=change_password"><i class="fas fa-key me-2"></i>Смена пароля</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выход</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
