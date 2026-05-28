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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/polesie/assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c5282;
            --secondary-color: #2b6cb0;
            --accent-color: #3182ce;
            --success-color: #38a169;
            --warning-color: #d69e2e;
            --danger-color: #e53e3e;
            --info-color: #319795;
            --light-bg: #f7fafc;
            --dark-text: #2d3748;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-brand h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .sidebar-brand small {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
            border-left: 4px solid white;
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 0.75rem 1.5rem;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .content-area {
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid var(--light-bg);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        .stat-card {
            border-left: 4px solid var(--accent-color);
        }
        
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.875rem;
        }
        
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 500;
            border-radius: 0.375rem;
        }
        
        .table th {
            font-weight: 600;
            color: #4a5568;
            border-top: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .page-title {
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
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
            <a href="/polesie/index.php?page=dashboard" class="<?php echo ($activePage ?? '') == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Панель управления</span>
            </a>
            
            <?php if ($auth->canAccessModule('orders')): ?>
            <a href="/polesie/index.php?page=orders" class="<?php echo ($activePage ?? '') == 'orders' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Заказы</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('production')): ?>
            <a href="/polesie/index.php?page=production" class="<?php echo ($activePage ?? '') == 'production' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Производство</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('quality')): ?>
            <a href="/polesie/index.php?page=quality" class="<?php echo ($activePage ?? '') == 'quality' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Контроль качества</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('warehouse')): ?>
            <a href="/polesie/index.php?page=warehouse" class="<?php echo ($activePage ?? '') == 'warehouse' ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i>
                <span>Склад</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('employees')): ?>
            <a href="/polesie/index.php?page=employees" class="<?php echo ($activePage ?? '') == 'employees' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Сотрудники</span>
            </a>
            <?php endif; ?>
            
            <?php if ($auth->canAccessModule('reports')): ?>
            <a href="/polesie/index.php?page=reports" class="<?php echo ($activePage ?? '') == 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Отчеты</span>
            </a>
            <?php endif; ?>
            
            <a href="/polesie/index.php?page=settings">
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
                        <li><a class="dropdown-item" href="/polesie/index.php?page=profile"><i class="fas fa-user me-2"></i>Профиль</a></li>
                        <li><a class="dropdown-item" href="/polesie/index.php?page=change_password"><i class="fas fa-key me-2"></i>Смена пароля</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/polesie/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выход</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
