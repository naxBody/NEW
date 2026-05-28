<?php
/**
 * Модуль управления производственной документацией
 * Планы производства, маршрутные карты, паспорта изделий, ТТН и другие документы
 */
require_once BASE_PATH . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$document_type = $_GET['type'] ?? '';
$message = '';
$messageType = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];
    
    // Создание плана производства
    if ($postAction === 'create_plan') {
        try {
            $db->beginTransaction();
            
            $planNumber = generateDocumentNumber('PLAN');
            $planType = $_POST['plan_type'];
            $planYear = (int)$_POST['plan_year'];
            $planMonth = !empty($_POST['plan_month']) ? (int)$_POST['plan_month'] : null;
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $notes = $_POST['notes'] ?? '';
            $createdBy = $user['id'];
            
            $stmt = $db->prepare("INSERT INTO production_plans 
                (plan_number, plan_type, plan_year, plan_month, start_date, end_date, notes, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$planNumber, $planType, $planYear, $planMonth, $startDate, $endDate, $notes, $createdBy]);
            $planId = $db->lastInsertId();
            
            // Добавляем позиции плана
            if (isset($_POST['products']) && is_array($_POST['products'])) {
                foreach ($_POST['products'] as $productId => $data) {
                    $quantityPlanned = (int)$data['quantity'];
                    $unitPrice = (float)$data['price'];
                    $totalValue = $quantityPlanned * $unitPrice;
                    $priority = (int)($data['priority'] ?? 5);
                    
                    $stmt = $db->prepare("INSERT INTO production_plan_items 
                        (plan_id, product_id, quantity_planned, unit_price, total_value, priority) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$planId, $productId, $quantityPlanned, $unitPrice, $totalValue, $priority]);
                }
            }
            
            // Обновляем общую сумму плана
            $stmt = $db->prepare("UPDATE production_plans SET total_value_planned = (
                SELECT COALESCE(SUM(total_value), 0) FROM production_plan_items WHERE plan_id = ?
            ) WHERE id = ?");
            $stmt->execute([$planId, $planId]);
            
            $db->commit();
            logAction('production_plan_created', 'documents', $planId);
            $message = 'План производства успешно создан';
            $messageType = 'success';
            $action = 'view_plan';
            $id = $planId;
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании плана: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Утверждение плана производства
    if ($postAction === 'approve_plan') {
        $planId = (int)$_POST['plan_id'];
        $approvedBy = $user['id'];
        
        $stmt = $db->prepare("UPDATE production_plans SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$approvedBy, $planId]);
        
        // Создаём напоминания о сроках
        $stmt = $db->prepare("SELECT end_date, plan_number FROM production_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if ($plan) {
            $stmt = $db->prepare("INSERT INTO deadlines 
                (deadline_type, reference_id, reference_type, title, due_date, priority, assigned_to) 
                VALUES ('task', ?, 'production_plan', ?, ?, 'normal', NULL)");
            $stmt->execute([$planId, 'Срок завершения плана ' . $plan['plan_number'], $plan['end_date']]);
        }
        
        logAction('production_plan_approved', 'documents', $planId);
        $message = 'План производства утверждён';
        $messageType = 'success';
    }
    
    // Создание маршрутной карты
    if ($postAction === 'create_route_card') {
        try {
            $cardNumber = generateDocumentNumber('MK');
            $productId = (int)$_POST['product_id'];
            $routeId = (int)$_POST['route_id'];
            $version = $_POST['version'] ?? '1.0';
            $qualityRequirements = $_POST['quality_requirements'] ?? '';
            $safetyRequirements = $_POST['safety_requirements'] ?? '';
            $validFrom = $_POST['valid_from'] ?? date('Y-m-d');
            $validUntil = $_POST['valid_until'] ?? null;
            $developedBy = $user['id'];
            
            $stmt = $db->prepare("INSERT INTO route_cards 
                (card_number, product_id, route_id, version, quality_requirements, safety_requirements, valid_from, valid_until, developed_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$cardNumber, $productId, $routeId, $version, $qualityRequirements, $safetyRequirements, $validFrom, $validUntil, $developedBy]);
            $cardId = $db->lastInsertId();
            
            // Копируем операции из технологического маршрута
            $stmt = $db->prepare("SELECT ro.*, op.name as operation_name 
                FROM route_operations ro 
                JOIN operations op ON ro.operation_id = op.id 
                WHERE ro.route_id = ? ORDER BY ro.sequence_order");
            $stmt->execute([$routeId]);
            $routeOps = $stmt->fetchAll();
            
            foreach ($routeOps as $routeOp) {
                $stmt = $db->prepare("INSERT INTO route_card_operations 
                    (card_id, operation_sequence, operation_id, operation_name, workstation_code, standard_time) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $cardId, 
                    $routeOp['sequence_order'], 
                    $routeOp['operation_id'], 
                    $routeOp['operation_name'],
                    $routeOp['workstation'],
                    $routeOp['standard_time']
                ]);
            }
            
            $db->commit();
            logAction('route_card_created', 'documents', $cardId);
            $message = 'Маршрутная карта успешно создана';
            $messageType = 'success';
            $action = 'view_route_card';
            $id = $cardId;
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании маршрутной карты: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Создание паспорта изделия
    if ($postAction === 'create_passport') {
        try {
            $passportNumber = generateDocumentNumber('PS');
            $serialNumber = $_POST['serial_number'];
            $productId = (int)$_POST['product_id'];
            $productionTaskId = !empty($_POST['production_task_id']) ? (int)$_POST['production_task_id'] : null;
            $manufactureDate = $_POST['manufacture_date'] ?? date('Y-m-d');
            $warrantyPeriod = (int)($_POST['warranty_period'] ?? 24);
            $testResults = json_encode($_POST['test_results'] ?? []);
            $componentsUsed = json_encode($_POST['components'] ?? []);
            
            $stmt = $db->prepare("INSERT INTO product_passports 
                (passport_number, serial_number, product_id, production_task_id, manufacture_date, warranty_period_months, test_results, components_used, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$passportNumber, $serialNumber, $productId, $productionTaskId, $manufactureDate, $warrantyPeriod, $testResults, $componentsUsed]);
            $passportId = $db->lastInsertId();
            
            // Генерируем QR-код данные
            $qrData = json_encode([
                'passport_number' => $passportNumber,
                'serial_number' => $serialNumber,
                'manufacture_date' => $manufactureDate,
                'product_id' => $productId
            ]);
            
            $stmt = $db->prepare("UPDATE product_passports SET qr_code_data = ? WHERE id = ?");
            $stmt->execute([$qrData, $passportId]);
            
            $db->commit();
            logAction('product_passport_created', 'documents', $passportId);
            $message = 'Паспорт изделия успешно создан';
            $messageType = 'success';
            $action = 'view_passport';
            $id = $passportId;
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании паспорта: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Создание ТТН
    if ($postAction === 'create_ttn') {
        try {
            $db->beginTransaction();
            
            $docNumber = generateDocumentNumber('ТТН');
            $docDate = $_POST['document_date'] ?? date('Y-m-d');
            $orderId = (int)$_POST['order_id'];
            $carrierName = $_POST['carrier_name'] ?? '';
            $carrierInn = $_POST['carrier_inn'] ?? '';
            $vehicleNumber = $_POST['vehicle_number'] ?? '';
            $driverName = $_POST['driver_name'] ?? '';
            $driverLicense = $_POST['driver_license'] ?? '';
            $shippingAddress = $_POST['shipping_address'] ?? '';
            $deliveryDate = $_POST['delivery_date'] ?? null;
            $createdBy = $user['id'];
            
            // Получаем информацию о заказе и клиенте
            $stmt = $db->prepare("SELECT o.customer_id, c.name as customer_name, c.address 
                FROM orders o 
                JOIN customers c ON o.customer_id = c.id 
                WHERE o.id = ?");
            $stmt->execute([$orderId]);
            $orderInfo = $stmt->fetch();
            
            $stmt = $db->prepare("INSERT INTO shipping_documents 
                (document_type, document_number, document_date, order_id, customer_id, carrier_name, carrier_inn, 
                vehicle_number, driver_name, driver_license, shipping_address, delivery_date, created_by, status) 
                VALUES ('ttn', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([
                $docNumber, $docDate, $orderId, $orderInfo['customer_id'], $carrierName, $carrierInn,
                $vehicleNumber, $driverName, $driverLicense, $shippingAddress ?? $orderInfo['address'], $deliveryDate, $createdBy
            ]);
            $docId = $db->lastInsertId();
            
            // Добавляем позиции из заказа
            $stmt = $db->prepare("SELECT oi.product_id, p.name, oi.quantity, p.unit, oi.unit_price, oi.total_price 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll();
            
            $totalWeight = 0;
            foreach ($items as $item) {
                // Примерный вес - нужно добавить в продукцию
                $weightPerUnit = 10; // заглушка
                $totalItemWeight = $weightPerUnit * $item['quantity'];
                $totalWeight += $totalItemWeight;
                
                $stmt = $db->prepare("INSERT INTO shipping_document_items 
                    (shipping_document_id, product_id, quantity, unit, weight_per_unit, total_weight, unit_price, total_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$docId, $item['product_id'], $item['quantity'], $item['unit'], $weightPerUnit, $totalItemWeight, $item['unit_price'], $item['total_price']]);
            }
            
            // Обновляем общий вес
            $stmt = $db->prepare("UPDATE shipping_documents SET total_weight = ? WHERE id = ?");
            $stmt->execute([$totalWeight, $docId]);
            
            $db->commit();
            logAction('ttn_created', 'documents', $docId);
            $message = 'ТТН успешно создана';
            $messageType = 'success';
            $action = 'view_ttn';
            $id = $docId;
        } catch (PDOException $e) {
            $db->rollBack();
            $message = 'Ошибка при создании ТТН: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Получение данных для отображения
if ($action === 'list') {
    $typeFilter = $_GET['type'] ?? 'all';
    
    $documents = [];
    
    // Планы производства
    if ($typeFilter === 'all' || $typeFilter === 'plans') {
        $stmt = $db->query("SELECT pp.*, u.full_name as created_by_name 
            FROM production_plans pp 
            LEFT JOIN users u ON pp.created_by = u.id 
            ORDER BY pp.created_at DESC LIMIT 10");
        $documents['plans'] = $stmt->fetchAll();
    }
    
    // Маршрутные карты
    if ($typeFilter === 'all' || $typeFilter === 'route_cards') {
        $stmt = $db->query("SELECT rc.*, p.name as product_name 
            FROM route_cards rc 
            JOIN products p ON rc.product_id = p.id 
            ORDER BY rc.created_at DESC LIMIT 10");
        $documents['route_cards'] = $stmt->fetchAll();
    }
    
    // Паспорта изделий
    if ($typeFilter === 'all' || $typeFilter === 'passports') {
        $stmt = $db->query("SELECT pp.*, p.name as product_name 
            FROM product_passports pp 
            JOIN products p ON pp.product_id = p.id 
            ORDER BY pp.created_at DESC LIMIT 10");
        $documents['passports'] = $stmt->fetchAll();
    }
    
    // ТТН
    if ($typeFilter === 'all' || $typeFilter === 'ttn') {
        $stmt = $db->query("SELECT sd.*, o.order_number, c.name as customer_name 
            FROM shipping_documents sd 
            JOIN orders o ON sd.order_id = o.id 
            JOIN customers c ON sd.customer_id = c.id 
            ORDER BY sd.created_at DESC LIMIT 10");
        $documents['ttn'] = $stmt->fetchAll();
    }
}

// Просмотр плана производства
if ($action === 'view_plan' && $id) {
    $stmt = $db->prepare("SELECT pp.*, u1.full_name as created_by_name, u2.full_name as approved_by_name 
        FROM production_plans pp 
        LEFT JOIN users u1 ON pp.created_by = u1.id 
        LEFT JOIN users u2 ON pp.approved_by = u2.id 
        WHERE pp.id = ?");
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    
    if ($plan) {
        $stmt = $db->prepare("SELECT ppi.*, p.name as product_name, p.article 
            FROM production_plan_items ppi 
            JOIN products p ON ppi.product_id = p.id 
            WHERE ppi.plan_id = ? 
            ORDER BY ppi.priority, p.name");
        $stmt->execute([$id]);
        $planItems = $stmt->fetchAll();
    }
}

// Просмотр маршрутной карты
if ($action === 'view_route_card' && $id) {
    $stmt = $db->prepare("SELECT rc.*, p.name as product_name, tr.name as route_name,
            u1.full_name as developed_by_name, u2.full_name as checked_by_name, u3.full_name as approved_by_name
        FROM route_cards rc 
        JOIN products p ON rc.product_id = p.id 
        JOIN technology_routes tr ON rc.route_id = tr.id
        LEFT JOIN users u1 ON rc.developed_by = u1.id 
        LEFT JOIN users u2 ON rc.checked_by = u2.id 
        LEFT JOIN users u3 ON rc.approved_by = u3.id 
        WHERE rc.id = ?");
    $stmt->execute([$id]);
    $routeCard = $stmt->fetch();
    
    if ($routeCard) {
        $stmt = $db->prepare("SELECT rco.*, op.description as operation_description 
            FROM route_card_operations rco 
            LEFT JOIN operations op ON rco.operation_id = op.id 
            WHERE rco.card_id = ? 
            ORDER BY rco.operation_sequence");
        $stmt->execute([$id]);
        $cardOperations = $stmt->fetchAll();
    }
}

// Просмотр паспорта изделия
if ($action === 'view_passport' && $id) {
    $stmt = $db->prepare("SELECT pp.*, p.name as product_name, p.article, pt.task_number 
        FROM product_passports pp 
        JOIN products p ON pp.product_id = p.id 
        LEFT JOIN production_tasks pt ON pp.production_task_id = pt.id 
        WHERE pp.id = ?");
    $stmt->execute([$id]);
    $passport = $stmt->fetch();
}

// Просмотр ТТН
if ($action === 'view_ttn' && $id) {
    $stmt = $db->prepare("SELECT sd.*, o.order_number, o.order_date, c.name as customer_name, c.inn as customer_inn, 
            c.address as customer_address, u.full_name as shipped_by_name
        FROM shipping_documents sd 
        JOIN orders o ON sd.order_id = o.id 
        JOIN customers c ON sd.customer_id = c.id 
        LEFT JOIN users u ON sd.shipped_by = u.id 
        WHERE sd.id = ?");
    $stmt->execute([$id]);
    $ttn = $stmt->fetch();
    
    if ($ttn) {
        $stmt = $db->prepare("SELECT sdi.*, p.name as product_name, p.article 
            FROM shipping_document_items sdi 
            JOIN products p ON sdi.product_id = p.id 
            WHERE sdi.shipping_document_id = ?");
        $stmt->execute([$id]);
        $ttnItems = $stmt->fetchAll();
    }
}

// Форма создания плана производства
if ($action === 'create_plan_form') {
    $products = getProductsList();
}

// Форма создания маршрутной карты
if ($action === 'create_route_card_form') {
    $products = getProductsList();
    $stmt = $db->query("SELECT tr.*, p.name as product_name FROM technology_routes tr 
        JOIN products p ON tr.product_id = p.id WHERE tr.is_active = TRUE");
    $routes = $stmt->fetchAll();
}

// Форма создания паспорта изделия
if ($action === 'create_passport_form') {
    $products = getProductsList();
    $stmt = $db->query("SELECT pt.*, p.name as product_name, o.order_number 
        FROM production_tasks pt 
        JOIN order_items oi ON pt.order_item_id = oi.id 
        JOIN products p ON oi.product_id = p.id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE pt.status IN ('completed', 'in_progress') 
        ORDER BY pt.created_at DESC");
    $completedTasks = $stmt->fetchAll();
}

// Форма создания ТТН
if ($action === 'create_ttn_form') {
    $stmt = $db->query("SELECT o.*, c.name as customer_name 
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        WHERE o.status IN ('ready', 'shipped') 
        ORDER BY o.created_at DESC");
    $readyOrders = $stmt->fetchAll();
}
?>

<div class="page-title">
    <h3><i class="fas fa-file-alt me-2"></i><?php 
        if ($action === 'list') echo 'Производственная документация';
        elseif ($action === 'view_plan') echo 'План производства №' . e($plan['plan_number'] ?? '');
        elseif ($action === 'view_route_card') echo 'Маршрутная карта №' . e($routeCard['card_number'] ?? '');
        elseif ($action === 'view_passport') echo 'Паспорт изделия №' . e($passport['passport_number'] ?? '');
        elseif ($action === 'view_ttn') echo 'ТТН №' . e($ttn['document_number'] ?? '');
        elseif (strpos($action, 'create_') !== false) {
            $types = ['plan' => 'Плана производства', 'route_card' => 'Маршрутной карты', 'passport' => 'Паспорта изделия', 'ttn' => 'ТТН'];
            $typeKey = str_replace('_form', '', str_replace('create_', '', $action));
            echo 'Создание ' . ($types[$typeKey] ?? 'документа');
        }
        else echo 'Документы';
    ?></h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=documents">Документы</a></li>
            <?php if ($action !== 'list'): ?>
            <li class="breadcrumb-item active"><?php echo $action === 'view_plan' || $action === 'view_route_card' || $action === 'view_passport' || $action === 'view_ttn' ? 'Просмотр' : 'Создание'; ?></li>
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
<!-- Фильтры и типы документов -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-filter me-2"></i>Фильтры</span>
        <div class="btn-group">
            <?php if ($auth->hasRole(['admin', 'manager', 'technologist'])): ?>
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-plus me-2"></i>Создать документ
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=documents&action=create_plan_form">План производства</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=documents&action=create_route_card_form">Маршрутную карту</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=documents&action=create_passport_form">Паспорт изделия</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/index.php?page=documents&action=create_ttn_form">ТТН</a></li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="documents">
            <div class="col-md-4">
                <label class="form-label">Тип документа</label>
                <select name="type" class="form-select">
                    <option value="all">Все документы</option>
                    <option value="plans" <?php echo $typeFilter === 'plans' ? 'selected' : ''; ?>>Планы производства</option>
                    <option value="route_cards" <?php echo $typeFilter === 'route_cards' ? 'selected' : ''; ?>>Маршрутные карты</option>
                    <option value="passports" <?php echo $typeFilter === 'passports' ? 'selected' : ''; ?>>Паспорта изделий</option>
                    <option value="ttn" <?php echo $typeFilter === 'ttn' ? 'selected' : ''; ?>>ТТН</option>
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

<!-- Карточки с документами -->
<div class="row">
    <!-- Планы производства -->
    <?php if ($typeFilter === 'all' || $typeFilter === 'plans'): ?>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-calendar-alt me-2"></i>Планы производства
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>№ плана</th>
                                <th>Период</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents['plans'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Нет планов</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents['plans'] as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['plan_number']); ?></td>
                                    <td><?php echo formatDate($doc['start_date']); ?> - <?php echo formatDate($doc['end_date']); ?></td>
                                    <td><span class="badge badge-<?php echo $doc['status'] === 'approved' ? 'success' : ($doc['status'] === 'draft' ? 'secondary' : 'warning'); ?>"><?php echo e($doc['status']); ?></span></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents&action=view_plan&id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
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
    </div>
    <?php endif; ?>
    
    <!-- Маршрутные карты -->
    <?php if ($typeFilter === 'all' || $typeFilter === 'route_cards'): ?>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="fas fa-map-signs me-2"></i>Маршрутные карты
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>№ карты</th>
                                <th>Продукция</th>
                                <th>Версия</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents['route_cards'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Нет маршрутных карт</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents['route_cards'] as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['card_number']); ?></td>
                                    <td><?php echo e($doc['product_name']); ?></td>
                                    <td><?php echo e($doc['version']); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents&action=view_route_card&id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
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
    </div>
    <?php endif; ?>
    
    <!-- Паспорта изделий -->
    <?php if ($typeFilter === 'all' || $typeFilter === 'passports'): ?>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <i class="fas fa-certificate me-2"></i>Паспорта изделий
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>№ паспорта</th>
                                <th>Продукция</th>
                                <th>Серийный номер</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents['passports'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Нет паспортов</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents['passports'] as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['passport_number']); ?></td>
                                    <td><?php echo e($doc['product_name']); ?></td>
                                    <td><?php echo e($doc['serial_number']); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents&action=view_passport&id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
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
    </div>
    <?php endif; ?>
    
    <!-- ТТН -->
    <?php if ($typeFilter === 'all' || $typeFilter === 'ttn'): ?>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-truck me-2"></i>Товарно-транспортные накладные
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>№ ТТН</th>
                                <th>Заказ</th>
                                <th>Клиент</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents['ttn'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Нет ТТН</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents['ttn'] as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['document_number']); ?></td>
                                    <td><?php echo e($doc['order_number']); ?></td>
                                    <td><?php echo e($doc['customer_name']); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/index.php?page=documents&action=view_ttn&id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
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
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($action === 'view_plan' && $plan): ?>
<!-- Просмотр плана производства -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-invoice me-2"></i>Информация о плане</span>
        <?php if ($plan['status'] === 'draft' && $auth->hasRole(['admin', 'manager'])): ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="approve_plan">
            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check me-2"></i>Утвердить план
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><strong>№ плана:</strong> <?php echo e($plan['plan_number']); ?></div>
            <div class="col-md-3"><strong>Тип:</strong> <?php echo e($plan['plan_type']); ?></div>
            <div class="col-md-3"><strong>Статус:</strong> <span class="badge badge-<?php echo $plan['status'] === 'approved' ? 'success' : 'secondary'; ?>"><?php echo e($plan['status']); ?></span></div>
            <div class="col-md-3"><strong>Период:</strong> <?php echo formatDate($plan['start_date']); ?> - <?php echo formatDate($plan['end_date']); ?></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6"><strong>Создан:</strong> <?php echo e($plan['created_by_name']); ?> (<?php echo formatDate($plan['created_at']); ?>)</div>
            <div class="col-md-6"><strong>Утверждён:</strong> <?php echo $plan['approved_by_name'] ? e($plan['approved_by_name']) : 'Не утверждён'; ?> <?php echo $plan['approved_at'] ? '(' . formatDate($plan['approved_at']) . ')' : ''; ?></div>
        </div>
        <div class="row">
            <div class="col-md-6"><strong>Плановая сумма:</strong> <?php echo formatPrice($plan['total_value_planned']); ?></div>
            <div class="col-md-6"><strong>Фактическая сумма:</strong> <?php echo formatPrice($plan['total_value_actual'] ?? 0); ?></div>
        </div>
        <?php if ($plan['notes']): ?>
        <div class="row mt-3">
            <div class="col-12"><strong>Примечание:</strong> <?php echo nl2br(e($plan['notes'])); ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="fas fa-list me-2"></i>Позиции плана</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>№</th>
                    <th>Артикул</th>
                    <th>Продукция</th>
                    <th>План</th>
                    <th>Факт</th>
                    <th>Цена</th>
                    <th>Сумма</th>
                    <th>Приоритет</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planItems as $idx => $item): ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <td><?php echo e($item['article']); ?></td>
                    <td><?php echo e($item['product_name']); ?></td>
                    <td><?php echo $item['quantity_planned']; ?></td>
                    <td><?php echo $item['quantity_actual']; ?></td>
                    <td><?php echo formatPrice($item['unit_price']); ?></td>
                    <td><?php echo formatPrice($item['total_value']); ?></td>
                    <td><span class="badge badge-<?php echo $item['priority'] <= 3 ? 'danger' : 'secondary'; ?>"><?php echo $item['priority']; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'view_route_card' && $routeCard): ?>
<!-- Просмотр маршрутной карты -->
<div class="card">
    <div class="card-header"><i class="fas fa-file-contract me-2"></i>Информация о маршрутной карте</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><strong>№ карты:</strong> <?php echo e($routeCard['card_number']); ?></div>
            <div class="col-md-3"><strong>Продукция:</strong> <?php echo e($routeCard['product_name']); ?></div>
            <div class="col-md-3"><strong>Версия:</strong> <?php echo e($routeCard['version']); ?></div>
            <div class="col-md-3"><strong>Статус:</strong> <span class="badge badge-<?php echo $routeCard['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo e($routeCard['status']); ?></span></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4"><strong>Разработал:</strong> <?php echo e($routeCard['developed_by_name'] ?? 'Не указан'); ?></div>
            <div class="col-md-4"><strong>Проверил:</strong> <?php echo e($routeCard['checked_by_name'] ?? 'Не указан'); ?></div>
            <div class="col-md-4"><strong>Утвердил:</strong> <?php echo e($routeCard['approved_by_name'] ?? 'Не указан'); ?></div>
        </div>
        <div class="row">
            <div class="col-md-6"><strong>Действует с:</strong> <?php echo formatDate($routeCard['valid_from']); ?></div>
            <div class="col-md-6"><strong>Действует по:</strong> <?php echo $routeCard['valid_until'] ? formatDate($routeCard['valid_until']) : 'Бессрочно'; ?></div>
        </div>
        <?php if ($routeCard['quality_requirements']): ?>
        <div class="row mt-3">
            <div class="col-12"><strong>Требования к качеству:</strong> <?php echo nl2br(e($routeCard['quality_requirements'])); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($routeCard['safety_requirements']): ?>
        <div class="row mt-3">
            <div class="col-12"><strong>Требования безопасности:</strong> <?php echo nl2br(e($routeCard['safety_requirements'])); ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="fas fa-tasks me-2"></i>Технологические операции</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>№</th>
                    <th>Операция</th>
                    <th>Рабочее место</th>
                    <th>Время (мин)</th>
                    <th>Оборудование</th>
                    <th>Инструмент</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cardOperations as $op): ?>
                <tr>
                    <td><?php echo $op['operation_sequence']; ?></td>
                    <td><strong><?php echo e($op['operation_name']); ?></strong><?php echo $op['operation_description'] ? '<br><small class="text-muted">' . e($op['operation_description']) . '</small>' : ''; ?></td>
                    <td><?php echo e($op['workstation_code'] ?? '-'); ?></td>
                    <td><?php echo $op['standard_time']; ?></td>
                    <td><?php echo e($op['equipment_required'] ?? '-'); ?></td>
                    <td><?php echo e($op['tooling_required'] ?? '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'view_passport' && $passport): ?>
<!-- Просмотр паспорта изделия -->
<div class="card">
    <div class="card-header"><i class="fas fa-certificate me-2"></i>Паспорт изделия</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4"><strong>№ паспорта:</strong> <?php echo e($passport['passport_number']); ?></div>
            <div class="col-md-4"><strong>Серийный номер:</strong> <?php echo e($passport['serial_number']); ?></div>
            <div class="col-md-4"><strong>Статус:</strong> <span class="badge badge-success"><?php echo e($passport['status']); ?></span></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4"><strong>Продукция:</strong> <?php echo e($passport['product_name']); ?> (<?php echo e($passport['article']); ?>)</div>
            <div class="col-md-4"><strong>Дата изготовления:</strong> <?php echo formatDate($passport['manufacture_date']); ?></div>
            <div class="col-md-4"><strong>Гарантия:</strong> <?php echo $passport['warranty_period_months']; ?> мес.</div>
        </div>
        <?php if ($passport['task_number']): ?>
        <div class="row mb-3">
            <div class="col-md-6"><strong>Производственное задание:</strong> <a href="<?php echo BASE_URL; ?>/index.php?page=production&action=task_view&id=<?php echo $passport['production_task_id']; ?>"><?php echo e($passport['task_number']); ?></a></div>
        </div>
        <?php endif; ?>
        <?php if ($passport['test_results']): ?>
        <div class="row mt-3">
            <div class="col-12">
                <strong>Результаты испытаний:</strong>
                <pre class="bg-light p-3 mt-2"><?php echo e(json_encode(json_decode($passport['test_results']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($passport['qr_code_data']): ?>
        <div class="row mt-3">
            <div class="col-md-6">
                <strong>QR-код:</strong>
                <div class="border p-3 mt-2 d-inline-block">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($passport['qr_code_data']); ?>" alt="QR Code">
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'view_ttn' && $ttn): ?>
<!-- Просмотр ТТН -->
<div class="card">
    <div class="card-header"><i class="fas fa-truck-loading me-2"></i>Товарно-транспортная накладная</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4"><strong>№ ТТН:</strong> <?php echo e($ttn['document_number']); ?></div>
            <div class="col-md-4"><strong>Дата:</strong> <?php echo formatDate($ttn['document_date']); ?></div>
            <div class="col-md-4"><strong>Статус:</strong> <span class="badge badge-<?php echo $ttn['status'] === 'signed' ? 'success' : 'secondary'; ?>"><?php echo e($ttn['status']); ?></span></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6"><strong>Заказ:</strong> <a href="<?php echo BASE_URL; ?>/index.php?page=orders&action=order_view&id=<?php echo $ttn['order_id']; ?>"><?php echo e($ttn['order_number']); ?></a> от <?php echo formatDate($ttn['order_date']); ?></div>
            <div class="col-md-6"><strong>Клиент:</strong> <?php echo e($ttn['customer_name']); ?> (ИНН: <?php echo e($ttn['customer_inn']); ?>)</div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12"><strong>Адрес доставки:</strong> <?php echo nl2br(e($ttn['shipping_address'])); ?></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4"><strong>Перевозчик:</strong> <?php echo e($ttn['carrier_name'] ?? '-'); ?> <?php echo $ttn['carrier_inn'] ? '(ИНН: ' . e($ttn['carrier_inn']) . ')' : ''; ?></div>
            <div class="col-md-4"><strong>Автомобиль:</strong> <?php echo e($ttn['vehicle_number'] ?? '-'); ?></div>
            <div class="col-md-4"><strong>Водитель:</strong> <?php echo e($ttn['driver_name'] ?? '-'); ?> <?php echo $ttn['driver_license'] ? '(' . e($ttn['driver_license']) . ')' : ''; ?></div>
        </div>
        <div class="row">
            <div class="col-md-4"><strong>Общий вес:</strong> <?php echo $ttn['total_weight']; ?> кг</div>
            <div class="col-md-4"><strong>Дата доставки:</strong> <?php echo $ttn['delivery_date'] ? formatDate($ttn['delivery_date']) : 'Не указана'; ?></div>
            <?php if ($ttn['shipped_by_name']): ?>
            <div class="col-md-4"><strong>Отгрузил:</strong> <?php echo e($ttn['shipped_by_name']); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="fas fa-boxes me-2"></i>Грузовые места</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>№</th>
                    <th>Артикул</th>
                    <th>Наименование</th>
                    <th>Кол-во</th>
                    <th>Вес за ед. (кг)</th>
                    <th>Общий вес (кг)</th>
                    <th>Цена</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ttnItems as $idx => $item): ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <td><?php echo e($item['article']); ?></td>
                    <td><?php echo e($item['product_name']); ?></td>
                    <td><?php echo $item['quantity']; ?> <?php echo e($item['unit']); ?></td>
                    <td><?php echo $item['weight_per_unit']; ?></td>
                    <td><?php echo $item['total_weight']; ?></td>
                    <td><?php echo formatPrice($item['unit_price']); ?></td>
                    <td><?php echo formatPrice($item['total_price']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end"><strong>Итого:</strong></td>
                    <td><strong><?php echo array_sum(array_column($ttnItems, 'total_weight')); ?> кг</strong></td>
                    <td colspan="2"><strong><?php echo formatPrice(array_sum(array_column($ttnItems, 'total_price'))); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'create_plan_form'): ?>
<!-- Форма создания плана производства -->
<form method="POST" class="card">
    <div class="card-header"><i class="fas fa-plus me-2"></i>Создание плана производства</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Тип плана *</label>
                <select name="plan_type" class="form-select" required>
                    <option value="monthly">Месячный</option>
                    <option value="quarterly">Квартальный</option>
                    <option value="annual">Годовой</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Год *</label>
                <input type="number" name="plan_year" class="form-control" value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Месяц</label>
                <select name="plan_month" class="form-select">
                    <option value="">-</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Дата начала *</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Дата окончания *</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Продукция в плане</label>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Продукция</th>
                        <th width="150">Количество</th>
                        <th width="150">Цена (BYN)</th>
                        <th width="100">Приоритет</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <?php echo e($product['name']); ?> (<?php echo e($product['article']); ?>)
                            <input type="hidden" name="products[<?php echo $product['id']; ?>][exists]" value="1">
                        </td>
                        <td><input type="number" name="products[<?php echo $product['id']; ?>][quantity]" class="form-control" value="0" min="0"></td>
                        <td><input type="number" name="products[<?php echo $product['id']; ?>][price]" class="form-control" value="<?php echo $product['base_price']; ?>" step="0.01" min="0"></td>
                        <td><input type="number" name="products[<?php echo $product['id']; ?>][priority]" class="form-control" value="5" min="1" max="10"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Примечание</label>
            <textarea name="notes" class="form-control" rows="3"></textarea>
        </div>
    </div>
    <div class="card-footer">
        <input type="hidden" name="action" value="create_plan">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Сохранить план</button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="btn btn-secondary">Отмена</a>
    </div>
</form>
<?php endif; ?>

<?php if ($action === 'create_route_card_form'): ?>
<!-- Форма создания маршрутной карты -->
<form method="POST" class="card">
    <div class="card-header"><i class="fas fa-plus me-2"></i>Создание маршрутной карты</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Продукция *</label>
                <select name="product_id" class="form-select" required>
                    <option value="">Выберите продукцию</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>"><?php echo e($product['name']); ?> (<?php echo e($product['article']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Технологический маршрут *</label>
                <select name="route_id" class="form-select" required>
                    <option value="">Выберите маршрут</option>
                    <?php foreach ($routes as $route): ?>
                    <option value="<?php echo $route['id']; ?>"><?php echo e($route['name']); ?> (<?php echo e($route['product_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Версия</label>
                <input type="text" name="version" class="form-control" value="1.0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Действует с</label>
                <input type="date" name="valid_from" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Действует по</label>
                <input type="date" name="valid_until" class="form-control">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Требования к качеству</label>
            <textarea name="quality_requirements" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Требования безопасности</label>
            <textarea name="safety_requirements" class="form-control" rows="3"></textarea>
        </div>
    </div>
    <div class="card-footer">
        <input type="hidden" name="action" value="create_route_card">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Сохранить маршрутную карту</button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="btn btn-secondary">Отмена</a>
    </div>
</form>
<?php endif; ?>

<?php if ($action === 'create_passport_form'): ?>
<!-- Форма создания паспорта изделия -->
<form method="POST" class="card">
    <div class="card-header"><i class="fas fa-plus me-2"></i>Создание паспорта изделия</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Продукция *</label>
                <select name="product_id" class="form-select" required>
                    <option value="">Выберите продукцию</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>"><?php echo e($product['name']); ?> (<?php echo e($product['article']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Серийный номер *</label>
                <input type="text" name="serial_number" class="form-control" required placeholder="Например: АД-100-2024-001">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Производственное задание</label>
                <select name="production_task_id" class="form-select">
                    <option value="">Не привязано</option>
                    <?php foreach ($completedTasks as $task): ?>
                    <option value="<?php echo $task['id']; ?>"><?php echo e($task['task_number']); ?> - <?php echo e($task['product_name']); ?> (<?php echo e($task['order_number']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Дата изготовления</label>
                <input type="date" name="manufacture_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Гарантия (мес.)</label>
                <input type="number" name="warranty_period" class="form-control" value="24" min="0">
            </div>
        </div>
    </div>
    <div class="card-footer">
        <input type="hidden" name="action" value="create_passport">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Сохранить паспорт</button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="btn btn-secondary">Отмена</a>
    </div>
</form>
<?php endif; ?>

<?php if ($action === 'create_ttn_form'): ?>
<!-- Форма создания ТТН -->
<form method="POST" class="card">
    <div class="card-header"><i class="fas fa-plus me-2"></i>Создание товарно-транспортной накладной</div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Дата документа</label>
                <input type="date" name="document_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label">Заказ *</label>
                <select name="order_id" class="form-select" required>
                    <option value="">Выберите заказ</option>
                    <?php foreach ($readyOrders as $order): ?>
                    <option value="<?php echo $order['id']; ?>"><?php echo e($order['order_number']); ?> - <?php echo e($order['customer_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <hr>
        <h5>Информация о перевозке</h5>
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Перевозчик</label>
                <input type="text" name="carrier_name" class="form-control" placeholder="Название организации">
            </div>
            <div class="col-md-4">
                <label class="form-label">ИНН перевозчика</label>
                <input type="text" name="carrier_inn" class="form-control" placeholder="УНН">
            </div>
            <div class="col-md-4">
                <label class="form-label">Автомобиль</label>
                <input type="text" name="vehicle_number" class="form-control" placeholder="Номер автомобиля">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Водитель</label>
                <input type="text" name="driver_name" class="form-control" placeholder="ФИО водителя">
            </div>
            <div class="col-md-4">
                <label class="form-label">Номер прав</label>
                <input type="text" name="driver_license" class="form-control" placeholder="Номер водительского удостоверения">
            </div>
            <div class="col-md-4">
                <label class="form-label">Плановая дата доставки</label>
                <input type="date" name="delivery_date" class="form-control">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Адрес доставки</label>
            <textarea name="shipping_address" class="form-control" rows="2" placeholder="Если не указано, будет использован адрес клиента"></textarea>
        </div>
    </div>
    <div class="card-footer">
        <input type="hidden" name="action" value="create_ttn">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Создать ТТН</button>
        <a href="<?php echo BASE_URL; ?>/index.php?page=documents" class="btn btn-secondary">Отмена</a>
    </div>
</form>
<?php endif; ?>
