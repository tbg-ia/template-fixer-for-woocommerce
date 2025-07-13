<?php
/**
 * Notification System for WooCommerce Template Fixer
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Notification_System {
    
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_notification_scripts' ) );
        add_action( 'wp_ajax_wc_template_fixer_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
    }
    
    /**
     * Show admin notifications
     */
    public function show_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Check if there are outdated templates
        $this->check_outdated_templates_notice();
        
        // Show notification after update
        $this->show_update_success_notice();
        
        // Show security warnings
        $this->show_security_warnings();
    }
    
    /**
     * Check and show outdated templates notification
     */
    private function check_outdated_templates_notice() {
        $dismissed = get_transient( 'wc_template_fixer_outdated_dismissed' );
        
        if ( $dismissed ) {
            return;
        }
        
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated = $scanner->scan_outdated_templates();
        
        if ( empty( $outdated ) ) {
            return;
        }
        
        $critical_count = 0;
        $high_risk_count = 0;
        
        foreach ( $outdated as $template ) {
            if ( $template['risk_level'] === 'critical' ) {
                $critical_count++;
            } elseif ( $template['risk_level'] === 'high' ) {
                $high_risk_count++;
            }
        }
        
        $notice_class = 'notice-warning';
        $notice_icon = '⚠️';
        
        if ( $critical_count > 0 ) {
            $notice_class = 'notice-error';
            $notice_icon = '🚨';
        }
        
        ?>
        <div class="notice <?php echo esc_attr( $notice_class ); ?> wc-template-fixer-notice" data-notice="outdated">
            <p>
                <strong><?php echo esc_html( $notice_icon ); ?> WooCommerce Template Fixer:</strong>
                Detected <strong><?php echo count( $outdated ); ?> outdated templates</strong> in your theme.
                <?php if ( $critical_count > 0 ) : ?>
                    <span style="color: #d63638;">
                        <strong><?php echo esc_html( $critical_count ); ?> are critical</strong> and require immediate update.
                    </span>
                <?php endif; ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-primary">
                    Review Templates
                </a>
                <button type="button" class="button button-secondary dismiss-notice" data-notice="outdated">
                    Remind later
                </button>
            </p>
        </div>
        <?php
    }
    
    /**
     * Show success notification after update
     */
    private function show_update_success_notice() {
        $success_data = get_transient( 'wc_template_fixer_update_success' );
        
        if ( ! $success_data ) {
            return;
        }
        
        delete_transient( 'wc_template_fixer_update_success' );
        
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>✅ Templates updated successfully:</strong>
                Updated <strong><?php echo esc_html( $success_data['count'] ); ?> templates</strong>.
                <?php if ( ! empty( $success_data['backups'] ) ) : ?>
                    Backups have been saved automatically.
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Show security warnings
     */
    private function show_security_warnings() {
        // Check if there are templates with known vulnerabilities
        $this->check_security_vulnerabilities();
        
        // Check backup configuration
        $this->check_backup_configuration();
    }
    
    /**
     * Check known security vulnerabilities
     */
    private function check_security_vulnerabilities() {
        $dismissed = get_transient( 'wc_template_fixer_security_dismissed' );
        
        if ( $dismissed ) {
            return;
        }
        
        $vulnerable_templates = $this->get_known_vulnerable_templates();
        $scanner = new WC_Template_Fixer_Scanner();
        $theme_templates = $this->get_theme_template_versions();
        
        $found_vulnerabilities = array();
        
        foreach ( $vulnerable_templates as $template => $vulnerable_versions ) {
            if ( isset( $theme_templates[$template] ) ) {
                $theme_version = $theme_templates[$template];
                
                foreach ( $vulnerable_versions as $vuln_version ) {
                    if ( version_compare( $theme_version, $vuln_version['fixed_in'], '<' ) ) {
                        $found_vulnerabilities[] = array(
                            'template' => $template,
                            'current_version' => $theme_version,
                            'vulnerability' => $vuln_version,
                        );
                    }
                }
            }
        }
        
        if ( ! empty( $found_vulnerabilities ) ) {
            ?>
            <div class="notice notice-error wc-template-fixer-notice" data-notice="security">
                <p>
                    <strong>🚨 Security Alert:</strong>
                    Detected <strong><?php echo count( $found_vulnerabilities ); ?> templates with known vulnerabilities</strong>.
                </p>
                <ul>
                    <?php foreach ( $found_vulnerabilities as $vuln ) : ?>
                    <li>
                        <strong><?php echo esc_html( $vuln['template'] ); ?></strong> 
                        (v<?php echo esc_html( $vuln['current_version'] ); ?>) - 
                        <?php echo esc_html( $vuln['vulnerability']['description'] ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-primary">
                        Update Immediately
                    </a>
                    <button type="button" class="button button-secondary dismiss-notice" data-notice="security">
                        I know
                    </button>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Check backup configuration
     */
    private function check_backup_configuration() {
        if ( ! wc_template_fixer_get_option( 'enable_daily_scan', true ) ) {
            $dismissed = get_transient( 'wc_template_fixer_backup_config_dismissed' );
            
            if ( ! $dismissed ) {
                ?>
                <div class="notice notice-info wc-template-fixer-notice" data-notice="backup_config">
                    <p>
                        <strong>💡 Recommendation:</strong>
                        Enable automatic daily scanning to proactively detect outdated templates.
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer-settings' ) ); ?>" class="button button-secondary">
                            Configure Now
                        </a>
                        <button type="button" class="button button-link dismiss-notice" data-notice="backup_config">
                            Dismiss
                        </button>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Add element to admin bar
     */
    public function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated = $scanner->scan_outdated_templates();
        
        if ( empty( $outdated ) ) {
            return;
        }
        
        $critical_count = 0;
        foreach ( $outdated as $template ) {
            if ( $template['risk_level'] === 'critical' ) {
                $critical_count++;
            }
        }
        
        $icon = $critical_count > 0 ? '🚨' : '⚠️';
        $title = sprintf( '%s %d Templates', $icon, count( $outdated ) );
        
        $wp_admin_bar->add_node( array(
            'id' => 'wc-template-fixer',
            'title' => $title,
            'href' => esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ),
            'meta' => array(
                'title' => 'Outdated WooCommerce templates detected'
            )
        ) );
        
        if ( $critical_count > 0 ) {
            $wp_admin_bar->add_node( array(
                'parent' => 'wc-template-fixer',
                'id' => 'wc-template-fixer-critical',
                'title' => sprintf( '🔥 %d Critical', $critical_count ),
                'href' => esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ),
            ) );
        }
        
        $wp_admin_bar->add_node( array(
            'parent' => 'wc-template-fixer',
            'id' => 'wc-template-fixer-scan',
            'title' => '🔍 Scan Now',
            'href' => esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ),
        ) );
    }
    
    /**
     * Add widget to dashboard
     */
    public function add_dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        wp_add_dashboard_widget(
            'wc_template_fixer_dashboard',
            '🔧 WooCommerce Template Fixer',
            array( $this, 'render_dashboard_widget' )
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $scanner = new WC_Template_Fixer_Scanner();
        $stats = $scanner->get_template_statistics();
        
        ?>
        <div class="wc-template-fixer-dashboard-widget">
            <div class="template-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo esc_html( $stats['total_theme_templates'] ); ?></span>
                    <span class="stat-label">Total Templates</span>
                </div>
                <div class="stat-item <?php echo $stats['outdated_templates'] > 0 ? 'warning' : 'success'; ?>">
                    <span class="stat-number"><?php echo esc_html( $stats['outdated_templates'] ); ?></span>
                    <span class="stat-label">Outdated</span>
                </div>
                <div class="stat-item <?php echo $stats['risk_levels']['critical'] > 0 ? 'critical' : 'success'; ?>">
                    <span class="stat-number"><?php echo esc_html( $stats['risk_levels']['critical'] ); ?></span>
                    <span class="stat-label">Critical</span>
                </div>
            </div>
            
            <?php if ( $stats['outdated_templates'] > 0 ) : ?>
            <div class="widget-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-primary">
                    Review Templates
                </a>
                <?php if ( $stats['safe_to_update'] > 0 ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-secondary">
                    Update Safe (<?php echo esc_html( $stats['safe_to_update'] ); ?>)
                </a>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div class="all-good">
                <p>✅ All templates are up to date</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-secondary">
                    View Details
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .wc-template-fixer-dashboard-widget .template-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .wc-template-fixer-dashboard-widget .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .wc-template-fixer-dashboard-widget .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .wc-template-fixer-dashboard-widget .stat-item.warning .stat-number {
            color: #dba617;
        }
        
        .wc-template-fixer-dashboard-widget .stat-item.critical .stat-number {
            color: #d63638;
        }
        
        .wc-template-fixer-dashboard-widget .stat-item.success .stat-number {
            color: #00a32a;
        }
        
        .wc-template-fixer-dashboard-widget .stat-label {
            display: block;
            font-size: 11px;
            color: #666;
        }
        
        .wc-template-fixer-dashboard-widget .widget-actions {
            text-align: center;
        }
        
        .wc-template-fixer-dashboard-widget .widget-actions .button {
            margin: 0 5px;
        }
        
        .wc-template-fixer-dashboard-widget .all-good {
            text-align: center;
            color: #00a32a;
        }
        </style>
        <?php
    }
    
    /**
     * Load scripts for notifications
     */
    public function enqueue_notification_scripts() {
        wp_enqueue_script(
            'wc-template-fixer-notifications',
            WC_TEMPLATE_FIXER_PLUGIN_URL . 'assets/js/notifications.js',
            array( 'jquery' ),
            WC_TEMPLATE_FIXER_VERSION,
            true
        );
        
        wp_localize_script( 'wc-template-fixer-notifications', 'wcTemplateFixerNotifications', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_template_fixer_notification_nonce' ),
        ) );
    }
    
    /**
     * AJAX: Dismiss notification
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'wc_template_fixer_notification_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $notice_type = sanitize_text_field( wp_unslash( $_POST['notice_type'] ?? '' ) );
        
        switch ( $notice_type ) {
            case 'outdated':
                set_transient( 'wc_template_fixer_outdated_dismissed', true, WEEK_IN_SECONDS );
                break;
            case 'security':
                set_transient( 'wc_template_fixer_security_dismissed', true, DAY_IN_SECONDS );
                break;
            case 'backup_config':
                set_transient( 'wc_template_fixer_backup_config_dismissed', true, MONTH_IN_SECONDS );
                break;
        }
        
        wp_send_json_success();
    }
    
    /**
     * Get known vulnerable templates
     */
    private function get_known_vulnerable_templates() {
        return array(
            'checkout/form-checkout.php' => array(
                array(
                    'fixed_in' => '7.8.0',
                    'description' => 'XSS vulnerability in checkout form',
                    'severity' => 'high'
                ),
                array(
                    'fixed_in' => '8.2.0',
                    'description' => 'Information leak in user data',
                    'severity' => 'medium'
                )
            ),
            'myaccount/form-login.php' => array(
                array(
                    'fixed_in' => '8.0.0',
                    'description' => 'SQL injection vulnerability in login',
                    'severity' => 'critical'
                )
            ),
            'cart/cart.php' => array(
                array(
                    'fixed_in' => '7.5.0',
                    'description' => 'Price manipulation in cart',
                    'severity' => 'high'
                )
            )
        );
    }
    
    /**
     * Get theme template versions
     */
    private function get_theme_template_versions() {
        $versions = array();
        $theme_wc_dir = get_stylesheet_directory() . '/woocommerce/';
        
        if ( ! is_dir( $theme_wc_dir ) ) {
            return $versions;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_wc_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->getExtension() === 'php' ) {
                $relative_path = str_replace( $theme_wc_dir, '', $file->getPathname() );
                $relative_path = str_replace( '\\', '/', $relative_path );
                
                $content = file_get_contents( $file->getPathname() );
                if ( preg_match( '/@version\s+(\d+\.\d+\.\d+)/', $content, $matches ) ) {
                    $versions[$relative_path] = $matches[1];
                }
            }
        }
        
        return $versions;
    }
    
    /**
     * Schedule follow-up notification
     */
    public function schedule_follow_up_notification( $template_names, $delay_hours = 24 ) {
        $data = array(
            'templates' => $template_names,
            'scheduled_at' => time(),
            'remind_at' => time() + ( $delay_hours * HOUR_IN_SECONDS )
        );
        
        set_transient( 'wc_template_fixer_follow_up', $data, $delay_hours * HOUR_IN_SECONDS );
    }
    
    /**
     * Send push notification (if configured)
     */
    public function send_push_notification( $title, $message, $url = '' ) {
        // Here you could integrate with services like Pusher, OneSignal, etc.
        // For now, we just log it
        
        wc_template_fixer_log(
            'push_notification',
            'notification',
            '',
            '',
            '',
            'success',
            sprintf( 'Push notification: %s - %s', $title, $message )
        );
    }
}