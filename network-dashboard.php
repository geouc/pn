<?php
/**
 * Network Admin Dashboard Template
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get network-wide stats
$db_manager = new MMPO_Database_Manager();
$network_stats = $db_manager->getNetworkSalesOverview();
$recent_activity = $db_manager->getRecentNetworkActivity(20);
$unsynced_sales = $db_manager->getUnsyncedSales();

// Get network settings
$default_commission = get_site_option('mmpo_default_commission_rate', '5.00');
$sync_frequency = get_site_option('mmpo_sync_frequency', 'daily');
$email_notifications = get_site_option('mmpo_email_notifications', 1);
$debug_mode = get_site_option('mmpo_debug_mode', 0);

// Get all sites in network
$sites = get_sites(['number' => 100]);
?>

<div class="wrap">
    <h1><?php esc_html_e('Multi-Merchant Payment Orchestrator - Network Dashboard', 'multi-merchant-payment-orchestrator'); ?></h1>
    
    <div class="mmpo-network-dashboard">
        <!-- Network Overview Stats -->
        <div class="mmpo-stats-section">
            <h2><?php esc_html_e('Network Overview', 'multi-merchant-payment-orchestrator'); ?></h2>
            <div class="mmpo-network-stats">
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Total Network Sales', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value">$<?php echo number_format($network_stats['total_sales'], 2); ?></span>
                    <span class="stat-change positive">+12.5% <?php esc_html_e('this month', 'multi-merchant-payment-orchestrator'); ?></span>
                </div>
                
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Active Merchants', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value"><?php echo $network_stats['active_merchants']; ?></span>
                    <span class="stat-change neutral"><?php esc_html_e('across network', 'multi-merchant-payment-orchestrator'); ?></span>
                </div>
                
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Products with Payment Config', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value"><?php echo $network_stats['total_products']; ?></span>
                    <span class="stat-change neutral"><?php esc_html_e('configured products', 'multi-merchant-payment-orchestrator'); ?></span>
                </div>
                
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Active Sites', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value"><?php echo $network_stats['active_sites']; ?></span>
                    <span class="stat-change neutral"><?php esc_html_e('of', 'multi-merchant-payment-orchestrator'); ?> <?php echo count($sites); ?> <?php esc_html_e('sites', 'multi-merchant-payment-orchestrator'); ?></span>
                </div>
            </div>
            
            <?php if (count($unsynced_sales) > 0): ?>
            <div class="mmpo-alert mmpo-alert-warning">
                <h4><?php esc_html_e('Sync Alert', 'multi-merchant-payment-orchestrator'); ?></h4>
                <p>
                    <?php 
                    printf(
                        esc_html__('There are %d unsynced sales across the network. ', 'multi-merchant-payment-orchestrator'),
                        count($unsynced_sales)
                    );
                    ?>
                    <a href="#" id="network-sync-all" class="button button-secondary">
                        <?php esc_html_e('Sync All Now', 'multi-merchant-payment-orchestrator'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Network Settings -->
        <div class="mmpo-settings-section">
            <h2><?php esc_html_e('Network Settings', 'multi-merchant-payment-orchestrator'); ?></h2>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="mmpo_save_network_settings">
                <?php wp_nonce_field('mmpo_network_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_commission_rate"><?php esc_html_e('Default Commission Rate (%)', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="default_commission_rate" name="default_commission_rate" 
                                   value="<?php echo esc_attr($default_commission); ?>" 
                                   step="0.01" min="0" max="100" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Default commission rate for new products. Can be overridden per product.', 'multi-merchant-payment-orchestrator'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sync_frequency"><?php esc_html_e('Sales Sync Frequency', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <select id="sync_frequency" name="sync_frequency">
                                <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>><?php esc_html_e('Hourly', 'multi-merchant-payment-orchestrator'); ?></option>
                                <option value="twicedaily" <?php selected($sync_frequency, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'multi-merchant-payment-orchestrator'); ?></option>
                                <option value="daily" <?php selected($sync_frequency, 'daily'); ?>><?php esc_html_e('Daily', 'multi-merchant-payment-orchestrator'); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('How often sales should be automatically synced to merchant sites.', 'multi-merchant-payment-orchestrator'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_notifications"><?php esc_html_e('Email Notifications', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="email_notifications" name="email_notifications" value="1" 
                                       <?php checked($email_notifications, 1); ?>>
                                <?php esc_html_e('Send email notifications to merchants for new sales', 'multi-merchant-payment-orchestrator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="debug_mode"><?php esc_html_e('Debug Mode', 'multi-merchant-payment-orchestrator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="debug_mode" name="debug_mode" value="1" 
                                       <?php checked($debug_mode, 1); ?>>
                                <?php esc_html_e('Enable debug logging for troubleshooting', 'multi-merchant-payment-orchestrator'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Logs will be written to the WordPress debug log.', 'multi-merchant-payment-orchestrator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Network Settings', 'multi-merchant-payment-orchestrator'); ?>">
                </p>
            </form>
        </div>
        
        <!-- Site Status Overview -->
        <div class="mmpo-sites-section">
            <h2><?php esc_html_e('Site Status Overview', 'multi-merchant-payment-orchestrator'); ?></h2>
            
            <div class="mmpo-sites-grid">
                <?php foreach ($sites as $site): ?>
                    <?php
                    $site_name = get_blog_option($site->blog_id, 'blogname');
                    $site_url = get_blog_option($site->blog_id, 'siteurl');
                    
                    // Check if site has gateway configured
                    switch_to_blog($site->blog_id);
                    $gateway_active = false;
                    if (class_exists('WooCommerce')) {
                        $gateways = WC()->payment_gateways->payment_gateways();
                        $gateway_active = isset($gateways['mmpo_dynamic_nmi']) && $gateways['mmpo_dynamic_nmi']->enabled === 'yes';
                    }
                    restore_current_blog();
                    
                    // Get site stats
                    global $wpdb;
                    $sales_table = $wpdb->prefix . 'mmpo_sales_tracking';
                    $site_sales = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $sales_table WHERE saas_site_id = %d",
                        $site->blog_id
                    ));
                    ?>
                    <div class="mmpo-site-card">
                        <div class="site-header">
                            <h4><?php echo esc_html($site_name); ?></h4>
                            <span class="site-status <?php echo $gateway_active ? 'active' : 'inactive'; ?>">
                                <?php echo $gateway_active ? esc_html__('Active', 'multi-merchant-payment-orchestrator') : esc_html__('Inactive', 'multi-merchant-payment-orchestrator'); ?>
                            </span>
                        </div>
                        <div class="site-details">
                            <p><strong><?php esc_html_e('URL:', 'multi-merchant-payment-orchestrator'); ?></strong> 
                               <a href="<?php echo esc_url($site_url); ?>" target="_blank"><?php echo esc_html($site->domain . $site->path); ?></a>
                            </p>
                            <p><strong><?php esc_html_e('Sales:', 'multi-merchant-payment-orchestrator'); ?></strong> <?php echo $site_sales; ?></p>
                            <p><strong><?php esc_html_e('Gateway:', 'multi-merchant-payment-orchestrator'); ?></strong> 
                               <?php echo $gateway_active ? 
                                   '<span style="color: green;">✓ ' . esc_html__('Configured', 'multi-merchant-payment-orchestrator') . '</span>' : 
                                   '<span style="color: red;">✗ ' . esc_html__('Not Configured', 'multi-merchant-payment-orchestrator') . '</span>'; ?>
                            </p>
                        </div>
                        <div class="site-actions">
                            <a href="<?php echo get_admin_url($site->blog_id, 'admin.php?page=mmpo-dashboard'); ?>" 
                               class="button button-small">
                                <?php esc_html_e('Manage', 'multi-merchant-payment-orchestrator'); ?>
                            </a>
                            <?php if (class_exists('WooCommerce')): ?>
                            <a href="<?php echo get_admin_url($site->blog_id, 'admin.php?page=wc-settings&tab=checkout&section=mmpo_dynamic_nmi'); ?>" 
                               class="button button-small">
                                <?php esc_html_e('Gateway Settings', 'multi-merchant-payment-orchestrator'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Network Activity -->
        <div class="mmpo-activity-section">
            <h2><?php esc_html_e('Recent Network Activity', 'multi-merchant-payment-orchestrator'); ?></h2>
            
            <?php if (empty($recent_activity)): ?>
                <p><?php esc_html_e('No recent activity across the network.', 'multi-merchant-payment-orchestrator'); ?></p>
            <?php else: ?>
                <div class="mmpo-activity-feed">
                    <?php foreach ($recent_activity as $activity): ?>
                        <?php
                        $site_name = get_blog_option($activity->saas_site_id, 'blogname');
                        $time_diff = human_time_diff(strtotime($activity->created_at), current_time('timestamp'));
                        ?>
                        <div class="mmpo-activity-item">
                            <div class="activity-icon status-<?php echo esc_attr($activity->status); ?>">
                                <?php if ($activity->status === 'completed'): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php elseif ($activity->status === 'pending'): ?>
                                    <span class="dashicons dashicons-clock"></span>
                                <?php elseif ($activity->status === 'failed'): ?>
                                    <span class="dashicons dashicons-dismiss"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-info"></span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title">
                                    <strong><?php echo esc_html($activity->merchant_name); ?></strong>
                                    <?php esc_html_e('sold', 'multi-merchant-payment-orchestrator'); ?>
                                    <strong><?php echo esc_html($activity->product_name); ?></strong>
                                    <?php esc_html_e('for', 'multi-merchant-payment-orchestrator'); ?>
                                    <strong>$<?php echo number_format($activity->amount, 2); ?></strong>
                                </div>
                                <div class="activity-meta">
                                    <span class="site-name"><?php echo esc_html($site_name); ?></span>
                                    <span class="activity-time"><?php echo esc_html($time_diff); ?> <?php esc_html_e('ago', 'multi-merchant-payment-orchestrator'); ?></span>
                                    <span class="sync-status">
                                        <?php if ($activity->synced_to_merchant): ?>
                                            <span style="color: green;">✓ <?php esc_html_e('Synced', 'multi-merchant-payment-orchestrator'); ?></span>
                                        <?php else: ?>
                                            <span style="color: orange;">⏳ <?php esc_html_e('Pending Sync', 'multi-merchant-payment-orchestrator'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="mmpo-actions-section">
            <h2><?php esc_html_e('Network Actions', 'multi-merchant-payment-orchestrator'); ?></h2>
            <div class="mmpo-quick-actions">
                <button id="test-all-network-connections" class="button button-primary">
                    <?php esc_html_e('Test All Merchant Connections', 'multi-merchant-payment-orchestrator'); ?>
                </button>
                <button id="generate-network-report" class="button">
                    <?php esc_html_e('Generate Network Report', 'multi-merchant-payment-orchestrator'); ?>
                </button>
                <a href="<?php echo admin_url('admin-post.php?action=mmpo_export_data'); ?>" 
                   class="button" onclick="return confirm('<?php echo esc_js(__('Export all network data?', 'multi-merchant-payment-orchestrator')); ?>')">
                    <?php esc_html_e('Export Network Data', 'multi-merchant-payment-orchestrator'); ?>
                </a>
                <button id="cleanup-old-data" class="button">
                    <?php esc_html_e('Cleanup Old Data', 'multi-merchant-payment-orchestrator'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <style>
    .mmpo-network-dashboard { max-width: 1400px; }
    .mmpo-stats-section, .mmpo-settings-section, .mmpo-sites-section, .mmpo-activity-section, .mmpo-actions-section { 
        background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px; 
    }
    .mmpo-network-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px; }
    .mmpo-stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; padding: 20px; text-align: center; }
    .mmpo-stat-box h3 { margin: 0 0 10px 0; font-size: 14px; opacity: 0.9; }
    .stat-value { font-size: 32px; font-weight: bold; display: block; margin-bottom: 5px; }
    .stat-change { font-size: 12px; opacity: 0.8; }
    .stat-change.positive { color: #90EE90; }
    .stat-change.negative { color: #FFB6C1; }
    .stat-change.neutral { color: #E0E0E0; }
    
    .mmpo-alert { padding: 15px; border-radius: 6px; margin: 15px 0; }
    .mmpo-alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    .mmpo-alert h4 { margin-top: 0; }
    
    .mmpo-sites-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px; }
    .mmpo-site-card { border: 1px solid #ddd; border-radius: 8px; padding: 16px; background: #f9f9f9; }
    .site-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .site-header h4 { margin: 0; }
    .site-status { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    .site-status.active { background: #d4edda; color: #155724; }
    .site-status.inactive { background: #f8d7da; color: #721c24; }
    .site-details p { margin: 4px 0; font-size: 13px; }
    .site-actions { margin-top: 12px; }
    .site-actions .button { margin-right: 8px; }
    
    .mmpo-activity-feed { max-height: 400px; overflow-y: auto; }
    .mmpo-activity-item { display: flex; align-items: flex-start; padding: 12px; border-bottom: 1px solid #f0f0f1; }
    .mmpo-activity-item:last-child { border-bottom: none; }
    .activity-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
    .activity-icon.status-completed { background: #d4edda; color: #155724; }
    .activity-icon.status-pending { background: #fff3cd; color: #856404; }
    .activity-icon.status-failed { background: #f8d7da; color: #721c24; }
    .activity-details { flex: 1; }
    .activity-title { font-size: 14px; margin-bottom: 4px; }
    .activity-meta { font-size: 12px; color: #666; }
    .activity-meta span { margin-right: 12px; }
    
    .mmpo-quick-actions { display: flex; gap: 12px; flex-wrap: wrap; }
    
    @media (max-width: 768px) {
        .mmpo-network-stats { grid-template-columns: 1fr; }
        .mmpo-sites-grid { grid-template-columns: 1fr; }
        .mmpo-quick-actions { flex-direction: column; }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#network-sync-all').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('<?php echo esc_js(__('Sync all unsynced sales across the network?', 'multi-merchant-payment-orchestrator')); ?>')) {
                return;
            }
            
            var button = $(this);
            button.prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'multi-merchant-payment-orchestrator')); ?>');
            
            $.post(ajaxurl, {
                action: 'mmpo_sync_sales',
                nonce: '<?php echo wp_create_nonce('mmpo_ajax_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('All sales synced successfully!', 'multi-merchant-payment-orchestrator')); ?>');
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Sync failed:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data);
                }
                button.prop('disabled', false).text('<?php echo esc_js(__('Sync All Now', 'multi-merchant-payment-orchestrator')); ?>');
            });
        });
        
        $('#test-all-network-connections').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'multi-merchant-payment-orchestrator')); ?>');
            
            $.post(ajaxurl, {
                action: 'mmpo_test_all_connections',
                nonce: '<?php echo wp_create_nonce('mmpo_ajax_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Connection test results:', 'multi-merchant-payment-orchestrator')); ?>\n' + response.data);
                } else {
                    alert('<?php echo esc_js(__('Test failed:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data);
                }
                button.prop('disabled', false).text('<?php echo esc_js(__('Test All Merchant Connections', 'multi-merchant-payment-orchestrator')); ?>');
            });
        });
        
        $('#generate-network-report').on('click', function(e) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Network reporting feature coming soon!', 'multi-merchant-payment-orchestrator')); ?>');
        });
        
        $('#cleanup-old-data').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('<?php echo esc_js(__('Clean up old sales data (older than 90 days)?', 'multi-merchant-payment-orchestrator')); ?>')) {
                return;
            }
            
            alert('<?php echo esc_js(__('Data cleanup feature coming soon!', 'multi-merchant-payment-orchestrator')); ?>');
        });
    });
    </script>
</div>