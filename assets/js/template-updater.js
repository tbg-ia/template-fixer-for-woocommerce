/**
 * Template Updater JavaScript
 */
(function($) {
    'use strict';
    
    let selectedCount = 0;
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        initializeTemplateUpdater();
    });
    
    /**
     * Inicializar el Template Updater
     */
    function initializeTemplateUpdater() {
        // Event listeners para checkboxes
        $('.template-checkbox input[type="checkbox"]').on('change', updateSelectedCount);
        
        // Update initial counter
        updateSelectedCount();
        
        // Configurar tooltips si están disponibles
        if ($.fn.tooltip) {
            $('.template-info').tooltip();
        }
    }
    
    /**
     * Update selected templates counter
     */
    function updateSelectedCount() {
        const checkboxes = $('.template-checkbox input[type="checkbox"]:checked:not([disabled])');
        selectedCount = checkboxes.length;
        $('#selected-templates-count').text(selectedCount);
        
        // Habilitar/deshabilitar botones según selección
        const hasSelection = selectedCount > 0;
        $('.batch-actions .button').prop('disabled', !hasSelection);
        
        if (hasSelection) {
            $('.batch-actions .button').removeClass('disabled');
        } else {
            $('.batch-actions .button').addClass('disabled');
        }
    }
    
    /**
     * Seleccionar solo templates críticos
     */
    window.selectCriticalTemplates = function() {
        clearSelection();
        const criticalCheckboxes = $('input[data-status="critical"]:not([disabled])');
        criticalCheckboxes.prop('checked', true);
        updateSelectedCount();
        
        // Mostrar mensaje
        showNotice('Templates críticos seleccionados', 'info');
    };
    
    /**
     * Seleccionar templates recomendados
     */
    window.selectRecommendedTemplates = function() {
        clearSelection();
        const recommendedSelectors = [
            'input[data-status="critical"]:not([disabled])',
            'input[value="cart/cart.php"]:not([disabled])',
            'input[value="cart/cart-totals.php"]:not([disabled])',
            'input[value="cart/mini-cart.php"]:not([disabled])',
            'input[value="single-product/add-to-cart/simple.php"]:not([disabled])',
            'input[value="single-product/add-to-cart/variable.php"]:not([disabled])'
        ];
        
        recommendedSelectors.forEach(selector => {
            $(selector).prop('checked', true);
        });
        
        updateSelectedCount();
        showNotice('Templates recomendados seleccionados', 'info');
    };
    
    /**
     * Seleccionar todos los templates
     */
    window.selectAllTemplates = function() {
        const checkboxes = $('.template-checkbox input[type="checkbox"]:not([disabled])');
        checkboxes.prop('checked', true);
        updateSelectedCount();
        
        showNotice('Todos los templates seleccionados', 'info');
    };
    
    /**
     * Limpiar selección
     */
    window.clearSelection = function() {
        const checkboxes = $('.template-checkbox input[type="checkbox"]');
        checkboxes.prop('checked', false);
        updateSelectedCount();
        
        showNotice('Selección limpiada', 'info');
    };
    
    /**
     * Confirmar actualización
     */
    window.confirmUpdate = function() {
        if (selectedCount === 0) {
            showNotice('Please select at least one template to update.', 'error');
            return false;
        }
        
        const criticalCount = $('input[data-status="critical"]:checked').length;
        const needsUpdateCount = $('input[data-status="needs_update"]:checked').length;
        
        let message = `Are you sure you want to update ${selectedCount} template(s)?\n\n`;
        
        if (criticalCount > 0) {
            message += `🔥 Critical templates: ${criticalCount}\n`;
        }
        if (needsUpdateCount > 0) {
            message += `🟡 Templates that need update: ${needsUpdateCount}\n`;
        }
        
        message += '\n✅ Automatic backups will be created\n';
        message += '✅ Theme customizations will be preserved\n';
        message += '✅ Bootstrap improvements will be applied\n\n';
        message += 'This action may take several minutes.';
        
        return confirm(message);
    };
    
    /**
     * Vista previa de cambios
     */
    window.previewChanges = function() {
        if (selectedCount === 0) {
            showNotice('Please select at least one template to preview.', 'error');
            return;
        }
        
        const selectedTemplates = getSelectedTemplates();
        
        $.ajax({
            url: wcTemplateUpdater.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_template_preview_changes',
                templates: selectedTemplates,
                nonce: wcTemplateUpdater.nonce
            },
            success: function(response) {
                if (response.success) {
                    showPreviewModal(response.data);
                } else {
                    showNotice('Error generating preview: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error while generating preview.', 'error');
            }
        });
    };
    
    /**
     * Validar templates
     */
    window.validateTemplates = function() {
        if (selectedCount === 0) {
            showNotice('Please select at least one template to validate.', 'error');
            return;
        }
        
        const selectedTemplates = getSelectedTemplates();
        
        $.ajax({
            url: wcTemplateUpdater.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_template_validate',
                templates: selectedTemplates,
                nonce: wcTemplateUpdater.nonce
            },
            success: function(response) {
                if (response.success) {
                    showValidationResults(response.data);
                } else {
                    showNotice('Error validating templates: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error while validating templates.', 'error');
            }
        });
    };
    
    /**
     * Obtener templates seleccionados
     */
    function getSelectedTemplates() {
        const selected = [];
        $('.template-checkbox input[type="checkbox"]:checked:not([disabled])').each(function() {
            selected.push($(this).val());
        });
        return selected;
    }
    
    /**
     * Mostrar modal de vista previa
     */
    function showPreviewModal(previewData) {
        const modal = $(`
            <div class="wc-template-modal-overlay">
                <div class="wc-template-modal">
                    <div class="modal-header">
                        <h2>🔍 Preview Changes</h2>
                        <button class="modal-close" onclick="closePreviewModal()">&times;</button>
                    </div>
                    <div class="modal-content">
                        <p>The following templates will be updated:</p>
                        <div class="preview-list"></div>
                        <div class="modal-footer">
                            <button class="button button-secondary" onclick="closePreviewModal()">Close</button>
                            <button class="button button-primary" onclick="closePreviewModal(); $('#template-updater-form').submit();">Proceed with Update</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        const previewList = modal.find('.preview-list');
        previewData.forEach(item => {
            const previewItem = $(`
                <div class="preview-item">
                    <div class="preview-template-name">${item.template}</div>
                    <div class="preview-risk">Risk: ${item.risk.toUpperCase()}</div>
                    <div class="preview-changes">
                        <h4>Changes that will be applied:</h4>
                        <ul>
                            ${item.changes.map(change => `<li>✅ ${change}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `);
            previewList.append(previewItem);
        });
        
        $('body').append(modal);
        modal.fadeIn(300);
    }
    
    /**
     * Cerrar modal de vista previa
     */
    window.closePreviewModal = function() {
        $('.wc-template-modal-overlay').fadeOut(300, function() {
            $(this).remove();
        });
    };
    
    /**
     * Mostrar resultados de validación
     */
    function showValidationResults(validationData) {
        const validCount = validationData.filter(item => item.valid && item.can_update).length;
        const invalidCount = validationData.length - validCount;
        
        let message = `Validation completed:\n\n`;
        message += `✅ Valid templates for update: ${validCount}\n`;
        if (invalidCount > 0) {
            message += `❌ Templates that require attention: ${invalidCount}\n`;
            validationData.forEach(result => {
                if (!result.can_update) {
                    $(`input[value="${result.template}"]`).prop('checked', false);
                }
            });
            updateSelectedCount();
            showNotice('Templates inválidos deseleccionados', 'info');
        }
    }
    
    /**
     * Mostrar notificación
     */
    function showNotice(message, type = 'info') {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-info';
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible wc-template-notice">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend(notice);
        
        // Auto-dismiss después de 5 segundos
        setTimeout(() => {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Permitir dismissal manual
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Animaciones de carga
     */
    function showLoadingState(button) {
        const originalText = button.text();
        button.data('original-text', originalText);
        button.text('Procesando...').prop('disabled', true);
        
        return function() {
            button.text(originalText).prop('disabled', false);
        };
    }
    
    /**
     * Manejar actualizaciones por lotes
     */
    function handleBatchUpdate() {
        const selectedTemplates = getSelectedTemplates();
        
        if (selectedTemplates.length === 0) {
            showNotice('No hay templates seleccionados', 'error');
            return;
        }
        
        const progressModal = showProgressModal();
        
        $.ajax({
            url: wcTemplateUpdater.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_template_mass_update',
                templates: selectedTemplates,
                nonce: wcTemplateUpdater.nonce
            },
            success: function(response) {
                if (response.success) {
                    showUpdateResults(response.data);
                } else {
                    showNotice('Error en actualización: ' + response.data, 'error');
                }
                progressModal.close();
            },
            error: function() {
                showNotice('Error de conexión durante la actualización.', 'error');
                progressModal.close();
            }
        });
    }
    
    /**
     * Mostrar modal de progreso
     */
    function showProgressModal() {
        const modal = $(`
            <div class="wc-template-modal-overlay">
                <div class="wc-template-modal">
                    <div class="modal-header">
                        <h2>🚀 Actualizando Templates</h2>
                    </div>
                    <div class="modal-content">
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%;"></div>
                            </div>
                            <div class="progress-text">Iniciando actualización...</div>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.fadeIn(300);
        
        // Simular progreso
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            
            modal.find('.progress-fill').css('width', progress + '%');
            modal.find('.progress-text').text(`Procesando... ${Math.round(progress)}%`);
        }, 500);
        
        return {
            close: function() {
                clearInterval(interval);
                modal.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        };
    }
    
    /**
     * Mostrar resultados de actualización
     */
    function showUpdateResults(results) {
        const message = `
            Actualización completada:\\n\\n
            ✅ Exitosos: ${results.success_count}\\n
            ❌ Errores: ${results.error_count}\\n
            📊 Total: ${results.total_processed}\\n\\n
            ${results.success_count > 0 ? 'Los templates han sido actualizados correctamente.' : ''}
        `;
        
        showNotice(message, results.error_count > 0 ? 'error' : 'success');
        
        // Recargar página después de 3 segundos
        setTimeout(() => {
            location.reload();
        }, 3000);
    }
    
})(jQuery);