<?php
/**
 * Dynamic NMI Gateway Class
 * WooCommerce payment gateway that routes to different NMI accounts
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Dynamic_NMI_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'mmpo_dynamic_nmi';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Multi-Merchant NMI', 'multi-merchant-payment-orchestrator');
        $this->method_description = __('Dynamic NMI gateway that routes payments to product owners', 'multi-merchant-payment-orchestrator');
        
        $this->supports = array(
            'products',
            'refunds'
        );
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'multi-merchant-payment-orchestrator'),
                'type'    => 'checkbox',
                'label'   => __('Enable Multi-Merchant NMI Gateway', 'multi-merchant-payment-orchestrator'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'multi-merchant-payment-orchestrator'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'multi-merchant-payment-orchestrator'),
                'default'     => __('Credit Card', 'multi-merchant-payment-orchestrator'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'multi-merchant-payment-orchestrator'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'multi-merchant-payment-orchestrator'),
                'default'     => __('Pay securely with your credit card.', 'multi-merchant-payment-orchestrator'),
                'desc_tip'    => true,
            ),
            'card_types' => array(
                'title'       => __('Accepted Cards', 'multi-merchant-payment-orchestrator'),
                'type'        => 'multiselect',
                'description' => __('Select which card types to accept.', 'multi-merchant-payment-orchestrator'),
                'default'     => array('visa', 'mastercard', 'amex', 'discover'),
                'options'     => array(
                    'visa'       => 'Visa',
                    'mastercard' => 'MasterCard',
                    'amex'       => 'American Express',
                    'discover'   => 'Discover',
                    'diners'     => 'Diners Club',
                    'jcb'        => 'JCB'
                ),
                'desc_tip'    => true,
            ),
            'test_mode' => array(
                'title'       => __('Test Mode', 'multi-merchant-payment-orchestrator'),
                'type'        => 'checkbox',
                'label'       => __('Enable test mode for development', 'multi-merchant-payment-orchestrator'),
                'default'     => 'no',
                'description' => __('In test mode, payments will use NMI test servers.', 'multi-merchant-payment-orchestrator'),
            ),
        );
    }
    
    public function payment_scripts() {
        if (!is_admin() && !is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }
        
        if ('no' === $this->enabled) {
            return;
        }
        
        wp_enqueue_script('mmpo-payment', MMPO_PLUGIN_URL . 'assets/js/payment.js', ['jquery'], MMPO_VERSION, true);
        wp_enqueue_style('mmpo-payment', MMPO_PLUGIN_URL . 'assets/css/payment.css', [], MMPO_VERSION);
        
        wp_localize_script('mmpo-payment', 'mmpo_payment_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmpo_payment_nonce')
        ]);
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        // Check if cart has products with valid payment configuration
        $validation_result = $this->validateCartPaymentConfig();
        if (!$validation_result['valid']) {
            echo '<div class="woocommerce-error">' . esc_html($validation_result['message']) . '</div>';
            return;
        }
        
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
            <div class="mmpo-payment-form">
                <p class="form-row form-row-wide">
                    <label for="<?php echo esc_attr($this->id); ?>-card-number"><?php esc_html_e('Card Number', 'multi-merchant-payment-orchestrator'); ?> <span class="required">*</span></label>
                    <input id="<?php echo esc_attr($this->id); ?>-card-number" 
                           class="input-text wc-credit-card-form-card-number" 
                           type="text" 
                           maxlength="20" 
                           autocomplete="cc-number" 
                           placeholder="•••• •••• •••• ••••" 
                           name="<?php echo esc_attr($this->id); ?>-card-number" />
                    <span class="card-type"></span>
                </p>
                
                <div class="form-row-wrapper">
                    <p class="form-row form-row-first">
                        <label for="<?php echo esc_attr($this->id); ?>-card-expiry"><?php esc_html_e('Expiry (MM/YY)', 'multi-merchant-payment-orchestrator'); ?> <span class="required">*</span></label>
                        <input id="<?php echo esc_attr($this->id); ?>-card-expiry" 
                               class="input-text wc-credit-card-form-card-expiry" 
                               type="text" 
                               autocomplete="cc-exp" 
                               placeholder="MM / YY" 
                               name="<?php echo esc_attr($this->id); ?>-card-expiry" 
                               maxlength="7" />
                    </p>
                    
                    <p class="form-row form-row-last">
                        <label for="<?php echo esc_attr($this->id); ?>-card-cvc"><?php esc_html_e('Security Code', 'multi-merchant-payment-orchestrator'); ?> <span class="required">*</span></label>
                        <input id="<?php echo esc_attr($this->id); ?>-card-cvc" 
                               class="input-text wc-credit-card-form-card-cvc" 
                               type="text" 
                               autocomplete="cc-csc" 
                               placeholder="CVC" 
                               name="<?php echo esc_attr($this->id); ?>-card-cvc" 
                               maxlength="4" />
                    </p>
                </div>
                
                <div class="clear"></div>
            </div>
        </fieldset>
        
        <style>
        .mmpo-payment-form {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 10px 0;
        }
        
        .form-row-wrapper {
            display: flex;
            gap: 15px;
        }
        
        .form-row-wrapper .form-row {
            margin-bottom: 0;
            flex: 1;
        }
        
        .card-type {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: #666;
        }
        
        .form-row {
            position: relative;
        }
        
        .wc-credit-card-form-card-number,
        .wc-credit-card-form-card-expiry,
        .wc-credit-card-form-card-cvc {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            font-size: 16px;
        }
        
        .wc-credit-card-form-card-number:focus,
        .wc-credit-card-form-card-expiry:focus,
        .wc-credit-card-form-card-cvc:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 5px rgba(34, 113, 177, 0.3);
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Format card number
            $('#<?php echo esc_attr($this->id); ?>-card-number').on('input', function() {
                var value = $(this).val().replace(/\s/g, '').replace(/[^0-9]/gi, '');
                var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                $(this).val(formattedValue);
                
                // Detect card type
                var cardType = detectCardType(value);
                $(this).siblings('.card-type').text(cardType.toUpperCase());
            });
            
            // Format expiry
            $('#<?php echo esc_attr($this->id); ?>-card-expiry').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                $(this).val(value);
            });
            
            // CVC numbers only
            $('#<?php echo esc_attr($this->id); ?>-card-cvc').on('input', function() {
                var value = $(this).val().replace(/[^0-9]/g, '');
                $(this).val(value);
            });
            
            function detectCardType(number) {
                var patterns = {
                    visa: /^4/,
                    mastercard: /^5[1-5]/,
                    amex: /^3[47]/,
                    discover: /^6(?:011|5)/,
                    diners: /^3[0689]/,
                    jcb: /^(?:2131|1800|35)/
                };
                
                for (var type in patterns) {
                    if (patterns[type].test(number)) {
                        return type;
                    }
                }
                return '';
            }
        });
        </script>
        <?php
    }
    
    public function validate_fields() {
        $card_number = sanitize_text_field($_POST[$this->id . '-card-number'] ?? '');
        $card_expiry = sanitize_text_field($_POST[$this->id . '-card-expiry'] ?? '');
        $card_cvc = sanitize_text_field($_POST[$this->id . '-card-cvc'] ?? '');
        
        $errors = [];
        
        // Validate card number
        $card_number_clean = preg_replace('/[^0-9]/', '', $card_number);
        if (empty($card_number_clean)) {
            $errors[] = __('Card number is required.', 'multi-merchant-payment-orchestrator');
        } elseif (!$this->validateCardNumber($card_number_clean)) {
            $errors[] = __('Please enter a valid card number.', 'multi-merchant-payment-orchestrator');
        }
        
        // Validate expiry
        if (empty($card_expiry)) {
            $errors[] = __('Card expiry is required.', 'multi-merchant-payment-orchestrator');
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_expiry)) {
            $errors[] = __('Please enter a valid expiry date (MM/YY).', 'multi-merchant-payment-orchestrator');
        } else {
            // Check if card is expired
            $expiry_parts = explode('/', $card_expiry);
            $exp_month = intval($expiry_parts[0]);
            $exp_year = intval('20' . $expiry_parts[1]);
            $current_year = intval(date('Y'));
            $current_month = intval(date('m'));
            
            if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
                $errors[] = __('Card has expired.', 'multi-merchant-payment-orchestrator');
            }
        }
        
        // Validate CVC
        if (empty($card_cvc)) {
            $errors[] = __('Security code is required.', 'multi-merchant-payment-orchestrator');
        } elseif (!preg_match('/^[0-9]{3,4}$/', $card_cvc)) {
            $errors[] = __('Please enter a valid security code.', 'multi-merchant-payment-orchestrator');
        }
        
        // Validate cart payment configuration
        $validation_result = $this->validateCartPaymentConfig();
        if (!$validation_result['valid']) {
            $errors[] = $validation_result['message'];
        }
        
        // Add errors to WooCommerce
        foreach ($errors as $error) {
            wc_add_notice($error, 'error');
        }
        
        return empty($errors);
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return array('result' => 'fail');
        }
        
        try {
            $db_manager = new MMPO_Database_Manager();
            $nmi_processor = new MMPO_NMI_Processor();
            
            $success = true;
            $transaction_ids = [];
            $total_processed = 0;
            
            // Get card data
            $card_data = [
                'number' => sanitize_text_field($_POST[$this->id . '-card-number']),
                'expiry' => sanitize_text_field($_POST[$this->id . '-card-expiry']),
                'cvc' => sanitize_text_field($_POST[$this->id . '-card-cvc'])
            ];
            
            // Process each item with its respective merchant credentials
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $credentials = $db_manager->getMerchantCredentialsForProduct($product_id);
                
                if (!$credentials) {
                    throw new Exception(
                        sprintf(
                            __('Payment configuration error for product: %s', 'multi-merchant-payment-orchestrator'),
                            $item->get_name()
                        )
                    );
                }
                
                $item_total = $item->get_total();
                
                // Process payment for this item
                $result = $nmi_processor->processPayment($credentials, $order, $card_data, $item_total);
                
                if ($result['success']) {
                    $transaction_ids[] = $result['transaction_id'];
                    $total_processed += $item_total;
                    
                    // Record the sale
                    $ownership = $db_manager->getProductOwnership($product_id);
                    if ($ownership) {
                        $commission = ($item_total * $ownership->commission_rate) / 100;
                        
                        $db_manager->recordSale(
                            $order_id,
                            $product_id,
                            $credentials->user_id,
                            $credentials->site_id,
                            get_current_blog_id(),
                            $item_total,
                            $commission,
                            $result['transaction_id'],
                            'completed'
                        );
                    }
                } else {
                    // If any payment fails, void successful transactions
                    foreach ($transaction_ids as $trans_id) {
                        $nmi_processor->voidTransaction($credentials, $trans_id);
                    }
                    
                    throw new Exception($result['message']);
                }
            }
            
            // Mark order as paid
            $order->payment_complete();
            $order->add_order_note(
                sprintf(
                    __('Payment processed via Multi-Merchant NMI. Transaction IDs: %s. Total: $%s', 'multi-merchant-payment-orchestrator'),
                    implode(', ', $transaction_ids),
                    number_format($total_processed, 2)
                )
            );
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Empty cart
            WC()->cart->empty_cart();
            
            // Trigger sales sync
            do_action('mmpo_payment_completed', $order_id, $transaction_ids);
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
            
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $order->add_order_note('Payment failed: ' . $e->getMessage());
            return array('result' => 'fail');
        }
    }
    
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        try {
            $db_manager = new MMPO_Database_Manager();
            $nmi_processor = new MMPO_NMI_Processor();
            
            // Get sales records for this order
            global $wpdb;
            $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
            
            $sales = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sales_table WHERE order_id = %d AND status = 'completed'",
                $order_id
            ));
            
            if (empty($sales)) {
                throw new Exception(__('No payment records found for this order.', 'multi-merchant-payment-orchestrator'));
            }
            
            $refund_amount = $amount ?: $order->get_total();
            $total_refunded = 0;
            $refund_results = [];
            
            foreach ($sales as $sale) {
                // Calculate refund amount for this transaction
                $sale_refund_amount = $refund_amount;
                if (count($sales) > 1) {
                    // Proportional refund for multiple transactions
                    $sale_percentage = $sale->amount / $order->get_total();
                    $sale_refund_amount = $refund_amount * $sale_percentage;
                }
                
                // Get merchant credentials
                $credentials = $db_manager->getCredentials($sale->merchant_user_id, $sale->merchant_site_id);
                if (!$credentials) {
                    continue;
                }
                
                // Process refund
                $result = $nmi_processor->processRefund(
                    $credentials,
                    $sale->nmi_transaction_id,
                    $sale_refund_amount,
                    $reason
                );
                
                if ($result['success']) {
                    $total_refunded += $sale_refund_amount;
                    $refund_results[] = $result['transaction_id'];
                    
                    // Update sale status
                    $wpdb->update(
                        $sales_table,
                        ['status' => 'refunded'],
                        ['id' => $sale->id],
                        ['%s'],
                        ['%d']
                    );
                } else {
                    throw new Exception($result['message']);
                }
            }
            
            $order->add_order_note(
                sprintf(
                    __('Refund processed: $%s. Refund transaction IDs: %s', 'multi-merchant-payment-orchestrator'),
                    number_format($total_refunded, 2),
                    implode(', ', $refund_results)
                )
            );
            
            return true;
            
        } catch (Exception $e) {
            $order->add_order_note('Refund failed: ' . $e->getMessage());
            return new WP_Error('refund_failed', $e->getMessage());
        }
    }
    
    /**
     * Validate cart payment configuration
     */
    private function validateCartPaymentConfig() {
        if (!WC()->cart) {
            return ['valid' => false, 'message' => __('Cart is empty.', 'multi-merchant-payment-orchestrator')];
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
                __('The following products have invalid payment configuration: %s', 'multi-merchant-payment-orchestrator'),
                implode(', ', $invalid_products)
            );
            return ['valid' => false, 'message' => $message];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate credit card number using Luhn algorithm
     */
    private function validateCardNumber($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }
        
        $sum = 0;
        $alternate = false;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = intval($number[$i]);
            
            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n = ($n % 10) + 1;
                }
            }
            
            $sum += $n;
            $alternate = !$alternate;
        }
        
        return ($sum % 10) == 0;
    }
    
    /**
     * Admin options
     */
    public function admin_options() {
        ?>
        <h2><?php echo esc_html($this->get_method_title()); ?></h2>
        <p><?php echo esc_html($this->get_method_description()); ?></p>
        
        <div class="mmpo-gateway-status">
            <?php $this->displayGatewayStatus(); ?>
        </div>
        
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }
    
    /**
     * Display gateway status in admin
     */
    private function displayGatewayStatus() {
        $db_manager = new MMPO_Database_Manager();
        $stats = $db_manager->getNetworkSalesOverview();
        
        ?>
        <div class="mmpo-gateway-stats">
            <h3><?php esc_html_e('Gateway Overview', 'multi-merchant-payment-orchestrator'); ?></h3>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Total Network Sales', 'multi-merchant-payment-orchestrator'); ?></strong></td>
                        <td>$<?php echo number_format($stats['total_sales'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Active Merchants', 'multi-merchant-payment-orchestrator'); ?></strong></td>
                        <td><?php echo $stats['active_merchants']; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Products with Payment Config', 'multi-merchant-payment-orchestrator'); ?></strong></td>
                        <td><?php echo $stats['total_products']; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Active Sites', 'multi-merchant-payment-orchestrator'); ?></strong></td>
                        <td><?php echo $stats['active_sites']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <style>
        .mmpo-gateway-stats {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .mmpo-gateway-stats table {
            margin-top: 10px;
        }
        .mmpo-gateway-stats td {
            padding: 8px 12px;
        }
        </style>
        <?php
    }
}