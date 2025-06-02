<?php
/**
 * User Dashboard Class
 * Frontend interface for merchants to manage credentials and view sales
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MMPO_User_Dashboard {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_shortcode('mmpo_merchant_dashboard', [$this, 'renderDashboard']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    public function init() {
        // Add dashboard to user account page
        add_action('woocommerce_account_menu_items', [$this, 'addAccountMenuItem']);
        add_action('woocommerce_account_payment-hub_endpoint', [$this, 'renderAccountDashboard']);
        add_rewrite_endpoint('payment-hub', EP_ROOT | EP_PAGES);
    }
    
    public function addAccountMenuItem($menu_items) {
        $menu_items['payment-hub'] = __('Payment Hub', 'multi-merchant-payment-orchestrator');
        return $menu_items;
    }
    
    public function enqueueScripts() {
        wp_enqueue_script('mmpo-dashboard', MMPO_PLUGIN_URL . 'assets/js/dashboard.js', ['jquery'], MMPO_VERSION, true);
        wp_enqueue_style('mmpo-dashboard', MMPO_PLUGIN_URL . 'assets/css/dashboard.css', [], MMPO_VERSION);
        
        wp_localize_script('mmpo-dashboard', 'mmpo_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmpo_ajax_nonce')
        ]);
    }
    
    public function renderAccountDashboard() {
        $this->renderDashboard();
    }
    
    public function renderDashboard($atts = []) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to access your payment dashboard.', 'multi-merchant-payment-orchestrator') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $db_manager = new MMPO_Database_Manager();
        $credentials = $db_manager->getCredentials($user_id, get_current_blog_id());
        $sales_stats = $db_manager->getMerchantSalesStats($user_id);
        
        ob_start();
        ?>
        <div id="mmpo-merchant-dashboard" class="mmpo-dashboard">
            <h2><?php esc_html_e('Payment Hub Dashboard', 'multi-merchant-payment-orchestrator'); ?></h2>
            
            <!-- Credentials Section -->
            <div class="mmpo-section mmpo-credentials">
                <h3><?php esc_html_e('NMI Payment Credentials', 'multi-merchant-payment-orchestrator'); ?></h3>
                <form id="mmpo-credentials-form">
                    <?php wp_nonce_field('mmpo_save_credentials', 'mmpo_credentials_nonce'); ?>
                    
                    <div class="mmpo-form-row">
                        <label for="nmi_username"><?php esc_html_e('NMI Username:', 'multi-merchant-payment-orchestrator'); ?></label>
                        <input type="text" id="nmi_username" name="nmi_username" 
                               value="<?php echo esc_attr($credentials->nmi_username ?? ''); ?>" required>
                    </div>
                    
                    <div class="mmpo-form-row">
                        <label for="nmi_password"><?php esc_html_e('NMI Password:', 'multi-merchant-payment-orchestrator'); ?></label>
                        <input type="password" id="nmi_password" name="nmi_password" 
                               value="<?php echo esc_attr($credentials->nmi_password ?? ''); ?>" required>
                    </div>
                    
                    <div class="mmpo-form-row">
                        <label for="nmi_api_key"><?php esc_html_e('NMI API Key:', 'multi-merchant-payment-orchestrator'); ?></label>
                        <input type="text" id="nmi_api_key" name="nmi_api_key" 
                               value="<?php echo esc_attr($credentials->nmi_api_key ?? ''); ?>">
                        <p class="description"><?php esc_html_e('Optional - for advanced API features', 'multi-merchant-payment-orchestrator'); ?></p>
                    </div>
                    
                    <div class="mmpo-form-actions">
                        <button type="button" id="test-credentials" class="button"><?php esc_html_e('Test Connection', 'multi-merchant-payment-orchestrator'); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Credentials', 'multi-merchant-payment-orchestrator'); ?></button>
                    </div>
                    
                    <div id="mmpo-message" class="mmpo-message"></div>
                </form>
            </div>
            
            <!-- Stats Section -->
            <div class="mmpo-section mmpo-stats">
                <h3><?php esc_html_e('Sales Overview', 'multi-merchant-payment-orchestrator'); ?></h3>
                <div class="mmpo-stats-grid">
                    <div class="mmpo-stat-card">
                        <h4><?php esc_html_e('Total Sales', 'multi-merchant-payment-orchestrator'); ?></h4>
                        <span class="mmpo-stat-value">$<?php echo number_format($sales_stats['total_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="mmpo-stat-card">
                        <h4><?php esc_html_e('This Month', 'multi-merchant-payment-orchestrator'); ?></h4>
                        <span class="mmpo-stat-value">$<?php echo number_format($sales_stats['month_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="mmpo-stat-card">
                        <h4><?php esc_html_e('Products Sold', 'multi-merchant-payment-orchestrator'); ?></h4>
                        <span class="mmpo-stat-value"><?php echo $sales_stats['product_count'] ?? 0; ?></span>
                    </div>
                    <div class="mmpo-stat-card">
                        <h4><?php esc_html_e('Active Sites', 'multi-merchant-payment-orchestrator'); ?></h4>
                        <span class="mmpo-stat-value"><?php echo $sales_stats['site_count'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Sales -->
            <div class="mmpo-section mmpo-recent-sales">
                <h3><?php esc_html_e('Recent Sales', 'multi-merchant-payment-orchestrator'); ?></h3>
                <?php $this->renderRecentSales($user_id); ?>
            </div>
            
            <!-- Product Management -->
            <div class="mmpo-section mmpo-products">
                <h3><?php esc_html_e('Your Products Across Network', 'multi-merchant-payment-orchestrator'); ?></h3>
                <?php $this->renderNetworkProducts($user_id); ?>
            </div>
        </div>
        
        <style>
        .mmpo-dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .mmpo-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .mmpo-form-row {
            margin-bottom: 15px;
        }
        
        .mmpo-form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .mmpo-form-row input {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .mmpo-form-row .description {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .mmpo-form-actions {
            margin-top: 20px;
        }
        
        .mmpo-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .mmpo-stat-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
        }
        
        .mmpo-stat-card h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .mmpo-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
            display: block;
        }
        
        .mmpo-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        .mmpo-message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .mmpo-message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .mmpo-products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .mmpo-products-table th,
        .mmpo-products-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .mmpo-products-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .status-completed { color: #00a32a; font-weight: bold; }
        .status-pending { color: #dba617; font-weight: bold; }
        .status-failed { color: #d63638; font-weight: bold; }
        .status-refunded { color: #72aee6; font-weight: bold; }
        
        @media (max-width: 768px) {
            .mmpo-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .mmpo-products-table {
                font-size: 14px;
            }
            
            .mmpo-products-table th,
            .mmpo-products-table td {
                padding: 8px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#mmpo-credentials-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'mmpo_save_credentials',
                    nonce: mmpo_ajax.nonce,
                    nmi_username: $('#nmi_username').val(),
                    nmi_password: $('#nmi_password').val(),
                    nmi_api_key: $('#nmi_api_key').val()
                };
                
                $.post(mmpo_ajax.ajax_url, formData, function(response) {
                    if (response.success) {
                        showMessage('<?php echo esc_js(__('Credentials saved successfully!', 'multi-merchant-payment-orchestrator')); ?>', 'success');
                    } else {
                        showMessage('<?php echo esc_js(__('Error:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data, 'error');
                    }
                });
            });
            
            $('#test-credentials').on('click', function() {
                var credentials = {
                    username: $('#nmi_username').val(),
                    password: $('#nmi_password').val(),
                    api_key: $('#nmi_api_key').val()
                };
                
                if (!credentials.username || !credentials.password) {
                    showMessage('<?php echo esc_js(__('Please enter username and password first.', 'multi-merchant-payment-orchestrator')); ?>', 'error');
                    return;
                }
                
                var testData = {
                    action: 'mmpo_test_connection',
                    nonce: mmpo_ajax.nonce,
                    credentials: credentials
                };
                
                $(this).prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'multi-merchant-payment-orchestrator')); ?>');
                
                $.post(mmpo_ajax.ajax_url, testData, function(response) {
                    if (response.success) {
                        showMessage('<?php echo esc_js(__('Connection successful!', 'multi-merchant-payment-orchestrator')); ?>', 'success');
                    } else {
                        showMessage('<?php echo esc_js(__('Connection failed:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data, 'error');
                    }
                    
                    $('#test-credentials').prop('disabled', false).text('<?php echo esc_js(__('Test Connection', 'multi-merchant-payment-orchestrator')); ?>');
                });
            });
            
            function showMessage(message, type) {
                var messageDiv = $('#mmpo-message');
                messageDiv.removeClass('success error').addClass(type);
                messageDiv.text(message).show();
                
                setTimeout(function() {
                    messageDiv.fadeOut();
                }, 5000);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function renderRecentSales($user_id) {
        $db_manager = new MMPO_Database_Manager();
        $sales = $db_manager->getMerchantRecentSales($user_id);
        
        if (empty($sales)) {
            echo '<p>' . esc_html__('No sales yet.', 'multi-merchant-payment-orchestrator') . '</p>';
            return;
        }
        
        echo '<table class="mmpo-products-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Date', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Product', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Amount', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Site', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Status', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($sales as $sale) {
            $site_name = get_blog_option($sale->saas_site_id, 'blogname');
            echo '<tr>';
            echo '<td>' . date('M j, Y', strtotime($sale->created_at)) . '</td>';
            echo '<td>' . esc_html($sale->product_name) . '</td>';
            echo '<td>$' . number_format($sale->amount, 2) . '</td>';
            echo '<td>' . esc_html($site_name) . '</td>';
            echo '<td><span class="status-' . esc_attr($sale->status) . '">' . esc_html(ucfirst($sale->status)) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function renderNetworkProducts($user_id) {
        $db_manager = new MMPO_Database_Manager();
        $products = $db_manager->getMerchantNetworkProducts($user_id);
        
        if (empty($products)) {
            echo '<p>' . esc_html__('No products found across the network.', 'multi-merchant-payment-orchestrator') . '</p>';
            return;
        }
        
        echo '<table class="mmpo-products-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Product', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Site', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Commission Rate', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '<th>' . esc_html__('Added', 'multi-merchant-payment-orchestrator') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($products as $product) {
            $site_name = get_blog_option($product->saas_site_id, 'blogname');
            echo '<tr>';
            echo '<td>' . esc_html($product->product_name) . '</td>';
            echo '<td>' . esc_html($site_name) . '</td>';
            echo '<td>' . number_format($product->commission_rate, 2) . '%</td>';
            echo '<td>' . date('M j, Y', strtotime($product->created_at)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}