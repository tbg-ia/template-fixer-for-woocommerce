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
            // Registrar error en logs
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
            
            // Pequeña pausa para evitar sobrecarga
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
        
        if ( ! empty( $safe_templates ) ) {
            $results = $this->update_multiple_templates( $safe_templates, false );
            
            // Send automatic update notification
            if ( $results['success_count'] > 0 ) {
                $this->send_auto_update_notification( $results );
            }
            
            return $results;
        }
        
        return array(
            'total_processed' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'results' => array()
        );
    }
    
    /**
     * Aplicar personalizaciones del tema
     */
    private function apply_theme_customizations( $template_name, $core_content ) {
        $theme_file = $this->get_theme_template_path( $template_name );
        
        if ( ! file_exists( $theme_file ) ) {
            return $core_content;
        }
        
        $theme_content = file_get_contents( $theme_file );
        
        // Aplicar personalizaciones específicas según el template
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
     * Personalizar form-login.php
     */
    private function customize_form_login( $core_content, $theme_content ) {
        // Detectar si el tema usa Bootstrap
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
            
            // Agregar clases específicas del tema
            $core_content = str_replace(
                '<h2><?php esc_html_e( \'Login\', \'woocommerce\' ); ?></h2>',
                '<h2 class="login__heading"><?php esc_html_e( \'Login\', \'utech\' ); ?></h2>',
                $core_content
            );
            
            $core_content = str_replace(
                '<h2><?php esc_html_e( \'Register\', \'woocommerce\' ); ?></h2>',
                '<h2 class="register__heading"><?php esc_html_e( \'Register\', \'utech\' ); ?></h2>',
                $core_content
            );
            
            // Agregar estructura para caso sin registro
            $core_content = $this->add_no_registration_structure( $core_content );
        }
        
        return $this->preserve_text_domain( $core_content, 'utech' );
    }
    
    /**
     * Personalizar form-lost-password.php
     */
    private function customize_form_lost_password( $core_content, $theme_content ) {
        // Detectar estructura Bootstrap
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
        
        return $this->preserve_text_domain( $core_content, 'utech' );
    }
    
    /**
     * Personalizar my-address.php
     */
    private function customize_my_address( $core_content, $theme_content ) {
        // Preservar text domain del tema
        $core_content = str_replace(
            '__( \'Billing address\', \'woocommerce\' )',
            '__( \'Billing address\', \'utech\' )',
            $core_content
        );
        
        $core_content = str_replace(
            '__( \'Shipping address\', \'woocommerce\' )',
            '__( \'Shipping address\', \'utech\' )',
            $core_content
        );
        
        return $core_content;
    }
    
    /**
     * Personalizar order-details-customer.php
     */
    private function customize_order_details_customer( $core_content, $theme_content ) {
        // Detectar si usa estructura Bootstrap personalizada
        if ( strpos( $theme_content, 'woocommerce-customer-details-section' ) !== false ) {
            $core_content = str_replace(
                '<section class="woocommerce-customer-details">',
                '<section class="woocommerce-customer-details-section">',
                $core_content
            );
            
            // Agregar estructura Bootstrap si existe en el tema
            if ( strpos( $theme_content, 'row justify-content-center' ) !== false ) {
                $core_content = $this->add_bootstrap_structure_to_order_details( $core_content );
            }
        }
        
        return $this->preserve_text_domain( $core_content, 'utech' );
    }
    
    /**
     * Aplicar personalizaciones genéricas
     */
    private function apply_generic_customizations( $core_content, $theme_content ) {
        // Preservar text domain si se usa en el tema
        if ( strpos( $theme_content, '\'utech\'' ) !== false ) {
            $core_content = $this->preserve_text_domain( $core_content, 'utech' );
        }
        
        // Preservar clases CSS personalizadas comunes
        $custom_classes = $this->extract_custom_classes( $theme_content );
        foreach ( $custom_classes as $class ) {
            $core_content = $this->apply_custom_class( $core_content, $class );
        }
        
        return $core_content;
    }
    
    /**
     * Preservar text domain del tema
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
     * Extraer clases CSS personalizadas del tema
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
     * Aplicar clase personalizada
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
     * Agregar estructura Bootstrap a order-details-customer
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
        
        // Agregar Bootstrap classes a la columna de shipping
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
     * Agregar estructura sin registro
     */
    private function add_no_registration_structure( $content ) {
        $no_reg_structure = '
<?php else: ?>

	<div class="container">
		<div class="row customer__login__register justify-content-md-center" id="customer_login">
			<div class="col-lg-6 col-sm-12">
				<h2 class="login__heading"><?php esc_html_e( \'Login\', \'utech\' ); ?></h2>
				<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

					<?php do_action( \'woocommerce_login_form_start\' ); ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="username"><?php esc_html_e( \'Username or email address\', \'utech\' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( \'Required\', \'woocommerce\' ); ?></span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST[\'username\'] ) && is_string( $_POST[\'username\'] ) ) ? esc_attr( wp_unslash( $_POST[\'username\'] ) ) : \'\'; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
					</p>
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password"><?php esc_html_e( \'Password\', \'utech\' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( \'Required\', \'woocommerce\' ); ?></span></label>
						<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
					</p>

					<?php do_action( \'woocommerce_login_form\' ); ?>

					<p class="form-row">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
							<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( \'Remember me\', \'utech\' ); ?></span>
						</label>
						<?php wp_nonce_field( \'woocommerce-login\', \'woocommerce-login-nonce\' ); ?>
						<button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( \'button\' ) ? \' \' . wc_wp_theme_get_element_class_name( \'button\' ) : \'\' ); ?>" name="login" value="<?php esc_attr_e( \'Log in\', \'utech\' ); ?>"><?php esc_html_e( \'Log in\', \'utech\' ); ?></button>
					</p>
					<p class="woocommerce-LostPassword lost_password">
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( \'Lost your password?\', \'utech\' ); ?></a>
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
     * Obtener ruta del template del tema
     */
    private function get_theme_template_path( $template_name ) {
        return get_stylesheet_directory() . '/woocommerce/' . $template_name;
    }
    
    /**
     * Obtener ruta del template core
     */
    private function get_core_template_path( $template_name ) {
        return WC()->plugin_path() . '/templates/' . $template_name;
    }
    
    /**
     * Obtener versión del template
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
    
}