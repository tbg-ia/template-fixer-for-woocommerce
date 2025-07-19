<?php
/**
 * WooCommerce Template Updater
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Updater {
    
    private $backup_manager;
    
    public function __construct() {
        $this->backup_manager = new WC_Template_Fixer_Backup_Manager();
    }
    
    /**
     * Update specific template
     */
    public function update_template( $template_name, $preserve_customizations = true ) {
        try {
            // Validate that the template exists
            $theme_file = $this->get_theme_template_path( $template_name );
            $core_file = $this->get_core_template_path( $template_name );
            
            if ( ! file_exists( $core_file ) ) {
                throw new Exception( 'Core template not found: ' . $template_name );
            }
            
            // Create backup before updating
            $backup_file = $this->backup_manager->create_backup( $template_name );
            if ( ! $backup_file ) {
                throw new Exception( 'Could not create template backup' );
            }
            
            // Get versions
            $old_version = $this->get_template_version( $theme_file );
            $new_version = $this->get_template_version( $core_file );
            
            // Read core template content
            $core_content = file_get_contents( $core_file );
            
            if ( $preserve_customizations && file_exists( $theme_file ) ) {
                // Preserve theme customizations
                $customized_content = $this->apply_theme_customizations( $template_name, $core_content );
            } else {
                $customized_content = $core_content;
            }
            
            // Create directory if it doesn't exist
            $template_dir = dirname( $theme_file );
            if ( ! file_exists( $template_dir ) ) {
                wp_mkdir_p( $template_dir );
            }
            
            // Write updated template
            if ( file_put_contents( $theme_file, $customized_content ) === false ) {
                throw new Exception( 'Could not write updated template' );
            }
            
            // Log to records
            wc_template_fixer_log(
                $template_name,
                'update',
                $old_version,
                $new_version,
                basename( $backup_file ),
                'success',
                $preserve_customizations ? 'Updated with customizations preserved' : 'Updated without preserving customizations'
            );
            
            return array(
                'success' => true,
                'message' => 'Template updated successfully',
                'old_version' => $old_version,
                'new_version' => $new_version,
                'backup_file' => $backup_file
            );
            
        } catch ( Exception $e ) {
            // Log error to records
            wc_template_fixer_log(
                $template_name,
                'update',
                $old_version ?? '',
                $new_version ?? '',
                $backup_file ?? '',
                'error',
                $e->getMessage()
            );
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Update multiple templates
     */
    public function update_multiple_templates( $template_names, $preserve_customizations = true ) {
        $results = array();
        $success_count = 0;
        $error_count = 0;
        
        foreach ( $template_names as $template_name ) {
            $result = $this->update_template( $template_name, $preserve_customizations );
            $results[$template_name] = $result;
            
            if ( $result['success'] ) {
                $success_count++;
            } else {
                $error_count++;
            }
            
            // Small pause to prevent overload
            usleep( 100000 ); // 0.1 segundos
        }
        
        return array(
            'total_processed' => count( $template_names ),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'results' => $results
        );
    }
    
    /**
     * Automatic update of safe templates
     */
    public function auto_update_safe_templates( $outdated_templates ) {
        $safe_templates = array();
        
        foreach ( $outdated_templates as $template ) {
            // Only automatically update low-risk templates without customizations
            if ( $template['risk_level'] === 'low' && ! $template['has_customizations'] ) {
                $safe_templates[] = $template['name'];
            }
        }
        
        if ( empty( $safe_templates ) ) {
            return array(
                'success' => false,
                'message' => 'No safe templates found for automatic update'
            );
        }
        
        $results = $this->update_multiple_templates( $safe_templates, false );
        
        // Send notification
        if ( $results['success_count'] > 0 ) {
            $this->send_auto_update_notification( $results );
        }
        
        return $results;
    }
    
    /**
     * Universal auto-fix for all outdated templates (compatible with any theme)
     */
    public function auto_fix_all_outdated_templates( $options = array() ) {
        $default_options = array(
            'preserve_customizations' => true,
            'skip_critical_risk' => false,
            'create_backups' => true,
            'dry_run' => false
        );
        
        $options = array_merge( $default_options, $options );
        
        try {
            // Get all outdated templates using our enhanced scanner
            $scanner = new WC_Template_Fixer_Scanner();
            $outdated_templates = $scanner->scan_outdated_templates();
            
            if ( empty( $outdated_templates ) ) {
                return array(
                    'success' => true,
                    'message' => 'No outdated templates found',
                    'templates_processed' => 0,
                    'templates_updated' => 0,
                    'templates_skipped' => 0,
                    'results' => array()
                );
            }
            
            $results = array();
            $updated_count = 0;
            $skipped_count = 0;
            $error_count = 0;
            
            foreach ( $outdated_templates as $template ) {
                $template_name = $template['name'];
                
                // Skip critical risk templates if requested
                if ( $options['skip_critical_risk'] && $template['risk_level'] === 'critical' ) {
                    $results[$template_name] = array(
                        'success' => false,
                        'message' => 'Skipped due to critical risk level',
                        'skipped' => true,
                        'risk_level' => $template['risk_level']
                    );
                    $skipped_count++;
                    continue;
                }
                
                // Dry run - just simulate the update
                if ( $options['dry_run'] ) {
                    $results[$template_name] = array(
                        'success' => true,
                        'message' => 'Would be updated (dry run)',
                        'old_version' => $template['theme_version'],
                        'new_version' => $template['core_version'],
                        'risk_level' => $template['risk_level'],
                        'has_customizations' => $template['has_customizations'],
                        'dry_run' => true
                    );
                    continue;
                }
                
                // Perform the actual update
                $update_result = $this->update_template( $template_name, $options['preserve_customizations'] );
                
                if ( $update_result['success'] ) {
                    $updated_count++;
                    $results[$template_name] = array_merge( $update_result, array(
                        'risk_level' => $template['risk_level'],
                        'has_customizations' => $template['has_customizations'],
                        'theme_type' => $template['theme_type'] ?? 'unknown'
                    ));
                } else {
                    $error_count++;
                    $results[$template_name] = $update_result;
                }
                
                // Small delay to prevent server overload
                if ( ! $options['dry_run'] ) {
                    usleep( 100000 ); // 0.1 seconds
                }
            }
            
            // Send notification if templates were actually updated
            if ( $updated_count > 0 && ! $options['dry_run'] ) {
                $this->send_universal_update_notification( array(
                    'results' => $results,
                    'updated_count' => $updated_count,
                    'skipped_count' => $skipped_count,
                    'error_count' => $error_count,
                    'total_count' => count( $outdated_templates )
                ));
            }
            
            return array(
                'success' => true,
                'message' => sprintf( 
                    'Processed %d templates: %d updated, %d skipped, %d errors', 
                    count( $outdated_templates ), 
                    $updated_count, 
                    $skipped_count, 
                    $error_count 
                ),
                'templates_processed' => count( $outdated_templates ),
                'templates_updated' => $updated_count,
                'templates_skipped' => $skipped_count,
                'templates_errors' => $error_count,
                'results' => $results,
                'dry_run' => $options['dry_run']
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Error during auto-fix: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Apply theme customizations
     */
    private function apply_theme_customizations( $template_name, $core_content ) {
        $theme_file = $this->get_theme_template_path( $template_name );
        
        if ( ! file_exists( $theme_file ) ) {
            return $core_content;
        }
        
        $theme_content = file_get_contents( $theme_file );
        
        // Apply specific customizations according to template
        switch ( $template_name ) {
            case 'myaccount/form-login.php':
                return $this->customize_form_login( $core_content, $theme_content );
                
            case 'myaccount/form-lost-password.php':
                return $this->customize_form_lost_password( $core_content, $theme_content );
                
            case 'myaccount/my-address.php':
                return $this->customize_my_address( $core_content, $theme_content );
                
            case 'order/order-details-customer.php':
                return $this->customize_order_details_customer( $core_content, $theme_content );
                
            default:
                return $this->apply_generic_customizations( $core_content, $theme_content );
        }
    }
    
    /**
     * Customize form-login.php
     */
    private function customize_form_login( $core_content, $theme_content ) {
        // Detect if theme uses Bootstrap
        if ( strpos( $theme_content, 'container' ) !== false && strpos( $theme_content, 'row' ) !== false ) {
            
            // Reemplazar estructura base con Bootstrap
            $core_content = str_replace(
                '<div class="u-columns col2-set" id="customer_login">',
                '<div class="container">
		<div class="row customer__login__register" id="customer_login">',
                $core_content
            );
            
            $core_content = str_replace(
                '<div class="u-column1 col-1">',
                '<div class="col-lg-6 col-sm-12">',
                $core_content
            );
            
            $core_content = str_replace(
                '<div class="u-column2 col-2">',
                '<div class="col-lg-6 col-sm-12">',
                $core_content
            );
            
            // Add theme-specific classes
            $core_content = str_replace(
                '<h2><?php esc_html_e( \'Login\', \'woocommerce\' ); ?></h2>',
                '<h2 class="login__heading"><?php esc_html_e( \'Login\', get_template() ); ?></h2>',
                $core_content
            );
            
            $core_content = str_replace(
                '<h2><?php esc_html_e( \'Register\', \'woocommerce\' ); ?></h2>',
                '<h2 class="register__heading"><?php esc_html_e( \'Register\', get_template() ); ?></h2>',
                $core_content
            );
            
            // Add structure for no-registration case
            $core_content = $this->add_no_registration_structure( $core_content );
        }
        
        return $this->preserve_text_domain( $core_content, get_template() );
    }
    
    /**
     * Customize form-lost-password.php
     */
    private function customize_form_lost_password( $core_content, $theme_content ) {
        // Detect Bootstrap structure
        if ( strpos( $theme_content, 'container' ) !== false ) {
            $core_content = str_replace(
                '<form method="post" class="woocommerce-ResetPassword lost_reset_password">',
                '<div class="container">
		<div class="row customer__login__register justify-content-md-center" id="customer_reset_pass">
			<div class="col-lg-6 col-sm-12">
				<form method="post" class="woocommerce-ResetPassword lost_reset_password">',
                $core_content
            );
            
            $core_content = str_replace(
                '</form>',
                '</form>
			</div>
		</div>
	</div>',
                $core_content
            );
        }
        
        return $this->preserve_text_domain( $core_content, get_template() );
    }
    
    /**
     * Customize my-address.php
     */
    private function customize_my_address( $core_content, $theme_content ) {
        // Preserve theme text domain
        $core_content = str_replace(
            '__( \'Billing address\', \'woocommerce\' )',
            '__( \'Billing address\', \'' . get_template() . '\' )',
            $core_content
        );
        
        $core_content = str_replace(
            '__( \'Shipping address\', \'woocommerce\' )',
            '__( \'Shipping address\', \'' . get_template() . '\' )',
            $core_content
        );
        
        return $core_content;
    }
    
    /**
     * Customize order-details-customer.php
     */
    private function customize_order_details_customer( $core_content, $theme_content ) {
        // Detect if uses custom Bootstrap structure
        if ( strpos( $theme_content, 'woocommerce-customer-details-section' ) !== false ) {
            $core_content = str_replace(
                '<section class="woocommerce-customer-details">',
                '<section class="woocommerce-customer-details-section">',
                $core_content
            );
            
            // Add estructura Bootstrap si existe en el tema
            if ( strpos( $theme_content, 'row justify-content-center' ) !== false ) {
                $core_content = $this->add_bootstrap_structure_to_order_details( $core_content );
            }
        }
        
        return $this->preserve_text_domain( $core_content, get_template() );
    }
    
    /**
     * Apply generic customizations
     */
    private function apply_generic_customizations( $core_content, $theme_content ) {
        // Preserve text domain if used in theme
        if ( strpos( $theme_content, '\'' . get_template() . '\'' ) !== false ) {
            $core_content = $this->preserve_text_domain( $core_content, get_template() );
        }
        
        // Preserve common custom CSS classes
        $custom_classes = $this->extract_custom_classes( $theme_content );
        foreach ( $custom_classes as $class ) {
            $core_content = $this->apply_custom_class( $core_content, $class );
        }
        
        return $core_content;
    }
    
    /**
     * Preserve theme text domain
     */
    private function preserve_text_domain( $content, $text_domain ) {
        // Strings de usuario que deberían usar el text domain del tema
        $user_strings = array(
            'Login', 'Register', 'Username', 'Password', 'Email', 'Submit',
            'Billing address', 'Shipping address', 'Order', 'Product',
            'Cart', 'Checkout', 'Account', 'Dashboard'
        );
        
        foreach ( $user_strings as $string ) {
            $content = str_replace(
                "'{$string}', 'woocommerce'",
                "'{$string}', '{$text_domain}'",
                $content
            );
        }
        
        return $content;
    }
    
    /**
     * Extract custom CSS classes from theme
     */
    private function extract_custom_classes( $theme_content ) {
        $custom_classes = array();
        
        // Buscar clases que empiecen con patrones específicos del tema
        preg_match_all( '/class="([^"]*(?:login__|register__|customer__|theme-|custom-)[^"]*)"/', $theme_content, $matches );
        
        foreach ( $matches[1] as $class_string ) {
            $classes = explode( ' ', $class_string );
            foreach ( $classes as $class ) {
                if ( strpos( $class, 'login__' ) === 0 || 
                     strpos( $class, 'register__' ) === 0 || 
                     strpos( $class, 'customer__' ) === 0 ) {
                    $custom_classes[] = $class;
                }
            }
        }
        
        return array_unique( $custom_classes );
    }
    
    /**
     * Apply custom class
     */
    private function apply_custom_class( $content, $custom_class ) {
        // Buscar elementos h2 de login/register para aplicar clases específicas
        if ( $custom_class === 'login__heading' ) {
            $content = str_replace(
                '<h2><?php esc_html_e( \'Login\',',
                '<h2 class="login__heading"><?php esc_html_e( \'Login\',',
                $content
            );
        }
        
        if ( $custom_class === 'register__heading' ) {
            $content = str_replace(
                '<h2><?php esc_html_e( \'Register\',',
                '<h2 class="register__heading"><?php esc_html_e( \'Register\',',
                $content
            );
        }
        
        return $content;
    }
    
    /**
     * Add Bootstrap structure to order-details-customer
     */
    private function add_bootstrap_structure_to_order_details( $core_content ) {
        // Modificar la estructura para incluir Bootstrap y mantener compatibilidad WooCommerce
        $core_content = str_replace(
            '<?php if ( $show_shipping ) : ?>

	<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
		<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">

	<?php endif; ?>',
            '<?php if ( $show_shipping ) : ?>

	<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses row justify-content-center">
		<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1 col-md-6 col-lg-6 col-sm-12">

	<?php else : ?>

	<div class="row justify-content-center">
		<div class="col-md-12 col-lg-12 col-sm-12">

	<?php endif; ?>',
            $core_content
        );
        
        // Add Bootstrap classes to shipping column
        $core_content = str_replace(
            '<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">',
            '<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2 col-md-6 col-lg-6 col-sm-12">',
            $core_content
        );
        
        // Cerrar estructura adicional para caso sin shipping
        $core_content = str_replace(
            '	<?php endif; ?>

	<?php do_action( \'woocommerce_order_details_after_customer_details\', $order ); ?>

</section>',
            '	<?php else : ?>

		</div>
	</div>

	<?php endif; ?>

	<?php do_action( \'woocommerce_order_details_after_customer_details\', $order ); ?>

</section>',
            $core_content
        );
        
        return $core_content;
    }
    
    /**
     * Add no-registration structure
     */
    private function add_no_registration_structure( $content ) {
        $no_reg_structure = '
<?php else: ?>

	<div class="container">
		<div class="row customer__login__register justify-content-md-center" id="customer_login">
			<div class="col-lg-6 col-sm-12">
				<h2 class="login__heading"><?php esc_html_e( \'Login\', get_template() ); ?></h2>
				<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

					<?php do_action( \'woocommerce_login_form_start\' ); ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="username"><?php esc_html_e( \'Username or email address\', get_template() ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( \'Required\', \'woocommerce\' ); ?></span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST[\'username\'] ) && is_string( $_POST[\'username\'] ) ) ? esc_attr( wp_unslash( $_POST[\'username\'] ) ) : \'\'; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
					</p>
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password"><?php esc_html_e( \'Password\', get_template() ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( \'Required\', \'woocommerce\' ); ?></span></label>
						<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
					</p>

					<?php do_action( \'woocommerce_login_form\' ); ?>

					<p class="form-row">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
							<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( \'Remember me\', get_template() ); ?></span>
						</label>
						<?php wp_nonce_field( \'woocommerce-login\', \'woocommerce-login-nonce\' ); ?>
						<button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( \'button\' ) ? \' \' . wc_wp_theme_get_element_class_name( \'button\' ) : \'\' ); ?>" name="login" value="<?php esc_attr_e( \'Log in\', get_template() ); ?>"><?php esc_html_e( \'Log in\', get_template() ); ?></button>
					</p>
					<p class="woocommerce-LostPassword lost_password">
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( \'Lost your password?\', get_template() ); ?></a>
					</p>

					<?php do_action( \'woocommerce_login_form_end\' ); ?>

				</form>
			</div>

		</div>
	</div>


<?php endif; ?>';
        
        return str_replace( '</div>
<?php endif; ?>', $no_reg_structure, $content );
    }
    
    /**
     * Get theme template path (universal)
     */
    private function get_theme_template_path( $template_name ) {
        // Check child theme first
        $child_theme_path = get_stylesheet_directory() . '/woocommerce/' . $template_name;
        if ( file_exists( $child_theme_path ) ) {
            return $child_theme_path;
        }
        
        // Check parent theme
        $parent_theme_path = get_template_directory() . '/woocommerce/' . $template_name;
        if ( file_exists( $parent_theme_path ) ) {
            return $parent_theme_path;
        }
        
        // Return child theme path for new template creation
        return $child_theme_path;
    }
    
    /**
     * Get core template path (universal with fallbacks)
     */
    private function get_core_template_path( $template_name ) {
        // Use the enhanced scanner's logic for finding core templates
        if ( class_exists( 'WC_Template_Fixer_Scanner' ) ) {
            $scanner = new WC_Template_Fixer_Scanner();
            $reflection = new ReflectionClass( $scanner );
            $method = $reflection->getMethod( 'get_core_template_path' );
            $method->setAccessible( true );
            return $method->invoke( $scanner, $template_name );
        }
        
        // Fallback to direct path
        return WC()->plugin_path() . '/templates/' . $template_name;
    }
    
    /**
     * Get template version (universal)
     */
    private function get_template_version( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return '';
        }
        
        // Use the enhanced scanner's version detection
        if ( class_exists( 'WC_Template_Fixer_Scanner' ) ) {
            $scanner = new WC_Template_Fixer_Scanner();
            $reflection = new ReflectionClass( $scanner );
            $method = $reflection->getMethod( 'extract_template_version' );
            $method->setAccessible( true );
            $version = $method->invoke( $scanner, $file_path );
            return $version ?: '';
        }
        
        // Fallback to basic version detection
        $content = file_get_contents( $file_path );
        if ( preg_match( '/@version\s+(\d+\.\d+\.\d+)/', $content, $matches ) ) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * Send automatic update notification
     */
    private function send_auto_update_notification( $results ) {
        if ( ! wc_template_fixer_get_option( 'email_notifications', false ) ) {
            return;
        }
        
        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );
        
        $subject = sprintf( '[%s] WooCommerce Templates Auto-Updated', $site_name );
        
        $message = "The following WooCommerce templates have been automatically updated:\n\n";
        
        foreach ( $results['results'] as $template => $result ) {
            if ( $result['success'] ) {
                $message .= sprintf(
                    "✅ %s (v%s → v%s)\n",
                    $template,
                    $result['old_version'],
                    $result['new_version']
                );
            }
        }
        
        $message .= "\nTotal updated: " . $results['success_count'] . " templates\n";
        $message .= "Revisa los cambios en: " . admin_url( 'admin.php?page=wc-template-fixer' );
        
        wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * Send universal update notification for any theme
     */
    private function send_universal_update_notification( $data ) {
        if ( ! wc_template_fixer_get_option( 'email_notifications', false ) ) {
            return;
        }
        
        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );
        $theme_name = get_stylesheet();
        
        $subject = sprintf( '[%s] WooCommerce Templates Universal Auto-Fix Complete', $site_name );
        
        $message = "Universal WooCommerce Template Auto-Fix Results:\n\n";
        $message .= "Theme: {$theme_name}\n";
        $message .= "Total Templates Processed: {$data['total_count']}\n";
        $message .= "Successfully Updated: {$data['updated_count']}\n";
        $message .= "Skipped: {$data['skipped_count']}\n";
        $message .= "Errors: {$data['error_count']}\n\n";
        
        if ( $data['updated_count'] > 0 ) {
            $message .= "Updated Templates:\n";
            foreach ( $data['results'] as $template => $result ) {
                if ( $result['success'] && ! isset( $result['skipped'] ) ) {
                    $risk_emoji = $this->get_risk_emoji( $result['risk_level'] ?? 'low' );
                    $customization_note = ( $result['has_customizations'] ?? false ) ? ' (customizations preserved)' : '';
                    $message .= sprintf(
                        "%s %s (v%s → v%s)%s\n",
                        $risk_emoji,
                        $template,
                        $result['old_version'] ?? 'unknown',
                        $result['new_version'] ?? 'unknown',
                        $customization_note
                    );
                }
            }
        }
        
        if ( $data['skipped_count'] > 0 ) {
            $message .= "\nSkipped Templates:\n";
            foreach ( $data['results'] as $template => $result ) {
                if ( isset( $result['skipped'] ) && $result['skipped'] ) {
                    $message .= "⚠️ {$template} - {$result['message']}\n";
                }
            }
        }
        
        if ( $data['error_count'] > 0 ) {
            $message .= "\nErrors:\n";
            foreach ( $data['results'] as $template => $result ) {
                if ( ! $result['success'] && ! isset( $result['skipped'] ) ) {
                    $message .= "❌ {$template} - {$result['message']}\n";
                }
            }
        }
        
        $message .= "\nView detailed results: " . admin_url( 'admin.php?page=wc-template-fixer' );
        $message .= "\nWooCommerce Status: " . admin_url( 'admin.php?page=wc-status&tab=status' );
        
        wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * Get emoji for risk level
     */
    private function get_risk_emoji( $risk_level ) {
        $emojis = array(
            'critical' => '🚨',
            'high' => '⚠️',
            'medium' => '🔶',
            'low' => '✅'
        );
        
        return $emojis[ $risk_level ] ?? '✅';
    }
    
}