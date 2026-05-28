<?php
/**
 * Модуль контроля качества
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
    
    // Создание проверки качества
    if ($postAction === 'create') {
        try {
            $db->beginTransaction();
            
            $checkNumber = generateDocumentNumber('QC');
            $taskId = (int)$_POST['task_id'];
            $taskOperationId = !empty($_POST['task_operation_id']) ? (int)$_POST['task_operation_id'] : null;
            $checkType = $_POST['check_type'];
            $quantityChecked = (int)$_POST['quantity_checked'];
            $quantityPassed = (int)$_POST['quantity_passed'];
            $quantityDefective = $quantityChecked - $quantityPassed;
            $result = $quantityDefective > 0 ? 'failed' : 'passed';
            $defectDescription = $_POST['defect_description'] ?? '';
            $certificateNumber = $_POST['certificate_number'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $inspectorId = $user['id'];
            
            // Создаем проверку качества
            $stmt = $db->prepare("INSERT INTO quality_checks 
                (check_number, task_id, task_operation_id, check_type, inspector_id, quantity_checked, 
                 quantity_passed, quantity_defective, result, defect_description, certificate_number, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $checkNumber, $taskId, $taskOperationId, $checkType, $inspectorId,
                $quantityChecked, $quantityPassed, $quantityDefective, $result,
                $defectDescription, $certificateNumber, $notes
            ]);
            $checkId = $db->lastInsertId();
            
            // Если есть дефекты - сохраняем их
            if (isset($_POST['defects']) && is_array($_POST['defects'])) {
                foreach ($_POST['defects'] as $defectId => $quantity) {
                    if ($quantity > 0) {
                        $stmt = $db->prepare("INSERT INTO quality_check_defects (quality_check_id, defect_id, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$checkId, $defectId, $quantity]);
                    }
                }
            }
            
            // Обновляем статус задания если это финальная проверка
            if ($checkType === 'final' && $result === 'passed') {
                $db->exec("UPDATE production_tasks SET status = 'completed', quantity_completed = quantity_planned WHERE id = $taskId");
                
                // Обновляем статус заказа
                $db->exec("UPDATE orders SET status = 'ready' WHERE id = (SELECT order_id FROM order_items WHERE id = (SELECT order_item_id FROM production_tasks WHERE id = $taskId))");
            }
            
            $db->commit();
            logAction('quality_check_created', 'quality', $checkId);
            $message = 'Проверка качества успешно создана';
            $messageType = 'success';
            $action = 'list';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании проверки: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Получение списка проверок
if ($action === 'list') {
    $typeFilter = $_GET['type'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($typeFilter) {
        $where[] = "qc.check_type = ?";
        $params[] = $typeFilter;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
    
    $stmt = $db->prepare("SELECT qc.*, pt.task_number, p.name as product_name, u.full_name as inspector_name
                          FROM quality_checks qc
                          LEFT JOIN production_tasks pt ON qc.task_id = pt.id
                          LEFT JOIN products p ON pt.order_item_id = (SELECT oi.product_id FROM order_items oi WHERE oi.id = pt.order_item_id)
                          LEFT JOIN users u ON qc.inspector_id = u.id
                          $whereClause
                          ORDER BY qc.check_date DESC");
    $stmt->execute($params);
    $checks = $stmt->fetchAll();
    
    // Получаем список дефектов для формы
    $stmt = $db->query("SELECT * FROM defects WHERE is_active = TRUE ORDER BY name");
    $defects = $stmt->fetchAll();
}

// Получение одной проверки
if ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT qc.*, pt.task_number, p.name as product_name, o.order_number,
                          c.name as customer_name, u.full_name as inspector_name
                          FROM quality_checks qc
                          LEFT JOIN production_tasks pt ON qc.task_id = pt.id
                          LEFT JOIN order_items oi ON pt.order_item_id = oi.id
                          LEFT JOIN products p ON oi.product_id = p.id
                          LEFT JOIN orders o ON oi.order_id = o.id
                          LEFT JOIN customers c ON o.customer_id = c.id
                          LEFT JOIN users u ON qc.inspector_id = u.id
                          WHERE qc.id = ?");
    $stmt->execute([$id]);
    $check = $stmt->fetch();
    
    if ($check) {
        // Дефекты
        $stmt = $db->prepare("SELECT qcd.*, d.name as defect_name, d.severity
                              FROM quality_check_defects qcd
                              LEFT JOIN defects d ON qcd.defect_id = d.id
                              WHERE qcd.quality_check_id = ?");
        $stmt->execute([$id]);
        $checkDefects = $stmt->fetchAll();
    }
}

// Создание новой проверки (форма)
if ($action === 'create') {
    // Получаем активные задания
    $stmt = $db->query("SELECT pt.*, p.name as product_name, o.order_number
                        FROM production_tasks pt
                        LEFT JOIN order_items oi ON pt.order_item_id = oi.id
                        LEFT JOIN products p ON oi.product_id = p.id
                        LEFT JOIN orders o ON oi.order_id = o.id
                        WHERE pt.status IN ('released', 'in_progress')
                        ORDER BY pt.created_at DESC");
    $tasks = $stmt->fetchAll();
    
    // Получаем список дефектов
    $stmt = $db->query("SELECT * FROM defects WHERE is_active = TRUE ORDER BY category, name");
    $defects = $stmt->fetchAll();
}
?>

<div class="page-title">
    <h3><i class="fas fa-check-circle me-2"></i><?php 
        if ($action === 'create') echo 'Создание проверки качества';
        elseif ($action === 'view') echo 'Проверка №' . e($check['check_number'] ?? '');
        else echo 'Контроль качества (ОТК)';
    ?></h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=quality">Контроль качества</a></li>
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
<!-- Фильтры и список проверок -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter me-2"></i>Фильтры</span>
        <?php if ($auth->hasRole(['admin', 'manager', 'quality_controller'])): ?>
        <a href="<?php echo BASE_URL; ?>/index.php?page=quality_create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Новая проверка
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="quality">
            <div class="col-md-4">
                <label class="form-label">Тип проверки</label>
                <select name="type" class="form-select">
                    <option value="">Все типы</option>
                    <option value="incoming" <?php echo $typeFilter === 'incoming' ? 'selected' : ''; ?>>Входной контроль</option>
                    <option value="in_process" <?php echo $typeFilter === 'in_process' ? 'selected' : ''; ?>>Операционный контроль</option>
                    <option value="final" <?php echo $typeFilter === 'final' ? 'selected' : ''; ?>>Приёмочный контроль</option>
                    <option value="outgoing" <?php echo $typeFilter === 'outgoing' ? 'selected' : ''; ?>>Выходной контроль</option>
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
                        <th>№ проверки</th>
                        <th>Задание</th>
                        <th>Продукция</th>
                        <th>Тип</th>
                        <th>Дата</th>
                        <th>Проверено/Годен</th>
                        <th>Результат</th>
                        <th>Инспектор</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($checks)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Проверок не найдено</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($checks as $check): ?>
                        <?php 
                        $resultInfo = [
                            'passed' => ['text' => 'Пройдено', 'class' => 'badge-success'],
                            'failed' => ['text' => 'Не пройдено', 'class' => 'badge-danger'],
                            'conditional' => ['text' => 'Условно годен', 'class' => 'badge-warning']
                        ][$check['result']] ?? ['text' => $check['result'], 'class' => 'badge-secondary'];
                        
                        $typeLabels = [
                            'incoming' => 'Входной',
                            'in_process' => 'Операционный',
                            'final' => 'Приёмочный',
                            'outgoing' => 'Выходной'
                        ];
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=quality_view&id=<?php echo $check['id']; ?>">
                                    <?php echo e($check['check_number']); ?>
                                </a>
                            </td>
                            <td><?php echo e($check['task_number']); ?></td>
                            <td><?php echo e($check['product_name'] ?? '-'); ?></td>
                            <td><span class="badge bg-info"><?php echo $typeLabels[$check['check_type']] ?? $check['check_type']; ?></span></td>
                            <td><?php echo formatDate($check['check_date']); ?></td>
                            <td><?php echo $check['quantity_passed']; ?>/<?php echo $check['quantity_checked']; ?></td>
                            <td><span class="badge <?php echo $resultInfo['class']; ?>"><?php echo $resultInfo['text']; ?></span></td>
                            <td><?php echo e($check['inspector_name']); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=quality_view&id=<?php echo $check['id']; ?>" class="btn btn-sm btn-outline-primary">
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

<!-- Статистика по качеству -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="card stat-card success">
            <div class="card-body text-center">
                <h2 class="stat-number text-success">
                    <?php 
                    $stmt = $db->query("SELECT COUNT(*) FROM quality_checks WHERE result = 'passed'");
                    echo $stmt->fetchColumn();
                    ?>
                </h2>
                <p class="text-muted">Успешных проверок</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card danger">
            <div class="card-body text-center">
                <h2 class="stat-number text-danger">
                    <?php 
                    $stmt = $db->query("SELECT COUNT(*) FROM quality_checks WHERE result = 'failed'");
                    echo $stmt->fetchColumn();
                    ?>
                </h2>
                <p class="text-muted">Отклонено</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body text-center">
                <h2 class="stat-number">
                    <?php 
                    $stmt = $db->query("SELECT SUM(quantity_defective) FROM quality_checks");
                    echo $stmt->fetchColumn() ?? 0;
                    ?>
                </h2>
                <p class="text-muted">Всего дефектов</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'view' && $check): ?>
<!-- Просмотр проверки -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-check me-2"></i>Информация о проверке
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>№ проверки:</strong> <?php echo e($check['check_number']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Дата:</strong> <?php echo formatDate($check['check_date']); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Тип:</strong> 
                        <?php 
                        $typeLabels = [
                            'incoming' => 'Входной контроль',
                            'in_process' => 'Операционный контроль',
                            'final' => 'Приёмочный контроль',
                            'outgoing' => 'Выходной контроль'
                        ];
                        ?>
                        <span class="badge bg-info"><?php echo $typeLabels[$check['check_type']] ?? $check['check_type']; ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Результат:</strong> 
                        <?php $resultInfo = [
                            'passed' => ['text' => 'Пройдено', 'class' => 'badge-success'],
                            'failed' => ['text' => 'Не пройдено', 'class' => 'badge-danger'],
                            'conditional' => ['text' => 'Условно годен', 'class' => 'badge-warning']
                        ][$check['result']] ?? ['text' => $check['result'], 'class' => 'badge-secondary']; ?>
                        <span class="badge <?php echo $resultInfo['class']; ?>"><?php echo $resultInfo['text']; ?></span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Проверено:</strong> <?php echo $check['quantity_checked']; ?> шт.
                    </div>
                    <div class="col-md-4">
                        <strong>Годно:</strong> <?php echo $check['quantity_passed']; ?> шт.
                    </div>
                    <div class="col-md-4">
                        <strong>Дефект:</strong> <?php echo $check['quantity_defective']; ?> шт.
                    </div>
                </div>
                
                <?php if ($check['defect_description']): ?>
                <div class="mb-3">
                    <strong>Описание дефекта:</strong>
                    <p class="text-muted"><?php echo e($check['defect_description']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($check['certificate_number']): ?>
                <div class="mb-3">
                    <strong>№ сертификата:</strong> <?php echo e($check['certificate_number']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($checkDefects)): ?>
                <hr>
                <h5 class="mb-3">Выявленные дефекты</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Дефект</th>
                            <th>Категория</th>
                            <th>Кол-во</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checkDefects as $cd): ?>
                        <tr>
                            <td><?php echo e($cd['defect_name']); ?></td>
                            <td>
                                <?php 
                                $severityClass = [
                                    'critical' => 'badge-danger',
                                    'major' => 'badge-warning',
                                    'minor' => 'badge-info'
                                ][$cd['severity']] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?php echo $severityClass; ?>"><?php echo e($cd['category'] ?? '-'); ?></span>
                            </td>
                            <td><?php echo $cd['quantity']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Дополнительно
            </div>
            <div class="card-body">
                <p><strong>Задание:</strong> <?php echo e($check['task_number']); ?></p>
                <p><strong>Заказ:</strong> <?php echo e($check['order_number']); ?></p>
                <p><strong>Клиент:</strong> <?php echo e($check['customer_name']); ?></p>
                <p><strong>Продукция:</strong> <?php echo e($check['product_name']); ?></p>
                <p><strong>Инспектор:</strong> <?php echo e($check['inspector_name']); ?></p>
            </div>
        </div>
        
        <?php if ($check['notes']): ?>
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-sticky-note me-2"></i>Заметки
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo e($check['notes']); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo BASE_URL; ?>/index.php?page=quality" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Назад к списку
    </a>
</div>
<?php endif; ?>

<?php if ($action === 'create'): ?>
<!-- Форма создания проверки -->
<form method="POST">
    <input type="hidden" name="action" value="create">
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-clipboard-check me-2"></i>Основная информация
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Производственное задание <span class="text-danger">*</span></label>
                <select name="task_id" class="form-select" required>
                    <option value="">Выберите задание</option>
                    <?php foreach ($tasks as $task): ?>
                    <option value="<?php echo $task['id']; ?>">
                        <?php echo e($task['task_number']); ?> - <?php echo e($task['product_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Тип проверки <span class="text-danger">*</span></label>
                <select name="check_type" class="form-select" required>
                    <option value="">Выберите тип</option>
                    <option value="incoming">Входной контроль</option>
                    <option value="in_process">Операционный контроль</option>
                    <option value="final">Приёмочный контроль</option>
                    <option value="outgoing">Выходной контроль</option>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Проверено (шт) <span class="text-danger">*</span></label>
                    <input type="number" name="quantity_checked" class="form-control" min="1" value="1" required id="qtyChecked">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Годно (шт) <span class="text-danger">*</span></label>
                    <input type="number" name="quantity_passed" class="form-control" min="0" value="1" required id="qtyPassed">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Дефект (шт)</label>
                    <input type="text" class="form-control" id="qtyDefective" readonly value="0">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Описание дефекта (если есть)</label>
                <textarea name="defect_description" class="form-control" rows="2"></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">№ сертификата</label>
                <input type="text" name="certificate_number" class="form-control">
            </div>
            
            <hr>
            
            <h5 class="mb-3">Дефекты (если выявлены)</h5>
            <div class="row">
                <?php 
                $defectsByCategory = [];
                foreach ($defects as $d) {
                    $defectsByCategory[$d['category'] ?? 'Другие'][] = $d;
                }
                foreach ($defectsByCategory as $category => $catDefects): 
                ?>
                <div class="col-md-6 mb-3">
                    <strong><?php echo e($category); ?></strong>
                    <?php foreach ($catDefects as $defect): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="defects[<?php echo $defect['id']; ?>]" 
                               value="1" id="defect_<?php echo $defect['id']; ?>">
                        <label class="form-check-label" for="defect_<?php echo $defect['id']; ?>">
                            <?php echo e($defect['name']); ?>
                            <?php 
                            $sevClass = ['critical' => 'text-danger', 'major' => 'text-warning', 'minor' => 'text-info'][$defect['severity']] ?? '';
                            ?>
                            <small class="<?php echo $sevClass; ?>">(<?php echo e($defect['severity']); ?>)</small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Комментарий</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Сохранить проверку
        </button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=quality" class="btn btn-secondary">
            <i class="fas fa-times me-2"></i>Отмена
        </a>
    </div>
</form>

<script>
document.getElementById('qtyChecked').addEventListener('change', updateDefective);
document.getElementById('qtyPassed').addEventListener('change', updateDefective);

function updateDefective() {
    const checked = parseInt(document.getElementById('qtyChecked').value) || 0;
    const passed = parseInt(document.getElementById('qtyPassed').value) || 0;
    const defective = Math.max(0, checked - passed);
    document.getElementById('qtyDefective').value = defective;
}
</script>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
