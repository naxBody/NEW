<?php
/**
 * Модуль управления складом
 */
require_once BASE_PATH . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];
    
    // Создание операции движения товаров
    if ($postAction === 'transaction') {
        try {
            $db->beginTransaction();
            
            $transactionNumber = generateDocumentNumber('TRN');
            $transactionType = $_POST['transaction_type'];
            $warehouseFromId = !empty($_POST['warehouse_from_id']) ? (int)$_POST['warehouse_from_id'] : null;
            $warehouseToId = !empty($_POST['warehouse_to_id']) ? (int)$_POST['warehouse_to_id'] : null;
            $materialId = (int)$_POST['material_id'];
            $quantity = (float)$_POST['quantity'];
            $unitPrice = !empty($_POST['unit_price']) ? (float)$_POST['unit_price'] : null;
            $totalValue = $unitPrice ? $unitPrice * $quantity : null;
            $referenceType = $_POST['reference_type'] ?? '';
            $referenceId = !empty($_POST['reference_id']) ? (int)$_POST['reference_id'] : null;
            $notes = $_POST['notes'] ?? '';
            $performedBy = $user['id'];
            
            // Создаем транзакцию
            $stmt = $db->prepare("INSERT INTO inventory_transactions 
                (transaction_number, transaction_type, warehouse_from_id, warehouse_to_id, material_id, 
                 quantity, unit_price, total_value, reference_type, reference_id, notes, performed_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $transactionNumber, $transactionType, $warehouseFromId, $warehouseToId, $materialId,
                $quantity, $unitPrice, $totalValue, $referenceType, $referenceId, $notes, $performedBy
            ]);
            
            // Обновляем остатки
            if ($transactionType === 'receipt') {
                // Приход
                $stmt = $db->prepare("INSERT INTO inventory (warehouse_id, material_id, quantity) 
                                      VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                $stmt->execute([$warehouseToId, $materialId, $quantity, $quantity]);
            } elseif ($transactionType === 'issue') {
                // Расход
                $stmt = $db->prepare("UPDATE inventory SET quantity = quantity - ? 
                                      WHERE warehouse_id = ? AND material_id = ?");
                $stmt->execute([$quantity, $warehouseFromId, $materialId]);
            } elseif ($transactionType === 'transfer') {
                // Перемещение
                $stmt = $db->prepare("UPDATE inventory SET quantity = quantity - ? 
                                      WHERE warehouse_id = ? AND material_id = ?");
                $stmt->execute([$quantity, $warehouseFromId, $materialId]);
                
                $stmt = $db->prepare("INSERT INTO inventory (warehouse_id, material_id, quantity) 
                                      VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                $stmt->execute([$warehouseToId, $materialId, $quantity, $quantity]);
            }
            
            $db->commit();
            logAction('inventory_transaction_created', 'warehouse', $db->lastInsertId());
            $message = 'Операция успешно выполнена';
            $messageType = 'success';
            $action = 'list';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Получение остатков
if ($action === 'list' || $action === 'inventory') {
    $warehouseFilter = $_GET['warehouse'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($warehouseFilter) {
        $where[] = "i.warehouse_id = ?";
        $params[] = $warehouseFilter;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
    
    $stmt = $db->prepare("SELECT i.*, m.name as material_name, m.article, m.unit, m.category,
                          w.name as warehouse_name, w.type as warehouse_type
                          FROM inventory i
                          LEFT JOIN materials m ON i.material_id = m.id
                          LEFT JOIN warehouses w ON i.warehouse_id = w.id
                          $whereClause AND i.quantity > 0
                          ORDER BY w.name, m.name");
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();
    
    // Склады
    $stmt = $db->query("SELECT * FROM warehouses WHERE is_active = TRUE ORDER BY name");
    $warehouses = $stmt->fetchAll();
}

// Форма создания операции
if ($action === 'transaction_create') {
    // Склады
    $stmt = $db->query("SELECT * FROM warehouses WHERE is_active = TRUE ORDER BY name");
    $warehouses = $stmt->fetchAll();
    
    // Материалы
    $stmt = $db->query("SELECT * FROM materials WHERE is_active = TRUE ORDER BY name");
    $materials = $stmt->fetchAll();
}
?>

<div class="page-title">
    <h3><i class="fas fa-warehouse me-2"></i><?php 
        if ($action === 'transaction_create') echo 'Движение товаров';
        else echo 'Складской учёт';
    ?></h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/polesie/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item"><a href="/polesie/index.php?page=warehouse">Склад</a></li>
            <?php if ($action === 'transaction_create'): ?>
            <li class="breadcrumb-item active">Движение товаров</li>
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

<?php if ($action === 'list' || $action === 'inventory'): ?>
<!-- Фильтры -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter me-2"></i>Фильтры</span>
        <a href="/polesie/index.php?page=inventory_transaction" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Новая операция
        </a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="warehouse">
            <div class="col-md-4">
                <label class="form-label">Склад</label>
                <select name="warehouse" class="form-select">
                    <option value="">Все склады</option>
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?php echo $wh['id']; ?>" <?php echo $warehouseFilter == $wh['id'] ? 'selected' : ''; ?>>
                        <?php echo e($wh['name']); ?>
                    </option>
                    <?php endforeach; ?>
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

<!-- Остатки -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-boxes me-2"></i>Остатки на складе
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Склад</th>
                        <th>Артикул</th>
                        <th>Наименование</th>
                        <th>Категория</th>
                        <th>Остаток</th>
                        <th>Ед. изм.</th>
                        <th>Резерв</th>
                        <th>Доступно</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Остатков не найдено</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><?php echo e($item['warehouse_name']); ?></td>
                            <td><?php echo e($item['article']); ?></td>
                            <td><?php echo e($item['material_name']); ?></td>
                            <td><?php echo e($item['category'] ?? '-'); ?></td>
                            <td><strong><?php echo $item['quantity']; ?></strong></td>
                            <td><?php echo e($item['unit']); ?></td>
                            <td><?php echo $item['reserved_quantity'] ?? 0; ?></td>
                            <td><?php echo $item['quantity'] - ($item['reserved_quantity'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'transaction_create'): ?>
<!-- Форма операции -->
<form method="POST">
    <input type="hidden" name="action" value="transaction">
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-exchange-alt me-2"></i>Параметры операции
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Тип операции <span class="text-danger">*</span></label>
                <select name="transaction_type" class="form-select" id="transType" required onchange="updateWarehouses()">
                    <option value="">Выберите тип</option>
                    <option value="receipt">Приход</option>
                    <option value="issue">Расход</option>
                    <option value="transfer">Перемещение</option>
                    <option value="adjustment">Корректировка</option>
                    <option value="return">Возврат</option>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3" id="warehouseFromGroup" style="display:none;">
                    <label class="form-label">Со склада</label>
                    <select name="warehouse_from_id" class="form-select">
                        <option value="">Выберите склад</option>
                        <?php foreach ($warehouses as $wh): ?>
                        <option value="<?php echo $wh['id']; ?>"><?php echo e($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3" id="warehouseToGroup" style="display:none;">
                    <label class="form-label">На склад</label>
                    <select name="warehouse_to_id" class="form-select">
                        <option value="">Выберите склад</option>
                        <?php foreach ($warehouses as $wh): ?>
                        <option value="<?php echo $wh['id']; ?>"><?php echo e($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Материал <span class="text-danger">*</span></label>
                <select name="material_id" class="form-select" required>
                    <option value="">Выберите материал</option>
                    <?php foreach ($materials as $mat): ?>
                    <option value="<?php echo $mat['id']; ?>">
                        <?php echo e($mat['article']); ?> - <?php echo e($mat['name']); ?> (<?php echo e($mat['unit']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Количество <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" class="form-control" step="0.001" min="0.001" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Цена за ед.</label>
                    <input type="number" name="unit_price" class="form-control" step="0.01" min="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Общая сумма</label>
                    <input type="text" class="form-control" id="totalValue" readonly>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Основание</label>
                <select name="reference_type" class="form-select">
                    <option value="">Не указано</option>
                    <option value="order">Заказ</option>
                    <option value="task">Задание</option>
                    <option value="invoice">Накладная</option>
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
            <i class="fas fa-save me-2"></i>Выполнить операцию
        </button>
        <a href="/polesie/index.php?page=warehouse" class="btn btn-secondary">
            <i class="fas fa-times me-2"></i>Отмена
        </a>
    </div>
</form>

<script>
function updateWarehouses() {
    const type = document.getElementById('transType').value;
    const fromGroup = document.getElementById('warehouseFromGroup');
    const toGroup = document.getElementById('warehouseToGroup');
    
    fromGroup.style.display = 'none';
    toGroup.style.display = 'none';
    
    if (type === 'receipt') {
        toGroup.style.display = 'block';
    } else if (type === 'issue') {
        fromGroup.style.display = 'block';
    } else if (type === 'transfer') {
        fromGroup.style.display = 'block';
        toGroup.style.display = 'block';
    } else if (type === 'adjustment' || type === 'return') {
        fromGroup.style.display = 'block';
        toGroup.style.display = 'block';
    }
}

// Авто расчёт суммы
document.querySelector('[name="quantity"]').addEventListener('input', function() {
    const qty = parseFloat(this.value) || 0;
    const price = parseFloat(document.querySelector('[name="unit_price"]').value) || 0;
    document.getElementById('totalValue').value = (qty * price).toFixed(2);
});

document.querySelector('[name="unit_price"]').addEventListener('input', function() {
    const qty = parseFloat(document.querySelector('[name="quantity"]').value) || 0;
    const price = parseFloat(this.value) || 0;
    document.getElementById('totalValue').value = (qty * price).toFixed(2);
});
</script>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
