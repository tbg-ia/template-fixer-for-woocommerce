<?php
/**
 * WooCommerce Status Page Integration
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Status_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WooCommerce status page
        add_action( 'admin_init', array( $this, 'init_hooks' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_status_scripts' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_wc_template_fixer_quick_scan', array( $this, 'ajax_quick_scan' ) );
        add_action( 'wp_ajax_wc_template_fixer_quick_fix', array( $this, 'ajax_quick_fix' ) );
        
        // Auto-scan when visiting status page
        add_action( 'load-woocommerce_page_wc-status', array( $this, 'auto_scan_on_status_page' ) );
        
        // Add custom notice after template section  
        add_action( 'woocommerce_admin_status_template_overrides', array( $this, 'add_template_fixer_button' ), 20 );
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Check if we're on WooCommerce status page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-status' ) {
            add_filter( 'woocommerce_debug_tools', array( $this, 'add_debug_tool' ) );
        }
    }
    
    /**
     * Auto scan when visiting status page
     */
    public function auto_scan_on_status_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        
        // Check if auto-scan is enabled
        $auto_scan = wc_template_fixer_get_option( 'auto_scan_status_page', true );
        if ( ! $auto_scan ) {
            return;
        }
        
        // Perform scan in background
        $this->perform_background_scan();
    }
    
    /**
     * Perform background scan
     */
    private function perform_background_scan() {
        // Get last scan time
        $last_scan = get_transient( 'wc_template_fixer_last_status_scan' );
        
        // Only scan once per hour
        if ( $last_scan && ( time() - $last_scan ) < HOUR_IN_SECONDS ) {
            return;
        }
        
        // Perform scan
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated = $scanner->scan_outdated_templates();
        
        // Store results
        set_transient( 'wc_template_fixer_status_scan_results', $outdated, HOUR_IN_SECONDS );
        set_transient( 'wc_template_fixer_last_status_scan', time(), HOUR_IN_SECONDS );
        
        // If outdated templates found, set admin notice
        if ( ! empty( $outdated ) ) {
            set_transient( 'wc_template_fixer_has_outdated', count( $outdated ), DAY_IN_SECONDS );
        }
    }
    
    /**
     * Add template fixer button to template section
     */
    public function add_template_fixer_button() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Find the templates table
            var $templateTable = $('table.wc_status_table').filter(function() {
                return $(this).find('th:contains("Template")').length > 0;
            });
            
            if ($templateTable.length > 0) {
                // Check if there are any outdated templates
                var hasOutdated = $templateTable.find('mark.error').length > 0;
                
                if (hasOutdated) {
                    // Add our button after the table
                    var buttonHtml = '<div class="wc-template-fixer-status-actions" style="margin: 20px 0;">';
                    buttonHtml += '<h3>🔧 WooCommerce Template Fixer</h3>';
                    buttonHtml += '<p>Outdated templates detected. Use Template Fixer to update them automatically while preserving customizations.</p>';
                    buttonHtml += '<p>';
                    buttonHtml += '<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-primary">';
                    buttonHtml += '<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> ';
                    buttonHtml += 'Fix Outdated Templates';
                    buttonHtml += '</a> ';
                    buttonHtml += '<button class="button wc-template-fixer-quick-scan">';
                    buttonHtml += '<span class="dashicons dashicons-search" style="margin-top: 3px;"></span> ';
                    buttonHtml += 'Scan with Template Fixer';
                    buttonHtml += '</button>';
                    buttonHtml += '</p>';
                    buttonHtml += '<div class="wc-template-fixer-scan-results" style="display:none;"></div>';
                    buttonHtml += '</div>';
                    
                    $templateTable.after(buttonHtml);
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Add template fixer notice to status page
     */
    public function add_template_fixer_notice() {
        $outdated = get_transient( 'wc_template_fixer_status_scan_results' );
        
        if ( empty( $outdated ) ) {
            return;
        }
        
        $critical_count = 0;
        $high_count = 0;
        
        foreach ( $outdated as $template ) {
            if ( $template['risk_level'] === 'critical' ) {
                $critical_count++;
            } elseif ( $template['risk_level'] === 'high' ) {
                $high_count++;
            }
        }
        ?>
        <div class="wc-template-fixer-status-notice" style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
            <h3 style="margin-top: 0;">
                <span class="dashicons dashicons-warning" style="color: #f39c12;"></span>
                <?php 
                echo sprintf( 
                    /* translators: %d is the number of outdated templates detected */
                    esc_html__( 'Template Fixer: %d outdated templates detected', 'template-fixer-for-woocommerce' ), 
                    count( $outdated ) 
                ); ?>
            </h3>
            
            <?php if ( $critical_count > 0 || $high_count > 0 ) : ?>
                <p style="margin: 10px 0;">
                    <strong><?php esc_html_e( 'Risk Summary:', 'template-fixer-for-woocommerce' ); ?></strong>
                    <?php if ( $critical_count > 0 ) : ?>
                        <span style="color: #e74c3c;">
                            <?php 
                            echo sprintf( 
                                /* translators: %d is the number of critical templates */
                                esc_html__( '%d Critical', 'template-fixer-for-woocommerce' ), 
                                esc_html( $critical_count ) 
                            ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $high_count > 0 ) : ?>
                        <span style="color: #f39c12; margin-left: 10px;">
                            <?php 
                            echo sprintf( 
                                /* translators: %d is the number of high-risk templates */
                                esc_html__( '%d High', 'template-fixer-for-woocommerce' ), 
                                esc_html( $high_count ) 
                            ); ?>
                        </span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            
            <p>
                <button class="button button-primary wc-template-fixer-quick-fix" data-action="fix-all">
                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Fix All Templates', 'template-fixer-for-woocommerce' ); ?>
                </button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button">
                    <?php esc_html_e( 'View Details', 'template-fixer-for-woocommerce' ); ?>
                </a>
                <button class="button wc-template-fixer-rescan" style="float: right;">
                    <span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
                    <?php esc_html_e( 'Rescan', 'template-fixer-for-woocommerce' ); ?>
                </button>
            </p>
            
            <div class="wc-template-fixer-progress" style="display: none; margin-top: 10px;">
                <div class="progress-bar" style="width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;">
                    <div class="progress-fill" style="width: 0%; height: 100%; background: #2ecc71; transition: width 0.3s;"></div>
                </div>
                <p class="progress-text" style="margin-top: 5px; text-align: center;"></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts for status page
     */
    public function enqueue_status_scripts( $hook ) {
        if ( 'woocommerce_page_wc-status' !== $hook ) {
            return;
        }
        
        wp_enqueue_script( 
            'wc-template-fixer-status', 
            WC_TEMPLATE_FIXER_PLUGIN_URL . 'assets/js/status-integration.js', 
            array( 'jquery' ), 
            WC_TEMPLATE_FIXER_VERSION, 
            true 
        );
        
        wp_localize_script( 'wc-template-fixer-status', 'wcTemplateFixer', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce( 'wc_template_fixer_status' ),
            'strings' => array(
                'scanning' => __( 'Scanning templates...', 'template-fixer-for-woocommerce' ),
                'fixing' => __( 'Fixing templates...', 'template-fixer-for-woocommerce' ),
                'complete' => __( 'Complete!', 'template-fixer-for-woocommerce' ),
                'error' => __( 'An error occurred. Please try again.', 'template-fixer-for-woocommerce' ),
                'confirm_fix' => __( 'This will update all outdated templates. Backups will be created automatically. Continue?', 'template-fixer-for-woocommerce' ),
            )
        ) );
    }
    
    /**
     * AJAX handler for quick scan
     */
    public function ajax_quick_scan() {
        check_ajax_referer( 'wc_template_fixer_status', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( -1 );
        }
        
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated = $scanner->clear_cache_and_rescan();
        
        // Update transients
        set_transient( 'wc_template_fixer_status_scan_results', $outdated, HOUR_IN_SECONDS );
        set_transient( 'wc_template_fixer_last_status_scan', time(), HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'count' => count( $outdated ),
            'templates' => $outdated,
            'message' => sprintf( 
                /* translators: %d is the number of outdated templates found */
                __( 'Found %d outdated templates', 'template-fixer-for-woocommerce' ), 
                count( $outdated ) 
            )
        ) );
    }
    
    /**
     * AJAX handler for quick fix
     */
    public function ajax_quick_fix() {
        check_ajax_referer( 'wc_template_fixer_status', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( -1 );
        }
        
        $outdated = get_transient( 'wc_template_fixer_status_scan_results' );
        
        if ( empty( $outdated ) ) {
            wp_send_json_error( array(
                'message' => __( 'No outdated templates found. Please scan first.', 'template-fixer-for-woocommerce' )
            ) );
        }
        
        $updater = new WC_Template_Fixer_Updater();
        $template_names = wp_list_pluck( $outdated, 'name' );
        
        $results = $updater->update_multiple_templates( $template_names, true );
        
        // Clear scan cache after update
        delete_transient( 'wc_template_fixer_status_scan_results' );
        delete_transient( 'wc_template_fixer_has_outdated' );
        
        wp_send_json_success( array(
            'success_count' => $results['success_count'],
            'error_count' => $results['error_count'],
            'total' => $results['total_processed'],
            'message' => sprintf( 
                /* translators: %1$d is the number of successfully updated templates, %2$d is the total number of templates processed */
                __( 'Updated %1$d of %2$d templates successfully', 'template-fixer-for-woocommerce' ),
                $results['success_count'],
                $results['total_processed']
            ),
            'details' => $results['results']
        ) );
    }
    
    /**
     * Add debug tool to WooCommerce
     */
    public function add_debug_tool( $tools ) {
        $tools['template_fixer_scan'] = array(
            'name' => __( 'Scan WooCommerce Templates', 'template-fixer-for-woocommerce' ),
            'button' => __( 'Scan Templates', 'template-fixer-for-woocommerce' ),
            'desc' => __( 'Scan for outdated WooCommerce templates in your theme.', 'template-fixer-for-woocommerce' ),
            'callback' => array( $this, 'debug_tool_scan_templates' ),
        );
        
        return $tools;
    }
    
    /**
     * Debug tool callback
     */
    public function debug_tool_scan_templates() {
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated = $scanner->clear_cache_and_rescan();
        
        if ( empty( $outdated ) ) {
            return __( 'No outdated templates found. Your theme templates are up to date!', 'template-fixer-for-woocommerce' );
        }
        
        $message = sprintf( 
            /* translators: %d is the number of outdated templates found */
            __( 'Found %d outdated templates. ', 'template-fixer-for-woocommerce' ), 
            count( $outdated ) 
        );
        
        $message .= '<a href="' . admin_url( 'admin.php?page=wc-template-fixer' ) . '">';
        $message .= __( 'View and fix them here', 'template-fixer-for-woocommerce' );
        $message .= '</a>';
        
        return $message;
    }
    
    /**
     * Check if theme has WooCommerce templates
     */
    public static function theme_has_woocommerce_templates() {
        $theme_dir = get_stylesheet_directory() . '/woocommerce/';
        return is_dir( $theme_dir );
    }
    
    /**
     * Get template status for admin bar
     */
    public static function get_template_status() {
        $outdated_count = get_transient( 'wc_template_fixer_has_outdated' );
        
        if ( false === $outdated_count ) {
            // Perform quick scan
            $scanner = new WC_Template_Fixer_Scanner();
            $outdated = $scanner->scan_outdated_templates();
            $outdated_count = count( $outdated );
            set_transient( 'wc_template_fixer_has_outdated', $outdated_count, HOUR_IN_SECONDS );
        }
        
        return $outdated_count;
    }
}

// Initialize the integration
new WC_Template_Fixer_Status_Integration();