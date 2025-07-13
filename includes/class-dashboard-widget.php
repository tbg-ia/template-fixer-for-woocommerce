<?php
/**
 * Dashboard Widget for WooCommerce Template Fixer
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Dashboard_Widget {
    
    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
        add_action( 'wp_ajax_wc_template_fixer_widget_stats', array( $this, 'ajax_widget_stats' ) );
    }
    
    /**
     * Add widgets to dashboard
     */
    public function add_dashboard_widgets() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        wp_add_dashboard_widget(
            'wc_template_fixer_main_widget',
            '🔧 WooCommerce Template Fixer',
            array( $this, 'render_main_widget' ),
            null,
            null,
            'normal',
            'high'
        );
        
        wp_add_dashboard_widget(
            'wc_template_fixer_quick_actions',
            '⚡ Template Quick Actions',
            array( $this, 'render_quick_actions_widget' ),
            null,
            null,
            'side',
            'high'
        );
    }
    
    /**
     * Render main widget
     */
    public function render_main_widget() {
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated_templates = $scanner->scan_outdated_templates();
        
        $stats = array(
            'total' => count( $outdated_templates ),
            'critical' => 0,
            'medium' => 0,
            'low' => 0
        );
        
        foreach ( $outdated_templates as $template ) {
            switch ( $template['risk_level'] ) {
                case 'critical':
                case 'high':
                    $stats['critical']++;
                    break;
                case 'medium':
                    $stats['medium']++;
                    break;
                default:
                    $stats['low']++;
                    break;
            }
        }
        
        ?>
        <div class="wc-template-fixer-dashboard-widget">
            <div class="template-stats-grid">
                <div class="stat-card <?php echo $stats['critical'] > 0 ? 'critical' : 'good'; ?>">
                    <div class="stat-number"><?php echo esc_html( $stats['critical'] ); ?></div>
                    <div class="stat-label">Critical</div>
                </div>
                <div class="stat-card medium">
                    <div class="stat-number"><?php echo esc_html( $stats['medium'] ); ?></div>
                    <div class="stat-label">Medium</div>
                </div>
                <div class="stat-card low">
                    <div class="stat-number"><?php echo esc_html( $stats['low'] ); ?></div>
                    <div class="stat-label">Low</div>
                </div>
                <div class="stat-card total">
                    <div class="stat-number"><?php echo esc_html( $stats['total'] ); ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            
            <?php if ( $stats['total'] > 0 ) : ?>
                <div class="template-status-alert">
                    <?php if ( $stats['critical'] > 0 ) : ?>
                        <div class="alert critical">
                            <strong>🚨 Urgent Attention:</strong> 
                            <?php echo esc_html( $stats['critical'] ); ?> critical template(s) need immediate update.
                        </div>
                    <?php elseif ( $stats['medium'] > 0 ) : ?>
                        <div class="alert medium">
                            <strong>⚠️ Attention:</strong> 
                            <?php echo esc_html( $stats['medium'] ); ?> template(s) need updating.
                        </div>
                    <?php else : ?>
                        <div class="alert low">
                            <strong>ℹ️ Info:</strong> 
                            <?php echo esc_html( $stats['low'] ); ?> template(s) with minor updates available.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="template-actions">
                    <?php if ( $stats['critical'] > 0 ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-updater&action=critical' ) ); ?>" 
                           class="button button-primary button-large">
                            🔥 Update Critical
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-updater' ) ); ?>" 
                       class="button button-secondary">
                        🔧 Template Updater
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" 
                       class="button button-secondary">
                        📊 View Details
                    </a>
                </div>
            <?php else : ?>
                <div class="template-status-good">
                    <div class="good-icon">✅</div>
                    <h3>Excellent!</h3>
                    <p>All WooCommerce templates are up to date.</p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" 
                       class="button button-secondary">
                        📊 View Complete Status
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="widget-footer">
                <small>
                    🕐 Last scan: <?php echo esc_html( gmdate( 'H:i' ) ); ?> | 
                    <a href="#" onclick="wcTemplateFixer.refreshWidget(); return false;">🔄 Refresh</a> |
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer-settings' ) ); ?>">⚙️ Settings</a>
                </small>
            </div>
        </div>
        
        <style>
        .wc-template-fixer-dashboard-widget {
            padding: 10px 0;
        }
        
        .template-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px 10px;
            border-radius: 6px;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
        }
        
        .stat-card.critical { border-left-color: #d63638; background: #fff0f0; }
        .stat-card.medium { border-left-color: #ff8c00; background: #fff8f0; }
        .stat-card.low { border-left-color: #0073aa; background: #f0f9ff; }
        .stat-card.good { border-left-color: #00a32a; background: #f0f9f0; }
        .stat-card.total { border-left-color: #666; background: #f5f5f5; }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-card.critical .stat-number { color: #d63638; }
        .stat-card.medium .stat-number { color: #ff8c00; }
        .stat-card.low .stat-number { color: #0073aa; }
        .stat-card.good .stat-number { color: #00a32a; }
        .stat-card.total .stat-number { color: #666; }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .template-status-alert {
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid;
        }
        
        .alert.critical {
            background: #fff0f0;
            border-left-color: #d63638;
            color: #721c24;
        }
        
        .alert.medium {
            background: #fff8f0;
            border-left-color: #ff8c00;
            color: #8a4b08;
        }
        
        .alert.low {
            background: #f0f9ff;
            border-left-color: #0073aa;
            color: #1d2327;
        }
        
        .template-actions {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .template-actions .button {
            margin: 2px;
            min-width: 120px;
        }
        
        .template-status-good {
            text-align: center;
            padding: 20px 10px;
            background: #f0f9f0;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .good-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .template-status-good h3 {
            margin: 0 0 10px 0;
            color: #00a32a;
        }
        
        .template-status-good p {
            margin: 0 0 15px 0;
            color: #666;
        }
        
        .widget-footer {
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .widget-footer a {
            text-decoration: none;
            color: #0073aa;
        }
        
        .widget-footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 782px) {
            .template-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .template-actions .button {
                display: block;
                margin: 5px 0;
                width: 100%;
            }
        }
        </style>
        
        <script>
        window.wcTemplateFixer = window.wcTemplateFixer || {};
        wcTemplateFixer.refreshWidget = function() {
            jQuery('#wc_template_fixer_main_widget .inside').html('<p>🔄 Updating...</p>');
            location.reload();
        };
        </script>
        <?php
    }
    
    /**
     * Render quick actions widget
     */
    public function render_quick_actions_widget() {
        ?>
        <div class="wc-template-quick-actions">
            <div class="quick-action-item">
                <h4>🔍 Scan Templates</h4>
                <p>Search for outdated templates</p>
                <button class="button button-secondary button-small" onclick="wcTemplateFixer.quickScan()">
                    Scan Now
                </button>
            </div>
            
            <div class="quick-action-item">
                <h4>🔥 Update Critical</h4>
                <p>Only high priority templates</p>
                <button class="button button-primary button-small" onclick="wcTemplateFixer.updateCritical()">
                    Update Critical
                </button>
            </div>
            
            <div class="quick-action-item">
                <h4>💾 Create Backup</h4>
                <p>Backup current templates</p>
                <button class="button button-secondary button-small" onclick="wcTemplateFixer.createBackup()">
                    Create Backup
                </button>
            </div>
            
            <div class="quick-action-item">
                <h4>📊 View Logs</h4>
                <p>Update history</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer-logs' ) ); ?>" 
                   class="button button-secondary button-small">
                    View Logs
                </a>
            </div>
        </div>
        
        <style>
        .wc-template-quick-actions {
            padding: 5px 0;
        }
        
        .quick-action-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .quick-action-item:last-child {
            border-bottom: none;
        }
        
        .quick-action-item h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }
        
        .quick-action-item p {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #666;
        }
        
        .quick-action-item .button {
            width: 100%;
        }
        </style>
        
        <script>
        wcTemplateFixer.quickScan = function() {
            if (confirm('Scan WooCommerce templates now?')) {
                window.open('<?php echo esc_js( admin_url( "admin.php?page=wc-template-fixer&action=scan" ) ); ?>', '_blank');
            }
        };
        
        wcTemplateFixer.updateCritical = function() {
            if (confirm('Update only critical templates?\n\nAutomatic backups will be created.')) {
                window.open('<?php echo esc_js( admin_url( "admin.php?page=wc-template-updater&action=critical" ) ); ?>', '_blank');
            }
        };
        
        wcTemplateFixer.createBackup = function() {
            if (confirm('Create backup of all current templates?')) {
                jQuery.post(ajaxurl, {
                    action: 'wc_template_fixer_backup',
                    nonce: '<?php echo esc_js( wp_create_nonce( "wc_template_fixer_nonce" ) ); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('✅ Backup created successfully');
                    } else {
                        alert('❌ Error creating backup: ' + response.data);
                    }
                });
            }
        };
        </script>
        <?php
    }
    
    /**
     * AJAX for widget statistics
     */
    public function ajax_widget_stats() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated_templates = $scanner->scan_outdated_templates();
        
        $stats = array(
            'total' => count( $outdated_templates ),
            'critical' => 0,
            'medium' => 0,
            'low' => 0
        );
        
        foreach ( $outdated_templates as $template ) {
            switch ( $template['risk_level'] ) {
                case 'critical':
                case 'high':
                    $stats['critical']++;
                    break;
                case 'medium':
                    $stats['medium']++;
                    break;
                default:
                    $stats['low']++;
                    break;
            }
        }
        
        wp_send_json_success( $stats );
    }
}