<?php
/**
 * Admin Dashboard Template
 * 
 * @package Multi-Merchant Payment Orchestrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get stats data
$db_manager = new MMPO_Database_Manager();
$stats = $db_manager->getNetworkSalesOverview();
$recent_sales = $db_manager->getRecentNetworkActivity();
?>

<div class="wrap">
    <h1><?php esc_html_e('Multi-Merchant Payment Orchestrator', 'multi-merchant-payment-orchestrator'); ?></h1>
    
    <div class="mmpo-admin-dashboard">
        <!-- Overview Stats -->
        <div class="mmpo-stats-section">
            <h2><?php esc_html_e('Network Overview', 'multi-merchant-payment-orchestrator'); ?></h2>
            <div class="mmpo-admin-stats">
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Total Sales', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value">$<?php echo number_format($stats['total_sales'], 2); ?></span>
                </div>
                
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Active Merchants', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value"><?php echo $stats['active_merchants']; ?></span>
                </div>
                
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Network Products', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value"><?php echo $stats['total_products']; ?></span>
                </div>
                
                <div class="mmpo-stat-box">
                    <h3><?php esc_html_e('Active Sites', 'multi-merchant-payment-orchestrator'); ?></h3>
                    <span class="stat-value"><?php echo $stats['active_sites']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="mmpo-activity-section">
            <h2><?php esc_html_e('Recent Activity', 'multi-merchant-payment-orchestrator'); ?></h2>
            <?php if (empty($recent_sales)): ?>
                <p><?php esc_html_e('No recent activity.', 'multi-merchant-payment-orchestrator'); ?></p>
            <?php else: ?>
                <table class="mmpo-activity-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Merchant', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Product', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Amount', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Status', 'multi-merchant-payment-orchestrator'); ?></th>
                            <th><?php esc_html_e('Synced', 'multi-merchant-payment-orchestrator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($sale->created_at)); ?></td>
                                <td><?php echo esc_html($sale->merchant_name); ?></td>
                                <td><?php echo esc_html($sale->product_name); ?></td>
                                <td>$<?php echo number_format($sale->amount, 2); ?></td>
                                <td><span class="status-<?php echo esc_attr($sale->status); ?>"><?php echo esc_html(ucfirst($sale->status)); ?></span></td>
                                <td><?php echo $sale->synced_to_merchant ? '✓' : '✗'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="mmpo-actions-section">
            <h2><?php esc_html_e('Quick Actions', 'multi-merchant-payment-orchestrator'); ?></h2>
            <div class="mmpo-quick-actions">
                <button id="sync-all-sales" class="button button-primary"><?php esc_html_e('Sync All Sales', 'multi-merchant-payment-orchestrator'); ?></button>
                <button id="test-all-connections" class="button"><?php esc_html_e('Test All Connections', 'multi-merchant-payment-orchestrator'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=mmpo-credentials'); ?>" class="button"><?php esc_html_e('Manage Credentials', 'multi-merchant-payment-orchestrator'); ?></a>
            </div>
        </div>
    </div>
    
    <style>
    .mmpo-admin-dashboard { max-width: 1200px; }
    .mmpo-stats-section, .mmpo-activity-section, .mmpo-actions-section { 
        background: #fff; 
        border: 1px solid #ccd0d4; 
        border-radius: 8px; 
        padding: 20px; 
        margin-bottom: 20px; 
    }
    .mmpo-admin-stats { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
        gap: 20px; 
        margin-top: 15px; 
    }
    .mmpo-stat-box { 
        background: #f8f9fa; 
        border: 1px solid #e9ecef; 
        border-radius: 6px; 
        padding: 20px; 
        text-align: center; 
    }
    .mmpo-stat-box h3 { 
        margin: 0 0 10px 0; 
        color: #666; 
        font-size: 14px; 
        font-weight: normal; 
    }
    .stat-value { 
        font-size: 28px; 
        font-weight: bold; 
        color: #2271b1; 
        display: block; 
    }
    .mmpo-quick-actions { 
        display: flex; 
        gap: 10px; 
        flex-wrap: wrap; 
    }
    .mmpo-activity-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 15px; 
    }
    .mmpo-activity-table th, 
    .mmpo-activity-table td { 
        padding: 12px; 
        text-align: left; 
        border-bottom: 1px solid #ddd; 
    }
    .mmpo-activity-table th { 
        background: #f8f9fa; 
        font-weight: bold; 
    }
    .status-completed { color: #00a32a; }
    .status-pending { color: #dba617; }
    .status-failed { color: #d63638; }
    .status-refunded { color: #72aee6; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#sync-all-sales').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'multi-merchant-payment-orchestrator')); ?>');
            
            $.post(ajaxurl, {
                action: 'mmpo_sync_sales',
                nonce: '<?php echo wp_create_nonce('mmpo_ajax_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Sales synced successfully!', 'multi-merchant-payment-orchestrator')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Sync failed:', 'multi-merchant-payment-orchestrator')); ?> ' + response.data);
                }
                button.prop('disabled', false).text('<?php echo esc_js(__('Sync All Sales', 'multi-merchant-payment-orchestrator')); ?>');
            });
        });
        
        $('#test-all-connections').on('click', function() {
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
                button.prop('disabled', false).text('<?php echo esc_js(__('Test All Connections', 'multi-merchant-payment-orchestrator')); ?>');
            });
        });
    });
    </script>
</div>