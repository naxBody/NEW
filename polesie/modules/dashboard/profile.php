<?php
/**
 * Профиль пользователя
 */
require_once BASE_PATH . '/includes/header.php';

$successMessage = '';
$errorMessage = '';

// Обработка формы обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'full_name' => $_POST['full_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
        ];

        $result = $auth->updateProfile($user['id'], $data);
        
        if ($result['success']) {
            $successMessage = $result['message'];
            // Обновляем данные сессии
            $_SESSION['full_name'] = $data['full_name'];
        } else {
            $errorMessage = $result['message'];
        }
    } catch (Exception $e) {
        $errorMessage = 'Ошибка при обновлении профиля: ' . $e->getMessage();
    }
}
?>

<div class="page-title">
    <h3><i class="fas fa-user me-2"></i>Профиль пользователя</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/polesie/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item active">Профиль</li>
        </ol>
    </nav>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo e($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo e($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-id-card me-2"></i>Личная информация
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Имя пользователя</label>
                        <input type="text" class="form-control" id="username" value="<?php echo e($user['username']); ?>" disabled>
                        <div class="form-text">Имя пользователя нельзя изменить</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">ФИО</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?php echo e($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo e($user['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?php echo e($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Роль</label>
                            <input type="text" class="form-control" id="role" value="<?php echo e($user['role']); ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Отдел</label>
                            <input type="text" class="form-control" id="department" value="<?php echo e($user['department'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Сохранить изменения
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Информация об учетной записи
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Статус</th>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Активен</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Заблокирован</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Последний вход</th>
                        <td><?php echo $user['last_login'] ? formatDate($user['last_login'], 'd.m.Y H:i') : 'Никогда'; ?></td>
                    </tr>
                    <tr>
                        <th>Дата создания</th>
                        <td><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></td>
                    </tr>
                </table>
                
                <hr>
                
                <a href="/polesie/index.php?page=change_password" class="btn btn-warning w-100 mb-2">
                    <i class="fas fa-key me-2"></i>Сменить пароль
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
