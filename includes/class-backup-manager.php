<?php
/**
 * Backup Manager for WooCommerce Templates
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Backup_Manager {
    
    private $backup_dir;
    
    public function __construct() {
        $this->backup_dir = WP_CONTENT_DIR . '/wc-template-backups/';
        $this->ensure_backup_directory();
    }
    
    /**
     * Crear backup de un template
     */
    public function create_backup( $template_name ) {
        $theme_file = $this->get_theme_template_path( $template_name );
        
        if ( ! file_exists( $theme_file ) ) {
            return false;
        }
        
        $backup_filename = $this->generate_backup_filename( $template_name );
        $backup_path = $this->backup_dir . $backup_filename;
        
        // Crear subdirectorio si es necesario
        $backup_subdir = dirname( $backup_path );
        if ( ! file_exists( $backup_subdir ) ) {
            wp_mkdir_p( $backup_subdir );
        }
        
        // Copiar archivo con metadatos adicionales
        if ( copy( $theme_file, $backup_path ) ) {
            $this->save_backup_metadata( $backup_filename, $template_name, $theme_file );
            return $backup_path;
        }
        
        return false;
    }
    
    /**
     * Restaurar backup de un template
     */
    public function restore_backup( $backup_filename ) {
        $backup_path = $this->backup_dir . $backup_filename;
        
        if ( ! file_exists( $backup_path ) ) {
            return array(
                'success' => false,
                'message' => 'Backup file not found'
            );
        }
        
        $metadata = $this->get_backup_metadata( $backup_filename );
        if ( ! $metadata ) {
            return array(
                'success' => false,
                'message' => 'Metadatos del backup no encontrados'
            );
        }
        
        $template_name = $metadata['template_name'];
        $theme_file = $this->get_theme_template_path( $template_name );
        
        // Crear directorio del template si no existe
        $template_dir = dirname( $theme_file );
        if ( ! file_exists( $template_dir ) ) {
            wp_mkdir_p( $template_dir );
        }
        
        // Restaurar archivo
        if ( copy( $backup_path, $theme_file ) ) {
            // Log to records
            wc_template_fixer_log(
                $template_name,
                'restore',
                '',
                '',
                $backup_filename,
                'success',
                'Template restored from backup'
            );
            
            return array(
                'success' => true,
                'message' => 'Template restored successfully',
                'template_name' => $template_name
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Error al restaurar el template'
        );
    }
    
    /**
     * Listar todos los backups disponibles
     */
    public function list_backups() {
        $backups = array();
        
        if ( ! is_dir( $this->backup_dir ) ) {
            return $backups;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->backup_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->getExtension() === 'php' ) {
                $backup_filename = str_replace( $this->backup_dir, '', $file->getPathname() );
                $backup_filename = str_replace( '\\', '/', $backup_filename );
                
                $metadata = $this->get_backup_metadata( $backup_filename );
                
                $backups[] = array(
                    'filename' => $backup_filename,
                    'template_name' => $metadata['template_name'] ?? 'Desconocido',
                    'created_date' => $metadata['created_date'] ?? '',
                    'original_version' => $metadata['original_version'] ?? '',
                    'file_size' => filesize( $file->getPathname() ),
                    'created_timestamp' => filemtime( $file->getPathname() )
                );
            }
        }
        
        // Ordenar por fecha de creación (más recientes primero)
        usort( $backups, function( $a, $b ) {
            return $b['created_timestamp'] - $a['created_timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Eliminar backup específico
     */
    public function delete_backup( $backup_filename ) {
        $backup_path = $this->backup_dir . $backup_filename;
        $metadata_path = $this->get_metadata_path( $backup_filename );
        
        $success = true;
        
        if ( file_exists( $backup_path ) ) {
            $success = wp_delete_file( $backup_path );
        }
        
        if ( file_exists( $metadata_path ) ) {
            wp_delete_file( $metadata_path );
        }
        
        return $success;
    }
    
    /**
     * Limpiar backups antiguos
     */
    public function cleanup_old_backups( $days_to_keep = 30 ) {
        $backups = $this->list_backups();
        $cutoff_time = time() - ( $days_to_keep * DAY_IN_SECONDS );
        $deleted_count = 0;
        
        foreach ( $backups as $backup ) {
            if ( $backup['created_timestamp'] < $cutoff_time ) {
                if ( $this->delete_backup( $backup['filename'] ) ) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Get backup statistics
     */
    public function get_backup_statistics() {
        $backups = $this->list_backups();
        
        $stats = array(
            'total_backups' => count( $backups ),
            'total_size' => 0,
            'oldest_backup' => null,
            'newest_backup' => null,
            'templates_with_backups' => array()
        );
        
        if ( empty( $backups ) ) {
            return $stats;
        }
        
        foreach ( $backups as $backup ) {
            $stats['total_size'] += $backup['file_size'];
            $stats['templates_with_backups'][] = $backup['template_name'];
        }
        
        $stats['templates_with_backups'] = array_unique( $stats['templates_with_backups'] );
        $stats['oldest_backup'] = end( $backups );
        $stats['newest_backup'] = reset( $backups );
        
        return $stats;
    }
    
    /**
     * Crear backup completo de todos los templates
     */
    public function create_full_backup() {
        $scanner = new WC_Template_Fixer_Scanner();
        $theme_templates = $this->get_all_theme_templates();
        
        $results = array(
            'success_count' => 0,
            'error_count' => 0,
            'backups_created' => array(),
            'errors' => array()
        );
        
        foreach ( $theme_templates as $template_name ) {
            $backup_path = $this->create_backup( $template_name );
            
            if ( $backup_path ) {
                $results['success_count']++;
                $results['backups_created'][] = array(
                    'template' => $template_name,
                    'backup_file' => basename( $backup_path )
                );
            } else {
                $results['error_count']++;
                $results['errors'][] = $template_name;
            }
        }
        
        // Log to records
        wc_template_fixer_log(
            'full_backup',
            'backup',
            '',
            '',
            '',
            $results['error_count'] === 0 ? 'success' : 'partial',
            sprintf( 'Backup completo: %d exitosos, %d errores', $results['success_count'], $results['error_count'] )
        );
        
        return $results;
    }
    
    /**
     * Generar nombre de archivo de backup
     */
    private function generate_backup_filename( $template_name ) {
        $safe_name = str_replace( '/', '_', $template_name );
        $safe_name = str_replace( '.php', '', $safe_name );
        $timestamp = gmdate( 'Y-m-d_H-i-s' );
        
        return $safe_name . '_backup_' . $timestamp . '.php';
    }
    
    /**
     * Guardar metadatos del backup
     */
    private function save_backup_metadata( $backup_filename, $template_name, $original_file ) {
        $metadata = array(
            'template_name' => $template_name,
            'original_file' => $original_file,
            'created_date' => current_time( 'mysql' ),
            'created_timestamp' => time(),
            'original_version' => $this->get_template_version( $original_file ),
            'woocommerce_version' => WC()->version,
            'theme_name' => get_stylesheet(),
            'plugin_version' => WC_TEMPLATE_FIXER_VERSION
        );
        
        $metadata_path = $this->get_metadata_path( $backup_filename );
        file_put_contents( $metadata_path, json_encode( $metadata, JSON_PRETTY_PRINT ) );
    }
    
    /**
     * Get backup metadata
     */
    private function get_backup_metadata( $backup_filename ) {
        $metadata_path = $this->get_metadata_path( $backup_filename );
        
        if ( file_exists( $metadata_path ) ) {
            $metadata = json_decode( file_get_contents( $metadata_path ), true );
            return $metadata ?: array();
        }
        
        return array();
    }
    
    /**
     * Get metadata file path
     */
    private function get_metadata_path( $backup_filename ) {
        return $this->backup_dir . str_replace( '.php', '.json', $backup_filename );
    }
    
    /**
     * Asegurar que el directorio de backup existe
     */
    private function ensure_backup_directory() {
        if ( ! file_exists( $this->backup_dir ) ) {
            wp_mkdir_p( $this->backup_dir );
        }
        
        // Crear archivo .htaccess para proteger backups
        $htaccess_file = $this->backup_dir . '.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "# Proteger backups de WC Template Fixer\n";
            $htaccess_content .= "Order deny,allow\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents( $htaccess_file, $htaccess_content );
        }
        
        // Crear archivo index.php vacío
        $index_file = $this->backup_dir . 'index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, "<?php\n// Silence is golden." );
        }
    }
    
    /**
     * Get theme template path
     */
    private function get_theme_template_path( $template_name ) {
        return get_stylesheet_directory() . '/woocommerce/' . $template_name;
    }
    
    /**
     * Get template version
     */
    private function get_template_version( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return '';
        }
        
        $content = file_get_contents( $file_path );
        if ( preg_match( '/@version\s+(\d+\.\d+\.\d+)/', $content, $matches ) ) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * Get all theme templates
     */
    private function get_all_theme_templates() {
        $templates = array();
        $theme_wc_dir = get_stylesheet_directory() . '/woocommerce/';
        
        if ( ! is_dir( $theme_wc_dir ) ) {
            return $templates;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_wc_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->getExtension() === 'php' ) {
                $relative_path = str_replace( $theme_wc_dir, '', $file->getPathname() );
                $relative_path = str_replace( '\\', '/', $relative_path );
                $templates[] = $relative_path;
            }
        }
        
        return $templates;
    }
    
    /**
     * Verificar integridad de backup
     */
    public function verify_backup_integrity( $backup_filename ) {
        $backup_path = $this->backup_dir . $backup_filename;
        
        if ( ! file_exists( $backup_path ) ) {
            return array(
                'valid' => false,
                'message' => 'Backup file not found'
            );
        }
        
        // Verificar que es un archivo PHP válido
        $content = file_get_contents( $backup_path );
        
        if ( strpos( $content, '<?php' ) !== 0 ) {
            return array(
                'valid' => false,
                'message' => 'File is not a valid PHP template'
            );
        }
        
        // Verificar sintaxis PHP usando token_get_all (más seguro)
        $tokens = @token_get_all( $content );
        if ( $tokens === false ) {
            return array(
                'valid' => false,
                'message' => 'File has PHP syntax errors'
            );
        }
        
        return array(
            'valid' => true,
            'message' => 'Valid backup without errors'
        );
    }
    
    /**
     * Exportar backups como archivo ZIP
     */
    public function export_backups_zip() {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return array(
                'success' => false,
                'message' => 'ZipArchive no está disponible en el servidor'
            );
        }
        
        $backups = $this->list_backups();
        
        if ( empty( $backups ) ) {
            return array(
                'success' => false,
                'message' => 'No hay backups disponibles para exportar'
            );
        }
        
        $zip_filename = 'wc-template-backups-' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
        $zip_path = $this->backup_dir . $zip_filename;
        
        $zip = new ZipArchive();
        
        if ( $zip->open( $zip_path, ZipArchive::CREATE ) === TRUE ) {
            foreach ( $backups as $backup ) {
                $backup_path = $this->backup_dir . $backup['filename'];
                $metadata_path = $this->get_metadata_path( $backup['filename'] );
                
                $zip->addFile( $backup_path, $backup['filename'] );
                
                if ( file_exists( $metadata_path ) ) {
                    $zip->addFile( $metadata_path, str_replace( '.php', '.json', $backup['filename'] ) );
                }
            }
            
            $zip->close();
            
            return array(
                'success' => true,
                'zip_file' => $zip_path,
                'download_url' => $this->get_backup_download_url( $zip_filename )
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Error al crear el archivo ZIP'
        );
    }
    
    /**
     * Get backup download URL
     */
    private function get_backup_download_url( $filename ) {
        return admin_url( 'admin-ajax.php?action=wc_template_fixer_download_backup&file=' . urlencode( $filename ) . '&nonce=' . wp_create_nonce( 'download_backup' ) );
    }
}