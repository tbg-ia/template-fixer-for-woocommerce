<?php
/**
 * Template Scanner Class
 *
 * Scans for outdated WooCommerce templates using WooCommerce Status logic.
 * Replicates exactly how WooCommerce Status detects outdated templates.
 *
 * @package WC_Template_Fixer
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Template_Fixer_Scanner class.
 */
class WC_Template_Fixer_Scanner {

    /**
     * Constructor.
     */
    public function __construct() {
        // Constructor logic if needed
    }

    /**
     * Scan for outdated templates using WooCommerce Status logic.
     * This replicates exactly how WooCommerce Status detects outdated templates.
     *
     * @return array Array of outdated templates
     */
    public function scan_outdated_templates() {
        // Get WooCommerce Status theme info (this contains all template override data)
        $theme_info = $this->get_wc_status_theme_info();
        $outdated_templates = array();
        
        if ( isset( $theme_info['overrides'] ) && is_array( $theme_info['overrides'] ) ) {
            foreach ( $theme_info['overrides'] as $override ) {
                // Use the exact same logic as WooCommerce Status (lines 1367-1374)
                if ( $override['core_version'] && ( empty( $override['version'] ) || version_compare( $override['version'], $override['core_version'], '<' ) ) ) {
                    $theme_file_path = $this->get_full_theme_file_path( $override['file'] );
                    
                    $outdated_templates[] = array(
                        'template' => $override['file'],
                        'name' => $override['file'], // For backward compatibility
                        'theme_version' => $override['version'] ?: 'No version',
                        'core_version' => $override['core_version'],
                        'status' => 'outdated',
                        'theme_file' => $theme_file_path,
                        'core_file' => $this->get_core_template_path_from_override( $override['file'] ),
                        'risk_level' => $this->calculate_risk_level( $override['version'] ?: '0.0.0', $override['core_version'] ),
                        'has_customizations' => $this->detect_customizations( $theme_file_path ),
                        'last_modified' => file_exists( $theme_file_path ) ? filemtime( $theme_file_path ) : 0,
                        'size' => file_exists( $theme_file_path ) ? filesize( $theme_file_path ) : 0
                    );
                }
            }
        }
        
        return $outdated_templates;
    }

    /**
     * Get WooCommerce Status theme info.
     * This replicates the exact data that WooCommerce Status uses.
     *
     * @return array Theme info from WooCommerce Status
     */
    public function get_wc_status_theme_info() {
        // Use WooCommerce Status controller to get the exact same data
        $status_controller = new WC_REST_System_Status_V2_Controller();
        return $status_controller->get_theme_info();
    }

    /**
     * Get full theme file path from relative path.
     *
     * @param string $relative_file Relative file path from WooCommerce Status
     * @return string Full path to theme file
     */
    private function get_full_theme_file_path( $relative_file ) {
        // WooCommerce Status returns paths relative to themes directory
        return WP_CONTENT_DIR . '/themes/' . $relative_file;
    }

    /**
     * Get core template path from override file.
     *
     * @param string $override_file Override file path
     * @return string Core template path
     */
    private function get_core_template_path_from_override( $override_file ) {
        // Extract just the template filename from the override path
        $template_name = basename( $override_file );
        
        // Handle special cases for product category/tag templates
        if ( false !== strpos( $template_name, '-product_cat' ) || false !== strpos( $template_name, '-product_tag' ) ) {
            $template_name = str_replace( '_', '-', $template_name );
        }
        
        return WC()->plugin_path() . '/templates/' . $template_name;
    }

    /**
     * Get theme templates using WooCommerce Status logic.
     * This method replicates the exact template scanning logic from WooCommerce Status.
     *
     * @return array Array of theme templates
     */
    private function get_theme_templates() {
        $templates = array();
        
        // Get all WooCommerce core template files (same as WooCommerce Status)
        $scan_files = WC_Admin_Status::scan_template_files( WC()->plugin_path() . '/templates/' );
        
        // Include *-product_<cat|tag> templates for backwards compatibility (same as WooCommerce Status)
        $scan_files[] = 'content-product_cat.php';
        $scan_files[] = 'taxonomy-product_cat.php';
        $scan_files[] = 'taxonomy-product_tag.php';
        
        foreach ( $scan_files as $file ) {
            // Use the same template location logic as WooCommerce Status
            $located = apply_filters( 'wc_get_template', $file, $file, array(), WC()->template_path(), WC()->plugin_path() . '/templates/' );
            
            $theme_file = false;
            $theme_type = 'none';
            
            // Check in the same order as WooCommerce Status (lines 1343-1355)
            if ( file_exists( $located ) ) {
                $theme_file = $located;
                $theme_type = 'located';
            } elseif ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
                $theme_file = get_stylesheet_directory() . '/' . $file;
                $theme_type = 'child_root';
            } elseif ( file_exists( get_stylesheet_directory() . '/' . WC()->template_path() . $file ) ) {
                $theme_file = get_stylesheet_directory() . '/' . WC()->template_path() . $file;
                $theme_type = 'child_wc';
            } elseif ( file_exists( get_template_directory() . '/' . $file ) ) {
                $theme_file = get_template_directory() . '/' . $file;
                $theme_type = 'parent_root';
            } elseif ( file_exists( get_template_directory() . '/' . WC()->template_path() . $file ) ) {
                $theme_file = get_template_directory() . '/' . WC()->template_path() . $file;
                $theme_type = 'parent_wc';
            }
            
            if ( $theme_file ) {
                // Use WooCommerce Status method to get version
                $theme_version = WC_Admin_Status::get_file_version( $theme_file );
                
                $templates[$file] = array(
                    'version' => $theme_version,
                    'file' => $theme_file,
                    'theme_type' => $theme_type
                );
            }
        }
        
        return $templates;
    }

    /**
     * Extract template version from file using WooCommerce Status method.
     *
     * @param string $file_path Path to the template file
     * @return string|false Version string or false if not found
     */
    private function extract_template_version( $file_path ) {
        // Use WooCommerce Status method directly
        return WC_Admin_Status::get_file_version( $file_path );
    }

    /**
     * Check if file content is a WooCommerce template.
     *
     * @param string $content File content
     * @return bool True if it's a WooCommerce template
     */
    private function is_woocommerce_template( $content ) {
        // Basic WooCommerce template detection
        $indicators = array(
            // Core WooCommerce identifiers
            'woocommerce',
            'WooCommerce',
            '@package WooCommerce',
            'WooCommerce\\Templates',
            
            // WooCommerce functions
            'wc_get_template',
            'wc_print_notices',
            'wc_get_product',
            'wc_get_order',
            'wc_price',
            'wc_format_decimal',
            'wc_add_to_cart_message',
            'wc_cart_totals_order_total_html',
            'wc_get_cart_url',
            'wc_get_checkout_url',
            'wc_get_account_endpoint_url',
            
            // WooCommerce hooks and filters
            'do_action( \'woocommerce_',
            'apply_filters( \'woocommerce_',
            'woocommerce_before_',
            'woocommerce_after_',
            'woocommerce_single_',
            'woocommerce_archive_',
            'woocommerce_cart_',
            'woocommerce_checkout_',
            'woocommerce_account_',
            
            // CSS classes and IDs
            'class="woocommerce',
            'id="woocommerce',
            'woocommerce-',
            'wc-',
            'product-',
            'cart-',
            'checkout-',
            'shop-',
            
            // WooCommerce objects and globals
            'WC()->',
            'wc_',
            '$product',
            '$order',
            '$cart',
            '$checkout',
            '$customer',
            'global $product',
            'global $woocommerce',
            
            // WooCommerce conditional functions
            'is_woocommerce()',
            'is_product()',
            'is_shop()',
            'is_cart()',
            'is_checkout()',
            'is_account_page()',
            'is_product_category()',
            'is_product_tag()',
            'is_product_taxonomy()',
            'is_wc_endpoint_url()',
            
            // Template-specific indicators
            'single-product',
            'archive-product',
            'content-product',
            'cart-totals',
            'mini-cart',
            'product-thumbnails',
            'add-to-cart',
            'quantity-input',
            'price-html',
            'rating-html',
            'review-meta',
            'product-attributes',
            'variation-add-to-cart',
            
            // Common WooCommerce template comments
            'This template can be overridden by copying it to yourtheme/woocommerce/',
            'HOWEVER, on occasion WooCommerce will need to update template files',
            'maintain compatibility'
        );
        
        // Count matches for better accuracy
        $match_count = 0;
        foreach ( $indicators as $indicator ) {
            if ( stripos( $content, $indicator ) !== false ) {
                $match_count++;
                // If we find multiple indicators, it's definitely a WooCommerce template
                if ( $match_count >= 2 ) {
                    return true;
                }
            }
        }
        
        // Single match might be enough for strong indicators
        $strong_indicators = array(
            '@package WooCommerce',
            'WooCommerce\\Templates',
            'This template can be overridden by copying it to yourtheme/woocommerce/',
            'global $product',
            'WC()->'
        );
        
        foreach ( $strong_indicators as $indicator ) {
            if ( stripos( $content, $indicator ) !== false ) {
                return true;
            }
        }
        
        return $match_count > 0;
    }
    
    /**
     * Get core template version
     */
    private function get_core_template_version( $template_path ) {
        $core_file = $this->get_core_template_path( $template_path );
        
        if ( file_exists( $core_file ) ) {
            $version = $this->extract_template_version( $core_file );
            if ( $version ) {
                return $version;
            }
        }
        
        // If no core template found or no version in core template,
        // use WooCommerce version as fallback for comparison
        if ( function_exists( 'WC' ) && WC()->version ) {
            $wc_version = WC()->version;
            
            // For custom theme templates that don't have core equivalents,
            // assume they should be compatible with current WooCommerce version
            // but add a small increment to encourage updates
            $version_parts = explode( '.', $wc_version );
            if ( count( $version_parts ) >= 2 ) {
                // Increment minor version to encourage template updates
                $version_parts[1] = (int)$version_parts[1] + 1;
                return implode( '.', array_slice( $version_parts, 0, 3 ) );
            }
            
            return $wc_version;
        }
        
        // Ultimate fallback - assume a reasonably current version
        return '8.0.0';
    }
    
    /**
     * Get core template path
     */
    private function get_core_template_path( $template_path ) {
        $core_template_path = WC()->plugin_path() . '/templates/' . $template_path;
        
        // If the direct path exists, return it
        if ( file_exists( $core_template_path ) ) {
            return $core_template_path;
        }
        
        // For custom theme templates, try to find the closest core template
        $fallback_mappings = array(
            // Custom product templates -> core product templates
            'content-product-deals.php' => 'content-product.php',
            'content-product-list.php' => 'content-product.php',
            'content-product-quick-view.php' => 'content-product.php',
            'content-single-product-deal.php' => 'content-single-product.php',
            'archive-product-2.php' => 'archive-product.php',
            
            // Custom cart templates -> core cart templates
            'content-mini-cart.php' => 'cart/mini-cart.php',
            'cart-shipping.php' => 'cart/cart-shipping.php',
            'cart-totals.php' => 'cart/cart-totals.php',
            
            // Custom single product templates -> core single product templates
            'sticky-product-info.php' => 'single-product/title.php',
            'product-image.php' => 'single-product/product-image.php',
            'product-thumbnails.php' => 'single-product/product-thumbnails.php',
            
            // Custom loop templates -> core loop templates
            'add-to-compare.php' => 'loop/add-to-cart.php',
            'add-to-wishlist.php' => 'loop/add-to-cart.php',
            
            // Custom global templates -> core global templates
            'wrapper-start.php' => 'global/wrapper-start.php',
            'wrapper-end.php' => 'global/wrapper-end.php',
            'form-login.php' => 'global/form-login.php',
            'quantity-input.php' => 'global/quantity-input.php',
        );
        
        // Check if we have a fallback mapping
        if ( isset( $fallback_mappings[ $template_path ] ) ) {
            $fallback_path = WC()->plugin_path() . '/templates/' . $fallback_mappings[ $template_path ];
            if ( file_exists( $fallback_path ) ) {
                return $fallback_path;
            }
        }
        
        // Try to find a similar template by removing prefixes/suffixes
        $base_name = basename( $template_path, '.php' );
        $directory = dirname( $template_path );
        
        // Remove common prefixes/suffixes
        $clean_patterns = array(
            '/^content-/',
            '/^archive-/',
            '/^single-/',
            '/-\d+$/',
            '/-list$/',
            '/-grid$/',
            '/-deals?$/',
            '/-quick-view$/',
            '/-mini$/',
        );
        
        foreach ( $clean_patterns as $pattern ) {
            $clean_name = preg_replace( $pattern, '', $base_name );
            if ( $clean_name !== $base_name ) {
                $potential_paths = array(
                    $directory . '/' . $clean_name . '.php',
                    $clean_name . '.php',
                    'content-' . $clean_name . '.php',
                    'single-' . $clean_name . '.php',
                    'archive-' . $clean_name . '.php'
                );
                
                foreach ( $potential_paths as $potential_path ) {
                    $full_path = WC()->plugin_path() . '/templates/' . $potential_path;
                    if ( file_exists( $full_path ) ) {
                        return $full_path;
                    }
                }
            }
        }
        
        // If no core template found, return the original path anyway
        // This allows the version comparison to fail gracefully
        return $core_template_path;
    }
    
    /**
     * Calculate risk level
     */
    private function calculate_risk_level( $theme_version, $core_version ) {
        $theme_parts = explode( '.', $theme_version );
        $core_parts = explode( '.', $core_version );
        
        $theme_major = intval( $theme_parts[0] );
        $theme_minor = intval( $theme_parts[1] ?? 0 );
        
        $core_major = intval( $core_parts[0] );
        $core_minor = intval( $core_parts[1] ?? 0 );
        
        // Major version difference
        if ( $core_major - $theme_major >= 2 ) {
            return 'critical';
        }
        
        if ( $core_major - $theme_major >= 1 ) {
            return 'high';
        }
        
        // Minor version difference
        if ( $core_minor - $theme_minor >= 3 ) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Get template name from full path
     */
    private function get_template_name_from_path( $theme_file ) {
        // Extract template name from theme file path
        $theme_dir = get_template_directory();
        $child_theme_dir = get_stylesheet_directory();
        
        // Remove theme directory paths
        $template_name = str_replace( array( $theme_dir, $child_theme_dir ), '', $theme_file );
        $template_name = str_replace( '/woocommerce/', '', $template_name );
        $template_name = ltrim( $template_name, '/' );
        
        return $template_name;
    }
    
    /**
     * Detect template customizations
     */
    private function detect_customizations( $theme_file ) {
        $theme_content = file_get_contents( $theme_file );
        $template_name = $this->get_template_name_from_path( $theme_file );
        $core_file = $this->get_core_template_path( $template_name );
        
        if ( ! file_exists( $core_file ) ) {
            return true; // If core doesn't exist, assume customization
        }
        
        $core_content = file_get_contents( $core_file );
        
        // Customization indicators
        $customization_indicators = array(
            get_template(),                    // Theme specific
            'bootstrap',                // CSS Framework
            'container',                // Specific classes
            'row',
            'col-',
            'custom-',
            'theme-',
            '_e(',                      // Custom translation functions
            '__(',
            'get_template_directory',
            'wp_enqueue_',
        );
        
        foreach ( $customization_indicators as $indicator ) {
            if ( stripos( $theme_content, $indicator ) !== false && 
                 stripos( $core_content, $indicator ) === false ) {
                return true;
            }
        }
        
        // Compare general similarity
        $similarity = 0;
        similar_text( $theme_content, $core_content, $similarity );
        
        return $similarity < 80; // If less than 80% similar, consider customized
    }
    
    /**
     * Get all theme templates
     */
    public function get_all_theme_templates() {
        $theme_templates = $this->get_theme_templates();
        $all_templates = array();
        
        foreach ( $theme_templates as $template_path => $template_data ) {
            $core_version = $this->get_core_template_version( $template_path );
            
            $all_templates[$template_path] = array(
                'theme_version' => $template_data['version'],
                'core_version' => $core_version,
                'file_path' => $template_data['file'],
                'last_modified' => filemtime( $template_data['file'] ),
                'has_customizations' => $this->detect_customizations( $template_data['file'] )
            );
        }
        
        return $all_templates;
    }
    
    /**
     * Get template statistics
     */
    public function get_template_statistics() {
        $outdated = $this->scan_outdated_templates();
        
        $stats = array(
            'total_theme_templates' => count( $this->get_theme_templates() ),
            'outdated_templates' => count( $outdated ),
            'up_to_date_templates' => 0,
            'risk_levels' => array(
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ),
            'customized_templates' => 0,
            'safe_to_update' => 0
        );
        
        $stats['up_to_date_templates'] = $stats['total_theme_templates'] - $stats['outdated_templates'];
        
        foreach ( $outdated as $template ) {
            $stats['risk_levels'][$template['risk_level']]++;
            
            if ( $template['has_customizations'] ) {
                $stats['customized_templates']++;
            } else {
                $stats['safe_to_update']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Check template compatibility with WooCommerce version
     */
    public function check_woocommerce_compatibility( $template_path ) {
        $core_file = $this->get_core_template_path( $template_path );
        
        if ( ! file_exists( $core_file ) ) {
            return array(
                'compatible' => false,
                'message' => 'Template not found in WooCommerce core'
            );
        }
        
        $wc_version = WC()->version;
        $template_version = $this->get_core_template_version( $template_path );
        
        if ( ! $template_version ) {
            return array(
                'compatible' => 'unknown',
                'message' => 'Could not determine template version'
            );
        }
        
        // Check if template is compatible with current WooCommerce version
        if ( version_compare( $template_version, $wc_version, '<=' ) ) {
            return array(
                'compatible' => true,
                'message' => 'Template compatible with WooCommerce ' . $wc_version
            );
        }
        
        return array(
            'compatible' => false,
            'message' => 'Template requires WooCommerce ' . $template_version . ' or higher'
        );
    }
    
    /**
     * Generate detailed template report
     */
    public function generate_detailed_report() {
        $outdated = $this->scan_outdated_templates();
        $stats = $this->get_template_statistics();
        
        $report = array(
            'scan_date' => current_time( 'mysql' ),
            'woocommerce_version' => WC()->version,
            'theme_name' => get_stylesheet(),
            'statistics' => $stats,
            'outdated_templates' => array(),
            'recommendations' => array()
        );
        
        foreach ( $outdated as $template ) {
            $compatibility = $this->check_woocommerce_compatibility( $template['name'] );
            
            $report['outdated_templates'][] = array(
                'name' => $template['name'],
                'theme_version' => $template['theme_version'],
                'core_version' => $template['core_version'],
                'risk_level' => $template['risk_level'],
                'has_customizations' => $template['has_customizations'],
                'compatibility' => $compatibility,
                'last_modified' => gmdate( 'Y-m-d H:i:s', $template['last_modified'] ),
                'file_size' => $this->format_file_size( $template['size'] )
            );
        }
        
        // Generate recommendations
        if ( $stats['risk_levels']['critical'] > 0 ) {
            $report['recommendations'][] = 'Update critical templates immediately to maintain site security.';
        }
        
        if ( $stats['customized_templates'] > 0 ) {
            $report['recommendations'][] = 'Carefully review customized templates before updating.';
        }
        
        if ( $stats['safe_to_update'] > 0 ) {
            $report['recommendations'][] = $stats['safe_to_update'] . ' templates can be updated automatically without risk.';
        }
        
        return $report;
    }
    
    /**
     * Format file size
     */
    private function format_file_size( $bytes ) {
        $units = array( 'B', 'KB', 'MB', 'GB' );
        
        for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }
        
        return round( $bytes, 2 ) . ' ' . $units[$i];
    }
    
    /**
     * Clear plugin cache and force rescan
     */
    public function clear_cache_and_rescan() {
        // Clear any cached data
        delete_transient( 'wc_template_fixer_scan_results' );
        delete_transient( 'wc_template_fixer_template_list' );
        
        // Clean up any backup files that might interfere
        $this->cleanup_backup_files();
        
        // Force a fresh scan
        return $this->scan_outdated_templates();
    }
    
    /**
     * Clean up backup files that might interfere with scanning
     */
    private function cleanup_backup_files() {
        $theme_template_dir = get_stylesheet_directory() . '/woocommerce/';
        
        if ( ! is_dir( $theme_template_dir ) ) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_template_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        foreach ( $iterator as $file ) {
            $filename = $file->getFilename();
            
            // Remove backup files that might interfere
            if ( strpos( $filename, '.backup' ) !== false || 
                 strpos( $filename, '.tmp' ) !== false ) {
                // Only log, don't actually delete to be safe
                wc_template_fixer_log(
                    $filename,
                    'cleanup',
                    '',
                    '',
                    '',
                    'info',
                    'Found backup file that might interfere with scanning: ' . $file->getPathname()
                );
            }
        }
    }
}