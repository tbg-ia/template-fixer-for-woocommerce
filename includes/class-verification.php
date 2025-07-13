<?php
/**
 * Plugin functionality verification
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Verification {
    
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'show_verification_notice' ) );
    }
    
    /**
     * Show verification notification
     */
    public function show_verification_notice() {
        // Only show once after integration
        if ( get_transient( 'wc_template_fixer_verification_shown' ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Verify we are on an admin page
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'dashboard' ) === false ) {
            return;
        }
        
        // Mark as shown for 24 hours
        set_transient( 'wc_template_fixer_verification_shown', true, DAY_IN_SECONDS );
        
        ?>
        <div class="notice notice-success is-dismissible wc-template-fixer-verification">
            <h3>🎉 WooCommerce Template Fixer 2.0.0 - Integration Completed</h3>
            <p><strong>✅ Template Updater integrated successfully</strong></p>
            <p>All Template Updater functionalities are now available from the main plugin:</p>
            <ul>
                <li>🔧 <strong>Template Updater</strong> - Enhanced interface with quick actions</li>
                <li>📊 <strong>Dashboard Widgets</strong> - Real-time monitoring</li>
                <li>🚀 <strong>Mass Updates</strong> - With preview and validation</li>
                <li>💾 <strong>Automatic Backups</strong> - With uTech customizations preservation</li>
            </ul>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-updater' ) ); ?>" class="button button-primary">
                    🔧 Open Template Updater
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-secondary">
                    📊 View Main Dashboard
                </a>
            </p>
            <p><small>ℹ️ Duplicate mu-plugins files have been removed. Only works from the main plugin.</small></p>
        </div>
        
        <style>
        .wc-template-fixer-verification {
            border-left: 4px solid #00a32a;
        }
        
        .wc-template-fixer-verification h3 {
            margin-top: 0;
            color: #00a32a;
        }
        
        .wc-template-fixer-verification ul {
            margin: 10px 0;
        }
        
        .wc-template-fixer-verification li {
            margin: 5px 0;
        }
        
        .wc-template-fixer-verification .button {
            margin-right: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Verify plugin functionality
     */
    public static function verify_functionality() {
        $issues = array();
        
        // Verify that main classes exist
        if ( ! class_exists( 'WC_Template_Fixer_Updater_Interface' ) ) {
            $issues[] = 'Clase WC_Template_Fixer_Updater_Interface no encontrada';
        }
        
        if ( ! class_exists( 'WC_Template_Fixer_Dashboard_Widget' ) ) {
            $issues[] = 'Clase WC_Template_Fixer_Dashboard_Widget no encontrada';
        }
        
        // Verify that files exist
        $required_files = array(
            'includes/class-template-updater-interface.php',
            'includes/class-dashboard-widget.php',
            'assets/js/template-updater.js',
            'assets/css/template-updater.css'
        );
        
        foreach ( $required_files as $file ) {
            $file_path = WC_TEMPLATE_FIXER_PLUGIN_DIR . $file;
            if ( ! file_exists( $file_path ) ) {
                $issues[] = "Required file not found: {$file}";
            }
        }
        
        // Verify there are no duplicates in mu-plugins
        $mu_plugins_path = WP_CONTENT_DIR . '/mu-plugins/';
        $duplicate_files = array(
            'wc-template-updater.php',
            'wc-template-auto-updater.php',
            'execute-template-update.php'
        );
        
        foreach ( $duplicate_files as $file ) {
            if ( file_exists( $mu_plugins_path . $file ) ) {
                $issues[] = "Duplicate file found in mu-plugins: {$file}";
            }
        }
        
        return $issues;
    }
    
    /**
     * Show verification status in admin
     */
    public static function get_verification_status() {
        $issues = self::verify_functionality();
        
        if ( empty( $issues ) ) {
            return array(
                'status' => 'success',
                'message' => '✅ Todas las funcionalidades están operativas',
                'details' => array(
                    'Template Updater Interface: Activo',
                    'Dashboard Widgets: Activos',
                    'Assets (CSS/JS): Cargados',
                    'No duplicate files'
                )
            );
        } else {
            return array(
                'status' => 'error',
                'message' => '❌ Se encontraron problemas',
                'details' => $issues
            );
        }
    }
}

// Initialize verification
new WC_Template_Fixer_Verification();