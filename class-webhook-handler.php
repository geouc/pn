<?php
/**
 * Webhook Handler Class
 * Manages webhooks and cross-site communication
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Webhook_Handler {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerWebhookEndpoints']);
        add_action('init', [$this, 'handleLegacyWebhooks']);
    }
    
    /**
     * Register REST API endpoints for webhooks
     */
    public function registerWebhookEndpoints() {
        register_rest_route('mmpo/v1', '/webhook/sale', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSaleWebhook'],
            'permission_callback' => [$this, 'verifyWebhookAuth']
        ]);
        
        register_rest_route('mmpo/v1', '/webhook/refund', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRefundWebhook'],
            'permission_callback' => [$this, 'verifyWebhookAuth']
        ]);
        
        register_rest_route('mmpo/v1', '/webhook/status', [
            'methods' => 'GET',
            'callback' => [$this, 'handleStatusWebhook'],
            'permission_callback' => [$this, 'verifyWebhookAuth']
        ]);
        
        register_rest_route('mmpo/v1', '/webhook/test', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTestWebhook'],
            'permission_callback' => [$this, 'verifyWebhookAuth']
        ]);
    }
    
    /**
     * Verify webhook authentication
     */
    public function verifyWebhookAuth($request) {
        $auth_header = $request->get_header('Authorization');
        $expected_key = get_option('mmpo_webhook_key', wp_generate_password(32, false));
        
        // Check Bearer token
        if ($auth_header === 'Bearer ' . $expected_key) {
            return true;
        }
        
        // Fallback: Check query parameter for legacy support
        $query_key = $request->get_param('key');
        if ($query_key === $expected_key) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle sale webhook
     */
    public function handleSaleWebhook($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (empty($data['order_id']) || empty($data['transaction_id']) || empty($data['amount'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Missing required fields', 'multi-merchant-payment-orchestrator')
            ], 400);
        }
        
        // Log the webhook
        $this->logWebhook('sale', $data);
        
        // Process the sale webhook
        do_action('mmpo_webhook_sale_received', $data);
        
        // Trigger sales sync if needed
        if (!empty($data['merchant_user_id']) && !empty($data['merchant_site_id'])) {
            do_action('mmpo_sync_sale_to_merchant', $data);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Sale webhook processed successfully', 'multi-merchant-payment-orchestrator')
        ], 200);
    }
    
    /**
     * Handle refund webhook
     */
    public function handleRefundWebhook($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (empty($data['order_id']) || empty($data['refund_amount'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Missing required fields', 'multi-merchant-payment-orchestrator')
            ], 400);
        }
        
        // Log the webhook
        $this->logWebhook('refund', $data);
        
        // Process the refund webhook
        do_action('mmpo_webhook_refund_received', $data);
        
        // Update sale status in database
        $this->updateSaleStatus($data['order_id'], 'refunded', $data);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Refund webhook processed successfully', 'multi-merchant-payment-orchestrator')
        ], 200);
    }
    
    /**
     * Handle status webhook (health check)
     */
    public function handleStatusWebhook($request) {
        $db_manager = new MMPO_Database_Manager();
        $stats = $db_manager->getNetworkSalesOverview();
        
        return new WP_REST_Response([
            'success' => true,
            'status' => 'active',
            'version' => MMPO_VERSION,
            'timestamp' => current_time('mysql'),
            'stats' => $stats
        ], 200);
    }
    
    /**
     * Handle test webhook
     */
    public function handleTestWebhook($request) {
        $data = $request->get_json_params();
        
        // Log the test
        $this->logWebhook('test', $data);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Test webhook received successfully', 'multi-merchant-payment-orchestrator'),
            'received_data' => $data,
            'timestamp' => current_time('mysql')
        ], 200);
    }
    
    /**
     * Handle legacy webhooks (GET/POST parameters)
     */
    public function handleLegacyWebhooks() {
        if (isset($_GET['mmpo_webhook'])) {
            $this->processLegacyWebhook();
        }
    }
    
    /**
     * Process legacy webhook format
     */
    private function processLegacyWebhook() {
        // Verify authentication
        $webhook_key = sanitize_text_field($_GET['key'] ?? '');
        $expected_key = get_option('mmpo_webhook_key', '');
        
        if ($webhook_key !== $expected_key) {
            wp_die(__('Unauthorized', 'multi-merchant-payment-orchestrator'), 401);
        }
        
        $webhook_type = sanitize_text_field($_GET['mmpo_webhook']);
        
        switch ($webhook_type) {
            case 'sale':
                $this->handleLegacySaleWebhook();
                break;
            case 'refund':
                $this->handleLegacyRefundWebhook();
                break;
            case 'status':
                $this->handleLegacyStatusWebhook();
                break;
            default:
                wp_die(__('Invalid webhook type', 'multi-merchant-payment-orchestrator'), 400);
        }
        
        exit;
    }
    
    /**
     * Handle legacy sale webhook
     */
    private function handleLegacySaleWebhook() {
        $data = [
            'order_id' => intval($_POST['order_id'] ?? 0),
            'transaction_id' => sanitize_text_field($_POST['transaction_id'] ?? ''),
            'amount' => floatval($_POST['amount'] ?? 0),
            'merchant_user_id' => intval($_POST['merchant_user_id'] ?? 0),
            'merchant_site_id' => intval($_POST['merchant_site_id'] ?? 0),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'completed')
        ];
        
        // Log the webhook
        $this->logWebhook('sale_legacy', $data);
        
        // Process the sale
        do_action('mmpo_webhook_sale_received', $data);
        
        echo wp_json_encode(['success' => true, 'message' => 'Sale processed']);
    }
    
    /**
     * Handle legacy refund webhook
     */
    private function handleLegacyRefundWebhook() {
        $data = [
            'order_id' => intval($_POST['order_id'] ?? 0),
            'refund_amount' => floatval($_POST['refund_amount'] ?? 0),
            'refund_reason' => sanitize_text_field($_POST['refund_reason'] ?? ''),
            'transaction_id' => sanitize_text_field($_POST['transaction_id'] ?? '')
        ];
        
        // Log the webhook
        $this->logWebhook('refund_legacy', $data);
        
        // Process the refund
        do_action('mmpo_webhook_refund_received', $data);
        
        echo wp_json_encode(['success' => true, 'message' => 'Refund processed']);
    }
    
    /**
     * Handle legacy status webhook
     */
    private function handleLegacyStatusWebhook() {
        $db_manager = new MMPO_Database_Manager();
        $stats = $db_manager->getNetworkSalesOverview();
        
        echo wp_json_encode([
            'success' => true,
            'status' => 'active',
            'version' => MMPO_VERSION,
            'stats' => $stats
        ]);
    }
    
    /**
     * Send webhook to external URL
     */
    public function sendWebhook($url, $data, $type = 'sale') {
        $webhook_key = get_option('mmpo_webhook_key', '');
        
        $payload = wp_json_encode([
            'type' => $type,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'source' => get_site_url()
        ]);
        
        $args = [
            'body' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $webhook_key,
                'User-Agent' => 'MMPO-Webhook/1.0'
            ],
            'timeout' => 30,
            'blocking' => false, // Non-blocking for performance
            'sslverify' => true
        ];
        
        $response = wp_remote_post($url, $args);
        
        // Log the webhook attempt
        $this->logWebhookSent($url, $data, $type, $response);
        
        return $response;
    }
    
    /**
     * Update sale status in database
     */
    private function updateSaleStatus($order_id, $status, $webhook_data = []) {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $update_data = ['status' => $status];
        
        // Add additional data based on webhook type
        if ($status === 'refunded' && !empty($webhook_data['refund_amount'])) {
            $update_data['amount'] = $webhook_data['refund_amount'];
        }
        
        $result = $wpdb->update(
            $sales_table,
            $update_data,
            ['order_id' => $order_id],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Log webhook activity
     */
    private function logWebhook($type, $data) {
        if (!defined('MMPO_DEBUG') || !MMPO_DEBUG) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'data' => $data,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        error_log('MMPO Webhook Received: ' . wp_json_encode($log_entry));
    }
    
    /**
     * Log sent webhook
     */
    private function logWebhookSent($url, $data, $type, $response) {
        if (!defined('MMPO_DEBUG') || !MMPO_DEBUG) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'url' => $url,
            'data' => $data,
            'response_code' => wp_remote_retrieve_response_code($response),
            'success' => !is_wp_error($response)
        ];
        
        error_log('MMPO Webhook Sent: ' . wp_json_encode($log_entry));
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Get webhook endpoints info
     */
    public function getWebhookEndpoints() {
        $base_url = rest_url('mmpo/v1/webhook/');
        
        return [
            'sale' => $base_url . 'sale',
            'refund' => $base_url . 'refund',
            'status' => $base_url . 'status',
            'test' => $base_url . 'test'
        ];
    }
    
    /**
     * Test webhook functionality
     */
    public function testWebhook($type = 'test') {
        $endpoints = $this->getWebhookEndpoints();
        
        if (!isset($endpoints[$type])) {
            return new WP_Error('invalid_type', __('Invalid webhook type', 'multi-merchant-payment-orchestrator'));
        }
        
        $test_data = [
            'test' => true,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url()
        ];
        
        $webhook_key = get_option('mmpo_webhook_key', '');
        
        $response = wp_remote_post($endpoints[$type], [
            'body' => wp_json_encode($test_data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $webhook_key
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        return [
            'success' => $response_code === 200,
            'response_code' => $response_code,
            'response_body' => $response_body
        ];
    }
}