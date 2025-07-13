<?php
/**
 * WooCommerce Template Scanner
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Scanner {
    
    /**
     * Scan outdated templates
     */
    public function scan_outdated_templates() {
        $outdated_templates = array();
        $theme_templates = $this->get_theme_templates();
        
        foreach ( $theme_templates as $template_path => $template_data ) {
            $core_version = $this->get_core_template_version( $template_path );
            $theme_version = $template_data['version'];
            
            if ( $core_version && version_compare( $theme_version, $core_version, '<' ) ) {
                $outdated_templates[] = array(
                    'name' => $template_path,
                    'theme_version' => $theme_version,
                    'core_version' => $core_version,
                    'theme_file' => $template_data['file'],
                    'core_file' => $this->get_core_template_path( $template_path ),
                    'risk_level' => $this->calculate_risk_level( $theme_version, $core_version ),
                    'last_modified' => filemtime( $template_data['file'] ),
                    'size' => filesize( $template_data['file'] ),
                    'has_customizations' => $this->detect_customizations( $template_data['file'] )
                );
            }
        }
        
        // Sort by risk level
        usort( $outdated_templates, function( $a, $b ) {
            $risk_order = array( 'critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1 );
            return $risk_order[$b['risk_level']] - $risk_order[$a['risk_level']];
        });
        
        return $outdated_templates;
    }
    
    /**
     * Get theme templates
     */
    private function get_theme_templates() {
        $templates = array();
        $theme_template_dir = get_stylesheet_directory() . '/woocommerce/';
        
        if ( ! is_dir( $theme_template_dir ) ) {
            return $templates;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_template_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->getExtension() === 'php' ) {
                $filename = $file->getFilename();
                
                // Skip backup files and temporary files
                if ( strpos( $filename, '.backup' ) !== false || 
                     strpos( $filename, '.tmp' ) !== false ||
                     strpos( $filename, '~' ) !== false ) {
                    continue;
                }
                
                $relative_path = str_replace( $theme_template_dir, '', $file->getPathname() );
                $relative_path = str_replace( '\\', '/', $relative_path ); // Normalizar separadores
                
                $version = $this->extract_template_version( $file->getPathname() );
                
                if ( $version ) {
                    $templates[$relative_path] = array(
                        'version' => $version,
                        'file' => $file->getPathname()
                    );
                }
            }
        }
        
        return $templates;
    }
    
    /**
     * Extract template version
     */
    private function extract_template_version( $file_path ) {
        $file_content = file_get_contents( $file_path );
        
        // Search for @version pattern
        if ( preg_match( '/@version\s+(\d+\.\d+\.\d+)/', $file_content, $matches ) ) {
            return $matches[1];
        }
        
        return false;
    }
    
    /**
     * Get core template version
     */
    private function get_core_template_version( $template_path ) {
        $core_file = $this->get_core_template_path( $template_path );
        
        if ( file_exists( $core_file ) ) {
            return $this->extract_template_version( $core_file );
        }
        
        return false;
    }
    
    /**
     * Get core template path
     */
    private function get_core_template_path( $template_path ) {
        return WC()->plugin_path() . '/templates/' . $template_path;
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
            'utech',                    // Theme specific
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
     * Get template name from path
     */
    private function get_template_name_from_path( $file_path ) {
        $theme_wc_dir = get_stylesheet_directory() . '/woocommerce/';
        $template_name = str_replace( $theme_wc_dir, '', $file_path );
        return str_replace( '\\', '/', $template_name );
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