<?php
/**
 * Plugin Name: Template Fixer for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/template-fixer-for-woocommerce/
 * Description: Automatically fixes outdated WooCommerce template files by updating them while preserving theme customizations and ensuring compatibility.
 * Version: 2.0.0
 * Author: KeepXDev
 * Author URI: https://profiles.wordpress.org/keepxdev/
 * Text Domain: template-fixer-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.9
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Woo: woocommerce_custom_order_tables_enabled:no
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check minimum PHP version
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Template Fixer for WooCommerce:</strong> ';
        echo 'This plugin requires PHP 7.4 or higher. You are running PHP ' . esc_html( PHP_VERSION );
        echo '</p></div>';
    });
    return;
}

// Define plugin constants
define( 'WC_TEMPLATE_FIXER_VERSION', '2.0.0' );
define( 'WC_TEMPLATE_FIXER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_TEMPLATE_FIXER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_TEMPLATE_FIXER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
if ( ! function_exists( 'wc_template_fixer_check_woocommerce' ) ) {
function wc_template_fixer_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__( 'WooCommerce Template Fixer:', 'template-fixer-for-woocommerce' ) . '</strong> ';
            echo esc_html__( 'This plugin requires WooCommerce to be installed and activated.', 'template-fixer-for-woocommerce' );
            echo '</p></div>';
        });
        return false;
    }
    return true;
}
}

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Load plugin textdomain
 */
function wc_template_fixer_load_textdomain() {
    load_plugin_textdomain( 'template-fixer-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wc_template_fixer_load_textdomain' );

/**
 * Initialize plugin
 */
if ( ! function_exists( 'wc_template_fixer_init' ) ) {
function wc_template_fixer_init() {
    if ( ! wc_template_fixer_check_woocommerce() ) {
        return;
    }

    // Check if files exist before requiring them
    $required_files = array(
        'includes/class-template-scanner.php',
        'includes/class-template-updater.php',
        'includes/class-backup-manager.php',
        'includes/class-admin-interface.php',
        'includes/class-notification-system.php',
        'includes/class-template-updater-interface.php',
        'includes/class-dashboard-widget.php',
        'includes/class-verification.php'
    );

    foreach ( $required_files as $file ) {
        $file_path = WC_TEMPLATE_FIXER_PLUGIN_DIR . $file;
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        } else {
            add_action( 'admin_notices', function() use ( $file ) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Template Fixer for WooCommerce:</strong> Missing required file: ' . esc_html( $file );
                echo '</p></div>';
            });
            return;
        }
    }

    // Initialize main classes
    if ( class_exists( 'WC_Template_Fixer_Admin_Interface' ) ) {
        new WC_Template_Fixer_Admin_Interface();
    }
    if ( class_exists( 'WC_Template_Fixer_Notification_System' ) ) {
        new WC_Template_Fixer_Notification_System();
    }
    if ( class_exists( 'WC_Template_Fixer_Dashboard_Widget' ) ) {
        new WC_Template_Fixer_Dashboard_Widget();
    }
    if ( class_exists( 'WC_Template_Fixer_Updater_Interface' ) ) {
        new WC_Template_Fixer_Updater_Interface();
    }
}
}
add_action( 'plugins_loaded', 'wc_template_fixer_init' );

/**
 * Plugin activation
 */
function wc_template_fixer_activate() {
    if ( ! wc_template_fixer_check_woocommerce() ) {
        wp_die( esc_html__( 'This plugin requires WooCommerce to be installed and activated.', 'template-fixer-for-woocommerce' ) );
    }
    
    // Create database table for logs
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    $table_name = $wpdb->prefix . 'wc_template_fixer_logs';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        template_name varchar(255) NOT NULL,
        action_type varchar(50) NOT NULL,
        old_version varchar(20),
        new_version varchar(20),
        backup_file varchar(255),
        status varchar(20) DEFAULT 'success',
        message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    dbDelta( $sql );
    
    // Set default options
    update_option( 'wc_template_fixer_db_version', '1.0' );
    
    // Schedule daily scan
    if ( ! wp_next_scheduled( 'wc_template_fixer_daily_scan' ) ) {
        wp_schedule_event( time(), 'daily', 'wc_template_fixer_daily_scan' );
    }
}
register_activation_hook( __FILE__, 'wc_template_fixer_activate' );

/**
 * Plugin deactivation
 */
function wc_template_fixer_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'wc_template_fixer_daily_scan' );
}
register_deactivation_hook( __FILE__, 'wc_template_fixer_deactivate' );

/**
 * Plugin uninstall
 */
if ( ! function_exists( 'wc_template_fixer_uninstall' ) ) {
function wc_template_fixer_uninstall() {
    global $wpdb;
    
    // Delete logs table (optional)
    if ( get_option( 'wc_template_fixer_remove_data_on_uninstall' ) ) {
        $table_name = $wpdb->prefix . 'wc_template_fixer_logs';
        $wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
        
        // Delete options
        delete_option( 'wc_template_fixer_db_version' );
        delete_option( 'wc_template_fixer_settings' );
        delete_option( 'wc_template_fixer_remove_data_on_uninstall' );
    }
}
}
register_uninstall_hook( __FILE__, 'wc_template_fixer_uninstall' );

/**
 * Add action links on plugins page
 */
function wc_template_fixer_plugin_action_links( $links ) {
    $action_links = array(
        'scanner' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ) . '">' . esc_html__( 'Scan Templates', 'template-fixer-for-woocommerce' ) . '</a>',
        'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-template-fixer-settings' ) ) . '">' . esc_html__( 'Settings', 'template-fixer-for-woocommerce' ) . '</a>',
    );
    
    return array_merge( $action_links, $links );
}
add_filter( 'plugin_action_links_' . WC_TEMPLATE_FIXER_BASENAME, 'wc_template_fixer_plugin_action_links' );

/**
 * Helper function to get plugin options
 */
if ( ! function_exists( 'wc_template_fixer_get_option' ) ) {
function wc_template_fixer_get_option( $key, $default = '' ) {
    $options = get_option( 'wc_template_fixer_settings', array() );
    return isset( $options[$key] ) ? $options[$key] : $default;
}
}

/**
 * Helper function to update plugin options
 */
if ( ! function_exists( 'wc_template_fixer_update_option' ) ) {
function wc_template_fixer_update_option( $key, $value ) {
    $options = get_option( 'wc_template_fixer_settings', array() );
    $options[$key] = $value;
    return update_option( 'wc_template_fixer_settings', $options );
}
}

/**
 * Helper function to log template actions
 */
if ( ! function_exists( 'wc_template_fixer_log' ) ) {
function wc_template_fixer_log( $template_name, $action_type, $old_version = '', $new_version = '', $backup_file = '', $status = 'success', $message = '' ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_template_fixer_logs';
    
    $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $table_name,
        array(
            'template_name' => $template_name,
            'action_type' => $action_type,
            'old_version' => $old_version,
            'new_version' => $new_version,
            'backup_file' => $backup_file,
            'status' => $status,
            'message' => $message,
            'created_at' => current_time( 'mysql' )
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
}
}

/**
 * Daily scan callback
 */
if ( ! function_exists( 'wc_template_fixer_daily_scan_callback' ) ) {
function wc_template_fixer_daily_scan_callback() {
    if ( ! class_exists( 'WC_Template_Fixer_Scanner' ) ) {
        return;
    }
    
    $scanner = new WC_Template_Fixer_Scanner();
    $outdated_templates = $scanner->scan_outdated_templates();
    
    if ( ! empty( $outdated_templates ) ) {
        wc_template_fixer_send_notification_email( $outdated_templates );
    }
}
}
add_action( 'wc_template_fixer_daily_scan', 'wc_template_fixer_daily_scan_callback' );

/**
 * Send notification email
 */
if ( ! function_exists( 'wc_template_fixer_send_notification_email' ) ) {
function wc_template_fixer_send_notification_email( $outdated_templates ) {
    $admin_email = get_option( 'admin_email' );
    $site_name = get_bloginfo( 'name' );
    
    $subject = sprintf( '[%s] WooCommerce Template Update Notification', $site_name );
    
    $message = "Hello,\n\n";
    $message .= sprintf( "Your website %s has %d outdated WooCommerce templates that need attention:\n\n", $site_name, count( $outdated_templates ) );
    
    foreach ( $outdated_templates as $template ) {
        $message .= sprintf( "- %s (Theme: v%s, Core: v%s)\n", $template['name'], $template['theme_version'], $template['core_version'] );
    }
    
    $message .= "\nPlease visit your WordPress admin panel to update these templates.\n\n";
    $message .= "Best regards,\nWooCommerce Template Fixer";
    
    wp_mail( $admin_email, $subject, $message );
}
}