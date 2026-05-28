-- База данных для системы управления производством "Полесьеэлектромаш"
-- Версия: 1.0
-- Дата создания: 2024

CREATE DATABASE IF NOT EXISTS polesie_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE polesie_production;

-- Таблица пользователей (сотрудники)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'manager', 'technologist', 'quality_controller', 'warehouse_worker', 'operator') NOT NULL,
    department VARCHAR(100),
    position VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица клиентов
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    inn VARCHAR(20) UNIQUE,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    contact_person VARCHAR(100),
    country VARCHAR(50) DEFAULT 'Беларусь',
    city VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Справочник категорий продукции
CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_id INT NULL,
    description TEXT,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Справочник продукции
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    description TEXT,
    unit VARCHAR(20) DEFAULT 'шт',
    base_price DECIMAL(12,2),
    currency VARCHAR(3) DEFAULT 'BYN',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Технологические операции
CREATE TABLE operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    standard_time INT COMMENT 'Нормативное время в минутах',
    required_skill_level INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Технологические маршруты
CREATE TABLE technology_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    version VARCHAR(20) DEFAULT '1.0',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Операции в технологическом маршруте
CREATE TABLE route_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    operation_id INT NOT NULL,
    sequence_order INT NOT NULL,
    workstation VARCHAR(100),
    standard_time INT,
    FOREIGN KEY (route_id) REFERENCES technology_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заказы клиентов
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE NOT NULL,
    status ENUM('new', 'confirmed', 'in_production', 'quality_check', 'ready', 'shipped', 'completed', 'cancelled') DEFAULT 'new',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    total_amount DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'BYN',
    notes TEXT,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции заказа
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    planned_start_date DATE,
    planned_end_date DATE,
    actual_start_date DATE,
    actual_end_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Производственные задания
CREATE TABLE production_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_number VARCHAR(50) UNIQUE NOT NULL,
    order_item_id INT NOT NULL,
    route_id INT,
    status ENUM('planned', 'released', 'in_progress', 'paused', 'completed', 'rejected') DEFAULT 'planned',
    quantity_planned INT NOT NULL,
    quantity_completed INT DEFAULT 0,
    quantity_rejected INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    actual_start_datetime TIMESTAMP NULL,
    actual_end_datetime TIMESTAMP NULL,
    assigned_to INT,
    workstation VARCHAR(100),
    priority INT DEFAULT 5,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES technology_routes(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Выполнение операций производственного задания
CREATE TABLE task_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    route_operation_id INT,
    operation_id INT NOT NULL,
    sequence_order INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    worker_id INT,
    actual_time INT COMMENT 'Фактическое время в минутах',
    notes TEXT,
    FOREIGN KEY (task_id) REFERENCES production_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (route_operation_id) REFERENCES route_operations(id) ON DELETE SET NULL,
    FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE RESTRICT,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Контроль качества
CREATE TABLE quality_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    check_number VARCHAR(50) UNIQUE NOT NULL,
    task_id INT NOT NULL,
    task_operation_id INT,
    check_type ENUM('incoming', 'in_process', 'final', 'outgoing') NOT NULL,
    check_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    inspector_id INT NOT NULL,
    quantity_checked INT NOT NULL,
    quantity_passed INT NOT NULL,
    quantity_defective INT DEFAULT 0,
    result ENUM('passed', 'failed', 'conditional') NOT NULL,
    defect_description TEXT,
    photos JSON,
    certificate_number VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (task_id) REFERENCES production_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (task_operation_id) REFERENCES task_operations(id) ON DELETE SET NULL,
    FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Дефекты и несоответствия
CREATE TABLE defects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    defect_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    severity ENUM('critical', 'major', 'minor') DEFAULT 'minor',
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Связь дефектов с проверками качества
CREATE TABLE quality_check_defects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quality_check_id INT NOT NULL,
    defect_id INT NOT NULL,
    quantity INT DEFAULT 1,
    comments TEXT,
    FOREIGN KEY (quality_check_id) REFERENCES quality_checks(id) ON DELETE CASCADE,
    FOREIGN KEY (defect_id) REFERENCES defects(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Складские помещения
CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('raw_materials', 'components', 'finished_goods', 'wip', 'returns') DEFAULT 'raw_materials',
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Номенклатура материалов и комплектующих
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    unit VARCHAR(20) NOT NULL,
    min_stock DECIMAL(10,3) DEFAULT 0,
    max_stock DECIMAL(10,3),
    current_price DECIMAL(12,2),
    currency VARCHAR(3) DEFAULT 'BYN',
    supplier_default INT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (supplier_default) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Остатки на складе
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(10,3) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_warehouse_material (warehouse_id, material_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Движение товаров (приход/расход)
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    transaction_type ENUM('receipt', 'issue', 'transfer', 'adjustment', 'return') NOT NULL,
    warehouse_from_id INT,
    warehouse_to_id INT,
    material_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(12,2),
    total_value DECIMAL(15,2),
    reference_type VARCHAR(50) COMMENT 'Тип связанного документа (order, task, etc)',
    reference_id INT COMMENT 'ID связанного документа',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    performed_by INT NOT NULL,
    notes TEXT,
    FOREIGN KEY (warehouse_from_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_to_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Рабочие места/оборудование
CREATE TABLE workstations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50),
    location VARCHAR(100),
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    capabilities JSON,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Журнал событий системы
CREATE TABLE system_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    record_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Настройки системы
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка начальных данных

-- Пользователи (пароль по умолчанию: admin123)
INSERT INTO users (username, password_hash, full_name, email, role, department, position) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор Системы', 'admin@polesie.by', 'admin', 'Administration', 'Системный администратор'),
('director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Иванов Иван Иванович', 'director@polesie.by', 'manager', 'Management', 'Генеральный директор'),
('tech_lead', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Петров Петр Петрович', 'petrov@polesie.by', 'technologist', 'Technology', 'Ведущий технолог'),
('quality_head', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Сидоров Сидор Сидорович', 'sidorov@polesie.by', 'quality_controller', 'Quality', 'Начальник ОТК'),
('warehouse_mgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Козлов Козел Козлович', 'kozlov@polesie.by', 'warehouse_worker', 'Warehouse', 'Заведующий складом');

-- Категории продукции
INSERT INTO product_categories (name, description) VALUES
('Электродвигатели', 'Все типы электродвигателей'),
('Генераторы', 'Электрогенераторы различной мощности'),
('Трансформаторы', 'Силовые и специальные трансформаторы'),
('Комплектующие', 'Запчасти и комплектующие изделия');

-- Продукция
INSERT INTO products (article, name, category_id, description, unit, base_price) VALUES
('AD-100', 'Асинхронный двигатель АД-100', 1, 'Трехфазный асинхронный двигатель мощностью 100 кВт', 'шт', 2500.00),
('AD-50', 'Асинхронный двигатель АД-50', 1, 'Трехфазный асинхронный двигатель мощностью 50 кВт', 'шт', 1500.00),
('GEN-200', 'Генератор Г-200', 2, 'Дизель-генератор мощностью 200 кВт', 'шт', 15000.00),
('TR-500', 'Трансформатор Т-500', 3, 'Силовой трансформатор 500 кВА', 'шт', 8000.00);

-- Операции
INSERT INTO operations (code, name, description, standard_time) VALUES
('OP001', 'Заготовка', 'Подготовка заготовок', 30),
('OP002', 'Токарная обработка', 'Обработка на токарном станке', 60),
('OP003', 'Фрезерная обработка', 'Обработка на фрезерном станке', 45),
('OP004', 'Сверление', 'Сверлильные работы', 20),
('OP005', 'Намотка обмотки', 'Намотка статорной обмотки', 120),
('OP006', 'Пропитка', 'Пропитка обмотки лаком', 90),
('OP007', 'Сушка', 'Сушка после пропитки', 180),
('OP008', 'Сборка', 'Сборка узла', 60),
('OP009', 'Балансировка', 'Балансировка ротора', 40),
('OP010', 'Испытания', 'Приемо-сдаточные испытания', 30),
('OP011', 'Окраска', 'Нанесение лакокрасочного покрытия', 45),
('OP012', 'Упаковка', 'Упаковка готовой продукции', 15);

-- Клиенты
INSERT INTO customers (name, inn, address, phone, email, contact_person, city) VALUES
('ОАО "Белэнерго"', '100123456', 'г. Минск, ул. Энергетиков, 1', '+375 17 123-45-67', 'info@belenergo.by', 'Кузнецов А.В.', 'Минск'),
('ООО "Промстрой"', '200234567', 'г. Гомель, пр. Строителей, 10', '+375 232 23-45-67', 'zakaz@promstroy.by', 'Морозов Б.Г.', 'Гомель'),
('РУП "Минский тракторный завод"', '300345678', 'г. Минск, Долгиновский тракт, 100', '+375 17 234-56-78', 'mtz@mtz.by', 'Орлов В.Д.', 'Минск'),
('ЧТУП "ЭлектроСервис"', '400456789', 'г. Брест, ул. Промышленная, 5', '+375 162 34-56-78', 'service@electro.by', 'Волков Г.Е.', 'Брест');

-- Склады
INSERT INTO warehouses (code, name, type) VALUES
('WH-RM', 'Склад сырья', 'raw_materials'),
('WH-COMP', 'Склад комплектующих', 'components'),
('WH-FG', 'Склад готовой продукции', 'finished_goods'),
('WH-WIP', 'Склад незавершенного производства', 'wip');

-- Материалы
INSERT INTO materials (article, name, category, unit, min_stock, current_price) VALUES
('MAT-001', 'Медный провод ПЭТВ-2 1.5мм', 'Проводниковые материалы', 'кг', 500, 45.00),
('MAT-002', 'Медный провод ПЭТВ-2 2.5мм', 'Проводниковые материалы', 'кг', 300, 42.00),
('MAT-003', 'Сталь электротехническая 0.35мм', 'Магнитные материалы', 'кг', 1000, 12.00),
('MAT-004', 'Лак электроизоляционный', 'Изоляционные материалы', 'л', 200, 35.00),
('MAT-005', 'Подшипник 6309-2RS', 'Комплектующие', 'шт', 100, 25.00),
('MAT-006', 'Подшипник 6312-2RS', 'Комплектующие', 'шт', 50, 35.00),
('MAT-007', 'Корпус двигателя АД-100', 'Корпуса', 'шт', 20, 450.00),
('MAT-008', 'Корпус двигателя АД-50', 'Корпуса', 'шт', 30, 320.00);

-- Рабочие места
INSERT INTO workstations (code, name, type, location, status) VALUES
('WS-001', 'Токарный станок ЧПУ-1', 'CNC Lathe', 'Цех №1, линия 1', 'active'),
('WS-002', 'Токарный станок ЧПУ-2', 'CNC Lathe', 'Цех №1, линия 2', 'active'),
('WS-003', 'Фрезерный станок ЧПУ-1', 'CNC Mill', 'Цех №1, линия 1', 'active'),
('WS-004', 'Намоточный станок НС-100', 'Winding Machine', 'Цех №2, линия 1', 'active'),
('WS-005', 'Намоточный станок НС-200', 'Winding Machine', 'Цех №2, линия 2', 'active'),
('WS-006', 'Пропиточная ванна ПВ-1', 'Impregnation', 'Цех №2, участок пропитки', 'active'),
('WS-007', 'Сушильная камера СК-1', 'Drying Oven', 'Цех №2, участок сушки', 'active'),
('WS-008', 'Сборочный стенд СБ-1', 'Assembly Station', 'Цех №3, линия 1', 'active'),
('WS-009', 'Балансировочный станок БС-1', 'Balancing Machine', 'Цех №3, участок балансировки', 'active'),
('WS-010', 'Испытательный стенд ИС-1', 'Test Station', 'Цех №3, ОТК', 'active');

-- Настройки
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'ОАО "Полесьеэлектромаш"', 'string', 'Полное наименование предприятия'),
('company_inn', '500123456', 'string', 'УНП предприятия'),
('company_address', 'Республика Беларусь, Гомельская обл., г. Мозырь, ул. Промышленная, 1', 'string', 'Юридический адрес'),
('company_phone', '+375 2351 12-34-56', 'string', 'Контактный телефон'),
('company_email', 'info@polesie.by', 'string', 'Email для связи'),
('currency_default', 'BYN', 'string', 'Валюта по умолчанию'),
('production_working_hours', '8', 'integer', 'Продолжительность рабочей смены в часах'),
('quality_auto_check_required', 'true', 'boolean', 'Требуется ли автоматическая проверка качества');

-- Дефекты
INSERT INTO defects (defect_code, name, description, category, severity) VALUES
('DEF-001', 'Царапина корпуса', 'Механическое повреждение поверхности', 'Механические', 'minor'),
('DEF-002', 'Трещина обмотки', 'Нарушение целостности обмотки', 'Электрические', 'critical'),
('DEF-003', 'Превышение вибрации', 'Вибрация выше допустимых норм', 'Эксплуатационные', 'major'),
('DEF-004', 'Перегрев подшипников', 'Температура подшипников выше нормы', 'Тепловые', 'major'),
('DEF-005', 'Недостаточная изоляция', 'Сопротивление изоляции ниже нормы', 'Электрические', 'critical'),
('DEF-006', 'Дефект окраски', 'Неравномерное нанесение краски', 'Внешние', 'minor'),
('DEF-007', 'Люфт вала', 'Превышение допустимого люфта', 'Механические', 'major');
