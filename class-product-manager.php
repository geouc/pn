<?php
// =============================================================================
// FILE: includes/class-product-manager.php
// =============================================================================
/**
 * Product Manager Class
 * Handles product ownership and payment routing configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMPO_Product_Manager {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveProductOwnership'], 10, 2);
    }
    
    public function init() {
        // Add product ownership fields to product edit page
        if (is_admin()) {
            add_filter('woocommerce_product_data_tabs', [$this, 'addProductTab']);
            add_action('woocommerce_product_data_panels', [$this, 'addProductPanel']);
            add_action('woocommerce_process_product_meta', [$this, 'saveProductMeta']);
        }
    }
    
    public function addMetaBoxes() {
        add_meta_box(
            'mmpo_product_ownership',
            __('Payment Routing', 'multi-merchant-payment-orchestrator'),
            [$this, 'renderOwnershipMetaBox'],
            'product',
            'side',
            'high'
        );
    }
    
    public function addProductTab($tabs) {
        $tabs['mmpo_payment'] = [
            'label' => __('Payment Routing', 'multi-merchant-payment-orchestrator'),
            'target' => 'mmpo_payment_data',
            'class' => []
        ];
        return $tabs;
    }
    
    public function addProductPanel() {
        global $post;
        
        $db_manager = new MMPO_Database_Manager();
        $ownership = $db_manager->getProductOwnership($post->ID);
        $merchants = $db_manager->getEligibleMerchants();
        
        ?>
        <div id="mmpo_payment_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="mmpo_owner_user"><?php esc_html_e('Product Owner:', 'multi-merchant-payment-orchestrator'); ?></label>
                    <select id="mmpo_owner_user" name="mmpo_owner_user" class="select short">
                        <option value=""><?php esc_html_e('Select merchant...', 'multi-merchant-payment-orchestrator'); ?></option>
                        <?php foreach ($merchants as $merchant): ?>
                            <option value="<?php echo esc_attr($merchant['user_id'] . '-' . $merchant['site_id']); ?>"
                                <?php selected($ownership ? $ownership->owner_user_id . '-' . $ownership->owner_site_id : '', $merchant['user_id'] . '-' . $merchant['site_id']); ?>>
                                <?php echo esc_html($merchant['display_name'] . ' (' . $merchant['site_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                
                <p class="form-field">
                    <label for="mmpo_commission_rate"><?php esc_html_e('Commission Rate (%):', 'multi-merchant-payment-orchestrator'); ?></label>
                    <input type="number" id="mmpo_commission_rate" name="mmpo_commission_rate" 
                           value="<?php echo $ownership ? esc_attr($ownership->commission_rate) : '0'; ?>" 
                           step="0.01" min="0" max="100" class="input-text">
                </p>
                
                <?php if ($ownership): ?>
                <p class="form-field">
                    <strong><?php esc_html_e('Payment Status:', 'multi-merchant-payment-orchestrator'); ?></strong> 
                    <?php echo $db_manager->hasValidCredentials($ownership->owner_user_id, $ownership->owner_site_id) ? 
                        '<span style="color: green;">✓ ' . esc_html__('Configured', 'multi-merchant-payment-orchestrator') . '</span>' : 
                        '<span style="color: red;">✗ ' . esc_html__('Missing Credentials', 'multi-merchant-payment-orchestrator') . '</span>'; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function saveProductMeta($product_id) {
        $owner_data = sanitize_text_field($_POST['mmpo_owner_user'] ?? '');
        $commission_rate = floatval($_POST['mmpo_commission_rate'] ?? 0);
        
        $db_manager = new MMPO_Database_Manager();
        
        if (empty($owner_data)) {
            $db_manager->removeProductOwnership($product_id);
            return;
        }
        
        $owner_parts = explode('-', $owner_data);
        if (count($owner_parts) !== 2) {
            return;
        }
        
        $owner_user_id = intval($owner_parts[0]);
        $owner_site_id = intval($owner_parts[1]);
        $saas_site_id = get_current_blog_id();
        
        $db_manager->setProductOwnership($product_id, $owner_user_id, $owner_site_id, $saas_site_id, $commission_rate);
    }
    
    public function renderOwnershipMetaBox($post) {
        $db_manager = new MMPO_Database_Manager();
        $ownership = $db_manager->getProductOwnership($post->ID);
        
        if ($ownership) {
            $owner = get_userdata($ownership->owner_user_id);
            $site_name = get_blog_option($ownership->owner_site_id, 'blogname');
            
            echo '<p><strong>' . esc_html__('Owner:', 'multi-merchant-payment-orchestrator') . '</strong> ' . esc_html($owner->display_name) . '</p>';
            echo '<p><strong>' . esc_html__('Site:', 'multi-merchant-payment-orchestrator') . '</strong> ' . esc_html($site_name) . '</p>';
            echo '<p><strong>' . esc_html__('Commission:', 'multi-merchant-payment-orchestrator') . '</strong> ' . esc_html($ownership->commission_rate) . '%</p>';
            
            if ($db_manager->hasValidCredentials($ownership->owner_user_id, $ownership->owner_site_id)) {
                echo '<p style="color: green;"><strong>✓ ' . esc_html__('Payment configured', 'multi-merchant-payment-orchestrator') . '</strong></p>';
            } else {
                echo '<p style="color: red;"><strong>✗ ' . esc_html__('Payment not configured', 'multi-merchant-payment-orchestrator') . '</strong></p>';
            }
        } else {
            echo '<p>' . esc_html__('No payment routing configured for this product.', 'multi-merchant-payment-orchestrator') . '</p>';
        }
    }
}