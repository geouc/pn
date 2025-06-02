<?php
/**
 * Gateway Manager Class
 * Handles gateway registration and checkout validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Gateway_Manager {
    
    public function __construct() {
        add_filter('woocommerce_payment_gateways', [$this, 'addGateway']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);
        add_action('woocommerce_checkout_order_processed', [$this, 'processOrder'], 10, 3);
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            return;
        }
    }
    
    /**
     * Add our gateway to WooCommerce
     */
    public function addGateway($gateways) {
        $gateways[] = 'MMPO_Dynamic_NMI_Gateway';
        return $gateways;
    }
    
    /**
     * Validate checkout - ensure all products have valid payment config
     */
    public function validateCheckout() {
        if (!WC()->cart) {
            return;
        }
        
        $db_manager = new MMPO_Database_Manager();
        $invalid_products = [];
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $product_name = get_the_title($product_id);
            
            $credentials = $db_manager->getMerchantCredentialsForProduct($product_id);
            if (!$credentials) {
                $invalid_products[] = $product_name;
            }
        }
        
        if (!empty($invalid_products)) {
            $message = sprintf(
                __('The following products have invalid payment configuration: %s. Please contact support.', 'multi-merchant-payment-orchestrator'),
                implode(', ', $invalid_products)
            );
            wc_add_notice($message, 'error');
        }
    }
    
    /**
     * Handle order processing
     */
    public function processOrder($order_id, $posted_data, $order) {
        // This is handled by the gateway itself
        do_action('mmpo_process_order', $order_id, $posted_data, $order);
    }
    
    /**
     * Get merchant credentials for a product
     */
    public function getMerchantCredentials($product_id) {
        $db_manager = new MMPO_Database_Manager();
        return $db_manager->getMerchantCredentialsForProduct($product_id);
    }
    
    /**
     * Check if gateway is properly configured
     */
    public function isGatewayConfigured() {
        $gateways = WC()->payment_gateways->payment_gateways();
        
        if (!isset($gateways['mmpo_dynamic_nmi'])) {
            return false;
        }
        
        $gateway = $gateways['mmpo_dynamic_nmi'];
        return $gateway->enabled === 'yes';
    }
    
    /**
     * Get gateway settings
     */
    public function getGatewaySettings() {
        $gateways = WC()->payment_gateways->payment_gateways();
        
        if (!isset($gateways['mmpo_dynamic_nmi'])) {
            return [];
        }
        
        return $gateways['mmpo_dynamic_nmi']->settings;
    }
}