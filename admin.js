// Admin Dashboard JavaScript

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all admin modules
    initializeSidebar();
    initializeDashboard();
    initializeDataTables();
    initializeCharts();
    initializeNotifications();
    initializeUserManagement();
    initializeSettings();
    initializeSearch();
    initializeTheme();
});

// Sidebar Navigation
function initializeSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const menuItems = document.querySelectorAll('.sidebar-menu-item');
    const submenuItems = document.querySelectorAll('.has-submenu');

    // Toggle sidebar
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar?.classList.add('collapsed');
    }

    // Menu item clicks
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (!this.classList.contains('has-submenu')) {
                menuItems.forEach(mi => mi.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });

    // Submenu toggle
    submenuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('open');
            const submenu = this.nextElementSibling;
            if (submenu) {
                submenu.style.maxHeight = this.classList.contains('open') 
                    ? submenu.scrollHeight + 'px' 
                    : '0';
            }
        });
    });
}

// Dashboard Initialization
function initializeDashboard() {
    // Load dashboard statistics
    loadDashboardStats();
    
    // Initialize real-time updates
    if (window.WebSocket) {
        initializeWebSocket();
    }

    // Refresh data periodically
    setInterval(loadDashboardStats, 30000); // Every 30 seconds
}

// Load Dashboard Statistics
async function loadDashboardStats() {
    try {
        const stats = await fetchData('/api/admin/stats');
        updateStatCards(stats);
    } catch (error) {
        console.error('Failed to load dashboard stats:', error);
        showNotification('Failed to load statistics', 'error');
    }
}

// Update Statistics Cards
function updateStatCards(stats) {
    Object.keys(stats).forEach(key => {
        const card = document.querySelector(`[data-stat="${key}"]`);
        if (card) {
            const valueEl = card.querySelector('.stat-value');
            const changeEl = card.querySelector('.stat-change');
            
            if (valueEl) {
                animateValue(valueEl, parseInt(valueEl.textContent) || 0, stats[key].value);
            }
            
            if (changeEl && stats[key].change) {
                changeEl.textContent = `${stats[key].change > 0 ? '+' : ''}${stats[key].change}%`;
                changeEl.className = `stat-change ${stats[key].change > 0 ? 'positive' : 'negative'}`;
            }
        }
    });
}

// Animate number changes
function animateValue(el, start, end, duration = 1000) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            el.textContent = formatNumber(end);
            clearInterval(timer);
        } else {
            el.textContent = formatNumber(Math.floor(current));
        }
    }, 16);
}

// Format numbers with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Initialize Data Tables
function initializeDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const dataTable = new DataTableManager(table);
        dataTable.init();
    });
}

// Data Table Manager Class
class DataTableManager {
    constructor(table) {
        this.table = table;
        this.tbody = table.querySelector('tbody');
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        this.filters = {};
    }

    init() {
        this.setupControls();
        this.loadData();
        this.setupEventListeners();
    }

    setupControls() {
        // Add search input
        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'table-search';
        searchWrapper.innerHTML = `
            <input type="text" placeholder="Search..." class="table-search-input">
        `;
        this.table.parentNode.insertBefore(searchWrapper, this.table);

        // Add pagination
        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'table-pagination';
        this.table.parentNode.appendChild(paginationWrapper);
    }

    setupEventListeners() {
        // Search
        const searchInput = this.table.parentNode.querySelector('.table-search-input');
        searchInput?.addEventListener('input', debounce((e) => {
            this.filters.search = e.target.value;
            this.currentPage = 1;
            this.loadData();
        }, 300));

        // Sort
        const headers = this.table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.column;
                if (this.sortColumn === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
                this.loadData();
            });
        });

        // Row actions
        this.table.addEventListener('click', (e) => {
            if (e.target.matches('.btn-edit')) {
                this.handleEdit(e.target.closest('tr').dataset.id);
            } else if (e.target.matches('.btn-delete')) {
                this.handleDelete(e.target.closest('tr').dataset.id);
            }
        });
    }

    async loadData() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                limit: this.itemsPerPage,
                sort: this.sortColumn,
                direction: this.sortDirection,
                ...this.filters
            });

            const response = await fetch(`${this.table.dataset.source}?${params}`);
            const data = await response.json();

            this.renderTable(data.items);
            this.renderPagination(data.total);
        } catch (error) {
            console.error('Failed to load table data:', error);
        }
    }

    renderTable(items) {
        this.tbody.innerHTML = items.map(item => this.renderRow(item)).join('');
    }

    renderRow(item) {
        // Override in specific implementations
        return `<tr data-id="${item.id}">
            <td>${item.id}</td>
            <td>${item.name || 'N/A'}</td>
            <td>${new Date(item.created).toLocaleDateString()}</td>
            <td>
                <button class="btn btn-sm btn-edit">Edit</button>
                <button class="btn btn-sm btn-delete">Delete</button>
            </td>
        </tr>`;
    }

    renderPagination(total) {
        const totalPages = Math.ceil(total / this.itemsPerPage);
        const pagination = this.table.parentNode.querySelector('.table-pagination');
        
        let html = '<div class="pagination">';
        
        // Previous button
        html += `<button class="pagination-btn" ${this.currentPage === 1 ? 'disabled' : ''} 
                 onclick="this.closest('.data-table').dataTable.goToPage(${this.currentPage - 1})">
                 Previous</button>`;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                html += `<button class="pagination-btn ${i === this.currentPage ? 'active' : ''}"
                         onclick="this.closest('.data-table').dataTable.goToPage(${i})">${i}</button>`;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        // Next button
        html += `<button class="pagination-btn" ${this.currentPage === totalPages ? 'disabled' : ''} 
                 onclick="this.closest('.data-table').dataTable.goToPage(${this.currentPage + 1})">
                 Next</button>`;
        
        html += '</div>';
        pagination.innerHTML = html;
        
        // Store reference for pagination clicks
        this.table.dataTable = this;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadData();
    }

    async handleEdit(id) {
        const modal = new Modal({
            title: 'Edit Item',
            size: 'medium',
            content: await this.getEditForm(id),
            onSave: async (formData) => {
                await this.saveItem(id, formData);
                this.loadData();
            }
        });
        modal.show();
    }

    async handleDelete(id) {
        if (confirm('Are you sure you want to delete this item?')) {
            try {
                await fetch(`${this.table.dataset.source}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });
                showNotification('Item deleted successfully', 'success');
                this.loadData();
            } catch (error) {
                showNotification('Failed to delete item', 'error');
            }
        }
    }

    async getEditForm(id) {
        // Override in specific implementations
        return '<p>Edit form not implemented</p>';
    }

    async saveItem(id, data) {
        // Override in specific implementations
    }
}

// Initialize Charts
function initializeCharts() {
    // Revenue Chart
    const revenueChart = document.getElementById('revenueChart');
    if (revenueChart) {
        createRevenueChart(revenueChart);
    }

    // User Activity Chart
    const activityChart = document.getElementById('activityChart');
    if (activityChart) {
        createActivityChart(activityChart);
    }

    // Sales Distribution Chart
    const salesChart = document.getElementById('salesChart');
    if (salesChart) {
        createSalesChart(salesChart);
    }
}

// Create Revenue Chart
async function createRevenueChart(canvas) {
    const ctx = canvas.getContext('2d');
    const data = await fetchData('/api/admin/charts/revenue');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Revenue',
                data: data.values,
                borderColor: '#4285f4',
                backgroundColor: 'rgba(66, 133, 244, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

// Create Activity Chart
async function createActivityChart(canvas) {
    const ctx = canvas.getContext('2d');
    const data = await fetchData('/api/admin/charts/activity');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Active Users',
                data: data.values,
                backgroundColor: '#34a853'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Create Sales Distribution Chart
async function createSalesChart(canvas) {
    const ctx = canvas.getContext('2d');
    const data = await fetchData('/api/admin/charts/sales');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [
                    '#4285f4',
                    '#34a853',
                    '#fbbc04',
                    '#ea4335',
                    '#673ab7'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Notifications System
let notificationQueue = [];

function initializeNotifications() {
    // Create notification container
    if (!document.querySelector('.notification-container')) {
        const container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }

    // Check for new notifications periodically
    setInterval(checkNewNotifications, 60000); // Every minute
}

async function checkNewNotifications() {
    try {
        const notifications = await fetchData('/api/admin/notifications/new');
        notifications.forEach(notification => {
            showNotification(notification.message, notification.type);
        });
    } catch (error) {
        console.error('Failed to check notifications:', error);
    }
}

function showNotification(message, type = 'info', duration = 5000) {
    const container = document.querySelector('.notification-container');
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} fade-in`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${getNotificationIcon(type)}</span>
            <span class="notification-message">${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;

    container.appendChild(notification);

    // Remove notification
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => removeNotification(notification));

    // Auto remove
    setTimeout(() => removeNotification(notification), duration);
}

function removeNotification(notification) {
    notification.classList.add('fade-out');
    setTimeout(() => notification.remove(), 300);
}

function getNotificationIcon(type) {
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    return icons[type] || icons.info;
}

// User Management
function initializeUserManagement() {
    const userForm = document.getElementById('userForm');
    const roleSelect = document.getElementById('userRole');
    
    if (userForm) {
        userForm.addEventListener('submit', handleUserFormSubmit);
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', updatePermissions);
    }

    // Bulk actions
    const bulkActionBtn = document.querySelector('.bulk-action-btn');
    if (bulkActionBtn) {
        bulkActionBtn.addEventListener('click', handleBulkAction);
    }
}

async function handleUserFormSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('/api/admin/users', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(formData)),
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            }
        });

        if (response.ok) {
            showNotification('User saved successfully', 'success');
            e.target.reset();
            // Refresh user table if exists
            const userTable = document.querySelector('#usersTable');
            if (userTable?.dataTable) {
                userTable.dataTable.loadData();
            }
        } else {
            throw new Error('Failed to save user');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function updatePermissions(e) {
    const role = e.target.value;
    const permissionsContainer = document.getElementById('permissionsContainer');
    
    if (permissionsContainer) {
        // Load role-specific permissions
        fetch(`/api/admin/roles/${role}/permissions`)
            .then(res => res.json())
            .then(permissions => {
                permissionsContainer.innerHTML = permissions.map(perm => `
                    <label class="checkbox-label">
                        <input type="checkbox" name="permissions[]" value="${perm.id}" 
                               ${perm.default ? 'checked' : ''}>
                        <span>${perm.name}</span>
                    </label>
                `).join('');
            });
    }
}

async function handleBulkAction() {
    const action = document.getElementById('bulkAction')?.value;
    const checkboxes = document.querySelectorAll('.bulk-select:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);

    if (!action || ids.length === 0) {
        showNotification('Please select an action and at least one item', 'warning');
        return;
    }

    if (confirm(`Are you sure you want to ${action} ${ids.length} items?`)) {
        try {
            await fetch('/api/admin/bulk-action', {
                method: 'POST',
                body: JSON.stringify({ action, ids }),
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                }
            });
            showNotification('Bulk action completed', 'success');
            location.reload();
        } catch (error) {
            showNotification('Bulk action failed', 'error');
        }
    }
}

// Settings Management
function initializeSettings() {
    const settingsForms = document.querySelectorAll('.settings-form');
    
    settingsForms.forEach(form => {
        form.addEventListener('submit', handleSettingsSubmit);
        
        // Auto-save toggle
        const autoSave = form.querySelector('.auto-save-toggle');
        if (autoSave) {
            autoSave.addEventListener('change', (e) => {
                if (e.target.checked) {
                    enableAutoSave(form);
                } else {
                    disableAutoSave(form);
                }
            });
        }
    });

    // Tab navigation
    const settingsTabs = document.querySelectorAll('.settings-tab');
    settingsTabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            switchSettingsTab(tab.dataset.tab);
        });
    });
}

async function handleSettingsSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    try {
        const response = await fetch('/api/admin/settings', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': getCsrfToken()
            }
        });

        if (response.ok) {
            showNotification('Settings saved successfully', 'success');
        } else {
            throw new Error('Failed to save settings');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Settings';
    }
}

let autoSaveIntervals = new Map();

function enableAutoSave(form) {
    const interval = setInterval(() => {
        const formData = new FormData(form);
        fetch('/api/admin/settings/auto-save', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': getCsrfToken()
            }
        });
    }, 30000); // Every 30 seconds

    autoSaveIntervals.set(form, interval);
}

function disableAutoSave(form) {
    const interval = autoSaveIntervals.get(form);
    if (interval) {
        clearInterval(interval);
        autoSaveIntervals.delete(form);
    }
}

function switchSettingsTab(tabName) {
    // Update active tab
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.tab === tabName);
    });

    // Update active panel
    document.querySelectorAll('.settings-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === `${tabName}-settings`);
    });
}

// Search Functionality
function initializeSearch() {
    const globalSearch = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (globalSearch) {
        globalSearch.addEventListener('input', debounce(async (e) => {
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            const results = await searchGlobal(query);
            displaySearchResults(results, searchResults);
        }, 300));

        // Close search results on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.global-search-wrapper')) {
                searchResults.style.display = 'none';
            }
        });
    }
}

async function searchGlobal(query) {
    try {
        const response = await fetch(`/api/admin/search?q=${encodeURIComponent(query)}`);
        return await response.json();
    } catch (error) {
        console.error('Search failed:', error);
        return [];
    }
}

function displaySearchResults(results, container) {
    if (results.length === 0) {
        container.innerHTML = '<div class="search-no-results">No results found</div>';
    } else {
        container.innerHTML = results.map(result => `
            <a href="${result.url}" class="search-result-item">
                <div class="search-result-type">${result.type}</div>
                <div class="search-result-content">
                    <div class="search-result-title">${highlightMatch(result.title, result.query)}</div>
                    ${result.description ? `<div class="search-result-desc">${result.description}</div>` : ''}
                </div>
            </a>
        `).join('');
    }
    
    container.style.display = 'block';
}

function highlightMatch(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Theme Management
function initializeTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const currentTheme = localStorage.getItem('adminTheme') || 'light';
    
    // Set initial theme
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    if (themeToggle) {
        themeToggle.checked = currentTheme === 'dark';
        themeToggle.addEventListener('change', (e) => {
            const theme = e.target.checked ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('adminTheme', theme);
        });
    }
}

// WebSocket for real-time updates
function initializeWebSocket() {
    const ws = new WebSocket(`wss://${window.location.host}/admin/ws`);
    
    ws.onopen = () => {
        console.log('WebSocket connected');
    };
    
    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        handleRealtimeUpdate(data);
    };
    
    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
    };
    
    ws.onclose = () => {
        console.log('WebSocket disconnected');
        // Attempt to reconnect after 5 seconds
        setTimeout(initializeWebSocket, 5000);
    };
}

function handleRealtimeUpdate(data) {
    switch (data.type) {
        case 'stats_update':
            updateStatCards(data.stats);
            break;
        case 'new_order':
            showNotification(`New order #${data.orderId} received`, 'success');
            // Update relevant tables/charts
            break;
        case 'user_activity':
            updateActivityIndicators(data);
            break;
        default:
            console.log('Unknown update type:', data.type);
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function fetchData(url) {
    const response = await fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrfToken()
        }
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return await response.json();
}

// Modal Class
class Modal {
    constructor(options) {
        this.options = {
            title: 'Modal',
            content: '',
            size: 'medium',
            onSave: null,
            onClose: null,
            ...options
        };
        this.create();
    }

    create() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'modal-overlay';
        
        this.modal = document.createElement('div');
        this.modal.className = `modal modal-${this.options.size}`;
        this.modal.innerHTML = `
            <div class="modal-header">
                <h3 class="modal-title">${this.options.title}</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">${this.options.content}</div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-cancel">Cancel</button>
                <button class="btn btn-primary modal-save">Save</button>
            </div>
        `;
        
        this.overlay.appendChild(this.modal);
        this.setupEvents();
    }

    setupEvents() {
        this.modal.querySelector('.modal-close').addEventListener('click', () => this.close());
        this.modal.querySelector('.modal-cancel').addEventListener('click', () => this.close());
        this.modal.querySelector('.modal-save').addEventListener('click', () => this.save());
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) this.close();
        });
    }

    show() {
        document.body.appendChild(this.overlay);
        setTimeout(() => {
            this.overlay.classList.add('active');
            this.modal.classList.add('active');
        }, 10);
    }

    close() {
        this.overlay.classList.remove('active');
        this.modal.classList.remove('active');
        setTimeout(() => {
            this.overlay.remove();
            if (this.options.onClose) this.options.onClose();
        }, 300);
    }

    save() {
        if (this.options.onSave) {
            const form = this.modal.querySelector('form');
            const formData = form ? new FormData(form) : null;
            this.options.onSave(formData);
        }
        this.close();
    }
}

// Export functions for external use
window.AdminJS = {
    showNotification,
    Modal,
    fetchData,
    debounce
};