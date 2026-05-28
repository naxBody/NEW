/**
 * Основной JavaScript файл системы управления производством "Полесьеэлектромаш"
 */

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Инициализация popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Подтверждение удаления
    const confirmDeleteButtons = document.querySelectorAll('.confirm-delete');
    confirmDeleteButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Вы уверены, что хотите удалить этот элемент?')) {
                e.preventDefault();
            }
        });
    });
    
    // Автозакрытие уведомлений
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Форматирование чисел в полях ввода
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.value = this.min || 0;
            }
        });
    });
    
    // Подсветка текущей строки меню
    const currentPath = window.location.href;
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(function(link) {
        if (link.href === currentPath) {
            link.classList.add('active');
        }
    });
    
    // Поиск по таблицам
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(function(input) {
        input.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('table') || document.querySelector(this.getAttribute('data-table'));
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });
    
    // Экспорт таблицы в CSV
    const exportButtons = document.querySelectorAll('.export-csv');
    exportButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tableId = this.getAttribute('data-table');
            const table = document.getElementById(tableId);
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(function(row) {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach(function(col) {
                    rowData.push('"' + col.textContent.trim() + '"');
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'export_' + new Date().toISOString().slice(0,10) + '.csv';
            link.click();
        });
    });
    
    // Печать страницы
    const printButtons = document.querySelectorAll('.print-page');
    printButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Обновление прогресс-баров в реальном времени
    const progressBars = document.querySelectorAll('.progress-bar[data-target]');
    progressBars.forEach(function(bar) {
        const target = parseInt(bar.getAttribute('data-target'));
        const current = parseInt(bar.getAttribute('data-current'));
        if (target > 0) {
            const percent = Math.round((current / target) * 100);
            bar.style.width = percent + '%';
            bar.textContent = current + '/' + target;
        }
    });
    
    // Динамическое обновление времени
    const timeElements = document.querySelectorAll('.live-time');
    if (timeElements.length > 0) {
        setInterval(function() {
            const now = new Date();
            timeElements.forEach(function(el) {
                el.textContent = now.toLocaleTimeString('ru-RU');
            });
        }, 1000);
    }
    
    // Подтверждение важных действий
    const confirmActions = document.querySelectorAll('.confirm-action');
    confirmActions.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Вы уверены?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Автоматическое сохранение форм
    const autoSaveForms = document.querySelectorAll('.auto-save');
    autoSaveForms.forEach(function(form) {
        let saveTimeout;
        form.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(function() {
                console.log('Auto-saving form...');
                // Здесь можно добавить AJAX сохранение
            }, 2000);
        });
    });
    
    // Переключение темы (если нужно)
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
        });
        
        // Восстановление темы
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
        }
    }
    
    // Уведомления через Toast
    window.showToast = function(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer') || createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    };
    
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }
    
    // Обработка ошибок AJAX
    window.handleAjaxError = function(xhr) {
        const message = xhr.responseJSON?.message || 'Произошла ошибка при выполнении запроса';
        showToast(message, 'danger');
    };
    
    console.log('Polesie ERP System initialized');
});

// Глобальные функции
window.formatDate = function(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

window.formatCurrency = function(amount, currency = 'BYN') {
    return new Intl.NumberFormat('ru-BY', {
        style: 'currency',
        currency: currency
    }).format(amount);
};

window.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};
