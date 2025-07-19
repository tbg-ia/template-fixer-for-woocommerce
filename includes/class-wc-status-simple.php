<?php
/**
 * Simple WooCommerce Status Integration
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Status_Simple {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Add to status page
        add_action( 'admin_footer', array( __CLASS__, 'add_template_fixer_to_status' ) );
        add_action( 'wp_ajax_tf_quick_scan', array( __CLASS__, 'ajax_quick_scan' ) );
    }
    
    /**
     * Add Template Fixer integration to WooCommerce Status page
     */
    public static function add_template_fixer_to_status() {
        $screen = get_current_screen();
        
        // Only on WooCommerce Status page
        if ( ! $screen || $screen->id !== 'woocommerce_page_wc-status' ) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Wait for page to fully load
            setTimeout(function() {
                // Find template section multiple ways
                var $templateSection = null;
                
                // Method 1: Look for "Templates" heading
                $('h2').each(function() {
                    if ($(this).text().toLowerCase().indexOf('template') !== -1) {
                        $templateSection = $(this).parent();
                        return false;
                    }
                });
                
                // Method 2: Look for template table
                if (!$templateSection) {
                    $('table.wc_status_table').each(function() {
                        if ($(this).find('td:contains(".php")').length > 0) {
                            $templateSection = $(this).parent();
                            return false;
                        }
                    });
                }
                
                // Method 3: Look for overrides text
                if (!$templateSection) {
                    $('td').each(function() {
                        if ($(this).text().toLowerCase().indexOf('override') !== -1) {
                            $templateSection = $(this).closest('table').parent();
                            return false;
                        }
                    });
                }
                
                if ($templateSection && $templateSection.length > 0) {
                    console.log('Template Fixer: Found template section');
                    
                    // Check if there are template issues
                    var hasErrors = $templateSection.find('mark.error').length > 0;
                    
                    // Always add our interface (even if no errors detected)
                    var templateFixerHTML = '<div id="template-fixer-integration" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">';
                    templateFixerHTML += '<h3 style="margin-top: 0;"><span class="dashicons dashicons-admin-tools" style="color: #0073aa;"></span> WooCommerce Template Fixer</h3>';
                    
                    if (hasErrors) {
                        templateFixerHTML += '<p style="color: #d63638;"><strong>⚠️ Outdated templates detected!</strong> Template Fixer can help update them while preserving customizations.</p>';
                    } else {
                        templateFixerHTML += '<p>Scan your theme templates for WooCommerce compatibility and get automatic fixes.</p>';
                    }
                    
                    templateFixerHTML += '<p>';
                    templateFixerHTML += '<button id="tf-quick-scan" class="button button-primary" style="margin-right: 10px;">';
                    templateFixerHTML += '<span class="dashicons dashicons-search" style="vertical-align: middle;"></span> ';
                    templateFixerHTML += 'Scan Templates Now';
                    templateFixerHTML += '</button>';
                    templateFixerHTML += '<a href="' + '<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>' + '" class="button">';
                    templateFixerHTML += '<span class="dashicons dashicons-admin-tools" style="vertical-align: middle;"></span> ';
                    templateFixerHTML += 'Open Template Fixer';
                    templateFixerHTML += '</a>';
                    templateFixerHTML += '</p>';
                    templateFixerHTML += '<div id="tf-scan-results" style="display: none; margin-top: 15px;"></div>';
                    templateFixerHTML += '</div>';
                    
                    // Insert after the template section
                    $templateSection.after(templateFixerHTML);
                    
                    // Add click handler for scan button
                    $('#tf-quick-scan').on('click', function() {
                        var $btn = $(this);
                        var $results = $('#tf-scan-results');
                        
                        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scanning...');
                        
                        $.post(ajaxurl, {
                            action: 'tf_quick_scan',
                            nonce: '<?php echo esc_attr( wp_create_nonce( 'tf_quick_scan' ) ); ?>'
                        }, function(response) {
                            if (response.success) {
                                var html = '<div class="notice notice-info inline">';
                                html += '<h4>Scan Results (' + new Date().toLocaleTimeString() + ')</h4>';
                                
                                if (response.data.templates && response.data.templates.length > 0) {
                                    html += '<p><strong>Found ' + response.data.templates.length + ' templates in your theme:</strong></p>';
                                    html += '<ul style="margin: 10px 0 10px 20px;">';
                                    
                                    $.each(response.data.templates, function(i, tpl) {
                                        var icon = '📄';
                                        if (tpl.outdated) {
                                            icon = tpl.risk_level === 'critical' ? '🔴' : 
                                                  tpl.risk_level === 'high' ? '🟠' : 
                                                  tpl.risk_level === 'medium' ? '🟡' : '🟢';
                                        }
                                        
                                        html += '<li>' + icon + ' <code>' + tpl.name + '</code>';
                                        if (tpl.theme_version && tpl.core_version) {
                                            html += ' (Theme: v' + tpl.theme_version + ', Core: v' + tpl.core_version + ')';
                                            if (tpl.outdated) {
                                                html += ' - <strong style="color: #d63638;">OUTDATED</strong>';
                                            }
                                        }
                                        html += '</li>';
                                    });
                                    
                                    html += '</ul>';
                                    
                                    var outdatedCount = response.data.templates.filter(function(t) { return t.outdated; }).length;
                                    if (outdatedCount > 0) {
                                        html += '<p><strong style="color: #d63638;">⚠️ ' + outdatedCount + ' templates need updating!</strong></p>';
                                        html += '<p><a href="' + '<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>' + '" class="button button-primary">Fix Outdated Templates</a></p>';
                                    } else {
                                        html += '<p><strong style="color: #00a32a;">✅ All templates appear to be up to date!</strong></p>';
                                    }
                                } else {
                                    html += '<p><strong>No WooCommerce templates found in your theme.</strong></p>';
                                    html += '<p>This means your theme is using WooCommerce\'s default templates, which is perfectly fine.</p>';
                                }
                                
                                html += '</div>';
                                $results.html(html).slideDown();
                            } else {
                                $results.html('<div class="notice notice-error inline"><p>Error: ' + (response.data || 'Unknown error occurred') + '</p></div>').slideDown();
                            }
                        }).fail(function() {
                            $results.html('<div class="notice notice-error inline"><p>Network error occurred. Please try again.</p></div>').slideDown();
                        }).always(function() {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Scan Templates Now');
                        });
                    });
                    
                } else {
                    console.log('Template Fixer: Could not find template section');
                }
            }, 500);
        });
        
        // Add spinner animation
        jQuery('<style>').text('.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }').appendTo('head');
        </script>
        <?php
    }
    
    /**
     * AJAX handler for quick scan
     */
    public static function ajax_quick_scan() {
        check_ajax_referer( 'tf_quick_scan', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        try {
            // Check if scanner class exists
            if ( ! class_exists( 'WC_Template_Fixer_Scanner' ) ) {
                wp_send_json_error( 'Scanner class not found' );
            }
            
            $scanner = new WC_Template_Fixer_Scanner();
            
            // Get all theme templates (not just outdated ones)
            $all_templates = $scanner->get_all_theme_templates();
            $outdated_templates = $scanner->scan_outdated_templates();
            
            $templates_with_status = array();
            
            // Process all templates and mark which are outdated
            foreach ( $all_templates as $path => $data ) {
                $is_outdated = false;
                $risk_level = 'low';
                
                // Check if this template is in outdated list
                foreach ( $outdated_templates as $outdated ) {
                    if ( $outdated['name'] === $path ) {
                        $is_outdated = true;
                        $risk_level = $outdated['risk_level'];
                        break;
                    }
                }
                
                $templates_with_status[] = array(
                    'name' => $path,
                    'theme_version' => $data['theme_version'],
                    'core_version' => $data['core_version'],
                    'outdated' => $is_outdated,
                    'risk_level' => $risk_level,
                    'has_customizations' => $data['has_customizations']
                );
            }
            
            wp_send_json_success( array(
                'templates' => $templates_with_status,
                'total_count' => count( $templates_with_status ),
                'outdated_count' => count( $outdated_templates ),
                'message' => sprintf( 
                    'Found %d templates (%d outdated)', 
                    count( $templates_with_status ),
                    count( $outdated_templates )
                )
            ) );
            
        } catch ( Exception $e ) {
            wp_send_json_error( 'Scanner error: ' . $e->getMessage() );
        }
    }
}

// Initialize the simple integration
WC_Template_Fixer_Status_Simple::init();