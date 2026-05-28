<?php
/**
 * Модуль управления заказами
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
    
    // Создание заказа
    if ($postAction === 'create') {
        try {
            $db->beginTransaction();
            
            $orderNumber = generateDocumentNumber('ORD');
            $customerId = (int)$_POST['customer_id'];
            $orderDate = $_POST['order_date'] ?? date('Y-m-d');
            $deliveryDate = $_POST['delivery_date'];
            $priority = $_POST['priority'] ?? 'normal';
            $notes = $_POST['notes'] ?? '';
            $managerId = $user['id'];
            
            // Создаем заказ
            $stmt = $db->prepare("INSERT INTO orders (order_number, customer_id, order_date, delivery_date, priority, notes, manager_id, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')");
            $stmt->execute([$orderNumber, $customerId, $orderDate, $deliveryDate, $priority, $notes, $managerId]);
            $orderId = $db->lastInsertId();
            
            // Добавляем позиции заказа
            if (isset($_POST['products']) && is_array($_POST['products'])) {
                foreach ($_POST['products'] as $product) {
                    if (!empty($product['product_id']) && !empty($product['quantity'])) {
                        // Получаем цену товара
                        $stmt = $db->prepare("SELECT base_price FROM products WHERE id = ?");
                        $stmt->execute([$product['product_id']]);
                        $prod = $stmt->fetch();
                        
                        $unitPrice = $prod['base_price'] ?? 0;
                        $quantity = (int)$product['quantity'];
                        $totalPrice = $unitPrice * $quantity;
                        
                        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, status) 
                                              VALUES (?, ?, ?, ?, ?, 'pending')");
                        $stmt->execute([$orderId, $product['product_id'], $quantity, $unitPrice, $totalPrice]);
                        
                        // Обновляем общую сумму заказа
                        $db->exec("UPDATE orders SET total_amount = (SELECT SUM(total_price) FROM order_items WHERE order_id = $orderId) WHERE id = $orderId");
                    }
                }
            }
            
            $db->commit();
            logAction('order_created', 'orders', $orderId);
            $message = 'Заказ успешно создан';
            $messageType = 'success';
            $action = 'list';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании заказа: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Обновление статуса заказа
    if ($postAction === 'update_status') {
        $orderId = (int)$_POST['order_id'];
        $newStatus = $_POST['status'];
        
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        
        logAction('order_status_updated', 'orders', $orderId);
        $message = 'Статус заказа обновлен';
        $messageType = 'success';
    }
}

// Получение списка заказов для отображения
if ($action === 'list') {
    $statusFilter = $_GET['status'] ?? '';
    $searchQuery = $_GET['search'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($statusFilter) {
        $where[] = "o.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($searchQuery) {
        $where[] = "(o.order_number LIKE ? OR c.name LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
    
    $stmt = $db->prepare("SELECT o.*, c.name as customer_name, u.full_name as manager_name
                          FROM orders o
                          LEFT JOIN customers c ON o.customer_id = c.id
                          LEFT JOIN users u ON o.manager_id = u.id
                          $whereClause
                          ORDER BY o.created_at DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Получение списка клиентов для фильтра
    $customers = getCustomersList();
}

// Получение одного заказа
if ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT o.*, c.name as customer_name, c.inn, c.address, c.phone, c.email, c.contact_person,
                          u.full_name as manager_name
                          FROM orders o
                          LEFT JOIN customers c ON o.customer_id = c.id
                          LEFT JOIN users u ON o.manager_id = u.id
                          WHERE o.id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if ($order) {
        // Позиции заказа
        $stmt = $db->prepare("SELECT oi.*, p.name as product_name, p.article, p.unit
                              FROM order_items oi
                              LEFT JOIN products p ON oi.product_id = p.id
                              WHERE oi.order_id = ?");
        $stmt->execute([$id]);
        $orderItems = $stmt->fetchAll();
        
        // Производственные задания по заказу
        $stmt = $db->prepare("SELECT pt.*, GROUP_CONCAT(po.operation_id) as operation_ids
                              FROM production_tasks pt
                              LEFT JOIN order_items oi ON pt.order_item_id = oi.id
                              LEFT JOIN task_operations po ON pt.id = po.task_id
                              WHERE oi.order_id = ?
                              GROUP BY pt.id");
        $stmt->execute([$id]);
        $tasks = $stmt->fetchAll();
    }
}

// Создание нового заказа (форма)
if ($action === 'create') {
    $products = getProductsList();
    $customers = getCustomersList();
}
?>

<div class="page-title">
    <h3><i class="fas fa-shopping-cart me-2"></i><?php 
        if ($action === 'create') echo 'Создание заказа';
        elseif ($action === 'view') echo 'Просмотр заказа №' . e($order['order_number'] ?? '');
        else echo 'Заказы клиентов';
    ?></h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=orders">Заказы</a></li>
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
<!-- Фильтры и список заказов -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter me-2"></i>Фильтры</span>
        <?php if ($auth->canAccessModule('orders')): ?>
        <a href="<?php echo BASE_URL; ?>/index.php?page=order_create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Новый заказ
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="orders">
            <div class="col-md-4">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>Новый</option>
                    <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Подтвержден</option>
                    <option value="in_production" <?php echo $statusFilter === 'in_production' ? 'selected' : ''; ?>>В производстве</option>
                    <option value="quality_check" <?php echo $statusFilter === 'quality_check' ? 'selected' : ''; ?>>Проверка качества</option>
                    <option value="ready" <?php echo $statusFilter === 'ready' ? 'selected' : ''; ?>>Готов</option>
                    <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Отгружен</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Завершен</option>
                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Отменен</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" class="form-control" placeholder="№ заказа или клиент" value="<?php echo e($searchQuery); ?>">
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
                        <th>№ заказа</th>
                        <th>Клиент</th>
                        <th>Дата заказа</th>
                        <th>Дата поставки</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Приоритет</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Заказов не найдено</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <?php $statusInfo = getOrderStatusText($order['status']); ?>
                        <tr>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=order_view&id=<?php echo $order['id']; ?>">
                                    <?php echo e($order['order_number']); ?>
                                </a>
                            </td>
                            <td><?php echo e($order['customer_name']); ?></td>
                            <td><?php echo formatDate($order['order_date']); ?></td>
                            <td><?php echo formatDate($order['delivery_date']); ?></td>
                            <td><?php echo formatPrice($order['total_amount']); ?></td>
                            <td><span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span></td>
                            <td>
                                <?php
                                $priorityLabels = [
                                    'low' => ['text' => 'Низкий', 'class' => 'badge-secondary'],
                                    'normal' => ['text' => 'Обычный', 'class' => 'badge-info'],
                                    'high' => ['text' => 'Высокий', 'class' => 'badge-warning'],
                                    'urgent' => ['text' => 'Срочный', 'class' => 'badge-danger']
                                ];
                                $priorityInfo = $priorityLabels[$order['priority']] ?? ['text' => $order['priority'], 'class' => 'badge-secondary'];
                                ?>
                                <span class="badge <?php echo $priorityInfo['class']; ?>"><?php echo $priorityInfo['text']; ?></span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=order_view&id=<?php echo $order['id']; ?>" class="btn btn-outline-primary" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($auth->hasRole(['admin', 'manager'])): ?>
                                    <button type="button" class="btn btn-outline-success" title="Изменить статус" 
                                            data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Modal для смены статуса -->
                                <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Изменение статуса заказа <?php echo e($order['order_number']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Новый статус</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="new" <?php echo $order['status'] === 'new' ? 'selected' : ''; ?>>Новый</option>
                                                            <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Подтвержден</option>
                                                            <option value="in_production" <?php echo $order['status'] === 'in_production' ? 'selected' : ''; ?>>В производстве</option>
                                                            <option value="quality_check" <?php echo $order['status'] === 'quality_check' ? 'selected' : ''; ?>>Проверка качества</option>
                                                            <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Готов</option>
                                                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Отгружен</option>
                                                            <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Завершен</option>
                                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Отменен</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                                </div>
                                            </form>
                                        </div>
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
<?php endif; ?>

<?php if ($action === 'view' && $order): ?>
<!-- Просмотр заказа -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-invoice me-2"></i>Информация о заказе
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>№ заказа:</strong> <?php echo e($order['order_number']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Дата:</strong> <?php echo formatDate($order['order_date']); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Клиент:</strong> <?php echo e($order['customer_name']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Менеджер:</strong> <?php echo e($order['manager_name']); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Дата поставки:</strong> <?php echo formatDate($order['delivery_date']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Статус:</strong> 
                        <?php $statusInfo = getOrderStatusText($order['status']); ?>
                        <span class="badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['text']; ?></span>
                    </div>
                </div>
                
                <hr>
                
                <h5 class="mb-3">Позиции заказа</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Артикул</th>
                            <th>Продукция</th>
                            <th>Кол-во</th>
                            <th>Цена</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?php echo e($item['article']); ?></td>
                            <td><?php echo e($item['product_name']); ?></td>
                            <td><?php echo $item['quantity']; ?> <?php echo e($item['unit']); ?></td>
                            <td><?php echo formatPrice($item['unit_price']); ?></td>
                            <td><?php echo formatPrice($item['total_price']); ?></td>
                            <td>
                                <?php $itemStatus = [
                                    'pending' => ['text' => 'Ожидает', 'class' => 'badge-secondary'],
                                    'in_progress' => ['text' => 'В работе', 'class' => 'badge-warning'],
                                    'completed' => ['text' => 'Готов', 'class' => 'badge-success'],
                                    'cancelled' => ['text' => 'Отменен', 'class' => 'badge-danger']
                                ][$item['status']] ?? ['text' => $item['status'], 'class' => 'badge-secondary']; ?>
                                <span class="badge <?php echo $itemStatus['class']; ?>"><?php echo $itemStatus['text']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Итого:</th>
                            <th colspan="2"><?php echo formatPrice($order['total_amount']); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user me-2"></i>Контакты клиента
            </div>
            <div class="card-body">
                <p><strong>ИНН:</strong> <?php echo e($order['inn']); ?></p>
                <p><strong>Адрес:</strong> <?php echo e($order['address']); ?></p>
                <p><strong>Телефон:</strong> <?php echo e($order['phone']); ?></p>
                <p><strong>Email:</strong> <?php echo e($order['email']); ?></p>
                <p><strong>Контактное лицо:</strong> <?php echo e($order['contact_person']); ?></p>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-tasks me-2"></i>Производственные задания
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                <p class="text-muted mb-0">Заданий пока нет</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($tasks as $task): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=task_view&id=<?php echo $task['id']; ?>">
                            <?php echo e($task['task_number']); ?>
                        </a>
                        <?php $taskStatus = getTaskStatusText($task['status']); ?>
                        <span class="badge <?php echo $taskStatus['class']; ?>"><?php echo $taskStatus['text']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo BASE_URL; ?>/index.php?page=orders" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Назад к списку
    </a>
</div>
<?php endif; ?>

<?php if ($action === 'create'): ?>
<!-- Форма создания заказа -->
<form method="POST" id="orderForm">
    <input type="hidden" name="action" value="create">
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-file-invoice me-2"></i>Основная информация
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Клиент <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Выберите клиента</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>"><?php echo e($customer['name']); ?> (<?php echo e($customer['inn']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Приоритет</label>
                    <select name="priority" class="form-select">
                        <option value="low">Низкий</option>
                        <option value="normal" selected>Обычный</option>
                        <option value="high">Высокий</option>
                        <option value="urgent">Срочный</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата заказа <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата поставки <span class="text-danger">*</span></label>
                    <input type="date" name="delivery_date" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Комментарий</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-box me-2"></i>Продукция</span>
            <button type="button" class="btn btn-sm btn-success" id="addProductBtn">
                <i class="fas fa-plus me-2"></i>Добавить позицию
            </button>
        </div>
        <div class="card-body">
            <div id="productsContainer">
                <div class="product-row mb-3">
                    <div class="row">
                        <div class="col-md-8">
                            <select name="products[0][product_id]" class="form-select product-select" required>
                                <option value="">Выберите продукцию</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['base_price']; ?>">
                                    <?php echo e($product['article']); ?> - <?php echo e($product['name']); ?> (<?php echo formatPrice($product['base_price']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="products[0][quantity]" class="form-control" placeholder="Количество" min="1" value="1" required>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger remove-product" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Создать заказ
        </button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=orders" class="btn btn-secondary">
            <i class="fas fa-times me-2"></i>Отмена
        </a>
    </div>
</form>

<script>
let productCounter = 1;

document.getElementById('addProductBtn').addEventListener('click', function() {
    const container = document.getElementById('productsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'product-row mb-3';
    newRow.innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <select name="products[${productCounter}][product_id]" class="form-select product-select" required>
                    <option value="">Выберите продукцию</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['base_price']; ?>">
                        <?php echo e($product['article']); ?> - <?php echo e($product['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="products[${productCounter}][quantity]" class="form-control" placeholder="Количество" min="1" value="1" required>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger remove-product">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    productCounter++;
    
    updateRemoveButtons();
});

function updateRemoveButtons() {
    const buttons = document.querySelectorAll('.remove-product');
    buttons.forEach((btn, index) => {
        btn.disabled = buttons.length === 1;
        btn.onclick = function() {
            if (buttons.length > 1) {
                btn.closest('.product-row').remove();
            }
        };
    });
}

updateRemoveButtons();
</script>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
