/**
 * Multi-Merchant Payment Orchestrator - Dashboard JavaScript
 * Handles merchant dashboard interactions and AJAX operations
 * 
 * @package Multi-Merchant Payment Orchestrator
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Dashboard Manager Object
    const MMPODashboard = {
        
        // Initialize dashboard
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initDataTables();
            this.setupAutoRefresh();
            this.initTooltips();
            this.loadDashboardWidgets();
        },

        // Bind all event handlers
        bindEvents: function() {
            // Credentials form submission
            $('#mmpo-credentials-form').on('submit', this.handleCredentialsSave.bind(this));
            
            // Test connection button
            $('#test-credentials').on('click', this.handleTestConnection.bind(this));
            
            // Refresh dashboard data
            $('.mmpo-refresh-data').on('click', this.refreshDashboardData.bind(this));
            
            // Export data
            $('.mmpo-export-data').on('click', this.handleExportData.bind(this));
            
            // Filter controls
            $('.mmpo-date-filter').on('change', this.handleDateFilterChange.bind(this));
            
            // Product actions
            $(document).on('click', '.mmpo-sync-product', this.handleProductSync.bind(this));
            
            // Tab navigation
            $('.mmpo-tab').on('click', this.handleTabClick.bind(this));
            
            // Show/hide password
            $('.toggle-password').on('click', this.togglePasswordVisibility.bind(this));
            
            // Credential form field changes
            $('#nmi_username, #nmi_password').on('input', this.clearTestStatus.bind(this));
            
            // Sales table actions
            $(document).on('click', '.view-sale-details', this.viewSaleDetails.bind(this));
            
            // Network site selector
            $('#mmpo-site-selector').on('change', this.handleSiteChange.bind(this));
        },

        // Handle credentials save
        handleCredentialsSave: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            const $messageDiv = $('#mmpo-message');
            
            // Get form data
            const formData = {
                action: 'mmpo_save_credentials',
                nonce: mmpo_ajax.nonce,
                nmi_username: $('#nmi_username').val().trim(),
                nmi_password: $('#nmi_password').val().trim(),
                nmi_api_key: $('#nmi_api_key').val().trim()
            };
            
            // Validate required fields
            if (!formData.nmi_username || !formData.nmi_password) {
                this.showMessage('Username and password are required.', 'error');
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');
            
            // Send AJAX request
            $.post(mmpo_ajax.ajax_url, formData)
                .done(response => {
                    if (response.success) {
                        this.showMessage('Credentials saved successfully!', 'success');
                        
                        // Update UI to show credentials are saved
                        $('.mmpo-credentials').addClass('has-credentials');
                        
                        // Refresh dashboard stats
                        this.refreshDashboardStats();
                    } else {
                        this.showMessage('Error: ' + response.data, 'error');
                    }
                })
                .fail(() => {
                    this.showMessage('Network error. Please try again.', 'error');
                })
                .always(() => {
                    $submitBtn.prop('disabled', false).text('Save Credentials');
                });
        },

        // Handle test connection
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $btn = $(e.target);
            const credentials = {
                username: $('#nmi_username').val().trim(),
                password: $('#nmi_password').val().trim(),
                api_key: $('#nmi_api_key').val().trim()
            };
            
            if (!credentials.username || !credentials.password) {
                this.showMessage('Please enter username and password first.', 'error');
                return;
            }
            
            // Show testing state
            $btn.prop('disabled', true).text('Testing...');
            $('.test-status').remove();
            
            $.post(mmpo_ajax.ajax_url, {
                action: 'mmpo_test_connection',
                nonce: mmpo_ajax.nonce,
                credentials: credentials
            })
            .done(response => {
                const statusClass = response.success ? 'success' : 'error';
                const statusIcon = response.success ? '✓' : '✗';
                const statusText = response.success ? 'Connection successful!' : 'Connection failed: ' + response.data;
                
                // Add status indicator
                $btn.after(`<span class="test-status ${statusClass}">${statusIcon} ${statusText}</span>`);
                
                if (response.success) {
                    this.showMessage('Connection test passed!', 'success');
                } else {
                    this.showMessage('Connection test failed: ' + response.data, 'error');
                }
            })
            .fail(() => {
                this.showMessage('Network error during test.', 'error');
            })
            .always(() => {
                $btn.prop('disabled', false).text('Test Connection');
            });
        },

        // Refresh dashboard data
        refreshDashboardData: function(e) {
            if (e) e.preventDefault();
            
            const $refreshBtn = $('.mmpo-refresh-data');
            $refreshBtn.addClass('spinning');
            
            // Refresh all dashboard components
            Promise.all([
                this.refreshDashboardStats(),
                this.refreshRecentSales(),
                this.refreshNetworkProducts()
            ]).then(() => {
                this.showMessage('Dashboard refreshed!', 'success');
            }).catch(error => {
                this.showMessage('Error refreshing dashboard.', 'error');
                console.error('Dashboard refresh error:', error);
            }).finally(() => {
                $refreshBtn.removeClass('spinning');
            });
        },

        // Refresh dashboard statistics
        refreshDashboardStats: function() {
            return new Promise((resolve, reject) => {
                $.get(mmpo_ajax.ajax_url, {
                    action: 'mmpo_get_dashboard_stats',
                    nonce: mmpo_ajax.nonce
                })
                .done(response => {
                    if (response.success) {
                        this.updateStatCards(response.data);
                        resolve();
                    } else {
                        reject(new Error(response.data));
                    }
                })
                .fail(() => reject(new Error('Network error')));
            });
        },

        // Update statistics cards
        updateStatCards: function(stats) {
            // Animate stat values
            $('.mmpo-stat-value').each(function() {
                const $el = $(this);
                const key = $el.data('stat');
                
                if (stats[key] !== undefined) {
                    const currentValue = parseFloat($el.text().replace(/[^0-9.-]/g, ''));
                    const newValue = parseFloat(stats[key]);
                    
                    // Animate number change
                    $({ value: currentValue }).animate({ value: newValue }, {
                        duration: 1000,
                        easing: 'swing',
                        step: function() {
                            if (key.includes('amount') || key.includes('sales')) {
                                $el.text('$' + this.value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
                            } else {
                                $el.text(Math.floor(this.value));
                            }
                        },
                        complete: function() {
                            if (key.includes('amount') || key.includes('sales')) {
                                $el.text('$' + newValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
                            } else {
                                $el.text(newValue);
                            }
                        }
                    });
                }
            });
        },

        // Refresh recent sales
        refreshRecentSales: function() {
            return new Promise((resolve, reject) => {
                const $container = $('.mmpo-recent-sales');
                
                $.get(mmpo_ajax.ajax_url, {
                    action: 'mmpo_get_recent_sales',
                    nonce: mmpo_ajax.nonce
                })
                .done(response => {
                    if (response.success) {
                        $container.html(response.data.html);
                        this.initDataTables();
                        resolve();
                    } else {
                        reject(new Error(response.data));
                    }
                })
                .fail(() => reject(new Error('Network error')));
            });
        },

        // Refresh network products
        refreshNetworkProducts: function() {
            return new Promise((resolve, reject) => {
                const $container = $('.mmpo-products');
                
                $.get(mmpo_ajax.ajax_url, {
                    action: 'mmpo_get_network_products',
                    nonce: mmpo_ajax.nonce
                })
                .done(response => {
                    if (response.success) {
                        $container.html(response.data.html);
                        resolve();
                    } else {
                        reject(new Error(response.data));
                    }
                })
                .fail(() => reject(new Error('Network error')));
            });
        },

        // Handle export data
        handleExportData: function(e) {
            e.preventDefault();
            
            const $link = $(e.currentTarget);
            const exportType = $link.data('export-type') || 'csv';
            const dateRange = $('#export-date-range').val() || 'all';
            
            // Build export URL
            const exportUrl = mmpo_ajax.ajax_url + '?' + $.param({
                action: 'mmpo_export_sales',
                nonce: mmpo_ajax.nonce,
                format: exportType,
                range: dateRange
            });
            
            // Trigger download
            window.location.href = exportUrl;
            
            this.showMessage('Export started...', 'info');
        },

        // Handle date filter change
        handleDateFilterChange: function(e) {
            const filterValue = $(e.target).val();
            const customRange = $('.custom-date-range');
            
            if (filterValue === 'custom') {
                customRange.show();
            } else {
                customRange.hide();
                this.applyDateFilter(filterValue);
            }
        },

        // Apply date filter
        applyDateFilter: function(range) {
            const $salesTable = $('.mmpo-products-table');
            
            $.get(mmpo_ajax.ajax_url, {
                action: 'mmpo_filter_sales',
                nonce: mmpo_ajax.nonce,
                range: range
            })
            .done(response => {
                if (response.success) {
                    $salesTable.replaceWith(response.data.html);
                    this.initDataTables();
                }
            });
        },

        // Handle product sync
        handleProductSync: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const productId = $btn.data('product-id');
            
            $btn.prop('disabled', true).text('Syncing...');
            
            $.post(mmpo_ajax.ajax_url, {
                action: 'mmpo_sync_product',
                nonce: mmpo_ajax.nonce,
                product_id: productId
            })
            .done(response => {
                if (response.success) {
                    this.showMessage('Product synced successfully!', 'success');
                    $btn.text('Synced').addClass('success');
                } else {
                    this.showMessage('Sync failed: ' + response.data, 'error');
                    $btn.prop('disabled', false).text('Sync');
                }
            })
            .fail(() => {
                this.showMessage('Network error during sync.', 'error');
                $btn.prop('disabled', false).text('Sync');
            });
        },

        // Handle tab click
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const target = $tab.data('tab');
            
            // Update active states
            $('.mmpo-tab').removeClass('active');
            $tab.addClass('active');
            
            // Show/hide content
            $('.mmpo-tab-content').removeClass('active');
            $('#' + target).addClass('active');
            
            // Save preference
            localStorage.setItem('mmpo_active_tab', target);
        },

        // Toggle password visibility
        togglePasswordVisibility: function(e) {
            const $btn = $(e.currentTarget);
            const $input = $btn.siblings('input');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.html('<span class="dashicons dashicons-hidden"></span>');
            } else {
                $input.attr('type', 'password');
                $btn.html('<span class="dashicons dashicons-visibility"></span>');
            }
        },

        // Clear test status
        clearTestStatus: function() {
            $('.test-status').fadeOut(200, function() {
                $(this).remove();
            });
        },

        // View sale details
        viewSaleDetails: function(e) {
            e.preventDefault();
            
            const saleId = $(e.currentTarget).data('sale-id');
            
            $.get(mmpo_ajax.ajax_url, {
                action: 'mmpo_get_sale_details',
                nonce: mmpo_ajax.nonce,
                sale_id: saleId
            })
            .done(response => {
                if (response.success) {
                    this.showSaleModal(response.data);
                }
            });
        },

        // Show sale details modal
        showSaleModal: function(saleData) {
            const modalHtml = `
                <div class="mmpo-modal-overlay">
                    <div class="mmpo-modal">
                        <div class="mmpo-modal-header">
                            <h3>Sale Details</h3>
                            <button class="mmpo-modal-close">&times;</button>
                        </div>
                        <div class="mmpo-modal-body">
                            <dl class="mmpo-detail-list">
                                <dt>Order ID:</dt>
                                <dd>#${saleData.order_id}</dd>
                                
                                <dt>Product:</dt>
                                <dd>${saleData.product_name}</dd>
                                
                                <dt>Amount:</dt>
                                <dd>$${saleData.amount}</dd>
                                
                                <dt>Commission:</dt>
                                <dd>$${saleData.commission} (${saleData.commission_rate}%)</dd>
                                
                                <dt>Transaction ID:</dt>
                                <dd>${saleData.transaction_id}</dd>
                                
                                <dt>Status:</dt>
                                <dd><span class="status-${saleData.status}">${saleData.status}</span></dd>
                                
                                <dt>Date:</dt>
                                <dd>${saleData.date}</dd>
                                
                                <dt>Site:</dt>
                                <dd>${saleData.site_name}</dd>
                            </dl>
                        </div>
                        <div class="mmpo-modal-footer">
                            <button class="button mmpo-modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Close modal handlers
            $('.mmpo-modal-close, .mmpo-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.mmpo-modal-overlay').fadeOut(200, function() {
                        $(this).remove();
                    });
                }
            });
        },

        // Handle site change
        handleSiteChange: function(e) {
            const siteId = $(e.target).val();
            
            if (siteId) {
                window.location.href = window.location.pathname + '?site_id=' + siteId;
            }
        },

        // Initialize charts
        initCharts: function() {
            // Sales trend chart
            const salesChartEl = document.getElementById('mmpo-sales-chart');
            if (salesChartEl && window.Chart) {
                this.createSalesChart(salesChartEl);
            }
            
            // Revenue distribution chart
            const revenueChartEl = document.getElementById('mmpo-revenue-chart');
            if (revenueChartEl && window.Chart) {
                this.createRevenueChart(revenueChartEl);
            }
        },

        // Create sales trend chart
        createSalesChart: function(canvas) {
            const ctx = canvas.getContext('2d');
            
            $.get(mmpo_ajax.ajax_url, {
                action: 'mmpo_get_sales_chart_data',
                nonce: mmpo_ajax.nonce
            })
            .done(response => {
                if (response.success) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: 'Sales',
                                data: response.data.values,
                                borderColor: '#2271b1',
                                backgroundColor: 'rgba(34, 113, 177, 0.1)',
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
                                            return '$' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        },

        // Create revenue distribution chart
        createRevenueChart: function(canvas) {
            const ctx = canvas.getContext('2d');
            
            $.get(mmpo_ajax.ajax_url, {
                action: 'mmpo_get_revenue_chart_data',
                nonce: mmpo_ajax.nonce
            })
            .done(response => {
                if (response.success) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                data: response.data.values,
                                backgroundColor: [
                                    '#2271b1',
                                    '#00a32a',
                                    '#dba617',
                                    '#d63638',
                                    '#72aee6'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            });
        },

        // Initialize data tables
        initDataTables: function() {
            const $tables = $('.mmpo-products-table');
            
            $tables.each(function() {
                const $table = $(this);
                
                // Add sorting
                $table.find('th[data-sortable]').on('click', function() {
                    const $th = $(this);
                    const column = $th.data('column');
                    const currentOrder = $th.hasClass('asc') ? 'desc' : 'asc';
                    
                    // Update UI
                    $table.find('th').removeClass('asc desc');
                    $th.addClass(currentOrder);
                    
                    // Sort table
                    MMPODashboard.sortTable($table, column, currentOrder);
                });
            });
        },

        // Sort table
        sortTable: function($table, column, order) {
            const $tbody = $table.find('tbody');
            const $rows = $tbody.find('tr').toArray();
            
            $rows.sort((a, b) => {
                const aVal = $(a).find(`td:eq(${column})`).text();
                const bVal = $(b).find(`td:eq(${column})`).text();
                
                // Check if numeric
                const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return order === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // String comparison
                return order === 'asc' ? 
                    aVal.localeCompare(bVal) : 
                    bVal.localeCompare(aVal);
            });
            
            $tbody.empty().append($rows);
        },

        // Setup auto refresh
        setupAutoRefresh: function() {
            // Check if auto-refresh is enabled
            const autoRefresh = localStorage.getItem('mmpo_auto_refresh');
            
            if (autoRefresh === 'true') {
                // Refresh every 5 minutes
                setInterval(() => {
                    this.refreshDashboardData();
                }, 300000);
            }
            
            // Toggle auto-refresh
            $('#auto-refresh-toggle').on('change', function() {
                localStorage.setItem('mmpo_auto_refresh', this.checked);
            });
        },

        // Initialize tooltips
        initTooltips: function() {
            $('.mmpo-help-tip').tooltip({
                position: {
                    my: 'center bottom-10',
                    at: 'center top'
                },
                show: {
                    duration: 200
                },
                hide: {
                    duration: 100
                }
            });
        },

        // Load dashboard widgets
        loadDashboardWidgets: function() {
            // Activity feed widget
            this.loadActivityFeed();
            
            // Quick actions widget
            this.initQuickActions();
            
            // Notifications widget
            this.loadNotifications();
        },

        // Load activity feed
        loadActivityFeed: function() {
            const $feed = $('.mmpo-activity-feed');
            if (!$feed.length) return;
            
            $.get(mmpo_ajax.ajax_url, {
                action: 'mmpo_get_activity_feed',
                nonce: mmpo_ajax.nonce
            })
            .done(response => {
                if (response.success) {
                    $feed.html(response.data.html);
                }
            });
        },

        // Initialize quick actions
        initQuickActions: function() {
            $('.mmpo-quick-action').on('click', function(e) {
                e.preventDefault();
                
                const action = $(this).data('action');
                
                switch(action) {
                    case 'sync-all':
                        MMPODashboard.syncAllSales();
                        break;
                    case 'test-all':
                        MMPODashboard.testAllConnections();
                        break;
                    case 'export-report':
                        MMPODashboard.exportMonthlyReport();
                        break;
                }
            });
        },

        // Sync all sales
        syncAllSales: function() {
            const $btn = $('[data-action="sync-all"]');
            $btn.prop('disabled', true).text('Syncing...');
            
            $.post(mmpo_ajax.ajax_url, {
                action: 'mmpo_sync_all_sales',
                nonce: mmpo_ajax.nonce
            })
            .done(response => {
                if (response.success) {
                    this.showMessage(`Synced ${response.data.count} sales successfully!`, 'success');
                    this.refreshDashboardData();
                } else {
                    this.showMessage('Sync failed: ' + response.data, 'error');
                }
            })
            .always(() => {
                $btn.prop('disabled', false).text('Sync All Sales');
            });
        },

        // Test all connections
        testAllConnections: function() {
            const $btn = $('[data-action="test-all"]');
            $btn.prop('disabled', true).text('Testing...');
            
            $.post(mmpo_ajax.ajax_url, {
                action: 'mmpo_test_all_connections',
                nonce: mmpo_ajax.nonce
            })
            .done(response => {
                if (response.success) {
                    this.showConnectionResults(response.data);
                }
            })
            .always(() => {
                $btn.prop('disabled', false).text('Test All Connections');
            });
        },

        // Show connection test results
        showConnectionResults: function(results) {
            const modalHtml = `
                <div class="mmpo-modal-overlay">
                    <div class="mmpo-modal">
                        <div class="mmpo-modal-header">
                            <h3>Connection Test Results</h3>
                            <button class="mmpo-modal-close">&times;</button>
                        </div>
                        <div class="mmpo-modal-body">
                            <div class="connection-results">
                                ${results.map(result => `
                                    <div class="connection-result ${result.success ? 'success' : 'failed'}">
                                        <span class="status-icon">${result.success ? '✓' : '✗'}</span>
                                        <span class="merchant-name">${result.merchant}</span>
                                        ${result.error ? `<span class="error-message">${result.error}</span>` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('.mmpo-modal-close, .mmpo-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.mmpo-modal-overlay').remove();
                }
            });
        },

        // Export monthly report
        exportMonthlyReport: function() {
            const month = $('#report-month').val() || new Date().toISOString().slice(0, 7);
            
            window.location.href = mmpo_ajax.ajax_url + '?' + $.param({
                action: 'mmpo_export_monthly_report',
                nonce: mmpo_ajax.nonce,
                month: month
            });
        },

        // Load notifications
        loadNotifications: function() {
            const $container = $('.mmpo-notifications');
            if (!$container.length) return;
            
            $.get(mmpo_ajax.ajax_url, {
                action: 'mmpo_get_notifications',
                nonce: mmpo_ajax.nonce
            })
            .done(response => {
                if (response.success && response.data.notifications.length > 0) {
                    const $badge = $('.mmpo-notification-badge');
                    $badge.text(response.data.count).show();
                    
                    // Build notification list
                    const notificationHtml = response.data.notifications.map(n => `
                        <div class="mmpo-notification ${n.type}" data-id="${n.id}">
                            <div class="notification-content">
                                <strong>${n.title}</strong>
                                <p>${n.message}</p>
                                <time>${n.time}</time>
                            </div>
                            <button class="dismiss-notification" data-id="${n.id}">&times;</button>
                        </div>
                    `).join('');
                    
                    $container.html(notificationHtml);
                }
            });
            
            // Dismiss notification handler
            $(document).on('click', '.dismiss-notification', function() {
                const notificationId = $(this).data('id');
                $(this).closest('.mmpo-notification').fadeOut(200, function() {
                    $(this).remove();
                });
                
                // Mark as read
                $.post(mmpo_ajax.ajax_url, {
                    action: 'mmpo_dismiss_notification',
                    nonce: mmpo_ajax.nonce,
                    notification_id: notificationId
                });
            });
        },

        // Show message
        showMessage: function(message, type) {
            const $messageDiv = $('#mmpo-message');
            
            if (!$messageDiv.length) {
                // Create message div if it doesn't exist
                $('<div id="mmpo-message" class="mmpo-message"></div>').appendTo('.mmpo-dashboard');
            }
            
            $('#mmpo-message')
                .removeClass('success error info warning')
                .addClass(type)
                .text(message)
                .fadeIn();
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                $('#mmpo-message').fadeOut();
            }, 5000);
        },

        // Utility: Format currency
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },

        // Utility: Format date
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on dashboard pages
        if ($('#mmpo-merchant-dashboard').length || $('.mmpo-dashboard').length) {
            MMPODashboard.init();
        }
    });

    // Expose for external use
    window.MMPODashboard = MMPODashboard;

})(jQuery);
