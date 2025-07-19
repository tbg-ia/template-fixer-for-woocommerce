jQuery(document).ready(function($) {
    'use strict';
    
    // Quick scan button handler (new)
    $(document).on('click', '.wc-template-fixer-quick-scan', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $container = $button.closest('.wc-template-fixer-status-actions');
        var $results = $container.find('.wc-template-fixer-scan-results');
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scanning...');
        
        $.ajax({
            url: wcTemplateFixer.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_template_fixer_quick_scan',
                nonce: wcTemplateFixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    var resultsHtml = '<div class="notice notice-info" style="margin-top: 10px;">';
                    resultsHtml += '<p><strong>' + response.data.message + '</strong></p>';
                    
                    if (response.data.count > 0) {
                        resultsHtml += '<ul>';
                        $.each(response.data.templates, function(i, template) {
                            var riskClass = template.risk_level === 'critical' ? 'color: #e74c3c;' : 
                                          template.risk_level === 'high' ? 'color: #f39c12;' : '';
                            resultsHtml += '<li style="' + riskClass + '">';
                            resultsHtml += template.name + ' (v' + template.theme_version + ' → v' + template.core_version + ')';
                            resultsHtml += ' - <strong>' + template.risk_level.toUpperCase() + '</strong>';
                            resultsHtml += '</li>';
                        });
                        resultsHtml += '</ul>';
                        resultsHtml += '<p><a href="' + wcTemplateFixer.admin_url + 'admin.php?page=wc-template-fixer" class="button button-primary">Go to Template Fixer</a></p>';
                    }
                    
                    resultsHtml += '</div>';
                    $results.html(resultsHtml).slideDown();
                } else {
                    alert(response.data.message || wcTemplateFixer.strings.error);
                }
                
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Scan with Template Fixer');
            },
            error: function() {
                alert(wcTemplateFixer.strings.error);
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Scan with Template Fixer');
            }
        });
    });
    
    // Quick fix button handler
    $('.wc-template-fixer-quick-fix').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(wcTemplateFixer.strings.confirm_fix)) {
            return;
        }
        
        var $button = $(this);
        var $notice = $button.closest('.wc-template-fixer-status-notice');
        var $progress = $notice.find('.wc-template-fixer-progress');
        var $progressBar = $progress.find('.progress-fill');
        var $progressText = $progress.find('.progress-text');
        
        // Disable buttons
        $notice.find('button').prop('disabled', true);
        
        // Show progress
        $progress.show();
        $progressText.text(wcTemplateFixer.strings.fixing);
        
        // Start fix process
        $.ajax({
            url: wcTemplateFixer.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_template_fixer_quick_fix',
                nonce: wcTemplateFixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $progressBar.css('width', '100%');
                    $progressText.text(wcTemplateFixer.strings.complete + ' ' + response.data.message);
                    
                    // Reload page after 2 seconds to update status
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert(response.data.message || wcTemplateFixer.strings.error);
                    $notice.find('button').prop('disabled', false);
                    $progress.hide();
                }
            },
            error: function() {
                alert(wcTemplateFixer.strings.error);
                $notice.find('button').prop('disabled', false);
                $progress.hide();
            }
        });
    });
    
    // Rescan button handler
    $('.wc-template-fixer-rescan').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $notice = $button.closest('.wc-template-fixer-status-notice');
        
        // Update button text
        $button.prop('disabled', true).text(wcTemplateFixer.strings.scanning);
        
        $.ajax({
            url: wcTemplateFixer.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_template_fixer_quick_scan',
                nonce: wcTemplateFixer.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated results
                    window.location.reload();
                } else {
                    alert(response.data.message || wcTemplateFixer.strings.error);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Rescan');
                }
            },
            error: function() {
                alert(wcTemplateFixer.strings.error);
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Rescan');
            }
        });
    });
    
    // Auto-highlight template section if there are errors
    var $templateSection = $('h2:contains("Templates")').parent();
    if ($templateSection.find('.error, .notice-error').length > 0) {
        // Add visual indicator
        $templateSection.css({
            'border-left': '4px solid #dc3232',
            'padding-left': '10px',
            'margin-left': '-14px'
        });
        
        // Check if our notice should be inserted
        if ($('.wc-template-fixer-status-notice').length === 0) {
            // Trigger background scan
            $.post(wcTemplateFixer.ajax_url, {
                action: 'wc_template_fixer_quick_scan',
                nonce: wcTemplateFixer.nonce
            });
        }
    }
    
    // Add CSS for spinner
    $('<style>')
        .text('.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }')
        .appendTo('head');
});