<?php
/**
 * Смена пароля
 */
global $db;
require_once BASE_PATH . '/includes/header.php';

$successMessage = '';
$errorMessage = '';

// Обработка формы смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Проверка совпадения паролей
        if ($newPassword !== $confirmPassword) {
            throw new Exception('Новый пароль и подтверждение не совпадают');
        }

        // Проверка длины пароля
        if (strlen($newPassword) < 6) {
            throw new Exception('Пароль должен быть не менее 6 символов');
        }

        $result = $auth->changePassword($user['id'], $oldPassword, $newPassword);
        
        if ($result['success']) {
            $successMessage = $result['message'];
        } else {
            $errorMessage = $result['message'];
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="page-title">
    <h3><i class="fas fa-key me-2"></i>Смена пароля</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=profile">Профиль</a></li>
            <li class="breadcrumb-item active">Смена пароля</li>
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
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-lock me-2"></i>Изменение пароля
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="old_password" class="form-label">Текущий пароль</label>
                        <input type="password" class="form-control" id="old_password" name="old_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Новый пароль</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <div class="form-text">Минимальная длина пароля - 6 символов</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Подтверждение нового пароля</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <hr>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Сменить пароль
                    </button>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=profile" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Назад к профилю
                    </a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-shield-alt me-2"></i>Требования к паролю
            </div>
            <div class="card-body">
                <h5>Рекомендации по безопасности:</h5>
                <ul>
                    <li>Используйте минимум 6 символов</li>
                    <li>Используйте комбинацию букв и цифр</li>
                    <li>Не используйте простые последовательности (123456, qwerty)</li>
                    <li>Не используйте личную информацию (даты рождения, имена)</li>
                    <li>Регулярно меняйте пароль</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Важно!</strong> После смены пароля вам не нужно заново входить в систему. 
                    Изменения применяются немедленно.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
