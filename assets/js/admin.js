/**
 * JavaScript para WooCommerce Template Fixer
 */

(function($) {
    'use strict';

    const TemplateFixer = {
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        bindEvents: function() {
            // Botón de escaneo
            $('#scan-templates').on('click', this.scanTemplates.bind(this));
            
            // Botón de backup completo
            $('#create-full-backup').on('click', this.createFullBackup.bind(this));
            
            // Botón de actualización segura
            $('#update-safe-templates').on('click', this.updateSafeTemplates.bind(this));
            
            // Selección de templates
            $(document).on('change', '.template-checkbox input', this.updateSelectionCount.bind(this));
            
            // Botones de actualización individual
            $(document).on('click', '.update-template', this.updateSingleTemplate.bind(this));
            
            // Acciones de backup
            $('#create-backup').on('click', this.createBackup.bind(this));
            $('#cleanup-backups').on('click', this.cleanupBackups.bind(this));
            $('#export-backups').on('click', this.exportBackups.bind(this));
            
            // Restaurar backup
            $(document).on('click', '.restore-backup', this.restoreBackup.bind(this));
            
            // Eliminar backup
            $(document).on('click', '.delete-backup', this.deleteBackup.bind(this));
            
            // Seleccionar todos/ninguno
            $('#select-all-templates').on('click', this.selectAllTemplates.bind(this));
            $('#select-none-templates').on('click', this.selectNoneTemplates.bind(this));
            $('#select-critical-templates').on('click', this.selectCriticalTemplates.bind(this));
        },

        initializeComponents: function() {
            // Inicializar tooltips
            this.initTooltips();
            
            // Cargar estadísticas iniciales
            this.loadInitialStats();
        },

        scanTemplates: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $progress = $('#scan-progress');
            const $results = $('#scan-results');
            
            // Mostrar estado de carga
            this.setButtonLoading($button, wcTemplateFixer.strings.scanning);
            $progress.show();
            $results.hide();
            
            // Animar barra de progreso
            this.animateProgressBar(0, 100, 3000);
            
            $.ajax({
                url: wcTemplateFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_scan',
                    nonce: wcTemplateFixer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayScanResults(response.data);
                        this.updateStats(response.data.statistics);
                    } else {
                        this.showError('Error al escanear templates: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Error de conexión al escanear templates');
                }.bind(this),
                complete: function() {
                    this.resetButtonLoading($button);
                    $progress.hide();
                }.bind(this)
            });
        },

        displayScanResults: function(data) {
            const $results = $('#scan-results');
            const $templatesList = $('#templates-list');
            
            if (data.outdated_templates.length === 0) {
                $templatesList.html('<div class="no-templates"><p>🎉 ¡Todos los templates están actualizados!</p></div>');
            } else {
                let html = '<div class="templates-header">';
                html += '<div class="selection-controls">';
                html += '<button id="select-all-templates" class="button button-secondary">Seleccionar Todos</button>';
                html += '<button id="select-critical-templates" class="button button-secondary">Critical Only</button>';
                html += '<button id="select-none-templates" class="button button-link">Clear</button>';
                html += '</div>';
                html += '<div class="bulk-actions">';
                html += '<button id="update-selected-templates" class="button button-primary" disabled>Update Selected (<span id="selected-count">0</span>)</button>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="templates-grid">';
                
                data.outdated_templates.forEach(function(template) {
                    html += this.renderTemplateItem(template);
                }.bind(this));
                
                html += '</div>';
                
                $templatesList.html(html);
                
                // Habilitar botón de actualización segura si hay templates seguros
                const safeTemplates = data.outdated_templates.filter(t => t.risk_level === 'low' && !t.has_customizations);
                if (safeTemplates.length > 0) {
                    $('#update-safe-templates').prop('disabled', false);
                }
            }
            
            $results.show();
        },

        renderTemplateItem: function(template) {
            const riskClass = template.risk_level;
            const customizedText = template.has_customizations ? '🎨 Personalizado' : '✨ Sin personalizar';
            const lastModified = new Date(template.last_modified * 1000).toLocaleDateString();
            
            let html = '<div class="template-item" data-risk="' + riskClass + '">';
            html += '<div class="template-header">';
            html += '<label class="template-checkbox">';
            html += '<input type="checkbox" value="' + template.name + '" data-risk="' + riskClass + '">';
            html += '<span class="template-name">' + template.name + '</span>';
            html += '</label>';
            html += '<span class="risk-badge ' + riskClass + '">' + this.getRiskLabel(riskClass) + '</span>';
            html += '</div>';
            
            html += '<div class="template-versions">';
            html += '<span class="version-old">v' + template.theme_version + '</span>';
            html += '<span class="version-arrow">→</span>';
            html += '<span class="version-new">v' + template.core_version + '</span>';
            html += '</div>';
            
            html += '<div class="template-info">';
            html += customizedText + ' • Modificado: ' + lastModified;
            html += '</div>';
            
            html += '<div class="template-actions">';
            html += '<button class="button button-small update-template" data-template="' + template.name + '">Update</button>';
            html += '<button class="button button-small button-secondary preview-changes" data-template="' + template.name + '">View Changes</button>';
            html += '</div>';
            
            html += '</div>';
            
            return html;
        },

        getRiskLabel: function(risk) {
            const labels = {
                'critical': 'CRÍTICO',
                'high': 'ALTO',
                'medium': 'MEDIO',
                'low': 'BAJO'
            };
            return labels[risk] || risk.toUpperCase();
        },

        updateSelectionCount: function() {
            const selectedCount = $('.template-checkbox input:checked').length;
            $('#selected-count').text(selectedCount);
            $('#update-selected-templates').prop('disabled', selectedCount === 0);
        },

        selectAllTemplates: function(e) {
            e.preventDefault();
            $('.template-checkbox input').prop('checked', true);
            this.updateSelectionCount();
        },

        selectNoneTemplates: function(e) {
            e.preventDefault();
            $('.template-checkbox input').prop('checked', false);
            this.updateSelectionCount();
        },

        selectCriticalTemplates: function(e) {
            e.preventDefault();
            $('.template-checkbox input').prop('checked', false);
            $('.template-checkbox input[data-risk="critical"]').prop('checked', true);
            this.updateSelectionCount();
        },

        updateSafeTemplates: function(e) {
            e.preventDefault();
            
            if (!confirm('Automatically update all safe templates (low risk without customizations)?')) {
                return;
            }
            
            const $button = $(e.currentTarget);
            this.setButtonLoading($button, 'Updating...');
            
            // Seleccionar templates seguros
            const safeTemplates = [];
            $('.template-item[data-risk="low"]').each(function() {
                const $item = $(this);
                const templateName = $item.find('.template-checkbox input').val();
                const isCustomized = $item.text().includes('Personalizado');
                
                if (!isCustomized) {
                    safeTemplates.push(templateName);
                }
            });
            
            this.performBulkUpdate(safeTemplates, false, function() {
                this.resetButtonLoading($button);
                this.scanTemplates({ preventDefault: function() {} });
            }.bind(this));
        },

        updateSingleTemplate: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const templateName = $button.data('template');
            
            if (!confirm('Update template ' + templateName + '?')) {
                return;
            }
            
            this.setButtonLoading($button, 'Updating...');
            
            this.performBulkUpdate([templateName], true, function() {
                this.resetButtonLoading($button);
                $button.closest('.template-item').fadeOut();
                this.showSuccess('Template actualizado exitosamente');
            }.bind(this));
        },

        performBulkUpdate: function(templates, preserveCustomizations, callback) {
            $.ajax({
                url: wcTemplateFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_update',
                    nonce: wcTemplateFixer.nonce,
                    templates: templates,
                    preserve_customizations: preserveCustomizations
                },
                success: function(response) {
                    if (response.success) {
                        const results = response.data;
                        this.showUpdateResults(results);
                    } else {
                        this.showError('Error updating templates: ' + response.data);
                    }
                    if (callback) callback();
                }.bind(this),
                error: function() {
                    this.showError('Connection error while updating templates');
                    if (callback) callback();
                }.bind(this)
            });
        },

        showUpdateResults: function(results) {
            let message = '';
            
            if (results.success_count > 0) {
                message += '✅ ' + results.success_count + ' templates actualizados exitosamente. ';
            }
            
            if (results.error_count > 0) {
                message += '❌ ' + results.error_count + ' templates con errores. ';
            }
            
            if (results.success_count > 0) {
                this.showSuccess(message);
            } else {
                this.showError(message);
            }
        },

        createFullBackup: function(e) {
            e.preventDefault();
            
            if (!confirm(wcTemplateFixer.strings.confirm_backup)) {
                return;
            }
            
            const $button = $(e.currentTarget);
            this.setButtonLoading($button, 'Creando backup...');
            
            $.ajax({
                url: wcTemplateFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_backup',
                    nonce: wcTemplateFixer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const results = response.data;
                        this.showSuccess('Backup creado: ' + results.success_count + ' templates respaldados');
                    } else {
                        this.showError('Error al crear backup: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Error de conexión al crear backup');
                }.bind(this),
                complete: function() {
                    this.resetButtonLoading($button);
                }.bind(this)
            });
        },

        restoreBackup: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const backupFilename = $button.data('backup');
            
            if (!confirm('¿Restaurar este backup? Esta acción sobrescribirá el template actual.')) {
                return;
            }
            
            this.setButtonLoading($button, 'Restaurando...');
            
            $.ajax({
                url: wcTemplateFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_restore',
                    nonce: wcTemplateFixer.nonce,
                    backup_filename: backupFilename
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('Backup restaurado exitosamente');
                        location.reload();
                    } else {
                        this.showError('Error al restaurar backup: ' + response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Error de conexión al restaurar backup');
                }.bind(this),
                complete: function() {
                    this.resetButtonLoading($button);
                }.bind(this)
            });
        },

        deleteBackup: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const backupFilename = $button.data('backup');
            
            if (!confirm('¿Eliminar este backup permanentemente?')) {
                return;
            }
            
            // Eliminar fila de la tabla
            $button.closest('tr').fadeOut(function() {
                $(this).remove();
            });
            
            this.showSuccess('Backup eliminado');
        },

        animateProgressBar: function(start, end, duration) {
            const $fill = $('.progress-fill');
            const $text = $('.progress-text');
            
            let current = start;
            const increment = (end - start) / (duration / 50);
            
            const interval = setInterval(function() {
                current += increment;
                
                if (current >= end) {
                    current = end;
                    clearInterval(interval);
                }
                
                $fill.css('width', current + '%');
                $text.text(Math.round(current) + '% completado');
            }, 50);
        },

        updateStats: function(stats) {
            $('#total-templates .stat-number').text(stats.total_theme_templates);
            $('#outdated-templates .stat-number').text(stats.outdated_templates);
            $('#critical-templates .stat-number').text(stats.risk_levels.critical);
            $('#safe-templates .stat-number').text(stats.safe_to_update);
        },

        loadInitialStats: function() {
            // Cargar estadísticas iniciales sin escaneo completo
            $.ajax({
                url: wcTemplateFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_quick_stats',
                    nonce: wcTemplateFixer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateStats(response.data);
                    }
                }.bind(this)
            });
        },

        setButtonLoading: function($button, text) {
            $button.addClass('loading')
                   .prop('disabled', true)
                   .data('original-text', $button.text())
                   .text(text);
        },

        resetButtonLoading: function($button) {
            $button.removeClass('loading')
                   .prop('disabled', false)
                   .text($button.data('original-text'));
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wc-template-fixer').prepend($notice);
            
            // Auto-dismiss después de 5 segundos
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        initTooltips: function() {
            // Inicializar tooltips simples
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const $this = $(this);
                const tooltip = $this.attr('data-tooltip');
                
                const $tooltip = $('<div class="template-fixer-tooltip">' + tooltip + '</div>');
                $('body').append($tooltip);
                
                const offset = $this.offset();
                $tooltip.css({
                    position: 'absolute',
                    top: offset.top - $tooltip.height() - 10,
                    left: offset.left + ($this.width() / 2) - ($tooltip.width() / 2),
                    background: 'rgba(0,0,0,0.8)',
                    color: 'white',
                    padding: '5px 10px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    zIndex: 9999
                });
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.template-fixer-tooltip').remove();
            });
        }
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        TemplateFixer.init();
    });

})(jQuery);