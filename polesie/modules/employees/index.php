<?php
/**
 * Модуль управления сотрудниками
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

    // Создание сотрудника
    if ($postAction === 'create') {
        try {
            $data = [
                'username' => trim($_POST['username']),
                'password' => $_POST['password'],
                'full_name' => trim($_POST['full_name']),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'role' => $_POST['role'],
                'department' => trim($_POST['department'] ?? ''),
                'position' => trim($_POST['position'] ?? '')
            ];

            $auth = new Auth();
            $result = $auth->register($data);

            if ($result['success']) {
                $message = 'Сотрудник успешно добавлен';
                $messageType = 'success';
                logAction('employee_created', 'employees');
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // Редактирование сотрудника
    if ($postAction === 'update') {
        try {
            $employeeId = (int)$_POST['employee_id'];
            
            $data = [
                'full_name' => trim($_POST['full_name']),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'department' => trim($_POST['department'] ?? ''),
                'position' => trim($_POST['position'] ?? '')
            ];

            $auth = new Auth();
            $result = $auth->updateProfile($employeeId, $data);

            if ($result['success']) {
                $message = 'Данные сотрудника успешно обновлены';
                $messageType = 'success';
                logAction('employee_updated', 'employees', $employeeId);
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // Смена статуса активности
    if ($postAction === 'toggle_active') {
        try {
            $employeeId = (int)$_POST['employee_id'];
            $isActive = (bool)$_POST['is_active'];

            $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive ? 1 : 0, $employeeId]);

            $message = 'Статус сотрудника изменен';
            $messageType = 'success';
            logAction('employee_status_changed', 'employees', $employeeId);
        } catch (Exception $e) {
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // Смена пароля
    if ($postAction === 'change_password') {
        try {
            $employeeId = (int)$_POST['employee_id'];
            $newPassword = $_POST['new_password'];

            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Пароль должен быть не менее ' . PASSWORD_MIN_LENGTH . ' символов');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $employeeId]);

            $message = 'Пароль успешно изменен';
            $messageType = 'success';
            logAction('password_changed_admin', 'employees', $employeeId);
        } catch (Exception $e) {
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Получение списка сотрудников
$employees = [];
if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    $employees = $stmt->fetchAll();
}

// Получение данных одного сотрудника
$employee = null;
if ($action === 'view' && $id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
}

$activePage = 'employees';
?>

<div class="page-title">
    <h3><i class="fas fa-users me-2"></i>Сотрудники</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item active">Сотрудники</li>
        </ol>
    </nav>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
    <?php echo e($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- Список сотрудников -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Список сотрудников</span>
        <?php if ($auth->hasRole(['admin', 'manager'])): ?>
        <a href="<?php echo BASE_URL; ?>/index.php?page=employee_create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-2"></i>Добавить сотрудника
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Логин</th>
                        <th>Роль</th>
                        <th>Отдел</th>
                        <th>Должность</th>
                        <th>Статус</th>
                        <th>Последний вход</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Сотрудников пока нет</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?php echo $emp['id']; ?></td>
                            <td><strong><?php echo e($emp['full_name']); ?></strong></td>
                            <td><?php echo e($emp['username']); ?></td>
                            <td><span class="badge bg-info"><?php echo getRoleText($emp['role']); ?></span></td>
                            <td><?php echo e($emp['department']); ?></td>
                            <td><?php echo e($emp['position']); ?></td>
                            <td>
                                <?php if ($emp['is_active']): ?>
                                    <span class="badge bg-success">Активен</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Не активен</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($emp['last_login'], 'd.m.Y H:i'); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=employee_view&id=<?php echo $emp['id']; ?>" 
                                       class="btn btn-outline-primary" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($auth->hasRole(['admin', 'manager'])): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=employee_edit&id=<?php echo $emp['id']; ?>" 
                                       class="btn btn-outline-warning" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
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

<?php elseif ($action === 'view' && $employee): ?>
<!-- Просмотр сотрудника -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user me-2"></i>Информация о сотруднике
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">ФИО:</dt>
                    <dd class="col-sm-8"><?php echo e($employee['full_name']); ?></dd>

                    <dt class="col-sm-4">Логин:</dt>
                    <dd class="col-sm-8"><?php echo e($employee['username']); ?></dd>

                    <dt class="col-sm-4">Email:</dt>
                    <dd class="col-sm-8"><?php echo e($employee['email']); ?></dd>

                    <dt class="col-sm-4">Телефон:</dt>
                    <dd class="col-sm-8"><?php echo e($employee['phone']); ?></dd>

                    <dt class="col-sm-4">Роль:</dt>
                    <dd class="col-sm-8"><span class="badge bg-info"><?php echo getRoleText($employee['role']); ?></span></dd>

                    <dt class="col-sm-4">Отдел:</dt>
                    <dd class="col-sm-8"><?php echo e($employee['department']); ?></dd>

                    <dt class="col-sm-4">Должность:</dt>
                    <dd class="col-sm-8"><?php echo e($employee['position']); ?></dd>

                    <dt class="col-sm-4">Статус:</dt>
                    <dd class="col-sm-8">
                        <?php if ($employee['is_active']): ?>
                            <span class="badge bg-success">Активен</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Не активен</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Создан:</dt>
                    <dd class="col-sm-8"><?php echo formatDate($employee['created_at'], 'd.m.Y H:i'); ?></dd>

                    <dt class="col-sm-4">Последний вход:</dt>
                    <dd class="col-sm-8"><?php echo formatDate($employee['last_login'], 'd.m.Y H:i'); ?></dd>
                </dl>
            </div>
            <div class="card-footer">
                <a href="<?php echo BASE_URL; ?>/index.php?page=employees" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Назад к списку
                </a>
                <?php if ($auth->hasRole(['admin', 'manager'])): ?>
                <a href="<?php echo BASE_URL; ?>/index.php?page=employee_edit&id=<?php echo $employee['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i>Редактировать
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history me-2"></i>История действий
            </div>
            <div class="card-body">
                <?php
                $logStmt = $db->prepare("SELECT * FROM system_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                $logStmt->execute([$employee['id']]);
                $logs = $logStmt->fetchAll();
                ?>
                <?php if (empty($logs)): ?>
                <p class="text-muted text-center">Действий пока нет</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($logs as $log): ?>
                    <li class="list-group-item">
                        <small>
                            <strong><?php echo e($log['action']); ?></strong> 
                            в модуле <?php echo e($log['module']); ?>
                            <br>
                            <span class="text-muted"><?php echo formatDate($log['created_at'], 'd.m.Y H:i'); ?></span>
                        </small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
<!-- Форма создания/редактирования -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-user-plus me-2"></i><?php echo $action === 'create' ? 'Добавление сотрудника' : 'Редактирование сотрудника'; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
            <?php if ($action === 'edit' && $employee): ?>
            <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Логин *</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo e($employee['username'] ?? ''); ?>" 
                           <?php echo $action === 'edit' ? 'disabled' : 'required'; ?>>
                    <?php if ($action === 'edit'): ?>
                    <small class="text-muted">Логин нельзя изменить</small>
                    <?php endif; ?>
                </div>

                <?php if ($action === 'create'): ?>
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Пароль *</label>
                    <input type="password" class="form-control" id="password" name="password" required 
                           minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                </div>
                <?php endif; ?>

                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">ФИО *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo e($employee['full_name'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo e($employee['email'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Телефон</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo e($employee['phone'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Роль *</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="operator" <?php echo ($employee['role'] ?? '') === 'operator' ? 'selected' : ''; ?>>Оператор</option>
                        <option value="warehouse_worker" <?php echo ($employee['role'] ?? '') === 'warehouse_worker' ? 'selected' : ''; ?>>Кладовщик</option>
                        <option value="quality_controller" <?php echo ($employee['role'] ?? '') === 'quality_controller' ? 'selected' : ''; ?>>Контролер ОТК</option>
                        <option value="technologist" <?php echo ($employee['role'] ?? '') === 'technologist' ? 'selected' : ''; ?>>Технолог</option>
                        <option value="manager" <?php echo ($employee['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Менеджер</option>
                        <option value="admin" <?php echo ($employee['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Администратор</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="department" class="form-label">Отдел</label>
                    <input type="text" class="form-control" id="department" name="department" 
                           value="<?php echo e($employee['department'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="position" class="form-label">Должность</label>
                    <input type="text" class="form-control" id="position" name="position" 
                           value="<?php echo e($employee['position'] ?? ''); ?>">
                </div>
            </div>

            <hr>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Сохранить
            </button>
            <a href="<?php echo BASE_URL; ?>/index.php?page=employees" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i>Отмена
            </a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
