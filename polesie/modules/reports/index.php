<?php
/**
 * Модуль отчетов и аналитики
 */
global $db;
require_once BASE_PATH . '/includes/header.php';

$reportType = $_GET['type'] ?? 'summary';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Получение данных для отчета
$reportData = [];

// Сводный отчет
if ($reportType === 'summary') {
    // Заказы за период
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total,
        SUM(total_amount) as total_sum,
        AVG(total_amount) as avg_amount
        FROM orders 
        WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $reportData['orders'] = $stmt->fetch();

    // Производство за период
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total,
        SUM(quantity_planned) as planned_qty,
        SUM(quantity_completed) as completed_qty
        FROM production_tasks 
        WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $reportData['production'] = $stmt->fetch();

    // Качество за период
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed
        FROM quality_checks 
        WHERE check_date BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $reportData['quality'] = $stmt->fetch();
}

// Отчет по заказам
if ($reportType === 'orders') {
    $stmt = $db->prepare("SELECT 
        o.order_number,
        c.name as customer_name,
        o.order_date,
        o.delivery_date,
        o.total_amount,
        o.status,
        u.full_name as manager_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN users u ON o.manager_id = u.id
        WHERE o.order_date BETWEEN ? AND ?
        ORDER BY o.order_date DESC");
    $stmt->execute([$dateFrom, $dateTo]);
    $reportData['orders_list'] = $stmt->fetchAll();
}

// Отчет по производству
if ($reportType === 'production') {
    $stmt = $db->prepare("SELECT 
        pt.task_number,
        p.name as product_name,
        pt.quantity_planned,
        pt.quantity_completed,
        pt.status,
        pt.start_date,
        pt.end_date,
        u.full_name as responsible_name
        FROM production_tasks pt
        LEFT JOIN order_items oi ON pt.order_item_id = oi.id
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u ON pt.responsible_id = u.id
        WHERE pt.created_at BETWEEN ? AND ?
        ORDER BY pt.created_at DESC");
    $stmt->execute([$dateFrom, $dateTo]);
    $reportData['tasks_list'] = $stmt->fetchAll();
}

// Отчет по качеству
if ($reportType === 'quality') {
    $stmt = $db->prepare("SELECT 
        qc.check_number,
        qc.check_type,
        qc.result,
        qc.defect_count,
        qc.check_date,
        u.full_name as inspector_name,
        pt.task_number
        FROM quality_checks qc
        LEFT JOIN users u ON qc.inspector_id = u.id
        LEFT JOIN production_tasks pt ON qc.task_id = pt.id
        WHERE qc.check_date BETWEEN ? AND ?
        ORDER BY qc.check_date DESC");
    $stmt->execute([$dateFrom, $dateTo]);
    $reportData['quality_list'] = $stmt->fetchAll();
}

$activePage = 'reports';
?>

<div class="page-title">
    <h3><i class="fas fa-chart-bar me-2"></i>Отчеты и аналитика</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item active">Отчеты</li>
        </ol>
    </nav>
</div>

<!-- Фильтры -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="type" value="<?php echo e($reportType); ?>">
            
            <div class="col-md-3">
                <label for="date_from" class="form-label">С даты</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo e($dateFrom); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="date_to" class="form-label">По дату</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo e($dateTo); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="type" class="form-label">Тип отчета</label>
                <select class="form-select" id="type" name="type">
                    <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Сводный отчет</option>
                    <option value="orders" <?php echo $reportType === 'orders' ? 'selected' : ''; ?>>По заказам</option>
                    <option value="production" <?php echo $reportType === 'production' ? 'selected' : ''; ?>>По производству</option>
                    <option value="quality" <?php echo $reportType === 'quality' ? 'selected' : ''; ?>>По качеству</option>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter me-2"></i>Применить
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/reports/export.php?type=<?php echo e($reportType); ?>&date_from=<?php echo e($dateFrom); ?>&date_to=<?php echo e($dateTo); ?>" 
                   class="btn btn-success" target="_blank">
                    <i class="fas fa-file-excel me-2"></i>Экспорт
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($reportType === 'summary'): ?>
<!-- Сводный отчет -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-shopping-cart me-2"></i>Заказы</h5>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <h3><?php echo $reportData['orders']['total'] ?? 0; ?></h3>
                        <small class="text-muted">Всего заказов</small>
                    </div>
                    <div class="col-6">
                        <h3><?php echo formatPrice($reportData['orders']['total_sum'] ?? 0); ?></h3>
                        <small class="text-muted">Общая сумма</small>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted">Средний чек: <?php echo formatPrice($reportData['orders']['avg_amount'] ?? 0); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card stat-card success">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-cogs me-2"></i>Производство</h5>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <h3><?php echo $reportData['production']['total'] ?? 0; ?></h3>
                        <small class="text-muted">Заданий</small>
                    </div>
                    <div class="col-6">
                        <h3><?php echo $reportData['production']['completed_qty'] ?? 0; ?></h3>
                        <small class="text-muted">Выполнено</small>
                    </div>
                </div>
                <div class="mt-3">
                    <?php 
                    $completionRate = $reportData['production']['planned_qty'] > 0 
                        ? round(($reportData['production']['completed_qty'] / $reportData['production']['planned_qty']) * 100) 
                        : 0;
                    ?>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: <?php echo $completionRate; ?>%">
                            <?php echo $completionRate; ?>%
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block">План: <?php echo $reportData['production']['planned_qty'] ?? 0; ?> шт.</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card stat-card warning">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Качество</h5>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <h3><?php echo $reportData['quality']['passed'] ?? 0; ?></h3>
                        <small class="text-muted">Пройдено</small>
                    </div>
                    <div class="col-6">
                        <h3><?php echo $reportData['quality']['failed'] ?? 0; ?></h3>
                        <small class="text-muted">Отклонено</small>
                    </div>
                </div>
                <div class="mt-3">
                    <?php 
                    $totalChecks = ($reportData['quality']['passed'] ?? 0) + ($reportData['quality']['failed'] ?? 0);
                    $passRate = $totalChecks > 0 
                        ? round(($reportData['quality']['passed'] / $totalChecks) * 100) 
                        : 0;
                    ?>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: <?php echo $passRate; ?>%">
                            <?php echo $passRate; ?>%
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block">Всего проверок: <?php echo $reportData['quality']['total'] ?? 0; ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'orders'): ?>
<!-- Отчет по заказам -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list me-2"></i>Детализация по заказам
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>№ заказа</th>
                        <th>Клиент</th>
                        <th>Дата заказа</th>
                        <th>Дата поставки</th>
                        <th>Сумма</th>
                        <th>Менеджер</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData['orders_list'])): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Нет данных за выбранный период</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($reportData['orders_list'] as $order): ?>
                        <?php $statusInfo = getOrderStatusText($order['status']); ?>
                        <tr>
                            <td><?php echo e($order['order_number']); ?></td>
                            <td><?php echo e($order['customer_name']); ?></td>
                            <td><?php echo formatDate($order['order_date'], 'd.m.Y'); ?></td>
                            <td><?php echo formatDate($order['delivery_date'], 'd.m.Y'); ?></td>
                            <td><?php echo formatPrice($order['total_amount']); ?></td>
                            <td><?php echo e($order['manager_name']); ?></td>
                            <td><span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'production'): ?>
<!-- Отчет по производству -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-tasks me-2"></i>Детализация по производственным заданиям
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>№ задания</th>
                        <th>Продукция</th>
                        <th>План</th>
                        <th>Факт</th>
                        <th>% выполнения</th>
                        <th>Ответственный</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData['tasks_list'])): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Нет данных за выбранный период</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($reportData['tasks_list'] as $task): ?>
                        <?php $statusInfo = getTaskStatusText($task['status']); ?>
                        <?php 
                        $percent = $task['quantity_planned'] > 0 
                            ? round(($task['quantity_completed'] / $task['quantity_planned']) * 100) 
                            : 0;
                        ?>
                        <tr>
                            <td><?php echo e($task['task_number']); ?></td>
                            <td><?php echo e($task['product_name']); ?></td>
                            <td><?php echo $task['quantity_planned']; ?></td>
                            <td><?php echo $task['quantity_completed']; ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" style="width: <?php echo $percent; ?>%">
                                        <?php echo $percent; ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?php echo e($task['responsible_name']); ?></td>
                            <td><span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'quality'): ?>
<!-- Отчет по качеству -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-clipboard-check me-2"></i>Детализация по проверкам качества
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>№ проверки</th>
                        <th>Тип</th>
                        <th>Результат</th>
                        <th>Дефекты</th>
                        <th>Задание</th>
                        <th>Инспектор</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData['quality_list'])): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Нет данных за выбранный период</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($reportData['quality_list'] as $check): ?>
                        <tr>
                            <td><?php echo e($check['check_number']); ?></td>
                            <td><?php echo e($check['check_type']); ?></td>
                            <td>
                                <?php if ($check['result'] === 'passed'): ?>
                                    <span class="badge bg-success">Пройдено</span>
                                <?php elseif ($check['result'] === 'failed'): ?>
                                    <span class="badge bg-danger">Отклонено</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">В процессе</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $check['defect_count']; ?></td>
                            <td><?php echo e($check['task_number']); ?></td>
                            <td><?php echo e($check['inspector_name']); ?></td>
                            <td><?php echo formatDate($check['check_date'], 'd.m.Y H:i'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
