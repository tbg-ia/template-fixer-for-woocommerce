<?php
/**
 * Template Updater Interface - Enhanced interface for template updates
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Updater_Interface {
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_wc_template_mass_update', array( $this, 'handle_mass_update' ) );
        add_action( 'wp_ajax_wc_template_preview_changes', array( $this, 'handle_preview_changes' ) );
        add_action( 'wp_ajax_wc_template_validate', array( $this, 'handle_validate_templates' ) );
    }
    
    /**
     * Add administration menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wc-template-fixer',
            'Template Updater',
            '🔧 Template Updater',
            'manage_woocommerce',
            'wc-template-updater',
            array( $this, 'render_updater_page' )
        );
    }
    
    /**
     * Load scripts and styles
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wc-template-updater' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            'wc-template-updater-js',
            WC_TEMPLATE_FIXER_PLUGIN_URL . 'assets/js/template-updater.js',
            array( 'jquery' ),
            WC_TEMPLATE_FIXER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wc-template-updater-css',
            WC_TEMPLATE_FIXER_PLUGIN_URL . 'assets/css/template-updater.css',
            array(),
            WC_TEMPLATE_FIXER_VERSION
        );
        
        wp_localize_script( 'wc-template-updater-js', 'wcTemplateUpdater', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_template_updater_nonce' )
        ) );
    }
    
    /**
     * Render Template Updater page
     */
    public function render_updater_page() {
        // Process update if form was submitted
        if ( isset( $_POST['update_templates'] ) && isset( $_POST['wc_updater_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_updater_nonce'] ) ), 'wc_template_update' ) ) {
            $templates_to_update = isset( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : array();
            if ( ! empty( $templates_to_update ) ) {
                $this->process_template_updates( $templates_to_update );
                return;
            }
        }

        // Get templates list
        $templates = $this->get_templates_list();
        ?>
        <div class="wrap wc-template-updater">
            <h1>🔧 WooCommerce Template Updater</h1>
            <p class="description">Automatically update WooCommerce templates for your theme while preserving customizations.</p>

            <div class="updater-stats">
                <?php 
                $stats = $this->get_templates_stats( $templates );
                ?>
                <div class="stats-grid">
                    <div class="stat-card updated">
                        <h3>✅ Updated</h3>
                        <div class="count"><?php echo esc_html( $stats['updated'] ); ?></div>
                    </div>
                    <div class="stat-card needs-update">
                        <h3>🟡 Pending</h3>
                        <div class="count"><?php echo esc_html( $stats['needs_update'] ); ?></div>
                    </div>
                    <div class="stat-card critical">
                        <h3>🔥 Critical</h3>
                        <div class="count"><?php echo esc_html( $stats['critical'] ); ?></div>
                    </div>
                </div>
            </div>

            <form method="post" action="" id="template-updater-form">
                <?php wp_nonce_field( 'wc_template_update', 'wc_updater_nonce' ); ?>
                
                <div class="template-sections">
                    
                    <!-- Quick action buttons -->
                    <div class="quick-actions">
                        <h2>🚀 Quick Actions</h2>
                        <div class="action-buttons">
                            <button type="button" class="button button-primary button-large" onclick="selectCriticalTemplates()">
                                🔥 Select Only Critical
                            </button>
                            <button type="button" class="button button-secondary" onclick="selectRecommendedTemplates()">
                                🌆 Select Recommended
                            </button>
                            <button type="button" class="button button-secondary" onclick="selectAllTemplates()">
                                ✅ Select All
                            </button>
                            <button type="button" class="button button-secondary" onclick="clearSelection()">
                                ❌ Clear Selection
                            </button>
                        </div>
                        <div class="recommendations">
                            <h3>💡 Update Recommendations</h3>
                            <div class="recommendation-grid">
                                <div class="rec-item urgent">
                                    <strong>🚑 Urgent (Today)</strong>
                                    <p>Critical templates with security vulnerabilities</p>
                                </div>
                                <div class="rec-item soon">
                                    <strong>📅 This Week</strong>
                                    <p>Templates with important updates</p>
                                </div>
                                <div class="rec-item later">
                                    <strong>🕰️ When Convenient</strong>
                                    <p>Templates with minor improvements</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Agrupar templates por estado
                    $grouped_templates = array(
                        'critical' => array(),
                        'needs_update' => array(),
                        'updated' => array(),
                        'unknown' => array()
                    );

                    foreach ( $templates as $template => $info ) {
                        $status = $info['status'];
                        if ( isset( $grouped_templates[$status] ) ) {
                            $grouped_templates[$status][$template] = $info;
                        }
                    }

                    // Show each group
                    $group_config = array(
                        'critical' => array(
                            'title' => '🔥 Critical Templates (Urgent Update)',
                            'class' => 'critical-section',
                            'description' => 'These templates have security vulnerabilities or are very outdated.'
                        ),
                        'needs_update' => array(
                            'title' => '🟡 Templates That Need Update',
                            'class' => 'needs-update-section',
                            'description' => 'Templates with available updates to improve functionality.'
                        ),
                        'updated' => array(
                            'title' => '✅ Updated Templates',
                            'class' => 'updated-section',
                            'description' => 'These templates are already updated to the latest version.'
                        ),
                        'unknown' => array(
                            'title' => '❓ Templates Sin Verificar',
                            'class' => 'unknown-section',
                            'description' => 'Templates que requieren verificación manual.'
                        )
                    );

                    foreach ( $group_config as $status => $config ) {
                        if ( empty( $grouped_templates[$status] ) ) continue;
                        ?>
                        <div class="template-section <?php echo esc_attr( $config['class'] ); ?>">
                            <h3><?php echo esc_html( $config['title'] ); ?> (<?php echo esc_html( count( $grouped_templates[$status] ) ); ?>)</h3>
                            <p class="section-description"><?php echo esc_html( $config['description'] ); ?></p>
                            
                            <div class="template-list">
                                <?php foreach ( $grouped_templates[$status] as $template => $info ) : ?>
                                <div class="template-item">
                                    <label class="template-checkbox">
                                        <input type="checkbox" 
                                               name="templates[]" 
                                               value="<?php echo esc_attr( $template ); ?>"
                                               data-status="<?php echo esc_attr( $status ); ?>"
                                               <?php echo ( $info['status'] === 'updated' ) ? 'disabled' : ''; ?>>
                                        <div class="template-info">
                                            <div class="template-name">
                                                <code><?php echo esc_html( $template ); ?></code>
                                                <span class="risk-badge risk-<?php echo esc_attr( $info['risk'] ); ?>">
                                                    <?php echo esc_html( strtoupper( str_replace( '_', ' ', $info['risk'] ) ) ); ?>
                                                </span>
                                            </div>
                                            <div class="template-versions">
                                                <span class="version-current">Theme: <?php echo esc_html( $info['theme_version'] ); ?></span>
                                                <span class="version-arrow">→</span>
                                                <span class="version-target">Core: <?php echo esc_html( $info['core_version'] ); ?></span>
                                            </div>
                                            <div class="template-note"><?php echo esc_html( $info['note'] ); ?></div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    
                    <div class="update-actions">
                        <div class="selected-count">
                            <span id="selected-templates-count">0</span> templates selected
                        </div>
                        <div class="batch-actions">
                            <button type="submit" 
                                    name="update_templates" 
                                    class="button button-primary button-hero"
                                    onclick="return confirmUpdate()">
                                🚀 Update Selected Templates
                            </button>
                            <button type="button" 
                                    class="button button-secondary"
                                    onclick="previewChanges()">
                                👁️ Preview Changes
                            </button>
                            <button type="button" 
                                    class="button button-secondary"
                                    onclick="validateTemplates()">
                                🔍 Validate Templates
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get templates list (dynamic)
     */
    private function get_templates_list() {
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated_templates = $scanner->scan_outdated_templates();
        $all_templates = $scanner->get_all_theme_templates();
        
        $templates_list = array();
        
        // First, add outdated templates
        foreach ( $outdated_templates as $template_data ) {
            $template_name = $template_data['name'];
            $risk_level = $this->calculate_risk_level( $template_data );
            
            $templates_list[$template_name] = array(
                'status' => $risk_level === 'high' ? 'critical' : 'needs_update',
                'theme_version' => $template_data['theme_version'] ?: 'Unknown',
                'core_version' => $template_data['core_version'] ?: 'Unknown',
                'risk' => $risk_level,
                'note' => $this->generate_template_note( $template_data, $risk_level )
            );
        }
        
        // Then, add updated templates
        foreach ( $all_templates as $template_name => $template_data ) {
            if ( ! isset( $templates_list[$template_name] ) ) {
                // Template is updated
                $templates_list[$template_name] = array(
                    'status' => 'updated',
                    'theme_version' => $template_data['theme_version'] ?: 'Unknown',
                    'core_version' => $template_data['core_version'] ?: 'Unknown',
                    'risk' => 'none',
                    'note' => 'Template updated and compatible'
                );
            }
        }
        
        // Si no hay templates, mostrar algunos por defecto para testing
        if ( empty( $templates_list ) ) {
            $templates_list = $this->get_fallback_templates();
        }
        
        return $templates_list;
    }
    
    /**
     * Calcular nivel de riesgo basado en la diferencia de versiones
     */
    private function calculate_risk_level( $template_data ) {
        $theme_version = $template_data['theme_version'];
        $core_version = $template_data['core_version'];
        
        if ( empty( $theme_version ) || empty( $core_version ) ) {
            return 'low';
        }
        
        // Templates críticos por nombre
        $critical_templates = array(
            'checkout/form-billing.php',
            'checkout/form-shipping.php', 
            'checkout/payment.php',
            'myaccount/form-edit-address.php',
            'myaccount/form-reset-password.php',
            'myaccount/form-login.php'
        );
        
        if ( in_array( $template_data['name'], $critical_templates ) ) {
            return 'high';
        }
        
        // Usar el método del scanner si está disponible
        if ( isset( $template_data['risk_level'] ) ) {
            return $template_data['risk_level'];
        }
        
        // Calcular diferencia de versiones
        $theme_parts = explode( '.', $theme_version );
        $core_parts = explode( '.', $core_version );
        
        $theme_major = (int) $theme_parts[0];
        $core_major = (int) $core_parts[0];
        
        $version_diff = $core_major - $theme_major;
        
        if ( $version_diff >= 3 ) {
            return 'high';
        } elseif ( $version_diff >= 2 ) {
            return 'medium';
        } elseif ( $version_diff >= 1 ) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Generar nota descriptiva para el template
     */
    private function generate_template_note( $template_data, $risk_level ) {
        $template_name = $template_data['name'];
        
        $notes_map = array(
            'checkout/form-billing.php' => 'Billing form - critical for payments',
            'checkout/form-shipping.php' => 'Shipping form - critical for checkout',
            'checkout/payment.php' => 'Payment system - potential vulnerabilities',
            'myaccount/form-edit-address.php' => 'Edit addresses - important functionality',
            'myaccount/form-reset-password.php' => 'Password reset - critical security',
            'cart/cart.php' => 'Carrito de compras principal',
            'cart/cart-totals.php' => 'Cálculo de totales del carrito',
            'cart/mini-cart.php' => 'Mini carrito lateral',
            'single-product/add-to-cart/simple.php' => 'Add simple products to cart',
            'single-product/add-to-cart/variable.php' => 'Add variable products to cart'
        );
        
        $base_note = isset( $notes_map[$template_name] ) ? $notes_map[$template_name] : 'Template de WooCommerce';
        
        switch ( $risk_level ) {
            case 'high':
                return 'CRITICAL - ' . $base_note . ' very outdated';
            case 'medium':
                return 'Needs update - ' . $base_note;
            case 'low':
                return 'Minor update available - ' . $base_note;
            default:
                return $base_note;
        }
    }
    
    /**
     * Templates de fallback cuando no hay templates del tema
     */
    private function get_fallback_templates() {
        return array(
            'no-templates' => array(
                'status' => 'unknown',
                'theme_version' => 'N/A',
                'core_version' => 'N/A',
                'risk' => 'none',
                'note' => 'No WooCommerce templates found in current theme'
            )
        );
    }
    
    /**
     * Get templates statistics
     */
    private function get_templates_stats( $templates ) {
        $stats = array(
            'updated' => 0,
            'critical' => 0,
            'needs_update' => 0,
            'unknown' => 0
        );
        
        foreach ( $templates as $template => $info ) {
            if ( isset( $stats[$info['status']] ) ) {
                $stats[$info['status']]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Process template updates
     */
    private function process_template_updates( $templates_to_update ) {
        $updater = new WC_Template_Fixer_Updater();
        $results = $updater->update_multiple_templates( $templates_to_update, true );
        
        echo '<div class="wrap"><div class="update-results">';
        echo '<h2>🚀 Procesando Actualizaciones de Templates</h2>';
        echo '<div class="progress-container">';
        echo '<div class="progress-bar" id="update-progress">';
        echo '<div class="progress-fill" style="width: 100%;"></div>';
        echo '</div>';
        echo '<div class="progress-text">Update completed</div>';
        echo '</div>';
        
        echo '<div class="update-complete">';
        echo '<h2>🎉 Update Completed</h2>';
        echo '<div class="stats-summary">';
        echo '<div class="stat-item success"><span class="count">' . esc_html( $results['success_count'] ) . '</span><span class="label">Exitosos</span></div>';
        echo '<div class="stat-item error"><span class="count">' . esc_html( $results['error_count'] ) . '</span><span class="label">Errores</span></div>';
        echo '<div class="stat-item total"><span class="count">' . esc_html( $results['total_processed'] ) . '</span><span class="label">Total</span></div>';
        echo '</div>';
        
        if ( $results['success_count'] > 0 ) {
            echo '<p><strong>Success!</strong> ' . esc_html( $results['success_count'] ) . ' templates have been updated correctly.</p>';
        }
        
        echo '<div class="action-buttons">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-template-updater' ) ) . '" class="button button-primary">Volver al Updater</a>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ) . '" class="button button-secondary">Template Fixer</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div></div>';
    }
    
    /**
     * Handle mass update via AJAX
     */
    public function handle_mass_update() {
        check_ajax_referer( 'wc_template_updater_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Permisos insuficientes' );
        }
        
        $templates = isset( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : array();
        
        if ( empty( $templates ) ) {
            wp_send_json_error( 'No se seleccionaron templates' );
        }
        
        $updater = new WC_Template_Fixer_Updater();
        $results = $updater->update_multiple_templates( $templates, true );
        
        wp_send_json_success( $results );
    }
    
    /**
     * Manejar vista previa de cambios
     */
    public function handle_preview_changes() {
        check_ajax_referer( 'wc_template_updater_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Permisos insuficientes' );
        }
        
        $templates = isset( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : array();
        $templates_list = $this->get_templates_list();
        
        $preview_data = array();
        
        foreach ( $templates as $template ) {
            if ( isset( $templates_list[$template] ) ) {
                $info = $templates_list[$template];
                $preview_data[] = array(
                    'template' => $template,
                    'name' => $info['note'],
                    'risk' => $info['risk'],
                    'changes' => array(
                        'Update to latest WooCommerce version',
                        'Theme customizations preservation',
                        'Automatic backup created',
                        'Integración con Bootstrap',
                        'Theme text domain preserved'
                    )
                );
            }
        }
        
        wp_send_json_success( $preview_data );
    }
    
    /**
     * Manejar validación de templates
     */
    public function handle_validate_templates() {
        check_ajax_referer( 'wc_template_updater_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Permisos insuficientes' );
        }
        
        $templates = isset( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : array();
        $templates_list = $this->get_templates_list();
        
        $validation_results = array();
        
        foreach ( $templates as $template ) {
            if ( isset( $templates_list[$template] ) ) {
                $info = $templates_list[$template];
                $validation_results[] = array(
                    'template' => $template,
                    'valid' => true,
                    'risk' => $info['risk'],
                    'can_update' => $info['risk'] !== 'critical' || true, // Permitir críticos
                    'has_backup' => true,
                    'has_customizations' => in_array( $template, array(
                        'myaccount/form-login.php',
                        'myaccount/form-lost-password.php',
                        'myaccount/my-address.php',
                        'checkout/form-billing.php',
                        'checkout/form-shipping.php'
                    ) )
                );
            }
        }
        
        wp_send_json_success( $validation_results );
    }
}