<?php
/**
 * WooCommerce Template Helper
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Helper {
    
    /**
     * Get all WooCommerce templates being used by the system
     */
    public static function get_overridden_templates() {
        $templates = array();
        
        // Hook into WooCommerce's template loading system
        add_filter( 'woocommerce_locate_template', array( __CLASS__, 'track_template_usage' ), 99999, 3 );
        
        // Get templates from WooCommerce system status
        if ( function_exists( 'WC' ) && method_exists( WC(), 'api' ) ) {
            $system_status = WC()->api->get_endpoint_data( '/wc/v3/system_status' );
            
            if ( isset( $system_status['theme']['overrides'] ) ) {
                foreach ( $system_status['theme']['overrides'] as $override ) {
                    $templates[] = array(
                        'file' => $override['file'],
                        'version' => $override['version'],
                        'core_version' => $override['core_version'],
                        'outdated' => version_compare( $override['version'], $override['core_version'], '<' )
                    );
                }
            }
        }
        
        // Alternative method: Direct check
        if ( empty( $templates ) ) {
            $templates = self::scan_theme_templates_directly();
        }
        
        return $templates;
    }
    
    /**
     * Track template usage
     */
    public static function track_template_usage( $template, $template_name, $template_path ) {
        // Store template usage for analysis
        $used_templates = get_transient( 'wc_template_fixer_used_templates' );
        if ( ! is_array( $used_templates ) ) {
            $used_templates = array();
        }
        
        $used_templates[$template_name] = $template;
        set_transient( 'wc_template_fixer_used_templates', $used_templates, HOUR_IN_SECONDS );
        
        return $template;
    }
    
    /**
     * Scan theme templates directly
     */
    private static function scan_theme_templates_directly() {
        $templates = array();
        $template_paths = array(
            get_stylesheet_directory() . '/woocommerce/',
            get_template_directory() . '/woocommerce/'
        );
        
        // Remove duplicates
        $template_paths = array_unique( $template_paths );
        
        foreach ( $template_paths as $template_path ) {
            if ( ! is_dir( $template_path ) ) {
                continue;
            }
            
            $scanned_templates = self::scan_template_directory( $template_path );
            $templates = array_merge( $templates, $scanned_templates );
        }
        
        return $templates;
    }
    
    /**
     * Scan a template directory
     */
    private static function scan_template_directory( $template_path ) {
        $templates = array();
        $core_path = WC()->plugin_path() . '/templates/';
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $template_path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $relative_path = str_replace( $template_path, '', $file->getPathname() );
                $relative_path = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
                
                // Get versions
                $theme_version = self::get_file_version( $file->getPathname() );
                $core_file = $core_path . $relative_path;
                $core_version = '';
                
                if ( file_exists( $core_file ) ) {
                    $core_version = self::get_file_version( $core_file );
                }
                
                $templates[] = array(
                    'file' => $relative_path,
                    'version' => $theme_version ?: '1.0.0',
                    'core_version' => $core_version,
                    'outdated' => $core_version && version_compare( $theme_version ?: '1.0.0', $core_version, '<' ),
                    'path' => $file->getPathname()
                );
            }
        }
        
        return $templates;
    }
    
    /**
     * Get file version
     */
    private static function get_file_version( $file ) {
        $version = '';
        
        if ( ! file_exists( $file ) ) {
            return $version;
        }
        
        $file_content = file_get_contents( $file );
        
        if ( preg_match( '/\*\s*@version\s+(.+)/i', $file_content, $matches ) ) {
            $version = trim( $matches[1] );
        }
        
        return $version;
    }
    
    /**
     * Force WooCommerce to detect templates
     */
    public static function force_template_detection() {
        // Clear WooCommerce template cache
        delete_transient( 'wc_template_cache' );
        
        // Force WooCommerce to rebuild template list
        if ( class_exists( 'WC_Admin_Status' ) ) {
            WC_Admin_Status::scan_template_files( WC()->plugin_path() . '/templates/' );
        }
        
        // Clear our cache too
        delete_transient( 'wc_template_fixer_used_templates' );
        delete_transient( 'wc_template_fixer_status_scan_results' );
        
        return true;
    }
    
    /**
     * Create missing template structure
     */
    public static function create_template_structure() {
        $wc_template_dir = get_stylesheet_directory() . '/woocommerce/';
        
        if ( ! is_dir( $wc_template_dir ) ) {
            wp_mkdir_p( $wc_template_dir );
            
            // Create a readme file
            $readme_content = "# WooCommerce Template Directory\n\n";
            $readme_content .= "This directory contains WooCommerce template overrides for your theme.\n";
            $readme_content .= "Templates placed here will override the default WooCommerce templates.\n\n";
            $readme_content .= "Created by WooCommerce Template Fixer plugin.\n";
            
            file_put_contents( $wc_template_dir . 'README.md', $readme_content );
        }
        
        return $wc_template_dir;
    }
    
    /**
     * Get template override status from WooCommerce
     */
    public static function get_wc_template_overrides() {
        $overrides = array();
        
        // Try to get from WooCommerce system status
        if ( class_exists( 'WC_REST_System_Status_V2_Controller' ) ) {
            $controller = new WC_REST_System_Status_V2_Controller();
            $schema = $controller->get_public_item_schema();
            $mappings = $controller->get_item_mappings();
            $response = array();
            
            foreach ( $mappings as $section => $values ) {
                $response[ $section ] = $values;
            }
            
            if ( isset( $response['theme']['overrides'] ) ) {
                $overrides = $response['theme']['overrides'];
            }
        }
        
        // Alternative: Direct template scan
        if ( empty( $overrides ) ) {
            $template_path = get_stylesheet_directory() . '/woocommerce/';
            $core_path = WC()->plugin_path() . '/templates/';
            
            if ( is_dir( $template_path ) ) {
                $templates = WC_Admin_Status::scan_template_files( $template_path );
                
                foreach ( $templates as $file ) {
                    $theme_file = str_replace( '.php', '', $file );
                    
                    if ( file_exists( $core_path . $file ) ) {
                        $core_version = WC_Admin_Status::get_file_version( $core_path . $file );
                        $theme_version = WC_Admin_Status::get_file_version( $template_path . $file );
                        
                        $overrides[] = array(
                            'file' => $file,
                            'version' => $theme_version,
                            'core_version' => $core_version,
                            'outdated' => ( ! empty( $theme_version ) && version_compare( $theme_version, $core_version, '<' ) )
                        );
                    }
                }
            }
        }
        
        return $overrides;
    }
}