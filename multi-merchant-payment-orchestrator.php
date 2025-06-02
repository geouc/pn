<?php
/**
 * Plugin Name: Multi-Merchant Payment Orchestrator
 * Plugin URI: https://unitedcity.org/plugins/multi-merchant-payment-orchestrator
 * Description: Enables dynamic NMI gateway routing based on product ownership across WordPress multisite network. Merchants can use their own payment gateways while selling on your SaaS platforms.
 * Version: 1.0.0
 * Author: UC Dev Team
 * Author URI: https://unitedcity.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multi-merchant-payment-orchestrator
 * Domain Path: /languages
 * Network: true
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MMPO_VERSION', '1.0.0');
define('MMPO_PLUGIN_FILE', __FILE__);
define('MMPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMPO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMPO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class MultiMerchantPaymentOrchestrator {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        // Check dependencies
        if (!$this->checkDependencies()) {
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('multi-merchant-payment-orchestrator', false, dirname(MMPO_PLUGIN_BASENAME) . '/languages');
        
        // Include required files
        $this->includeFiles();
        
        // Initialize components
        $this->initializeComponents();
        
        // Setup admin
        if (is_admin()) {
            $this->setupAdmin();
        }
        
        // Setup frontend
        $this->setupFrontend();
    }
    
    private function checkDependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Multi-Merchant Payment Orchestrator:</strong> WooCommerce is required but not active.</p></div>';
            });
            return false;
        }
        
        if (!is_multisite()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Multi-Merchant Payment Orchestrator:</strong> This plugin requires WordPress Multisite.</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    private function includeFiles() {
        // Core classes
        require_once MMPO_PLUGIN_DIR . 'includes/class-database-manager.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-nmi-processor.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-dynamic-nmi-gateway.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-gateway-manager.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-product-manager.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-sales-hub.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-user-dashboard.php';
        require_once MMPO_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        
        // Admin classes
        if (is_admin()) {
            require_once MMPO_PLUGIN_DIR . 'includes/class-admin-manager.php';
        }
    }
    
    private function initializeComponents() {
        // Initialize core components
        new MMPO_Database_Manager();
        new MMPO_Gateway_Manager();
        new MMPO_Product_Manager();
        new MMPO_Sales_Hub();
        new MMPO_User_Dashboard();
        new MMPO_Webhook_Handler();
    }
    
    private function setupAdmin() {
        new MMPO_Admin_Manager();
        
        // Add admin menu
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('network_admin_menu', [$this, 'addNetworkAdminMenu']);
        
        // Handle AJAX requests
        add_action('wp_ajax_mmpo_save_credentials', [$this, 'ajaxSaveCredentials']);
        add_action('wp_ajax_mmpo_test_connection', [$this, 'ajaxTestConnection']);
        add_action('wp_ajax_mmpo_admin_save_credentials', [$this, 'ajaxAdminSaveCredentials']);
        add_action('wp_ajax_mmpo_delete_credentials', [$this, 'ajaxDeleteCredentials']);
        add_action('wp_ajax_mmpo_sync_sales', [$this, 'ajaxSyncSales']);
        add_action('wp_ajax_mmpo_test_all_connections', [$this, 'ajaxTestAllConnections']);
    }
    
    private function setupFrontend() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    public function addAdminMenu() {
        add_menu_page(
            __('Payment Orchestrator', 'multi-merchant-payment-orchestrator'),
            __('Payment Hub', 'multi-merchant-payment-orchestrator'),
            'manage_options',
            'mmpo-dashboard',
            [$this, 'renderAdminDashboard'],
            'dashicons-money-alt',
            30
        );
        
        add_submenu_page(
            'mmpo-dashboard',
            __('Merchant Credentials', 'multi-merchant-payment-orchestrator'),
            __('Credentials', 'multi-merchant-payment-orchestrator'),
            'manage_options',
            'mmpo-credentials',
            [$this, 'renderCredentialsPage']
        );
    }
    
    public function addNetworkAdminMenu() {
        add_menu_page(
            __('Payment Orchestrator Network', 'multi-merchant-payment-orchestrator'),
            __('Payment Hub', 'multi-merchant-payment-orchestrator'),
            'manage_network_options',
            'mmpo-network-dashboard',
            [$this, 'renderNetworkDashboard'],
            'dashicons-money-alt',
            30
        );
    }
    
    public function enqueueScripts() {
        wp_enqueue_script('mmpo-frontend', MMPO_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], MMPO_VERSION, true);
        wp_enqueue_style('mmpo-frontend', MMPO_PLUGIN_URL . 'assets/css/frontend.css', [], MMPO_VERSION);
        
        wp_localize_script('mmpo-frontend', 'mmpo_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmpo_ajax_nonce')
        ]);
    }
    
    public function renderAdminDashboard() {
        include MMPO_PLUGIN_DIR . 'admin/templates/dashboard.php';
    }
    
    public function renderCredentialsPage() {
        include MMPO_PLUGIN_DIR . 'admin/templates/credentials.php';
    }
    
    public function renderNetworkDashboard() {
        include MMPO_PLUGIN_DIR . 'admin/templates/network-dashboard.php';
    }
    
    // AJAX Handlers
    public function ajaxSaveCredentials() {
        check_ajax_referer('mmpo_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'multi-merchant-payment-orchestrator'));
        }
        
        $user_id = get_current_user_id();
        $site_id = get_current_blog_id();
        
        $nmi_username = sanitize_text_field($_POST['nmi_username'] ?? '');
        $nmi_password = sanitize_text_field($_POST['nmi_password'] ?? '');
        $nmi_api_key = sanitize_text_field($_POST['nmi_api_key'] ?? '');
        
        if (empty($nmi_username) || empty($nmi_password)) {
            wp_send_json_error(__('Username and password are required', 'multi-merchant-payment-orchestrator'));
        }
        
        $db_manager = new MMPO_Database_Manager();
        $result = $db_manager->saveCredentials($user_id, $site_id, $nmi_username, $nmi_password, $nmi_api_key);
        
        if ($result) {
            wp_send_json_success(__('Credentials saved successfully', 'multi-merchant-payment-orchestrator'));
        } else {
            wp_send_json_error(__('Failed to save credentials', 'multi-merchant-payment-orchestrator'));
        }
    }
    
    public function ajaxTestConnection() {
        check_ajax_referer('mmpo_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'multi-merchant-payment-orchestrator'));
        }
        
        $credentials = $_POST['credentials'] ?? [];
        
        if (empty($credentials['username']) || empty($credentials['password'])) {
            wp_send_json_error(__('Username and password required', 'multi-merchant-payment-orchestrator'));
        }
        
        $nmi_processor = new MMPO_NMI_Processor();
        $is_valid = $nmi_processor->testCredentials($credentials);
        
        if ($is_valid) {
            wp_send_json_success(__('Connection successful', 'multi-merchant-payment-orchestrator'));
        } else {
            wp_send_json_error(__('Invalid credentials or connection failed', 'multi-merchant-payment-orchestrator'));
        }
    }
    
    public function ajaxAdminSaveCredentials() {
        check_ajax_referer('mmpo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'multi-merchant-payment-orchestrator'));
        }
        
        $user_id = intval($_POST['merchant_user'] ?? 0);
        $site_id = intval($_POST['merchant_site'] ?? 0);
        $nmi_username = sanitize_text_field($_POST['nmi_username'] ?? '');
        $nmi_password = sanitize_text_field($_POST['nmi_password'] ?? '');
        $nmi_api_key = sanitize_text_field($_POST['nmi_api_key'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($user_id) || empty($site_id) || empty($nmi_username) || empty($nmi_password)) {
            wp_send_json_error(__('All required fields must be filled', 'multi-merchant-payment-orchestrator'));
        }
        
        $db_manager = new MMPO_Database_Manager();
        $result = $db_manager->saveCredentials($user_id, $site_id, $nmi_username, $nmi_password, $nmi_api_key, $is_active);
        
        if ($result) {
            wp_send_json_success(__('Credentials saved successfully', 'multi-merchant-payment-orchestrator'));
        } else {
            wp_send_json_error(__('Failed to save credentials', 'multi-merchant-payment-orchestrator'));
        }
    }
    
    public function ajaxDeleteCredentials() {
        check_ajax_referer('mmpo_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'multi-merchant-payment-orchestrator'));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $site_id = intval($_POST['site_id'] ?? 0);
        
        if (empty($user_id) || empty($site_id)) {
            wp_send_json_error(__('Invalid user or site ID', 'multi-merchant-payment-orchestrator'));
        }
        
        $db_manager = new MMPO_Database_Manager();
        $result = $db_manager->deleteCredentials($user_id, $site_id);
        
        if ($result) {
            wp_send_json_success(__('Credentials deleted successfully', 'multi-merchant-payment-orchestrator'));
        } else {
            wp_send_json_error(__('Failed to delete credentials', 'multi-merchant-payment-orchestrator'));
        }
    }
    
    public function ajaxSyncSales() {
        check_ajax_referer('mmpo_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'multi-merchant-payment-orchestrator'));
        }
        
        $sales_hub = new MMPO_Sales_Hub();
        $sales_hub->dailySalesSync();
        
        wp_send_json_success(__('Sales synced successfully', 'multi-merchant-payment-orchestrator'));
    }
    
    public function ajaxTestAllConnections() {
        check_ajax_referer('mmpo_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'multi-merchant-payment-orchestrator'));
        }
        
        $db_manager = new MMPO_Database_Manager();
        $all_credentials = $db_manager->getAllActiveCredentials();
        
        $results = [];
        $nmi_processor = new MMPO_NMI_Processor();
        
        foreach ($all_credentials as $cred) {
            $test_creds = [
                'username' => $cred->nmi_username,
                'password' => $cred->nmi_password,
                'api_key' => $cred->nmi_api_key
            ];
            
            $is_valid = $nmi_processor->testCredentials($test_creds);
            $site_name = get_blog_option($cred->site_id, 'blogname');
            
            $results[] = $cred->display_name . ' (' . $site_name . '): ' . ($is_valid ? 'OK' : 'FAILED');
        }
        
        wp_send_json_success(implode("\n", $results));
    }
    
    public function activate() {
        // Create database tables
        $db_manager = new MMPO_Database_Manager();
        $db_manager->createTables();
        
        // Set default options
        add_option('mmpo_version', MMPO_VERSION);
        add_option('mmpo_webhook_key', wp_generate_password(32, false));
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('mmpo_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'mmpo_daily_sync');
        }
    }
    
    public function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('mmpo_daily_sync');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    MultiMerchantPaymentOrchestrator::getInstance();
});

// Uninstall hook
register_uninstall_hook(__FILE__, function() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }
    
    global $wpdb;
    
    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'mmpo_merchant_credentials',
        $wpdb->prefix . 'mmpo_product_ownership',
        $wpdb->prefix . 'mmpo_sales_tracking'
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Delete options
    delete_option('mmpo_version');
    delete_option('mmpo_webhook_key');
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('mmpo_daily_sync');
});