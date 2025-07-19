<?php
/**
 * Diagnostic tools for Template Fixer
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Diagnostic {
    
    /**
     * Run full diagnostic
     */
    public static function run_diagnostic() {
        $report = array(
            'environment' => self::check_environment(),
            'theme' => self::check_theme(),
            'templates' => self::check_templates(),
            'permissions' => self::check_permissions(),
            'woocommerce' => self::check_woocommerce_status()
        );
        
        return $report;
    }
    
    /**
     * Check environment
     */
    private static function check_environment() {
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo( 'version' ),
            'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not detected',
            'memory_limit' => ini_get( 'memory_limit' ),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'theme' => get_stylesheet(),
            'is_child_theme' => is_child_theme(),
            'plugin_version' => WC_TEMPLATE_FIXER_VERSION
        );
    }
    
    /**
     * Check theme structure
     */
    private static function check_theme() {
        $theme_dir = get_stylesheet_directory();
        $wc_dir = $theme_dir . '/woocommerce/';
        
        $info = array(
            'theme_directory' => $theme_dir,
            'woocommerce_directory_exists' => is_dir( $wc_dir ),
            'woocommerce_directory_path' => $wc_dir,
            'is_writable' => wp_is_writable( $theme_dir ),
            'template_files' => array()
        );
        
        if ( is_dir( $wc_dir ) ) {
            $files = self::scan_directory( $wc_dir );
            $info['template_files'] = $files;
            $info['template_count'] = count( $files );
        }
        
        // Check parent theme if child theme
        if ( is_child_theme() ) {
            $parent_dir = get_template_directory();
            $parent_wc_dir = $parent_dir . '/woocommerce/';
            
            $info['parent_theme'] = array(
                'directory' => $parent_dir,
                'woocommerce_directory_exists' => is_dir( $parent_wc_dir ),
                'template_count' => is_dir( $parent_wc_dir ) ? count( self::scan_directory( $parent_wc_dir ) ) : 0
            );
        }
        
        return $info;
    }
    
    /**
     * Check templates
     */
    private static function check_templates() {
        $templates = array();
        
        // Method 1: Use our scanner
        $scanner = new WC_Template_Fixer_Scanner();
        $our_scan = $scanner->scan_outdated_templates();
        
        // Method 2: Use WooCommerce detection
        $wc_overrides = WC_Template_Fixer_Helper::get_wc_template_overrides();
        
        // Method 3: Direct file scan
        $direct_scan = WC_Template_Fixer_Helper::get_overridden_templates();
        
        return array(
            'our_scanner_count' => count( $our_scan ),
            'our_scanner_results' => $our_scan,
            'wc_overrides_count' => count( $wc_overrides ),
            'wc_overrides_results' => $wc_overrides,
            'direct_scan_count' => count( $direct_scan ),
            'direct_scan_results' => $direct_scan,
            'discrepancies' => self::find_discrepancies( $our_scan, $wc_overrides )
        );
    }
    
    /**
     * Check permissions
     */
    private static function check_permissions() {
        $paths = array(
            'theme_directory' => get_stylesheet_directory(),
            'woocommerce_directory' => get_stylesheet_directory() . '/woocommerce/',
            'uploads_directory' => wp_upload_dir()['basedir'],
            'backup_directory' => wp_upload_dir()['basedir'] . '/wc-template-backups/'
        );
        
        $permissions = array();
        
        foreach ( $paths as $key => $path ) {
            $permissions[$key] = array(
                'path' => $path,
                'exists' => file_exists( $path ),
                'is_writable' => wp_is_writable( $path ),
                'permissions' => file_exists( $path ) ? substr( sprintf( '%o', fileperms( $path ) ), -4 ) : 'N/A'
            );
        }
        
        return $permissions;
    }
    
    /**
     * Check WooCommerce status
     */
    private static function check_woocommerce_status() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return array( 'error' => 'WooCommerce not active' );
        }
        
        $status = array(
            'version' => WC()->version,
            'database_version' => get_option( 'woocommerce_db_version' ),
            'template_debug_mode' => ( defined( 'WC_TEMPLATE_DEBUG_MODE' ) && WC_TEMPLATE_DEBUG_MODE ),
            'system_status_url' => admin_url( 'admin.php?page=wc-status' )
        );
        
        // Try to get template overrides from WC
        if ( class_exists( 'WC_Admin_Status' ) ) {
            $status['can_access_status_tools'] = true;
            $status['template_path_method'] = method_exists( 'WC_Admin_Status', 'scan_template_files' ) ? 'available' : 'not available';
        }
        
        return $status;
    }
    
    /**
     * Scan directory for PHP files
     */
    private static function scan_directory( $dir ) {
        $files = array();
        
        if ( ! is_dir( $dir ) ) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $relative_path = str_replace( $dir, '', $file->getPathname() );
                $files[] = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
            }
        }
        
        return $files;
    }
    
    /**
     * Find discrepancies between scans
     */
    private static function find_discrepancies( $our_scan, $wc_scan ) {
        $discrepancies = array();
        
        $our_files = wp_list_pluck( $our_scan, 'name' );
        $wc_files = wp_list_pluck( $wc_scan, 'file' );
        
        $missing_in_our_scan = array_diff( $wc_files, $our_files );
        $missing_in_wc_scan = array_diff( $our_files, $wc_files );
        
        if ( ! empty( $missing_in_our_scan ) ) {
            $discrepancies['missing_in_our_scan'] = $missing_in_our_scan;
        }
        
        if ( ! empty( $missing_in_wc_scan ) ) {
            $discrepancies['missing_in_wc_scan'] = $missing_in_wc_scan;
        }
        
        return $discrepancies;
    }
    
    /**
     * Generate diagnostic report HTML
     */
    public static function generate_report_html() {
        $diagnostic = self::run_diagnostic();
        
        ob_start();
        ?>
        <div class="wrap">
            <h1>WooCommerce Template Fixer - Diagnostic Report</h1>
            
            <div class="notice notice-info">
                <p><strong>Diagnostic Date:</strong> <?php echo esc_html( current_time( 'mysql' ); ?></p>
            </div>
            
            <h2>Environment</h2>
            <table class="widefat">
                <tbody>
                    <?php foreach ( $diagnostic['environment'] as $key => $value ) : ?>
                    <tr>
                        <th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
                        <td><?php echo esc_html( $value ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Theme Information</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th>WooCommerce Directory</th>
                        <td><?php echo $diagnostic['theme']['woocommerce_directory_exists'] ? '✅ Exists' : '❌ Not Found'; ?></td>
                    </tr>
                    <tr>
                        <th>Template Count</th>
                        <td><?php echo esc_html( $diagnostic['theme']['template_count'] ?? 0 ); ?></td>
                    </tr>
                    <tr>
                        <th>Theme Writable</th>
                        <td><?php echo $diagnostic['theme']['is_writable'] ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h2>Template Detection</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th>Our Scanner</th>
                        <td><?php echo esc_html( $diagnostic['templates']['our_scanner_count'] ); ?> templates found</td>
                    </tr>
                    <tr>
                        <th>WooCommerce Detection</th>
                        <td><?php echo esc_html( $diagnostic['templates']['wc_overrides_count'] ); ?> templates found</td>
                    </tr>
                    <tr>
                        <th>Direct Scan</th>
                        <td><?php echo esc_html( $diagnostic['templates']['direct_scan_count'] ); ?> templates found</td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ( ! empty( $diagnostic['templates']['discrepancies'] ) ) : ?>
            <div class="notice notice-warning">
                <h3>Discrepancies Found</h3>
                <pre><?php // Removed debug code: print_r( $diagnostic['templates']['discrepancies'] ); ?></pre>
            </div>
            <?php endif; ?>
            
            <h2>Actions</h2>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer&force_scan=1' ); ?>" class="button button-primary">Force Rescan</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status' ); ?>" class="button">View WooCommerce Status</a>
                <button class="button" onclick="window.print()">Print Report</button>
            </p>
        </div>
        <?php
        
        return ob_get_clean();
    }
}