<?php
/**
 * NMI Processor Class
 * Handles NMI API communication and payment processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMPO_NMI_Processor {
    
    private $api_url = 'https://secure.nmi.com/api/transact.php';
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Process a payment through NMI
     */
    public function processPayment($credentials, $order, $card_data, $amount) {
        // Parse expiry date
        $expiry_parts = explode('/', str_replace(' ', '', $card_data['expiry']));
        if (count($expiry_parts) !== 2) {
            return [
                'success' => false,
                'message' => __('Invalid expiry date format', 'multi-merchant-payment-orchestrator')
            ];
        }
        
        $exp_month = str_pad($expiry_parts[0], 2, '0', STR_PAD_LEFT);
        $exp_year = '20' . $expiry_parts[1];
        
        // Prepare payment data
        $post_data = [
            'username' => $credentials->nmi_username,
            'password' => $credentials->nmi_password,
            'type' => 'sale',
            'amount' => number_format($amount, 2, '.', ''),
            'ccnumber' => str_replace([' ', '-'], '', $card_data['number']),
            'ccexp' => $exp_month . $exp_year,
            'cvv' => $card_data['cvc'],
            'orderid' => $order->get_id(),
            'orderdescription' => 'Order #' . $order->get_id() . ' - ' . get_bloginfo('name'),
            'firstname' => $order->get_billing_first_name(),
            'lastname' => $order->get_billing_last_name(),
            'address1' => $order->get_billing_address_1(),
            'address2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'ipaddress' => $this->getClientIP()
        ];
        
        // Add API key if available
        if (!empty($credentials->nmi_api_key)) {
            $post_data['security_key'] = $credentials->nmi_api_key;
        }
        
        // Make API request
        $response = wp_remote_post($this->api_url, [
            'body' => $post_data,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            ],
            'sslverify' => true
        ]);
        
        // Handle response errors
        if (is_wp_error($response)) {
            $this->logError('NMI API Error: ' . $response->get_error_message(), $post_data);
            return [
                'success' => false,
                'message' => __('Payment processing error. Please try again.', 'multi-merchant-payment-orchestrator')
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->logError('NMI HTTP Error: ' . $response_code, $post_data);
            return [
                'success' => false,
                'message' => __('Payment service unavailable. Please try again.', 'multi-merchant-payment-orchestrator')
            ];
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        parse_str($body, $result);
        
        // Log the transaction
        $this->logTransaction($post_data, $result);
        
        // Check response
        if (isset($result['response']) && $result['response'] == '1') {
            return [
                'success' => true,
                'transaction_id' => $result['transactionid'] ?? '',
                'auth_code' => $result['authcode'] ?? '',
                'message' => __('Payment successful', 'multi-merchant-payment-orchestrator'),
                'raw_response' => $result
            ];
        } else {
            $error_message = $this->parseErrorMessage($result);
            return [
                'success' => false,
                'message' => $error_message,
                'response_code' => $result['response'] ?? '',
                'raw_response' => $result
            ];
        }
    }
    
    /**
     * Test NMI credentials
     */
    public function testCredentials($credentials) {
        // Use a test authorization for $1.00
        $test_data = [
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'type' => 'auth',
            'ccnumber' => '4111111111111111', // Test card number
            'ccexp' => '1225', // Future expiry
            'cvv' => '123',
            'amount' => '1.00',
            'firstname' => 'Test',
            'lastname' => 'User',
            'address1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip' => '12345',
            'country' => 'US',
            'email' => 'test@example.com'
        ];
        
        // Add API key if provided
        if (!empty($credentials['api_key'])) {
            $test_data['security_key'] = $credentials['api_key'];
        }
        
        $response = wp_remote_post($this->api_url, [
            'body' => $test_data,
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        parse_str($body, $result);
        
        // If we get a successful auth or even a decline, credentials are valid
        // Invalid credentials would return response = 3 (error)
        return isset($result['response']) && in_array($result['response'], ['1', '2']);
    }
    
    /**
     * Process a refund
     */
    public function processRefund($credentials, $transaction_id, $amount, $reason = '') {
        $refund_data = [
            'username' => $credentials->nmi_username,
            'password' => $credentials->nmi_password,
            'type' => 'refund',
            'transactionid' => $transaction_id,
            'amount' => number_format($amount, 2, '.', ''),
            'orderdescription' => 'Refund: ' . $reason
        ];
        
        // Add API key if available
        if (!empty($credentials->nmi_api_key)) {
            $refund_data['security_key'] = $credentials->nmi_api_key;
        }
        
        $response = wp_remote_post($this->api_url, [
            'body' => $refund_data,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        parse_str($body, $result);
        
        if (isset($result['response']) && $result['response'] == '1') {
            return [
                'success' => true,
                'transaction_id' => $result['transactionid'] ?? '',
                'message' => __('Refund processed successfully', 'multi-merchant-payment-orchestrator')
            ];
        } else {
            return [
                'success' => false,
                'message' => $this->parseErrorMessage($result)
            ];
        }
    }
    
    /**
     * Void a transaction
     */
    public function voidTransaction($credentials, $transaction_id) {
        $void_data = [
            'username' => $credentials->nmi_username,
            'password' => $credentials->nmi_password,
            'type' => 'void',
            'transactionid' => $transaction_id
        ];
        
        // Add API key if available
        if (!empty($credentials->nmi_api_key)) {
            $void_data['security_key'] = $credentials->nmi_api_key;
        }
        
        $response = wp_remote_post($this->api_url, [
            'body' => $void_data,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        parse_str($body, $result);
        
        if (isset($result['response']) && $result['response'] == '1') {
            return [
                'success' => true,
                'transaction_id' => $result['transactionid'] ?? '',
                'message' => __('Transaction voided successfully', 'multi-merchant-payment-orchestrator')
            ];
        } else {
            return [
                'success' => false,
                'message' => $this->parseErrorMessage($result)
            ];
        }
    }
    
    /**
     * Parse error message from NMI response
     */
    private function parseErrorMessage($result) {
        if (isset($result['responsetext'])) {
            $message = $result['responsetext'];
            
            // Clean up common error messages
            $clean_messages = [
                'DECLINED' => __('Payment was declined. Please check your card details.', 'multi-merchant-payment-orchestrator'),
                'INVALID CARD NUMBER' => __('Invalid card number. Please check and try again.', 'multi-merchant-payment-orchestrator'),
                'INVALID EXPIRATION DATE' => __('Invalid expiration date. Please check and try again.', 'multi-merchant-payment-orchestrator'),
                'INSUFFICIENT FUNDS' => __('Insufficient funds. Please use a different card.', 'multi-merchant-payment-orchestrator'),
                'EXPIRED CARD' => __('Card has expired. Please use a different card.', 'multi-merchant-payment-orchestrator'),
                'INVALID CVV' => __('Invalid security code. Please check and try again.', 'multi-merchant-payment-orchestrator')
            ];
            
            $upper_message = strtoupper($message);
            foreach ($clean_messages as $key => $clean_message) {
                if (strpos($upper_message, $key) !== false) {
                    return $clean_message;
                }
            }
            
            return $message;
        }
        
        return __('Payment failed. Please try again or contact support.', 'multi-merchant-payment-orchestrator');
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
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
     * Log transaction details
     */
    private function logTransaction($request_data, $response_data) {
        if (!defined('MMPO_DEBUG') || !MMPO_DEBUG) {
            return;
        }
        
        // Remove sensitive data from logs
        $safe_request = $request_data;
        unset($safe_request['password']);
        unset($safe_request['ccnumber']);
        unset($safe_request['cvv']);
        if (isset($safe_request['security_key'])) {
            $safe_request['security_key'] = '***';
        }
        
        $log_data = [
            'timestamp' => current_time('mysql'),
            'request' => $safe_request,
            'response' => $response_data
        ];
        
        error_log('MMPO Transaction: ' . wp_json_encode($log_data));
    }
    
    /**
     * Log errors
     */
    private function logError($message, $context = []) {
        $safe_context = $context;
        // Remove sensitive data
        unset($safe_context['password']);
        unset($safe_context['ccnumber']);
        unset($safe_context['cvv']);
        if (isset($safe_context['security_key'])) {
            $safe_context['security_key'] = '***';
        }
        
        error_log('MMPO Error: ' . $message . ' Context: ' . wp_json_encode($safe_context));
    }
    
    /**
     * Validate credit card number using Luhn algorithm
     */
    public function validateCardNumber($number) {
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
     * Get card type from number
     */
    public function getCardType($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        
        $patterns = [
            'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'mastercard' => '/^5[1-5][0-9]{14}$/',
            'amex' => '/^3[47][0-9]{13}$/',
            'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            'diners' => '/^3[0689][0-9]{11}$/',
            'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/'
        ];
        
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $number)) {
                return $type;
            }
        }
        
        return 'unknown';
    }
}