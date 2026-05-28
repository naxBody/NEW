<?php
/**
 * Модуль управления производством
 */
require_once BASE_PATH . '/includes/header.php';

// Получаем подключение к базе данных
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];
    
    // Создание производственного задания
    if ($postAction === 'create') {
        try {
            $db->beginTransaction();
            
            $taskNumber = generateDocumentNumber('TSK');
            $orderItemId = (int)$_POST['order_item_id'];
            $routeId = !empty($_POST['route_id']) ? (int)$_POST['route_id'] : null;
            $quantityPlanned = (int)$_POST['quantity_planned'];
            $startDate = $_POST['start_date'] ?? date('Y-m-d');
            $endDate = $_POST['end_date'];
            $workstation = $_POST['workstation'] ?? '';
            $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $priority = (int)($_POST['priority'] ?? 5);
            $notes = $_POST['notes'] ?? '';
            
            // Создаем задание
            $stmt = $db->prepare("INSERT INTO production_tasks 
                (task_number, order_item_id, route_id, quantity_planned, start_date, end_date, workstation, assigned_to, priority, notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'released')");
            $stmt->execute([
                $taskNumber, $orderItemId, $routeId, $quantityPlanned, $startDate, $endDate, 
                $workstation, $assignedTo, $priority, $notes
            ]);
            $taskId = $db->lastInsertId();
            
            // Если есть маршрут - создаем операции
            if ($routeId) {
                $stmt = $db->prepare("SELECT id, operation_id, sequence_order FROM route_operations WHERE route_id = ? ORDER BY sequence_order");
                $stmt->execute([$routeId]);
                $routeOps = $stmt->fetchAll();
                
                foreach ($routeOps as $routeOp) {
                    $stmt = $db->prepare("INSERT INTO task_operations 
                        (task_id, route_operation_id, operation_id, sequence_order, status) 
                        VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$taskId, $routeOp['id'], $routeOp['operation_id'], $routeOp['sequence_order']]);
                }
            }
            
            // Обновляем статус позиции заказа
            $db->exec("UPDATE order_items SET status = 'in_progress' WHERE id = $orderItemId");
            
            // Обновляем статус заказа
            $db->exec("UPDATE orders SET status = 'in_production' WHERE id = (SELECT order_id FROM order_items WHERE id = $orderItemId)");
            
            $db->commit();
            logAction('task_created', 'production', $taskId);
            $message = 'Производственное задание успешно создано';
            $messageType = 'success';
            $action = 'list';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании задания: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Обновление статуса задания
    if ($postAction === 'update_status') {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $_POST['status'];
        
        $stmt = $db->prepare("UPDATE production_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $taskId]);
        
        // Если задание завершено - обновляем позицию заказа
        if ($newStatus === 'completed') {
            $stmt = $db->prepare("SELECT order_item_id, quantity_completed FROM production_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            // Обновляем количество выполненных
            $db->exec("UPDATE production_tasks SET quantity_completed = quantity_planned WHERE id = $taskId");
        }
        
        logAction('task_status_updated', 'production', $taskId);
        $message = 'Статус задания обновлен';
        $messageType = 'success';
    }
    
    // Отметка выполнения операции
    if ($postAction === 'complete_operation') {
        $operationId = (int)$_POST['operation_id'];
        $workerId = $user['id'];
        $actualTime = (int)($_POST['actual_time'] ?? 0);
        
        $stmt = $db->prepare("UPDATE task_operations 
            SET status = 'completed', completed_at = NOW(), worker_id = ?, actual_time = ? 
            WHERE id = ?");
        $stmt->execute([$workerId, $actualTime, $operationId]);
        
        logAction('operation_completed', 'production', $operationId);
        $message = 'Операция выполнена';
        $messageType = 'success';
    }
}

// Получение списка заданий
if ($action === 'list') {
    $statusFilter = $_GET['status'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($statusFilter) {
        $where[] = "pt.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
    
    $stmt = $db->prepare("SELECT pt.*, p.name as product_name, o.order_number
                          FROM production_tasks pt
                          LEFT JOIN order_items oi ON pt.order_item_id = oi.id
                          LEFT JOIN products p ON oi.product_id = p.id
                          LEFT JOIN orders o ON oi.order_id = o.id
                          $whereClause
                          ORDER BY pt.priority ASC, pt.created_at DESC");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
}

// Получение одного задания
if ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT pt.*, p.name as product_name, o.order_number, o.delivery_date,
                          c.name as customer_name
                          FROM production_tasks pt
                          LEFT JOIN order_items oi ON pt.order_item_id = oi.id
                          LEFT JOIN products p ON oi.product_id = p.id
                          LEFT JOIN orders o ON oi.order_id = o.id
                          LEFT JOIN customers c ON o.customer_id = c.id
                          WHERE pt.id = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if ($task) {
        // Операции задания
        $stmt = $db->prepare("SELECT to.*, op.name as operation_name, op.standard_time, u.full_name as worker_name
                              FROM task_operations to
                              LEFT JOIN operations op ON to.operation_id = op.id
                              LEFT JOIN users u ON to.worker_id = u.id
                              WHERE to.task_id = ?
                              ORDER BY to.sequence_order");
        $stmt->execute([$id]);
        $operations = $stmt->fetchAll();
    }
}

// Создание нового задания (форма)
if ($action === 'create') {
    // Получаем позиции заказов в работе
    $stmt = $db->query("SELECT oi.*, p.name as product_name, o.order_number 
                        FROM order_items oi
                        LEFT JOIN products p ON oi.product_id = p.id
                        LEFT JOIN orders o ON oi.order_id = o.id
                        WHERE oi.status IN ('pending', 'in_progress')
                        ORDER BY o.created_at DESC");
    $orderItems = $stmt->fetchAll();
    
    // Получаем технологические маршруты
    $stmt = $db->query("SELECT tr.*, p.name as product_name FROM technology_routes tr
                        LEFT JOIN products p ON tr.product_id = p.id
                        WHERE tr.is_active = TRUE");
    $routes = $stmt->fetchAll();
    
    // Получаем рабочих
    $workers = getUsersList('operator');
    
    // Получаем рабочие места
    $stmt = $db->query("SELECT * FROM workstations WHERE is_active = TRUE ORDER BY name");
    $workstations = $stmt->fetchAll();
}
?>

<div class="page-title">
    <h3><i class="fas fa-cogs me-2"></i><?php 
        if ($action === 'create') echo 'Создание производственного задания';
        elseif ($action === 'view') echo 'Задание №' . e($task['task_number'] ?? '');
        else echo 'Производство';
    ?></h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=production">Производство</a></li>
            <?php if ($action === 'view' || $action === 'create'): ?>
            <li class="breadcrumb-item active"><?php echo $action === 'create' ? 'Создание' : 'Просмотр'; ?></li>
            <?php endif; ?>
        </ol>
    </nav>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
    <?php echo e($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- Фильтры и список заданий -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter me-2"></i>Фильтры</span>
        <?php if ($auth->hasRole(['admin', 'manager', 'technologist'])): ?>
        <a href="<?php echo BASE_URL; ?>/index.php?page=task_create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Новое задание
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="production">
            <div class="col-md-4">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <option value="planned" <?php echo $statusFilter === 'planned' ? 'selected' : ''; ?>>Запланировано</option>
                    <option value="released" <?php echo $statusFilter === 'released' ? 'selected' : ''; ?>>Выпущено</option>
                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>В работе</option>
                    <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>Приостановлено</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Завершено</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Отклонено</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Найти
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>№ задания</th>
                        <th>Заказ</th>
                        <th>Продукция</th>
                        <th>План/Факт</th>
                        <th>Статус</th>
                        <th>Рабочее место</th>
                        <th>Исполнитель</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Производственных заданий не найдено</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                        <?php $statusInfo = getTaskStatusText($task['status']); ?>
                        <tr>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=task_view&id=<?php echo $task['id']; ?>">
                                    <?php echo e($task['task_number']); ?>
                                </a>
                            </td>
                            <td><?php echo e($task['order_number']); ?></td>
                            <td><?php echo e($task['product_name']); ?></td>
                            <td>
                                <?php echo $task['quantity_completed']; ?>/<?php echo $task['quantity_planned']; ?>
                                <div class="progress" style="height: 6px;">
                                    <?php 
                                    $percent = $task['quantity_planned'] > 0 
                                        ? round(($task['quantity_completed'] / $task['quantity_planned']) * 100) 
                                        : 0;
                                    ?>
                                    <div class="progress-bar bg-primary" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </td>
                            <td><span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span></td>
                            <td><?php echo e($task['workstation']); ?></td>
                            <td>
                                <?php if ($task['assigned_to']): ?>
                                    <?php 
                                    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                                    $stmt->execute([$task['assigned_to']]);
                                    $worker = $stmt->fetch();
                                    echo e($worker['full_name'] ?? '');
                                    ?>
                                <?php else: ?>
                                    <span class="text-muted">Не назначен</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=task_view&id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'view' && $task): ?>
<!-- Просмотр задания -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tasks me-2"></i>Информация о задании
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>№ задания:</strong> <?php echo e($task['task_number']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Статус:</strong> 
                        <?php $statusInfo = getTaskStatusText($task['status']); ?>
                        <span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Продукция:</strong> <?php echo e($task['product_name']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Заказ:</strong> <?php echo e($task['order_number']); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>План:</strong> <?php echo $task['quantity_planned']; ?> шт.
                    </div>
                    <div class="col-md-4">
                        <strong>Выполнено:</strong> <?php echo $task['quantity_completed']; ?> шт.
                    </div>
                    <div class="col-md-4">
                        <strong>Брак:</strong> <?php echo $task['quantity_rejected']; ?> шт.
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Начало:</strong> <?php echo formatDate($task['start_date']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Окончание:</strong> <?php echo formatDate($task['end_date']); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Рабочее место:</strong> <?php echo e($task['workstation']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Приоритет:</strong> <?php echo $task['priority']; ?>
                    </div>
                </div>
                <?php if ($task['notes']): ?>
                <div class="mt-3">
                    <strong>Заметки:</strong>
                    <p class="text-muted"><?php echo e($task['notes']); ?></p>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <h5 class="mb-3">Технологические операции</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Операция</th>
                                <th>Статус</th>
                                <th>Время (план)</th>
                                <th>Время (факт)</th>
                                <th>Исполнитель</th>
                                <?php if ($auth->hasRole(['operator', 'technologist'])): ?>
                                <th>Действие</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operations as $index => $op): ?>
                            <tr>
                                <td><?php echo $op['sequence_order']; ?></td>
                                <td><?php echo e($op['operation_name']); ?></td>
                                <td>
                                    <?php 
                                    $opStatus = [
                                        'pending' => ['text' => 'Ожидает', 'class' => 'badge-secondary'],
                                        'in_progress' => ['text' => 'В работе', 'class' => 'badge-warning'],
                                        'completed' => ['text' => 'Выполнено', 'class' => 'badge-success'],
                                        'skipped' => ['text' => 'Пропущено', 'class' => 'badge-info']
                                    ][$op['status']] ?? ['text' => $op['status'], 'class' => 'badge-secondary'];
                                    ?>
                                    <span class="badge <?php echo $opStatus['class']; ?>"><?php echo $opStatus['text']; ?></span>
                                </td>
                                <td><?php echo $op['standard_time']; ?> мин.</td>
                                <td><?php echo $op['actual_time'] ?? '-'; ?> мин.</td>
                                <td><?php echo e($op['worker_name'] ?? 'Не назначен'); ?></td>
                                <?php if ($auth->hasRole(['operator', 'technologist'])): ?>
                                <td>
                                    <?php if ($op['status'] === 'pending' || $op['status'] === 'in_progress'): ?>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            data-bs-toggle="modal" data-bs-target="#completeOpModal<?php echo $op['id']; ?>">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    
                                    <!-- Modal для завершения операции -->
                                    <div class="modal fade" id="completeOpModal<?php echo $op['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="complete_operation">
                                                    <input type="hidden" name="operation_id" value="<?php echo $op['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Завершение операции</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Операция:</strong> <?php echo e($op['operation_name']); ?></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Фактическое время (мин)</label>
                                                            <input type="number" name="actual_time" class="form-control" 
                                                                   value="<?php echo $op['standard_time']; ?>" min="0">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                                        <button type="submit" class="btn btn-success">Завершить</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user me-2"></i>Информация о клиенте
            </div>
            <div class="card-body">
                <p><strong>Клиент:</strong> <?php echo e($task['customer_name']); ?></p>
                <p><strong>Дата поставки:</strong> <?php echo formatDate($task['delivery_date']); ?></p>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-exchange-alt me-2"></i>Изменить статус
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                    <div class="mb-3">
                        <select name="status" class="form-select">
                            <option value="planned" <?php echo $task['status'] === 'planned' ? 'selected' : ''; ?>>Запланировано</option>
                            <option value="released" <?php echo $task['status'] === 'released' ? 'selected' : ''; ?>>Выпущено</option>
                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>В работе</option>
                            <option value="paused" <?php echo $task['status'] === 'paused' ? 'selected' : ''; ?>>Приостановлено</option>
                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Завершено</option>
                            <option value="rejected" <?php echo $task['status'] === 'rejected' ? 'selected' : ''; ?>>Отклонено</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Обновить статус</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo BASE_URL; ?>/index.php?page=production" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Назад к списку
    </a>
</div>
<?php endif; ?>

<?php if ($action === 'create'): ?>
<!-- Форма создания задания -->
<form method="POST">
    <input type="hidden" name="action" value="create">
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-tasks me-2"></i>Основная информация
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Позиция заказа <span class="text-danger">*</span></label>
                <select name="order_item_id" class="form-select" required>
                    <option value="">Выберите позицию заказа</option>
                    <?php foreach ($orderItems as $item): ?>
                    <option value="<?php echo $item['id']; ?>">
                        <?php echo e($item['order_number']); ?> - <?php echo e($item['product_name']); ?> (осталось: <?php echo $item['quantity']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Технологический маршрут</label>
                <select name="route_id" class="form-select">
                    <option value="">Без маршрута</option>
                    <?php foreach ($routes as $route): ?>
                    <option value="<?php echo $route['id']; ?>">
                        <?php echo e($route['name']); ?> (<?php echo e($route['product_name']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Планируемое количество <span class="text-danger">*</span></label>
                    <input type="number" name="quantity_planned" class="form-control" min="1" value="1" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Приоритет (1-10)</label>
                    <input type="number" name="priority" class="form-control" min="1" max="10" value="5">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата начала</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата окончания <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Рабочее место</label>
                <select name="workstation" class="form-select">
                    <option value="">Не указано</option>
                    <?php foreach ($workstations as $ws): ?>
                    <option value="<?php echo e($ws['name']); ?>"><?php echo e($ws['name']); ?> (<?php echo e($ws['location']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Ответственный исполнитель</label>
                <select name="assigned_to" class="form-select">
                    <option value="">Не назначен</option>
                    <?php foreach ($workers as $worker): ?>
                    <option value="<?php echo $worker['id']; ?>"><?php echo e($worker['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Комментарий</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Создать задание
        </button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=production" class="btn btn-secondary">
            <i class="fas fa-times me-2"></i>Отмена
        </a>
    </div>
</form>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
