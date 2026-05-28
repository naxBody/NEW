<?php
/**
 * Настройки системы
 */
require_once BASE_PATH . '/includes/header.php';

// Получаем подключение к базе данных
$db = Database::getInstance()->getConnection();

// Обработка формы сохранения настроек
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->hasRole('admin')) {
    try {
        $settingsToUpdate = [
            'company_name' => $_POST['company_name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'tax_id' => $_POST['tax_id'] ?? '',
        ];

        foreach ($settingsToUpdate as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                  ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        $successMessage = 'Настройки успешно сохранены';
    } catch (PDOException $e) {
        $errorMessage = 'Ошибка при сохранении настроек: ' . $e->getMessage();
    }
}

// Получаем текущие настройки
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="page-title">
    <h3><i class="fas fa-cog me-2"></i>Настройки системы</h3>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php?page=dashboard">Главная</a></li>
            <li class="breadcrumb-item active">Настройки</li>
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
                <i class="fas fa-building me-2"></i>Основная информация
            </div>
            <div class="card-body">
                <?php if (!$auth->hasRole('admin')): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Только администраторы могут изменять настройки системы.
                    </div>
                <?php endif; ?>
                
                <form method="POST" <?php echo $auth->hasRole('admin') ? '' : 'onsubmit="return false;"'; ?>>
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Название компании</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" 
                               value="<?php echo e($settings['company_name'] ?? 'Полесьеэлектромаш'); ?>" 
                               <?php echo $auth->hasRole('admin') ? '' : 'disabled'; ?>>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Адрес</label>
                        <textarea class="form-control" id="address" name="address" rows="3" 
                                  <?php echo $auth->hasRole('admin') ? '' : 'disabled'; ?>><?php echo e($settings['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo e($settings['phone'] ?? ''); ?>" 
                                   <?php echo $auth->hasRole('admin') ? '' : 'disabled'; ?>>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo e($settings['email'] ?? ''); ?>" 
                                   <?php echo $auth->hasRole('admin') ? '' : 'disabled'; ?>>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tax_id" class="form-label">УНН / ИНН</label>
                        <input type="text" class="form-control" id="tax_id" name="tax_id" 
                               value="<?php echo e($settings['tax_id'] ?? ''); ?>" 
                               <?php echo $auth->hasRole('admin') ? '' : 'disabled'; ?>>
                    </div>
                    
                    <?php if ($auth->hasRole('admin')): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Сохранить настройки
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Информация о системе
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Версия</th>
                        <td>1.0.0</td>
                    </tr>
                    <tr>
                        <th>PHP версия</th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th>База данных</th>
                        <td>PostgreSQL</td>
                    </tr>
                    <tr>
                        <th>Последнее обновление</th>
                        <td><?php echo date('d.m.Y H:i'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
