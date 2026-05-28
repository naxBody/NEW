-- База данных для системы управления производством "Полесьеэлектромаш"
-- Версия: 1.0
-- Дата создания: 2024

CREATE DATABASE IF NOT EXISTS db_polesie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_polesie;

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

-- Пользователи (пароль по умолчанию: admin123 - хранится в открытом виде)
INSERT INTO users (username, password_hash, full_name, email, role, department, position) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 'admin', 'Administration', 'Системный администратор'),
('director', 'admin123', 'Иванов Иван Иванович', 'director@polesie.by', 'manager', 'Management', 'Генеральный директор'),
('tech_lead', 'admin123', 'Петров Петр Петрович', 'petrov@polesie.by', 'technologist', 'Technology', 'Ведущий технолог'),
('quality_head', 'admin123', 'Сидоров Сидор Сидорович', 'sidorov@polesie.by', 'quality_controller', 'Quality', 'Начальник ОТК'),
('warehouse_mgr', 'admin123', 'Козлов Козел Козлович', 'kozlov@polesie.by', 'warehouse_worker', 'Warehouse', 'Заведующий складом');

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

-- Технологические маршруты (должны быть созданы перед производственными заданиями)
INSERT INTO technology_routes (product_id, name, version) VALUES
(1, 'Маршрут производства АД-100', '1.0'),
(2, 'Маршрут производства АД-50', '1.0'),
(3, 'Маршрут производства Г-200', '1.0'),
(4, 'Маршрут производства Т-500', '1.0');

-- Операции в технологических маршрутах
INSERT INTO route_operations (route_id, operation_id, sequence_order, workstation, standard_time) VALUES
-- Маршрут 1: АД-100
(1, 1, 10, 'WS-001', 30),
(1, 2, 20, 'WS-001', 60),
(1, 5, 30, 'WS-004', 120),
(1, 6, 40, 'WS-006', 90),
(1, 7, 50, 'WS-007', 180),
(1, 8, 60, 'WS-008', 60),
(1, 9, 70, 'WS-009', 40),
(1, 10, 80, 'WS-010', 30),
(1, 11, 90, NULL, 45),
(1, 12, 100, NULL, 15),
-- Маршрут 2: Г-200
(2, 1, 10, 'WS-001', 45),
(2, 3, 20, 'WS-003', 90),
(2, 5, 30, 'WS-005', 180),
(2, 6, 40, 'WS-006', 120),
(2, 7, 50, 'WS-007', 240),
(2, 8, 60, 'WS-008', 90),
(2, 10, 70, 'WS-010', 45),
(2, 11, 80, NULL, 60),
(2, 12, 90, NULL, 20),
-- Маршрут 3: Т-500
(3, 1, 10, 'WS-002', 60),
(3, 3, 20, 'WS-003', 120),
(3, 4, 30, 'WS-003', 45),
(3, 8, 40, 'WS-008', 180),
(3, 10, 50, 'WS-010', 60),
(3, 11, 60, NULL, 90),
(3, 12, 70, NULL, 30);

-- Дефекты
INSERT INTO defects (defect_code, name, description, category, severity) VALUES
('DEF-001', 'Царапина корпуса', 'Механическое повреждение поверхности', 'Механические', 'minor'),
('DEF-002', 'Трещина обмотки', 'Нарушение целостности обмотки', 'Электрические', 'critical'),
('DEF-003', 'Превышение вибрации', 'Вибрация выше допустимых норм', 'Эксплуатационные', 'major'),
('DEF-004', 'Перегрев подшипников', 'Температура подшипников выше нормы', 'Тепловые', 'major'),
('DEF-005', 'Недостаточная изоляция', 'Сопротивление изоляции ниже нормы', 'Электрические', 'critical'),
('DEF-006', 'Дефект окраски', 'Неравномерное нанесение краски', 'Внешние', 'minor'),
('DEF-007', 'Люфт вала', 'Превышение допустимого люфта', 'Механические', 'major');

-- Заказы
INSERT INTO orders (order_number, customer_id, order_date, delivery_date, status, priority, total_amount, notes, manager_id) VALUES
('ORD-2024-001', 1, '2024-01-15', '2024-02-15', 'completed', 'normal', 75000.00, 'Поставка электродвигателей для насосной станции', 2),
('ORD-2024-002', 2, '2024-01-20', '2024-02-20', 'in_production', 'high', 45000.00, 'Срочный заказ на генераторы', 2),
('ORD-2024-003', 3, '2024-01-25', '2024-03-01', 'in_production', 'normal', 120000.00, 'Комплект трансформаторов для подстанции', 2),
('ORD-2024-004', 4, '2024-02-01', '2024-03-15', 'new', 'normal', 30000.00, 'Запасные части для ремонта', 2),
('ORD-2024-005', 1, '2024-02-05', '2024-03-20', 'confirmed', 'urgent', 95000.00, 'Дополнительная партия двигателей', 2);

-- Позиции заказов
INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, planned_start_date, planned_end_date, status) VALUES
(1, 1, 20, 2500.00, 50000.00, '2024-01-20', '2024-02-10', 'completed'),
(1, 2, 10, 1500.00, 15000.00, '2024-01-20', '2024-02-10', 'completed'),
(2, 3, 3, 15000.00, 45000.00, '2024-01-25', '2024-02-15', 'in_progress'),
(3, 4, 15, 8000.00, 120000.00, '2024-02-01', '2024-02-28', 'in_progress'),
(4, 1, 8, 2500.00, 20000.00, '2024-02-15', '2024-03-10', 'pending'),
(4, 2, 5, 1500.00, 7500.00, '2024-02-15', '2024-03-10', 'pending'),
(5, 1, 30, 2500.00, 75000.00, '2024-02-10', '2024-03-15', 'pending'),
(5, 3, 2, 15000.00, 30000.00, '2024-02-10', '2024-03-15', 'pending');

-- Производственные задания (используем route_id из созданных маршрутов)
INSERT INTO production_tasks (task_number, order_item_id, route_id, status, quantity_planned, quantity_completed, quantity_rejected, start_date, end_date, assigned_to, workstation, priority, notes) VALUES
('TASK-2024-001', 1, 1, 'completed', 20, 20, 0, '2024-01-20', '2024-02-10', 3, 'WS-008', 5, 'Выполнено в срок'),
('TASK-2024-002', 2, 1, 'completed', 10, 10, 0, '2024-01-20', '2024-02-10', 3, 'WS-008', 5, 'Выполнено в срок'),
('TASK-2024-003', 3, 2, 'in_progress', 3, 1, 0, '2024-01-25', '2024-02-15', 3, 'WS-004', 3, 'В производстве'),
('TASK-2024-004', 4, 3, 'in_progress', 15, 5, 0, '2024-02-01', '2024-02-28', 3, 'WS-001', 5, 'В производстве'),
('TASK-2024-005', 5, 1, 'released', 8, 0, 0, '2024-02-15', '2024-03-10', NULL, 'WS-002', 5, 'Готово к запуску');

-- Проверки качества
INSERT INTO quality_checks (check_number, task_id, task_operation_id, check_type, inspector_id, quantity_checked, quantity_passed, quantity_defective, result, defect_description, notes) VALUES
('QC-2024-001', 1, NULL, 'final', 4, 20, 20, 0, 'passed', NULL, 'Все параметры в норме'),
('QC-2024-002', 2, NULL, 'final', 4, 10, 10, 0, 'passed', NULL, 'Все параметры в норме'),
('QC-2024-003', 3, NULL, 'in_process', 4, 1, 1, 0, 'passed', NULL, 'Промежуточная проверка'),
('QC-2024-004', 4, NULL, 'in_process', 4, 5, 4, 1, 'conditional', 'Незначительные дефекты окраски', 'Допущено с замечаниями'),
('QC-2024-005', 1, NULL, 'outgoing', 4, 20, 19, 1, 'passed', 'Минимальные косметические дефекты', 'Отгружено покупателю');

-- =====================================================
-- ДОКУМЕНТЫ ПРОИЗВОДСТВА (полная реализация)
-- =====================================================

-- План производства (месячный/квартальный)
CREATE TABLE production_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_number VARCHAR(50) UNIQUE NOT NULL,
    plan_type ENUM('monthly', 'quarterly', 'annual') DEFAULT 'monthly',
    plan_year INT NOT NULL,
    plan_month INT, -- 1-12 для monthly, 1-4 для quarterly, NULL для annual
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'approved', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
    total_value_planned DECIMAL(15,2) DEFAULT 0,
    total_value_actual DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции плана производства
CREATE TABLE production_plan_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_planned INT NOT NULL,
    quantity_actual INT DEFAULT 0,
    unit_price DECIMAL(12,2),
    total_value DECIMAL(15,2),
    priority INT DEFAULT 5,
    notes TEXT,
    FOREIGN KEY (plan_id) REFERENCES production_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Маршрутные карты (технологические документы)
CREATE TABLE route_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_number VARCHAR(50) UNIQUE NOT NULL,
    product_id INT NOT NULL,
    route_id INT NOT NULL,
    version VARCHAR(20) DEFAULT '1.0',
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    material_consumption JSON COMMENT 'Расход материалов по операциям',
    tooling_required JSON COMMENT 'Необходимая оснастка',
    labor_norms JSON COMMENT 'Нормы труда по операциям',
    quality_requirements TEXT,
    safety_requirements TEXT,
    developed_by INT,
    checked_by INT,
    approved_by INT,
    valid_from DATE,
    valid_until DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES technology_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (developed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Операции в маршрутной карте с детальными параметрами
CREATE TABLE route_card_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    operation_sequence INT NOT NULL,
    operation_id INT NOT NULL,
    operation_name VARCHAR(200),
    workstation_code VARCHAR(50),
    equipment_required TEXT,
    tooling_required TEXT,
    standard_time INT,
    labor_grade INT COMMENT 'Разряд рабочего',
    material_codes JSON COMMENT 'Коды используемых материалов',
    quality_checkpoints JSON COMMENT 'Контрольные точки качества',
    safety_instructions TEXT,
    sketches JSON COMMENT 'Эскизы и схемы',
    notes TEXT,
    FOREIGN KEY (card_id) REFERENCES route_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Паспорта изделий (сертификаты качества на каждое изделие)
CREATE TABLE product_passports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    passport_number VARCHAR(50) UNIQUE NOT NULL,
    serial_number VARCHAR(100),
    product_id INT NOT NULL,
    production_task_id INT,
    manufacture_date DATE NOT NULL,
    warranty_period_months INT DEFAULT 24,
    warranty_start_date DATE,
    status ENUM('active', 'warranty_expired', 'recalled') DEFAULT 'active',
    test_results JSON COMMENT 'Результаты испытаний',
    quality_certificate_id INT,
    components_used JSON COMMENT 'Использованные комплектующие',
    materials_used JSON COMMENT 'Использованные материалы',
    workers_involved JSON COMMENT 'Участники производства',
    inspector_signature VARCHAR(100),
    technical_director_signature VARCHAR(100),
    qr_code_data TEXT,
    pdf_document_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (production_task_id) REFERENCES production_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Товарно-транспортные накладные (ТТН)
CREATE TABLE shipping_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_type ENUM('ttn', 'invoice', 'act', 'uppd') DEFAULT 'ttn',
    document_number VARCHAR(50) UNIQUE NOT NULL,
    document_date DATE NOT NULL,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    carrier_name VARCHAR(200),
    carrier_inn VARCHAR(20),
    vehicle_number VARCHAR(50),
    driver_name VARCHAR(100),
    driver_license VARCHAR(50),
    shipping_address TEXT,
    delivery_date DATE,
    status ENUM('draft', 'printed', 'shipped', 'delivered', 'signed', 'cancelled') DEFAULT 'draft',
    total_weight DECIMAL(10,2),
    total_volume DECIMAL(10,2),
    packages_count INT,
    freight_cost DECIMAL(12,2),
    insurance_cost DECIMAL(12,2),
    total_amount DECIMAL(15,2),
    payment_terms TEXT,
    notes TEXT,
    shipped_by INT,
    shipped_at TIMESTAMP NULL,
    received_by_customer VARCHAR(100),
    received_at TIMESTAMP NULL,
    signature_scan_path VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (shipped_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции в транспортных документах
CREATE TABLE shipping_document_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipping_document_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit VARCHAR(20),
    weight_per_unit DECIMAL(10,2),
    total_weight DECIMAL(10,2),
    volume_per_unit DECIMAL(10,2),
    total_volume DECIMAL(10,2),
    unit_price DECIMAL(12,2),
    total_price DECIMAL(15,2),
    package_numbers TEXT COMMENT 'Номера мест/упаковок',
    passport_numbers TEXT COMMENT 'Номера паспортов изделий',
    FOREIGN KEY (shipping_document_id) REFERENCES shipping_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Акты выполненных работ/оказанных услуг
CREATE TABLE acts_of_work (
    id INT AUTO_INCREMENT PRIMARY KEY,
    act_number VARCHAR(50) UNIQUE NOT NULL,
    act_date DATE NOT NULL,
    order_id INT,
    customer_id INT NOT NULL,
    contract_number VARCHAR(50),
    period_start DATE,
    period_end DATE,
    total_amount DECIMAL(15,2),
    vat_rate DECIMAL(5,2) DEFAULT 20,
    vat_amount DECIMAL(15,2),
    total_with_vat DECIMAL(15,2),
    status ENUM('draft', 'signed', 'sent', 'received', 'paid', 'cancelled') DEFAULT 'draft',
    work_description TEXT,
    customer_signature VARCHAR(100),
    customer_seal BOOLEAN DEFAULT FALSE,
    our_signature VARCHAR(100),
    pdf_document_path VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Счет-фактуры
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    order_id INT,
    customer_id INT NOT NULL,
    contract_number VARCHAR(50),
    shipment_date DATE,
    subtotal DECIMAL(15,2),
    discount_percent DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(15,2),
    taxable_amount DECIMAL(15,2),
    vat_rate DECIMAL(5,2) DEFAULT 20,
    vat_amount DECIMAL(15,2),
    total_amount DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'BYN',
    exchange_rate DECIMAL(10,4) DEFAULT 1,
    payment_due_date DATE,
    payment_status ENUM('unpaid', 'partial', 'paid', 'overdue', 'cancelled') DEFAULT 'unpaid',
    payment_date DATE,
    payment_document VARCHAR(100),
    notes TEXT,
    pdf_document_path VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции счетов-фактур
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    article VARCHAR(50),
    name VARCHAR(200),
    unit VARCHAR(20),
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    vat_rate DECIMAL(5,2) DEFAULT 20,
    vat_amount DECIMAL(15,2),
    country_of_origin VARCHAR(50) DEFAULT 'Беларусь',
    customs_declaration VARCHAR(100),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Спецификации к заказам
CREATE TABLE specifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spec_number VARCHAR(50) UNIQUE NOT NULL,
    spec_date DATE NOT NULL,
    order_id INT NOT NULL,
    contract_number VARCHAR(50),
    total_items INT,
    total_amount DECIMAL(15,2),
    delivery_terms TEXT,
    packaging_requirements TEXT,
    special_requirements TEXT,
    status ENUM('draft', 'approved', 'changed', 'cancelled') DEFAULT 'draft',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Журнал выдачи материалов в производство
CREATE TABLE material_issue_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_number VARCHAR(50) UNIQUE NOT NULL,
    issue_date DATE NOT NULL,
    production_task_id INT,
    warehouse_id INT NOT NULL,
    issued_to INT,
    purpose TEXT COMMENT 'Цель выдачи (номер задания, заказа)',
    status ENUM('requested', 'approved', 'issued', 'returned', 'cancelled') DEFAULT 'requested',
    total_value DECIMAL(15,2),
    notes TEXT,
    requested_by INT,
    approved_by INT,
    issued_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_task_id) REFERENCES production_tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    FOREIGN KEY (issued_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции выдачи материалов
CREATE TABLE material_issue_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_log_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity_requested DECIMAL(10,3) NOT NULL,
    quantity_approved DECIMAL(10,3),
    quantity_issued DECIMAL(10,3),
    quantity_returned DECIMAL(10,3) DEFAULT 0,
    unit VARCHAR(20),
    unit_price DECIMAL(12,2),
    total_value DECIMAL(15,2),
    storage_location VARCHAR(100),
    batch_number VARCHAR(50),
    notes TEXT,
    FOREIGN KEY (issue_log_id) REFERENCES material_issue_log(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Отчёты о браке
CREATE TABLE rejection_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_number VARCHAR(50) UNIQUE NOT NULL,
    report_date DATE NOT NULL,
    production_task_id INT,
    quality_check_id INT,
    product_id INT,
    quantity_rejected INT NOT NULL,
    rejection_stage ENUM('incoming', 'in_process', 'final', 'warranty') NOT NULL,
    defect_types JSON,
    root_cause TEXT,
    corrective_actions TEXT,
    preventive_actions TEXT,
    financial_loss DECIMAL(15,2),
    responsible_person INT,
    status ENUM('open', 'investigating', 'resolved', 'closed') DEFAULT 'open',
    investigated_by INT,
    investigated_at TIMESTAMP NULL,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_task_id) REFERENCES production_tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (quality_check_id) REFERENCES quality_checks(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (responsible_person) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (investigated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Контроль сроков и напоминания
CREATE TABLE deadlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deadline_type ENUM('order', 'task', 'payment', 'delivery', 'quality', 'maintenance') NOT NULL,
    reference_id INT NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    due_date DATE NOT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'completed', 'overdue', 'cancelled') DEFAULT 'pending',
    assigned_to INT,
    reminder_days_before INT DEFAULT 3,
    reminder_sent BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    completed_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Шаблоны документов
CREATE TABLE document_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    template_content TEXT NOT NULL COMMENT 'HTML/PDF шаблон',
    variables JSON COMMENT 'Переменные для подстановки',
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- История изменений статусов документов
CREATE TABLE document_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_type VARCHAR(50) NOT NULL,
    document_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comments TEXT,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Подписи и печати (цифровые)
CREATE TABLE digital_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    signature_type ENUM('simple', 'enhanced', 'qualified') DEFAULT 'simple',
    certificate_data TEXT,
    private_key_path VARCHAR(255),
    public_key_path VARCHAR(255),
    valid_from DATE,
    valid_until DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ДОПОЛНИТЕЛЬНЫЕ СПРАВОЧНИКИ
-- ============================================

-- Единицы измерения
CREATE TABLE units_of_measure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(10),
    type ENUM('length', 'weight', 'volume', 'area', 'quantity', 'time', 'other') DEFAULT 'quantity',
    conversion_factor DECIMAL(10,6) DEFAULT 1,
    base_unit_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (base_unit_id) REFERENCES units_of_measure(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Контракты/Договоры
CREATE TABLE contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(50) UNIQUE NOT NULL,
    contract_date DATE NOT NULL,
    customer_id INT NOT NULL,
    contract_type ENUM('sale', 'purchase', 'service', 'partnership') DEFAULT 'sale',
    subject TEXT,
    total_amount DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'BYN',
    valid_from DATE,
    valid_until DATE,
    auto_renewal BOOLEAN DEFAULT FALSE,
    payment_terms TEXT,
    delivery_terms TEXT,
    penalty_terms TEXT,
    status ENUM('draft', 'active', 'expired', 'terminated', 'completed') DEFAULT 'draft',
    signed_by_customer VARCHAR(100),
    signed_by_us VARCHAR(100),
    scan_path VARCHAR(255),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заявки на закупку материалов
CREATE TABLE purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    request_date DATE NOT NULL,
    requested_by INT NOT NULL,
    department VARCHAR(100),
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    required_date DATE,
    status ENUM('draft', 'submitted', 'approved', 'ordered', 'received', 'cancelled') DEFAULT 'draft',
    total_value DECIMAL(15,2),
    supplier_id INT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции заявок на закупку
CREATE TABLE purchase_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    material_id INT,
    description TEXT,
    quantity DECIMAL(10,3) NOT NULL,
    unit VARCHAR(20),
    estimated_price DECIMAL(12,2),
    total_value DECIMAL(15,2),
    supplier_article VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индекс для ускорения поиска
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_delivery ON orders(delivery_date);
CREATE INDEX idx_production_tasks_status ON production_tasks(status);
CREATE INDEX idx_quality_checks_date ON quality_checks(check_date);
CREATE INDEX idx_inventory_material ON inventory(material_id);
CREATE INDEX idx_shipping_order ON shipping_documents(order_id);
CREATE INDEX idx_invoices_customer ON invoices(customer_id);
CREATE INDEX idx_contracts_customer ON contracts(customer_id);
CREATE INDEX idx_deadlines_due ON deadlines(due_date);
