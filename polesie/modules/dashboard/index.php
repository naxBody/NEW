<?php
/**
 * Панель управления (Dashboard)
 */
require_once BASE_PATH . '/includes/header.php';

// Получаем подключение к базе данных
$db = Database::getInstance()->getConnection();

// Получаем статистику
$stats = [];

// Количество заказов
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_orders,
    SUM(CASE WHEN status = 'in_production' THEN 1 ELSE 0 END) as in_production,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM orders");
$stats['orders'] = $stmt->fetch();

// Количество производственных заданий
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM production_tasks");
$stats['tasks'] = $stmt->fetch();

// Проверки качества
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed
    FROM quality_checks");
$stats['quality'] = $stmt->fetch();

// Последние заказы
$stmt = $db->query("SELECT o.*, c.name as customer_name 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    ORDER BY o.created_at DESC LIMIT 5");
$recentOrders = $stmt->fetchAll();

// Активные производственные задания
$stmt = $db->query("SELECT pt.*, p.name as product_name, pt.quantity_planned
    FROM production_tasks pt
    LEFT JOIN order_items oi ON pt.order_item_id = oi.id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE pt.status IN ('released', 'in_progress')
    ORDER BY pt.updated_at DESC LIMIT 5");
$activeTasks = $stmt->fetchAll();
?>

<div class="page-title">
    <h3><i class="fas fa-chart-line me-2"></i>Панель управления</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">Главная</li>
        </ol>
    </nav>
</div>

<!-- Статистика -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $stats['orders']['total'] ?? 0; ?></div>
                        <div class="stat-label">Всего заказов</div>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-info"><?php echo $stats['orders']['new_orders'] ?? 0; ?> новых</span>
                    <span class="badge bg-warning"><?php echo $stats['orders']['in_production'] ?? 0; ?> в работе</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $stats['tasks']['total'] ?? 0; ?></div>
                        <div class="stat-label">Производственных заданий</div>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-cogs fa-2x text-success"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-warning"><?php echo $stats['tasks']['in_progress'] ?? 0; ?> в работе</span>
                    <span class="badge bg-success"><?php echo $stats['tasks']['completed'] ?? 0; ?> завершено</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $stats['quality']['total'] ?? 0; ?></div>
                        <div class="stat-label">Проверок качества</div>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-check-circle fa-2x text-warning"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-success"><?php echo $stats['quality']['passed'] ?? 0; ?> пройдено</span>
                    <span class="badge bg-danger"><?php echo $stats['quality']['failed'] ?? 0; ?> отклонено</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo date('d.m.Y'); ?></div>
                        <div class="stat-label">Текущая дата</div>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-calendar fa-2x text-danger"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-primary">Смена 1</span>
                    <span class="badge bg-secondary">08:00 - 17:00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Последние заказы и активные задания -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-shopping-cart me-2"></i>Последние заказы</span>
                <a href="<?php echo BASE_URL; ?>/index.php?page=orders" class="btn btn-sm btn-primary">Все заказы</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>№ заказа</th>
                                <th>Клиент</th>
                                <th>Статус</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Заказов пока нет</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <?php $statusInfo = getOrderStatusText($order['status']); ?>
                                <tr>
                                    <td><a href="<?php echo BASE_URL; ?>/index.php?page=order_view&id=<?php echo $order['id']; ?>"><?php echo e($order['order_number']); ?></a></td>
                                    <td><?php echo e($order['customer_name']); ?></td>
                                    <td><span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span></td>
                                    <td><?php echo formatDate($order['order_date'], 'd.m.Y'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-cogs me-2"></i>Активные задания</span>
                <a href="<?php echo BASE_URL; ?>/index.php?page=production" class="btn btn-sm btn-primary">Все задания</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>№ задания</th>
                                <th>Продукция</th>
                                <th>Статус</th>
                                <th>Выполнено</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeTasks)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Активных заданий нет</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($activeTasks as $task): ?>
                                <?php $statusInfo = getTaskStatusText($task['status']); ?>
                                <tr>
                                    <td><a href="<?php echo BASE_URL; ?>/index.php?page=task_view&id=<?php echo $task['id']; ?>"><?php echo e($task['task_number']); ?></a></td>
                                    <td><?php echo e($task['product_name']); ?></td>
                                    <td><span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                            $percent = $task['quantity_planned'] > 0 
                                                ? round(($task['quantity_completed'] / $task['quantity_planned']) * 100) 
                                                : 0;
                                            ?>
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 style="width: <?php echo $percent; ?>%">
                                                <?php echo $task['quantity_completed']; ?>/<?php echo $task['quantity_planned']; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Быстрые действия -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i>Быстрые действия
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($auth->canAccessModule('orders')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=order_create" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-plus-circle fa-2x mb-2"></i>
                            <div>Новый заказ</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAccessModule('production')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=task_create" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-tasks fa-2x mb-2"></i>
                            <div>Задание</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAccessModule('quality')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=quality_create" class="btn btn-outline-warning w-100 py-3">
                            <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                            <div>Проверка ОТК</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAccessModule('warehouse')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=inventory_transaction" class="btn btn-outline-info w-100 py-3">
                            <i class="fas fa-dolly fa-2x mb-2"></i>
                            <div>Движение ТМЦ</div>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
