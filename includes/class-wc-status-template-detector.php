<?php
/**
 * WooCommerce Status Template Detector
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Status_Detector {
    
    /**
     * Hook into WooCommerce status page more reliably
     */
    public static function init() {
        // Multiple hooks to ensure we catch the templates section
        add_action( 'woocommerce_system_status_report', array( __CLASS__, 'inject_template_fixer_ui' ) );
        add_filter( 'woocommerce_system_status_environment_rows', array( __CLASS__, 'add_template_fixer_row' ) );
        add_action( 'admin_footer', array( __CLASS__, 'inject_js_on_status_page' ) );
    }
    
    /**
     * Inject Template Fixer UI into status report
     */
    public static function inject_template_fixer_ui() {
        // This runs during the status report generation
        add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_template_fixer_script' ), 999 );
    }
    
    /**
     * Add a row to environment section
     */
    public static function add_template_fixer_row( $rows ) {
        $scanner = new WC_Template_Fixer_Scanner();
        $outdated = $scanner->scan_outdated_templates();
        
        $status = count( $outdated ) > 0 ? 
            // translators: %d is the number of outdated templates
            '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( __( '%d outdated templates', 'template-fixer-for-woocommerce' ), count( $outdated ) ) . '</mark>' :
            '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . __( 'All templates up to date', 'template-fixer-for-woocommerce' ) . '</mark>';
        
        $rows['template_fixer_status'] = array(
            'name' => __( 'Template Status', 'template-fixer-for-woocommerce' ),
            'data' => $status . ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ) . '" class="button button-small">' . __( 'Manage Templates', 'template-fixer-for-woocommerce' ) . '</a>',
            'success' => count( $outdated ) === 0
        );
        
        return $rows;
    }
    
    /**
     * Inject JS only on status page
     */
    public static function inject_js_on_status_page() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'woocommerce_page_wc-status' ) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Method 1: Find by heading text
            var $templateHeading = $('h2').filter(function() {
                return $(this).text().indexOf('Templates') !== -1 || 
                       $(this).text().indexOf('Theme') !== -1;
            });
            
            // Method 2: Find by table content
            if ($templateHeading.length === 0) {
                $templateHeading = $('table').filter(function() {
                    return $(this).find('td:contains(".php")').length > 0 &&
                           $(this).find('td:contains("version")').length > 0;
                }).closest('.wc-system-status-section').find('h2');
            }
            
            // Method 3: Find overrides section
            if ($templateHeading.length === 0) {
                $templateHeading = $('td:contains("Overrides:")').closest('table').parent().find('h2');
            }
            
            if ($templateHeading.length > 0) {
                var $section = $templateHeading.parent();
                var $table = $section.find('table').first();
                
                // Check for outdated templates
                var hasOutdated = $table.find('mark.error').length > 0 || 
                                 $table.find('code:contains("version")').length > 0;
                
                if (hasOutdated) {
                    // Insert our UI
                    var fixerUI = '<div class="wc-template-fixer-integration" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #e1e1e1; border-radius: 4px;">';
                    fixerUI += '<h3 style="margin-top: 0;"><span class="dashicons dashicons-admin-tools"></span> WooCommerce Template Fixer</h3>';
                    fixerUI += '<p>We detected outdated WooCommerce templates in your theme. Template Fixer can update them automatically while preserving your customizations.</p>';
                    fixerUI += '<p>';
                    fixerUI += '<a href="' + '<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>' + '" class="button button-primary">';
                    fixerUI += '<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> ';
                    fixerUI += 'Open Template Fixer';
                    fixerUI += '</a> ';
                    fixerUI += '<button class="button wc-tf-inline-scan" data-nonce="' + '<?php echo esc_attr( wp_create_nonce( 'wc_template_fixer_status' ) ); ?>' + '">';
                    fixerUI += '<span class="dashicons dashicons-search" style="vertical-align: middle;"></span> ';
                    fixerUI += 'Quick Scan';
                    fixerUI += '</button>';
                    fixerUI += '</p>';
                    fixerUI += '<div class="wc-tf-scan-results" style="display:none; margin-top: 15px;"></div>';
                    fixerUI += '</div>';
                    
                    $table.after(fixerUI);
                    
                    // Add click handler
                    $('.wc-tf-inline-scan').on('click', function() {
                        var $btn = $(this);
                        var $results = $('.wc-tf-scan-results');
                        var nonce = $btn.data('nonce');
                        
                        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scanning...');
                        
                        $.post(ajaxurl, {
                            action: 'wc_template_fixer_quick_scan',
                            nonce: nonce
                        }, function(response) {
                            if (response.success && response.data.count > 0) {
                                var html = '<div class="notice notice-warning inline">';
                                html += '<p><strong>Found ' + response.data.count + ' outdated templates:</strong></p>';
                                html += '<ul style="margin: 10px 0 10px 20px;">';
                                
                                $.each(response.data.templates, function(i, tpl) {
                                    var icon = tpl.risk_level === 'critical' ? '🔴' : 
                                              tpl.risk_level === 'high' ? '🟠' : 
                                              tpl.risk_level === 'medium' ? '🟡' : '🟢';
                                    html += '<li>' + icon + ' <code>' + tpl.name + '</code> ';
                                    html += '(v' + tpl.theme_version + ' → v' + tpl.core_version + ')';
                                    html += ' - <strong>' + tpl.risk_level + '</strong> risk</li>';
                                });
                                
                                html += '</ul>';
                                html += '</div>';
                                
                                $results.html(html).slideDown();
                            } else {
                                $results.html('<div class="notice notice-success inline"><p>✅ All templates are up to date!</p></div>').slideDown();
                            }
                            
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Quick Scan');
                        });
                    });
                }
            }
        });
        
        // CSS for spinner
        jQuery('<style>').text('.dashicons.spin { animation: dashicons-spin 1s infinite linear; } @keyframes dashicons-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }').appendTo('head');
        </script>
        <?php
    }
    
    /**
     * Print template fixer script (backup method)
     */
    public static function print_template_fixer_script() {
        ?>
        <script type="text/javascript">
        // This script runs after the status report is generated
        jQuery(function($) {
            setTimeout(function() {
                // Look for any sign of template overrides
                var templateIndicators = [
                    'td:contains("Overrides")',
                    'td:contains(".php")',
                    'code:contains("version")',
                    'mark.error:contains("version")'
                ];
                
                var $templateSection = null;
                $.each(templateIndicators, function(i, selector) {
                    var $found = $(selector).closest('tr').parent().parent();
                    if ($found.length > 0) {
                        $templateSection = $found;
                        return false;
                    }
                });
                
                if ($templateSection && $templateSection.find('.wc-template-fixer-integration').length === 0) {
                    console.log('Template section found, injecting Template Fixer UI');
                }
            }, 100);
        });
        </script>
        <?php
    }
}