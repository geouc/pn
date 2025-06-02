<?php
/**
 * Admin Credentials Template
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get credentials data
$db_manager = new MMPO_Database_Manager();
$credentials = $db_manager->getAllCredentials();
?>

<div class="wrap">
    <h1><?php esc_html_e('Merchant Credentials Management', 'multi-merchant-payment-orchestrator'); ?></h1>
    
    <div class="mmpo-credentials-manager">
        <!-- Add New Credentials -->
        <div class="mmpo-section">
            <h2><?php esc_html_e('Add/Edit Merchant Credentials', 'multi-merchant-payment-orchestrator'); ?></h2>
            <form id="mmpo-admin-credentials-form" method="post">
                <?php wp_nonce_field('mmpo_admin_credentials', 'mmpo_admin_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="merchant_user"><?php esc_html_e('Merchant User', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <select id="merchant_user" name="merchant_user" required>
                                <option value=""><?php esc_html_e('Select user...', 'multi-merchant-payment-orchestrator'); ?></option>
                                <?php
                                $users = get_users(['role__not_in' => ['subscriber']]);
                                foreach ($users as $user) {
                                    echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="merchant_site"><?php esc_html_e('Site', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <select id="merchant_site" name="merchant_site" required>
                                <option value=""><?php esc_html_e('Select site...', 'multi-merchant-payment-orchestrator'); ?></option>
                                <?php
                                $sites = get_sites();
                                foreach ($sites as $site) {
                                    $site_name = get_blog_option($site->blog_id, 'blogname');
                                    echo '<option value="' . esc_attr($site->blog_id) . '">' . esc_html($site_name . ' (' . $site->domain . $site->path . ')') . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nmi_username"><?php esc_html_e('NMI Username', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="nmi_username" name="nmi_username" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nmi_password"><?php esc_html_e('NMI Password', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="nmi_password" name="nmi_password" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nmi_api_key"><?php esc_html_e('NMI API Key', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="nmi_api_key" name="nmi_api_key" class="regular-text">
                            <p class="description"><?php esc_html_e('Optional - for advanced API features', 'multi-merchant-payment-orchestrator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="is_active"><?php esc_html_e('Status', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <?php esc_html_e('Active', 'multi-merchant-payment-orchestrator'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Credentials', 'multi-merchant-payment-orchestrator'); ?>">
                    <button type="button" id="test-admin-credentials" class="button"><?php esc_html_e('Test Connection', 'multi-merchant-payment-orchestrator'); ?></button>
                </p>
            </form>
        </div>
        
        <!-- Existing Credentials -->
        <div class="mmpo-section">
            <h2><?php esc_html_e('Existing Credentials', 'multi-merchant-payment-orchestrator'); ?></h2>
            
            <?php if (empty($credentials)): ?>
                <p><?php esc_html_e('No credentials configured yet.', 'multi-merchant-payment-orchestrator'); ?></p>
            <?php else: ?>
                <table class="mmpo-credentials-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('User', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Site', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Username', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Status', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Last Updated', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Actions', 'multi-merchant-payment-orchestrator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credentials as $cred): ?>
                            <?php $site_name = get_blog_option($cred->site_id, 'blogname'); ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($cred->display_name); ?><br>
                                    <small><?php echo esc_html($cred->user_email); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html($site_name); ?><br>
                                    <small>ID: <?php echo esc_html($cred->site_id); ?></small>
                                </td>
                                <td><?php echo esc_html($cred->nmi_username); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($cred->is_active ? 'active' : 'inactive'); ?>">
                                        <?php echo esc_html($cred->is_active ? __('Active', 'multi-merchant-payment-orchestrator') : __('Inactive', 'multi-merchant-payment-orchestrator')); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($cred->updated_at)); ?></td>
                                <td>
                                    <button class="button edit-credentials" 
                                            data-user-id="<?php echo esc_attr($cred->user_id); ?>" 
                                            data-site-id="<?php echo esc_attr($cred->site_id); ?>">
                                        <?php esc_html_e('Edit', 'multi-merchant-payment-orchestrator'); ?>
                                    </button>
                                    <button class="button delete-credentials" 
                                            data-user-id="<?php echo esc_attr($cred->user_id); ?>" 
                                            data-site-id="<?php echo esc_attr($cred->site_id); ?>">
                                        <?php esc_html_e('Delete', 'multi-merchant-payment-orchestrator'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .mmpo-credentials-manager { max-width: 1000px; }
    .mmpo-section { 
        background: #fff; 
        border: 1px solid #ccd0d4; 
        border-radius: 8px; 
        padding: 20px; 
        margin-bottom: 20px; 
    }
    .mmpo-credentials-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 15px; 
    }
    .mmpo-credentials-table th, 
    .mmpo-credentials-table td { 
        padding: 12px; 
        text-align: left; 
        border-bottom: 1px solid #ddd; 
    }
    .mmpo-credentials-table th { 
        background: #f8f9fa; 
        font-weight: bold; 
    }
    .status-active { 
        color: #00a32a; 
        font-weight: bold; 
    }
    .status-inactive { 
        color: #d63638; 
        font-weight: bold; 
    }
    .mmpo-credentials-table .button {
        margin-right: 5px;
        padding: 4px 8px;
        font-size: 11px;
        line-height: 1.4;
        height: auto;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#mmpo-admin-credentials-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            formData += '&action=mmpo_admin_save_credentials';
            
            $.post(ajaxurl, formData, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Credentials saved successfully!', 'multi-merchant-payment-orchestrator')); ?>');
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Error:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data);
                }
            });
        });
        
        $('#test-admin-credentials').on('click', function() {
            var credentials = {
                username: $('#nmi_username').val(),
                password: $('#nmi_password').val(),
                api_key: $('#nmi_api_key').val()
            };
            
            if (!credentials.username || !credentials.password) {
                alert('<?php echo esc_js(__('Please enter username and password first.', 'multi-merchant-payment-orchestrator')); ?>');
                return;
            }
            
            $(this).prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'multi-merchant-payment-orchestrator')); ?>');
            
            $.post(ajaxurl, {
                action: 'mmpo_test_connection',
                nonce: '<?php echo wp_create_nonce('mmpo_ajax_nonce'); ?>',
                credentials: credentials
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Connection successful!', 'multi-merchant-payment-orchestrator')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Connection failed:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data);
                }
                
                $('#test-admin-credentials').prop('disabled', false).text('<?php echo esc_js(__('Test Connection', 'multi-merchant-payment-orchestrator')); ?>');
            });
        });
        
        // Edit credentials
        $('.edit-credentials').on('click', function() {
            var userId = $(this).data('user-id');
            var siteId = $(this).data('site-id');
            
            // Populate form with existing data
            $('#merchant_user').val(userId);
            $('#merchant_site').val(siteId);
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $('#mmpo-admin-credentials-form').offset().top
            }, 500);
        });
        
        // Delete credentials
        $('.delete-credentials').on('click', function() {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete these credentials?', 'multi-merchant-payment-orchestrator')); ?>')) {
                return;
            }
            
            var userId = $(this).data('user-id');
            var siteId = $(this).data('site-id');
            
            $.post(ajaxurl, {
                action: 'mmpo_delete_credentials',
                nonce: '<?php echo wp_create_nonce('mmpo_ajax_nonce'); ?>',
                user_id: userId,
                site_id: siteId
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Credentials deleted successfully!', 'multi-merchant-payment-orchestrator')); ?>');
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Error:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data);
                }
            });
        });
    });
    </script>
</div>