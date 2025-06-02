<?php
/**
 * Database Manager Class
 * Handles all database operations for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Database_Manager {
    
    public function __construct() {
        // Constructor can be empty since we're using static-like methods
    }
    
    /**
     * Create plugin tables
     */
    public function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Merchant credentials table
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        $credentials_sql = "CREATE TABLE $credentials_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            site_id int(11) NOT NULL,
            nmi_username varchar(255) NOT NULL,
            nmi_password varchar(255) NOT NULL,
            nmi_api_key varchar(255) DEFAULT '',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_site (user_id, site_id),
            INDEX idx_active (is_active),
            INDEX idx_user (user_id)
        ) $charset_collate;";
        
        // Product ownership table
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        $ownership_sql = "CREATE TABLE $ownership_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            owner_user_id bigint(20) NOT NULL,
            owner_site_id int(11) NOT NULL,
            saas_site_id int(11) NOT NULL,
            commission_rate decimal(5,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product (product_id, saas_site_id),
            INDEX idx_owner (owner_user_id, owner_site_id),
            INDEX idx_product (product_id)
        ) $charset_collate;";
        
        // Sales tracking table
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        $sales_sql = "CREATE TABLE $sales_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            merchant_user_id bigint(20) NOT NULL,
            merchant_site_id int(11) NOT NULL,
            saas_site_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL,
            commission decimal(10,2) DEFAULT 0.00,
            nmi_transaction_id varchar(255) DEFAULT '',
            status varchar(50) DEFAULT 'pending',
            synced_to_merchant tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_order (order_id),
            INDEX idx_merchant (merchant_user_id, merchant_site_id),
            INDEX idx_status (status),
            INDEX idx_sync (synced_to_merchant)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($credentials_sql);
        dbDelta($ownership_sql);
        dbDelta($sales_sql);
    }
    
    /**
     * Save merchant credentials
     */
    public function saveCredentials($user_id, $site_id, $username, $password, $api_key = '', $is_active = 1) {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        return $wpdb->replace(
            $credentials_table,
            [
                'user_id' => $user_id,
                'site_id' => $site_id,
                'nmi_username' => $username,
                'nmi_password' => $password,
                'nmi_api_key' => $api_key,
                'is_active' => $is_active
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d']
        );
    }
    
    /**
     * Get credentials for a user/site
     */
    public function getCredentials($user_id, $site_id) {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $credentials_table WHERE user_id = %d AND site_id = %d",
            $user_id, $site_id
        ));
    }
    
    /**
     * Delete credentials
     */
    public function deleteCredentials($user_id, $site_id) {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        return $wpdb->delete(
            $credentials_table,
            ['user_id' => $user_id, 'site_id' => $site_id],
            ['%d', '%d']
        );
    }
    
    /**
     * Get all active credentials
     */
    public function getAllActiveCredentials() {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        return $wpdb->get_results(
            "SELECT c.*, u.display_name 
             FROM $credentials_table c
             INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.is_active = 1
             ORDER BY u.display_name, c.site_id"
        );
    }
    
    /**
     * Get merchant credentials for a product
     */
    public function getMerchantCredentialsForProduct($product_id) {
        global $wpdb;
        
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        $sql = "SELECT c.* FROM $credentials_table c
                INNER JOIN $ownership_table o ON c.user_id = o.owner_user_id 
                AND c.site_id = o.owner_site_id
                WHERE o.product_id = %d AND c.is_active = 1";
        
        return $wpdb->get_row($wpdb->prepare($sql, $product_id));
    }
    
    /**
     * Set product ownership
     */
    public function setProductOwnership($product_id, $owner_user_id, $owner_site_id, $saas_site_id, $commission_rate = 0) {
        global $wpdb;
        
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        
        return $wpdb->replace(
            $ownership_table,
            [
                'product_id' => $product_id,
                'owner_user_id' => $owner_user_id,
                'owner_site_id' => $owner_site_id,
                'saas_site_id' => $saas_site_id,
                'commission_rate' => $commission_rate
            ],
            ['%d', '%d', '%d', '%d', '%f']
        );
    }
    
    /**
     * Get product ownership
     */
    public function getProductOwnership($product_id, $saas_site_id = null) {
        global $wpdb;
        
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        
        if ($saas_site_id === null) {
            $saas_site_id = get_current_blog_id();
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $ownership_table WHERE product_id = %d AND saas_site_id = %d",
            $product_id, $saas_site_id
        ));
    }
    
    /**
     * Remove product ownership
     */
    public function removeProductOwnership($product_id, $saas_site_id = null) {
        global $wpdb;
        
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        
        if ($saas_site_id === null) {
            $saas_site_id = get_current_blog_id();
        }
        
        return $wpdb->delete(
            $ownership_table,
            [
                'product_id' => $product_id,
                'saas_site_id' => $saas_site_id
            ],
            ['%d', '%d']
        );
    }
    
    /**
     * Record a sale
     */
    public function recordSale($order_id, $product_id, $merchant_user_id, $merchant_site_id, $saas_site_id, $amount, $commission, $transaction_id, $status = 'completed') {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        return $wpdb->insert(
            $sales_table,
            [
                'order_id' => $order_id,
                'product_id' => $product_id,
                'merchant_user_id' => $merchant_user_id,
                'merchant_site_id' => $merchant_site_id,
                'saas_site_id' => $saas_site_id,
                'amount' => $amount,
                'commission' => $commission,
                'nmi_transaction_id' => $transaction_id,
                'status' => $status
            ],
            ['%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s']
        );
    }
    
    /**
     * Get sales stats for a merchant
     */
    public function getMerchantSalesStats($user_id) {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        // Total sales
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $sales_table WHERE merchant_user_id = %d AND status = 'completed'",
            $user_id
        ));
        
        // This month sales
        $month = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $sales_table 
             WHERE merchant_user_id = %d AND status = 'completed' 
             AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE())",
            $user_id
        ));
        
        // Product count
        $products = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT product_id) FROM $sales_table WHERE merchant_user_id = %d",
            $user_id
        ));
        
        // Site count
        $sites = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT saas_site_id) FROM $sales_table WHERE merchant_user_id = %d",
            $user_id
        ));
        
        return [
            'total_amount' => $total ?: 0,
            'month_amount' => $month ?: 0,
            'product_count' => $products ?: 0,
            'site_count' => $sites ?: 0
        ];
    }
    
    /**
     * Get recent sales for a merchant
     */
    public function getMerchantRecentSales($user_id, $limit = 10) {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.post_title as product_name 
             FROM $sales_table s
             LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
             WHERE s.merchant_user_id = %d 
             ORDER BY s.created_at DESC 
             LIMIT %d",
            $user_id, $limit
        ));
    }
    
    /**
     * Get network sales overview
     */
    public function getNetworkSalesOverview() {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        
        $total_sales = $wpdb->get_var("SELECT SUM(amount) FROM $sales_table WHERE status = 'completed'");
        $active_merchants = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $credentials_table WHERE is_active = 1");
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $ownership_table");
        $active_sites = $wpdb->get_var("SELECT COUNT(DISTINCT saas_site_id) FROM $ownership_table");
        
        return [
            'total_sales' => $total_sales ?: 0,
            'active_merchants' => $active_merchants ?: 0,
            'total_products' => $total_products ?: 0,
            'active_sites' => $active_sites ?: 0
        ];
    }
    
    /**
     * Get unsynced sales
     */
    public function getUnsyncedSales() {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        return $wpdb->get_results(
            "SELECT * FROM $sales_table 
             WHERE synced_to_merchant = 0 AND status = 'completed'
             ORDER BY created_at ASC"
        );
    }
    
    /**
     * Mark sale as synced
     */
    public function markSaleAsSynced($sale_id) {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        return $wpdb->update(
            $sales_table,
            ['synced_to_merchant' => 1],
            ['id' => $sale_id],
            ['%d'],
            ['%d']
        );
    }
    
    /**
     * Get recent network activity
     */
    public function getRecentNetworkActivity($limit = 15) {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.post_title as product_name, u.display_name as merchant_name
             FROM $sales_table s
             LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
             LEFT JOIN {$wpdb->users} u ON s.merchant_user_id = u.ID
             ORDER BY s.created_at DESC
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get all credentials for admin management
     */
    public function getAllCredentials() {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        return $wpdb->get_results(
            "SELECT c.*, u.display_name, u.user_email
             FROM $credentials_table c
             INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
             ORDER BY u.display_name, c.site_id"
        );
    }
    
    /**
     * Get eligible merchants (those with credentials)
     */
    public function getEligibleMerchants() {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        $merchants = $wpdb->get_results(
            "SELECT c.user_id, c.site_id, u.display_name
             FROM $credentials_table c
             INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.is_active = 1
             ORDER BY u.display_name, c.site_id"
        );
        
        $result = [];
        foreach ($merchants as $merchant) {
            $site_name = get_blog_option($merchant->site_id, 'blogname');
            $result[] = [
                'user_id' => $merchant->user_id,
                'site_id' => $merchant->site_id,
                'display_name' => $merchant->display_name,
                'site_name' => $site_name ?: 'Site #' . $merchant->site_id
            ];
        }
        
        return $result;
    }
    
    /**
     * Check if merchant has valid credentials
     */
    public function hasValidCredentials($user_id, $site_id) {
        global $wpdb;
        
        $credentials_table = $wpdb->prefix . 'mmpo_merchant_credentials';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $credentials_table 
             WHERE user_id = %d AND site_id = %d AND is_active = 1 
             AND nmi_username != '' AND nmi_password != ''",
            $user_id, $site_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Get merchant products across network
     */
    public function getMerchantNetworkProducts($user_id) {
        global $wpdb;
        
        $ownership_table = $wpdb->prefix . 'mmpo_product_ownership';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, p.post_title as product_name 
             FROM $ownership_table o
             LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
             WHERE o.owner_user_id = %d
             ORDER BY o.created_at DESC",
            $user_id
        ));
    }
}