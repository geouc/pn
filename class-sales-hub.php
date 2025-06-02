<?php
/**
 * Sales Hub Class
 * Manages sales tracking and synchronization across sites
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Sales_Hub {
    
    public function __construct() {
        add_action('mmpo_payment_completed', [$this, 'onPaymentCompleted'], 10, 2);
        add_action('mmpo_sale_recorded', [$this, 'syncSaleToMerchant']);
        add_action('wp_ajax_mmpo_sync_sales', [$this, 'manualSyncSales']);
        add_action('mmpo_daily_sync', [$this, 'dailySalesSync']);
        
        // Hook into webhook events
        add_action('mmpo_webhook_sale_received', [$this, 'handleWebhookSale']);
        add_action('mmpo_webhook_refund_received', [$this, 'handleWebhookRefund']);
        
        // Schedule daily sync if not already scheduled
        if (!wp_next_scheduled('mmpo_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'mmpo_daily_sync');
        }
    }
    
    /**
     * Handle completed payment
     */
    public function onPaymentCompleted($order_id, $transaction_ids) {
        // Trigger sync for each sale
        $db_manager = new MMPO_Database_Manager();
        $unsynced_sales = $db_manager->getUnsyncedSales();
        
        foreach ($unsynced_sales as $sale) {
            if ($sale->order_id == $order_id) {
                $this->syncSaleToMerchant($sale->id);
            }
        }
        
        // Send confirmation emails
        $this->sendPaymentConfirmations($order_id);
    }
    
    /**
     * Sync sale to merchant site
     */
    public function syncSaleToMerchant($sale_id) {
        global $wpdb;
        
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sales_table WHERE id = %d",
            $sale_id
        ));
        
        if (!$sale || $sale->synced_to_merchant) {
            return false;
        }
        
        // Create order record in merchant's WooCommerce
        $merchant_order_id = $this->createMerchantOrder($sale);
        
        if ($merchant_order_id) {
            // Mark as synced
            $db_manager = new MMPO_Database_Manager();
            $db_manager->markSaleAsSynced($sale_id);
            
            // Send notification to merchant
            $this->notifyMerchant($sale);
            
            // Log successful sync
            $this->logSalesSync($sale_id, 'success', $merchant_order_id);
            
            return true;
        } else {
            // Log failed sync
            $this->logSalesSync($sale_id, 'failed', null);
            return false;
        }
    }
    
    /**
     * Create order in merchant's WooCommerce site
     */
    private function createMerchantOrder($sale) {
        // Switch to merchant site
        $current_blog_id = get_current_blog_id();
        switch_to_blog($sale->merchant_site_id);
        
        try {
            // Get original order details
            switch_to_blog($sale->saas_site_id);
            $original_order = wc_get_order($sale->order_id);
            $product = wc_get_product($sale->product_id);
            switch_to_blog($sale->merchant_site_id);
            
            if (!$original_order) {
                throw new Exception(__('Original order not found', 'multi-merchant-payment-orchestrator'));
            }
            
            // Create new order in merchant site
            $order = wc_create_order();
            
            if (!$order) {
                throw new Exception(__('Failed to create merchant order', 'multi-merchant-payment-orchestrator'));
            }
            
            // Set customer information
            $order->set_billing_first_name($original_order->get_billing_first_name());
            $order->set_billing_last_name($original_order->get_billing_last_name());
            $order->set_billing_email($original_order->get_billing_email());
            $order->set_billing_phone($original_order->get_billing_phone());
            $order->set_billing_address_1($original_order->get_billing_address_1());
            $order->set_billing_address_2($original_order->get_billing_address_2());
            $order->set_billing_city($original_order->get_billing_city());
            $order->set_billing_state($original_order->get_billing_state());
            $order->set_billing_postcode($original_order->get_billing_postcode());
            $order->set_billing_country($original_order->get_billing_country());
            
            // Copy shipping address if available
            if ($original_order->get_shipping_first_name()) {
                $order->set_shipping_first_name($original_order->get_shipping_first_name());
                $order->set_shipping_last_name($original_order->get_shipping_last_name());
                $order->set_shipping_address_1($original_order->get_shipping_address_1());
                $order->set_shipping_address_2($original_order->get_shipping_address_2());
                $order->set_shipping_city($original_order->get_shipping_city());
                $order->set_shipping_state($original_order->get_shipping_state());
                $order->set_shipping_postcode($original_order->get_shipping_postcode());
                $order->set_shipping_country($original_order->get_shipping_country());
            }
            
            // Add product to order (create a simple product if original doesn't exist)
            if ($product) {
                $order->add_product($product, 1, [
                    'subtotal' => $sale->amount,
                    'total' => $sale->amount
                ]);
            } else {
                // Create a simple line item if product doesn't exist on merchant site
                $item = new WC_Order_Item_Product();
                $item->set_name(sprintf(__('Network Sale - Product ID: %d', 'multi-merchant-payment-orchestrator'), $sale->product_id));
                $item->set_quantity(1);
                $item->set_subtotal($sale->amount);
                $item->set_total($sale->amount);
                $order->add_item($item);
            }
            
            // Set order details
            $order->set_payment_method('mmpo_sync');
            $order->set_payment_method_title(__('Network Sale Sync', 'multi-merchant-payment-orchestrator'));
            $order->set_status('completed');
            $order->set_total($sale->amount);
            
            // Add order meta
            $order->add_meta_data('_mmpo_original_order_id', $sale->order_id);
            $order->add_meta_data('_mmpo_original_site_id', $sale->saas_site_id);
            $order->add_meta_data('_mmpo_transaction_id', $sale->nmi_transaction_id);
            $order->add_meta_data('_mmpo_commission', $sale->commission);
            $order->add_meta_data('_mmpo_sale_id', $sale->id);
            
            // Add order notes
            $saas_site_name = get_blog_option($sale->saas_site_id, 'blogname');
            $order->add_order_note(
                sprintf(
                    __('Sale synced from %1$s. Original Order ID: %2$d. Transaction ID: %3$s. Commission: $%4$s', 'multi-merchant-payment-orchestrator'),
                    $saas_site_name,
                    $sale->order_id,
                    $sale->nmi_transaction_id,
                    number_format($sale->commission, 2)
                )
            );
            
            // Save the order
            $order->save();
            
            $order_id = $order->get_id();
            
            // Trigger order completion actions
            do_action('mmpo_merchant_order_created', $order_id, $sale);
            
            return $order_id;
            
        } catch (Exception $e) {
            error_log('MMPO Sales Hub Error: ' . $e->getMessage());
            return false;
        } finally {
            // Always restore the original blog
            switch_to_blog($current_blog_id);
        }
    }
    
    /**
     * Send notification to merchant
     */
    private function notifyMerchant($sale) {
        $merchant = get_userdata($sale->merchant_user_id);
        if (!$merchant) {
            return false;
        }
        
        $saas_site_name = get_blog_option($sale->saas_site_id, 'blogname');
        $merchant_site_name = get_blog_option($sale->merchant_site_id, 'blogname');
        
        $subject = sprintf(
            __('New Sale on %s', 'multi-merchant-payment-orchestrator'),
            $saas_site_name
        );
        
        $message = sprintf(
            __("Hi %s,\n\nYou have a new sale on %s!\n\nAmount: $%s\nCommission: $%s\nTransaction ID: %s\nDate: %s\n\nThe sale has been automatically synced to your %s WooCommerce store.\n\nLogin to view details: %s\n\nThanks!", 'multi-merchant-payment-orchestrator'),
            $merchant->display_name,
            $saas_site_name,
            number_format($sale->amount, 2),
            number_format($sale->commission, 2),
            $sale->nmi_transaction_id,
            date('F j, Y g:i A', strtotime($sale->created_at)),
            $merchant_site_name,
            get_blog_option($sale->merchant_site_id, 'siteurl') . '/wp-admin/admin.php?page=wc-orders'
        );
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($merchant->user_email, $subject, $message, $headers);
        
        // Log email attempt
        if (defined('MMPO_DEBUG') && MMPO_DEBUG) {
            error_log('MMPO Email Notification: ' . ($sent ? 'Sent' : 'Failed') . ' to ' . $merchant->user_email);
        }
        
        return $sent;
    }
    
    /**
     * Send payment confirmations
     */
    private function sendPaymentConfirmations($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Get all merchants involved in this order
        $db_manager = new MMPO_Database_Manager();
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $sales = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT merchant_user_id, merchant_site_id, SUM(amount) as total_amount
             FROM $sales_table 
             WHERE order_id = %d AND status = 'completed'
             GROUP BY merchant_user_id, merchant_site_id",
            $order_id
        ));
        
        foreach ($sales as $sale_summary) {
            $merchant = get_userdata($sale_summary->merchant_user_id);
            if ($merchant) {
                $this->sendMerchantPaymentConfirmation($merchant, $sale_summary, $order);
            }
        }
        
        return true;
    }
    
    /**
     * Send payment confirmation to individual merchant
     */
    private function sendMerchantPaymentConfirmation($merchant, $sale_summary, $order) {
        $subject = sprintf(
            __('Payment Confirmation - Order #%d', 'multi-merchant-payment-orchestrator'),
            $order->get_id()
        );
        
        $message = sprintf(
            __("Hi %s,\n\nPayment has been successfully processed for your products in Order #%d.\n\nYour portion: $%s\n\nCustomer: %s %s\nCustomer Email: %s\n\nThe funds will be deposited to your NMI merchant account.\n\nThank you!", 'multi-merchant-payment-orchestrator'),
            $merchant->display_name,
            $order->get_id(),
            number_format($sale_summary->total_amount, 2),
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            $order->get_billing_email()
        );
        
        return wp_mail($merchant->user_email, $subject, $message);
    }
    
    /**
     * Handle webhook sale data
     */
    public function handleWebhookSale($data) {
        if (empty($data['order_id']) || empty($data['merchant_user_id'])) {
            return false;
        }
        
        // Process webhook sale
        $db_manager = new MMPO_Database_Manager();
        
        // Find the sale record
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sales_table WHERE order_id = %d AND merchant_user_id = %d",
            $data['order_id'],
            $data['merchant_user_id']
        ));
        
        if ($sale && !$sale->synced_to_merchant) {
            $this->syncSaleToMerchant($sale->id);
        }
        
        return true;
    }
    
    /**
     * Handle webhook refund data
     */
    public function handleWebhookRefund($data) {
        if (empty($data['order_id']) || empty($data['refund_amount'])) {
            return false;
        }
        
        // Update sale status
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $result = $wpdb->update(
            $sales_table,
            [
                'status' => 'refunded',
                'amount' => $data['refund_amount']
            ],
            ['order_id' => $data['order_id']],
            ['%s', '%f'],
            ['%d']
        );
        
        // Notify merchants of refund
        if ($result !== false) {
            $this->notifyMerchantsOfRefund($data['order_id'], $data['refund_amount']);
        }
        
        return $result !== false;
    }
    
    /**
     * Notify merchants of refund
     */
    private function notifyMerchantsOfRefund($order_id, $refund_amount) {
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $sales = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sales_table WHERE order_id = %d",
            $order_id
        ));
        
        foreach ($sales as $sale) {
            $merchant = get_userdata($sale->merchant_user_id);
            if ($merchant) {
                $subject = sprintf(
                    __('Refund Processed - Order #%d', 'multi-merchant-payment-orchestrator'),
                    $order_id
                );
                
                $message = sprintf(
                    __("Hi %s,\n\nA refund has been processed for Order #%d.\n\nRefund Amount: $%s\n\nThe refund will be processed through your NMI merchant account.\n\nIf you have any questions, please contact support.", 'multi-merchant-payment-orchestrator'),
                    $merchant->display_name,
                    $order_id,
                    number_format($refund_amount, 2)
                );
                
                wp_mail($merchant->user_email, $subject, $message);
            }
        }
    }
    
    /**
     * Daily sales sync
     */
    public function dailySalesSync() {
        $db_manager = new MMPO_Database_Manager();
        $unsynced_sales = $db_manager->getUnsyncedSales();
        
        $synced_count = 0;
        $failed_count = 0;
        
        foreach ($unsynced_sales as $sale) {
            if ($this->syncSaleToMerchant($sale->id)) {
                $synced_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Log sync results
        if (defined('MMPO_DEBUG') && MMPO_DEBUG) {
            error_log("MMPO Daily Sync: {$synced_count} synced, {$failed_count} failed");
        }
        
        // Send admin notification if there are failures
        if ($failed_count > 0) {
            $this->notifyAdminOfSyncFailures($failed_count);
        }
        
        return ['synced' => $synced_count, 'failed' => $failed_count];
    }
    
    /**
     * Manual sales sync (AJAX handler)
     */
    public function manualSyncSales() {
        check_ajax_referer('mmpo_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'multi-merchant-payment-orchestrator'));
        }
        
        $results = $this->dailySalesSync();
        
        $message = sprintf(
            __('Sync completed: %d sales synced, %d failed', 'multi-merchant-payment-orchestrator'),
            $results['synced'],
            $results['failed']
        );
        
        wp_send_json_success($message);
    }
    
    /**
     * Notify admin of sync failures
     */
    private function notifyAdminOfSyncFailures($failed_count) {
        $admin_email = get_option('admin_email');
        
        $subject = __('MMPO Sales Sync Failures', 'multi-merchant-payment-orchestrator');
        $message = sprintf(
            __("Hi,\n\nThe Multi-Merchant Payment Orchestrator daily sync encountered %d failures.\n\nPlease review the sync logs and check the sales that failed to sync.\n\nAdmin URL: %s", 'multi-merchant-payment-orchestrator'),
            $failed_count,
            admin_url('admin.php?page=mmpo-dashboard')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Log sales sync attempts
     */
    private function logSalesSync($sale_id, $status, $merchant_order_id = null) {
        if (!defined('MMPO_DEBUG') || !MMPO_DEBUG) {
            return;
        }
        
        $log_data = [
            'sale_id' => $sale_id,
            'status' => $status,
            'merchant_order_id' => $merchant_order_id,
            'timestamp' => current_time('mysql')
        ];
        
        error_log('MMPO Sales Sync: ' . wp_json_encode($log_data));
    }
    
    /**
     * Get sales statistics
     */
    public function getSalesStatistics($date_range = '30 days') {
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $date_condition = '';
        if ($date_range) {
            $date_condition = $wpdb->prepare(
                "AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
                $date_range
            );
        }
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_sales,
                SUM(amount) as total_amount,
                SUM(commission) as total_commission,
                COUNT(DISTINCT merchant_user_id) as unique_merchants,
                COUNT(DISTINCT saas_site_id) as unique_sites,
                SUM(CASE WHEN synced_to_merchant = 1 THEN 1 ELSE 0 END) as synced_sales
             FROM $sales_table 
             WHERE status = 'completed' $date_condition"
        );
        
        return [
            'total_sales' => intval($stats->total_sales ?? 0),
            'total_amount' => floatval($stats->total_amount ?? 0),
            'total_commission' => floatval($stats->total_commission ?? 0),
            'unique_merchants' => intval($stats->unique_merchants ?? 0),
            'unique_sites' => intval($stats->unique_sites ?? 0),
            'synced_sales' => intval($stats->synced_sales ?? 0),
            'sync_percentage' => $stats->total_sales > 0 ? round(($stats->synced_sales / $stats->total_sales) * 100, 2) : 0
        ];
    }
    
    /**
     * Cleanup old sales data
     */
    public function cleanupOldSales($days = 90) {
        global $wpdb;
        $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $sales_table 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) 
             AND status IN ('refunded', 'failed')",
            $days
        ));
        
        return $result;
    }
}