<?php
/**
 * Admin Manager Class
 * Handles all admin interface functionality
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Admin_Manager {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('admin_init', [$this, 'init']);
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        add_action('network_admin_notices', [$this, 'displayNetworkAdminNotices']);
    }
    
    /**
     * Initialize admin functionality
     */
    public function init() {
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . MMPO_PLUGIN_BASENAME, [$this, 'addSettingsLink']);
        add_filter('network_admin_plugin_action_links_' . MMPO_PLUGIN_BASENAME, [$this, 'addNetworkSettingsLink']);
        
        // Add admin bar menu
        add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 100);
        
        // Handle admin form submissions
        add_action('admin_post_mmpo_save_network_settings', [$this, 'saveNetworkSettings']);
        add_action('admin_post_mmpo_export_data', [$this, 'exportData']);
        add_action('admin_post_mmpo_import_data', [$this, 'importData']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'mmpo') === false) {
            return;
        }
        
        wp_enqueue_script('mmpo-admin', MMPO_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-datepicker'], MMPO_VERSION, true);
        wp_enqueue_style('mmpo-admin', MMPO_PLUGIN_URL . 'assets/css/admin.css', [], MMPO_VERSION);
        wp_enqueue_style('jquery-ui-datepicker');
        
        // Enqueue Chart.js for statistics
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1', true);
        
        wp_localize_script('mmpo-admin', 'mmpo_admin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmpo_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'multi-merchant-payment-orchestrator'),
                'confirm_sync' => __('This will sync all unsynced sales. Continue?', 'multi-merchant-payment-orchestrator'),
                'confirm_export' => __('This will export all plugin data. Continue?', 'multi-merchant-payment-orchestrator'),
                'loading' => __('Loading...', 'multi-merchant-payment-orchestrator'),
                'error' => __('An error occurred. Please try again.', 'multi-merchant-payment-orchestrator'),
                'success' => __('Operation completed successfully.', 'multi-merchant-payment-orchestrator')
            ]
        ]);
    }
    
    /**
     * Add settings link to plugins page
     */
    public function addSettingsLink($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=mmpo-dashboard') . '">' . 
                        __('Settings', 'multi-merchant-payment-orchestrator') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add network settings link
     */
    public function addNetworkSettingsLink($links) {
        $settings_link = '<a href="' . network_admin_url('admin.php?page=mmpo-network-dashboard') . '">' . 
                        __('Network Settings', 'multi-merchant-payment-orchestrator') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add admin bar menu
     */
    public function addAdminBarMenu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get quick stats
        $db_manager = new MMPO_Database_Manager();
        $unsynced_count = count($db_manager->getUnsyncedSales());
        
        $title = __('Payment Hub', 'multi-merchant-payment-orchestrator');
        if ($unsynced_count > 0) {
            $title .= ' <span class="awaiting-mod count-' . $unsynced_count . '"><span class="pending-count">' . $unsynced_count . '</span></span>';
        }
        
        $wp_admin_bar->add_menu([
            'id' => 'mmpo-admin-bar',
            'title' => $title,
            'href' => admin_url('admin.php?page=mmpo-dashboard')
        ]);
        
        // Add submenu items
        $wp_admin_bar->add_menu([
            'parent' => 'mmpo-admin-bar',
            'id' => 'mmpo-dashboard',
            'title' => __('Dashboard', 'multi-merchant-payment-orchestrator'),
            'href' => admin_url('admin.php?page=mmpo-dashboard')
        ]);
        
        $wp_admin_bar->add_menu([
            'parent' => 'mmpo-admin-bar',
            'id' => 'mmpo-credentials',
            'title' => __('Credentials', 'multi-merchant-payment-orchestrator'),
            'href' => admin_url('admin.php?page=mmpo-credentials')
        ]);
        
        if ($unsynced_count > 0) {
            $wp_admin_bar->add_menu([
                'parent' => 'mmpo-admin-bar',
                'id' => 'mmpo-sync-sales',
                'title' => sprintf(__('Sync Sales (%d)', 'multi-merchant-payment-orchestrator'), $unsynced_count),
                'href' => '#',
                'meta' => ['class' => 'mmpo-sync-trigger']
            ]);
        }
        
        if (is_multisite() && is_network_admin()) {
            $wp_admin_bar->add_menu([
                'parent' => 'mmpo-admin-bar',
                'id' => 'mmpo-network',
                'title' => __('Network Overview', 'multi-merchant-payment-orchestrator'),
                'href' => network_admin_url('admin.php?page=mmpo-network-dashboard')
            ]);
        }
    }
    
    /**
     * Display admin notices
     */
    public function displayAdminNotices() {
        // Check if gateway is configured
        if ($this->isOnPluginPage() && !$this->isGatewayConfigured()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('Multi-Merchant Payment Orchestrator:', 'multi-merchant-payment-orchestrator') . '</strong> ';
            echo sprintf(
                __('The payment gateway is not enabled. <a href="%s">Configure it now</a> to start processing payments.', 'multi-merchant-payment-orchestrator'),
                admin_url('admin.php?page=wc-settings&tab=checkout&section=mmpo_dynamic_nmi')
            );
            echo '</p></div>';
        }
        
        // Check for unsynced sales
        $db_manager = new MMPO_Database_Manager();
        $unsynced_sales = $db_manager->getUnsyncedSales();
        
        if ($this->isOnPluginPage() && count($unsynced_sales) > 5) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('Multi-Merchant Payment Orchestrator:', 'multi-merchant-payment-orchestrator') . '</strong> ';
            echo sprintf(
                __('You have %d unsynced sales. <a href="#" class="mmpo-sync-trigger">Sync them now</a>.', 'multi-merchant-payment-orchestrator'),
                count($unsynced_sales)
            );
            echo '</p></div>';
        }
        
        // Display success/error messages from URL parameters
        if (isset($_GET['mmpo_message'])) {
            $message_type = sanitize_text_field($_GET['mmpo_type'] ?? 'success');
            $message = sanitize_text_field($_GET['mmpo_message']);
            
            echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Display network admin notices
     */
    public function displayNetworkAdminNotices() {
        if (!is_network_admin()) {
            return;
        }
        
        // Check network-wide configuration
        $db_manager = new MMPO_Database_Manager();
        $stats = $db_manager->getNetworkSalesOverview();
        
        if ($this->isOnPluginPage() && $stats['active_merchants'] === 0) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . __('Multi-Merchant Payment Orchestrator:', 'multi-merchant-payment-orchestrator') . '</strong> ';
            echo __('No merchants have been configured yet. Add merchant credentials to start processing payments across your network.', 'multi-merchant-payment-orchestrator');
            echo '</p></div>';
        }
    }
    
    /**
     * Check if we're on a plugin page
     */
    private function isOnPluginPage() {
        $screen = get_current_screen();
        return $screen && (strpos($screen->id, 'mmpo') !== false || strpos($screen->id, 'payment-hub') !== false);
    }
    
    /**
     * Check if gateway is configured
     */
    private function isGatewayConfigured() {
        $gateway_manager = new MMPO_Gateway_Manager();
        return $gateway_manager->isGatewayConfigured();
    }
    
    /**
     * Save network settings
     */
    public function saveNetworkSettings() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have permission to perform this action.', 'multi-merchant-payment-orchestrator'));
        }
        
        check_admin_referer('mmpo_network_settings');
        
        // Save network-wide settings
        $default_commission = sanitize_text_field($_POST['default_commission_rate'] ?? '0');
        $sync_frequency = sanitize_text_field($_POST['sync_frequency'] ?? 'daily');
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $debug_mode = isset($_POST['debug_mode']) ? 1 : 0;
        
        update_site_option('mmpo_default_commission_rate', $default_commission);
        update_site_option('mmpo_sync_frequency', $sync_frequency);
        update_site_option('mmpo_email_notifications', $email_notifications);
        update_site_option('mmpo_debug_mode', $debug_mode);
        
        // Update cron schedule if changed
        $this->updateCronSchedule($sync_frequency);
        
        // Redirect with success message
        $redirect_url = add_query_arg([
            'mmpo_message' => __('Network settings saved successfully.', 'multi-merchant-payment-orchestrator'),
            'mmpo_type' => 'success'
        ], network_admin_url('admin.php?page=mmpo-network-dashboard'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Update cron schedule
     */
    private function updateCronSchedule($frequency) {
        // Clear existing schedule
        wp_clear_scheduled_hook('mmpo_daily_sync');
        
        // Schedule new frequency
        switch ($frequency) {
            case 'hourly':
                wp_schedule_event(time(), 'hourly', 'mmpo_daily_sync');
                break;
            case 'twicedaily':
                wp_schedule_event(time(), 'twicedaily', 'mmpo_daily_sync');
                break;
            case 'daily':
            default:
                wp_schedule_event(time(), 'daily', 'mmpo_daily_sync');
                break;
        }
    }
    
    /**
     * Export plugin data
     */
    public function exportData() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'multi-merchant-payment-orchestrator'));
        }
        
        check_admin_referer('mmpo_export_data');
        
        $db_manager = new MMPO_Database_Manager();
        
        // Collect all plugin data (excluding sensitive credentials)
        $export_data = [
            'version' => MMPO_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'merchants' => $this->getSanitizedMerchants(),
            'product_ownership' => $this->getProductOwnership(),
            'sales_summary' => $this->getSalesSummary(),
            'settings' => $this->getPluginSettings()
        ];
        
        // Generate filename
        $filename = 'mmpo-export-' . date('Y-m-d-H-i-s') . '.json';
        
        // Set headers for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Import plugin data
     */
    public function importData() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'multi-merchant-payment-orchestrator'));
        }
        
        check_admin_referer('mmpo_import_data');
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg([
                'mmpo_message' => __('No file selected or upload error.', 'multi-merchant-payment-orchestrator'),
                'mmpo_type' => 'error'
            ], admin_url('admin.php?page=mmpo-dashboard')));
            exit;
        }
        
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        if (!$import_data || !isset($import_data['version'])) {
            wp_redirect(add_query_arg([
                'mmpo_message' => __('Invalid import file format.', 'multi-merchant-payment-orchestrator'),
                'mmpo_type' => 'error'
            ], admin_url('admin.php?page=mmpo-dashboard')));
            exit;
        }
        
        // Process import (implement based on your needs)
        $this->processImportData($import_data);
        
        wp_redirect(add_query_arg([
            'mmpo_message' => __('Data imported successfully.', 'multi-merchant-payment-orchestrator'),
            'mmpo_type' => 'success'
        ], admin_url('admin.php?page=mmpo-dashboard')));
        exit;
    }
    
    /**
     * Get sanitized merchants data (without sensitive info)
     */
    private function getSanitizedMerchants() {
        $db_manager = new MMPO_Database_Manager();
        $merchants = $db_manager->getAllCredentials();
        
        $sanitized = [];
        foreach ($merchants as $merchant) {
            $sanitized[] = [
                'user_id' => $merchant->user_id,
                'site_id' => $merchant->site_id,
                'display_name' => $merchant->display_name,
                'user_email' => $merchant->user_email,
                'nmi_username' => $merchant->nmi_username,
                'is_active' => $merchant->is_active,
                'created_at' => $merchant->created_at
                // Note: Excluding nmi_password and nmi_api_key for security
            ];
        }
        
        return $sanitized;
    }
    
    /**
     * Get product ownership data
     */
    private function getProductOwnership() {
        global $wpdb;
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        
        return $wpdb->get_results(
            "SELECT product_id, owner_user_id, owner_site_id, saas_site_id, commission_rate, created_at 
             FROM $ownership_table 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * Get sales summary (without sensitive transaction details)
     */
    private function getSalesSummary() {
        $db_manager = new MMPO_Database_Manager();
        return $db_manager->getNetworkSalesOverview();
    }
    
    /**
     * Get plugin settings
     */
    private function getPluginSettings() {
        return [
            'default_commission_rate' => get_site_option('mmpo_default_commission_rate', '0'),
            'sync_frequency' => get_site_option('mmpo_sync_frequency', 'daily'),
            'email_notifications' => get_site_option('mmpo_email_notifications', 1),
            'debug_mode' => get_site_option('mmpo_debug_mode', 0)
        ];
    }
    
    /**
     * Process imported data
     */
    private function processImportData($import_data) {
        // Implement import logic based on your requirements
        // This is a placeholder for the import functionality
        
        if (isset($import_data['settings'])) {
            foreach ($import_data['settings'] as $key => $value) {
                update_site_option('mmpo_' . $key, $value);
            }
        }
        
        // Note: Be very careful with importing credentials and sales data
        // Consider security implications and data integrity
    }
    
    /**
     * Get admin dashboard data
     */
    public function getDashboardData() {
        $db_manager = new MMPO_Database_Manager();
        
        return [
            'network_stats' => $db_manager->getNetworkSalesOverview(),
            'recent_activity' => $db_manager->getRecentNetworkActivity(10),
            'unsynced_count' => count($db_manager->getUnsyncedSales()),
            'gateway_configured' => $this->isGatewayConfigured(),
            'plugin_version' => MMPO_VERSION
        ];
    }
    
    /**
     * Generate sales report
     */
    public function generateSalesReport($start_date = null, $end_date = null, $format = 'html') {
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $date_condition = '';
        if ($start_date && $end_date) {
            $date_condition = $wpdb->prepare(
                "AND created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        }
        
        $sales_data = $wpdb->get_results(
            "SELECT s.*, p.post_title as product_name, u.display_name as merchant_name
             FROM $sales_table s
             LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
             LEFT JOIN {$wpdb->users} u ON s.merchant_user_id = u.ID
             WHERE s.status = 'completed' $date_condition
             ORDER BY s.created_at DESC"
        );
        
        if ($format === 'csv') {
            return $this->generateCSVReport($sales_data);
        }
        
        return $sales_data;
    }
    
    /**
     * Generate CSV report
     */
    private function generateCSVReport($sales_data) {
        $csv_output = "Date,Merchant,Product,Amount,Commission,Transaction ID,Site\n";
        
        foreach ($sales_data as $sale) {
            $site_name = get_blog_option($sale->saas_site_id, 'blogname');
            $csv_output .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $sale->created_at,
                '"' . str_replace('"', '""', $sale->merchant_name) . '"',
                '"' . str_replace('"', '""', $sale->product_name) . '"',
                $sale->amount,
                $sale->commission,
                $sale->nmi_transaction_id,
                '"' . str_replace('"', '""', $site_name) . '"'
            );
        }
        
        return $csv_output;
    }
}