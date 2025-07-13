/**
 * JavaScript para Notificaciones de WooCommerce Template Fixer
 */

(function($) {
    'use strict';

    const TemplateFixerNotifications = {
        init: function() {
            this.bindEvents();
            this.initDismissibleNotices();
        },

        bindEvents: function() {
            // Dismissar notificaciones
            $(document).on('click', '.wc-template-fixer-notice .dismiss-notice', this.dismissNotice.bind(this));
            
            // Auto-refresh de notificaciones críticas
            this.scheduleNotificationRefresh();
        },

        dismissNotice: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $notice = $button.closest('.notice');
            const noticeType = $notice.data('notice');
            
            // Animar salida
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
            
            // Enviar AJAX para recordar dismissal
            $.ajax({
                url: wcTemplateFixerNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_dismiss_notice',
                    nonce: wcTemplateFixerNotifications.nonce,
                    notice_type: noticeType
                }
            });
        },

        initDismissibleNotices: function() {
            // Hacer que las notificaciones sean dismissible con animación
            $('.wc-template-fixer-notice').each(function() {
                const $notice = $(this);
                
                if (!$notice.hasClass('is-dismissible')) {
                    $notice.addClass('is-dismissible');
                }
                
                // Agregar botón de cierre si no existe
                if (!$notice.find('.notice-dismiss').length) {
                    const $dismissButton = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
                    $notice.append($dismissButton);
                    
                    $dismissButton.on('click', function() {
                        $notice.fadeOut(300, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },

        scheduleNotificationRefresh: function() {
            // Verificar notificaciones críticas cada 5 minutos
            setInterval(this.checkCriticalNotifications.bind(this), 5 * 60 * 1000);
        },

        checkCriticalNotifications: function() {
            $.ajax({
                url: wcTemplateFixerNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_template_fixer_check_critical',
                    nonce: wcTemplateFixerNotifications.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_critical) {
                        this.showCriticalAlert(response.data);
                    }
                }.bind(this)
            });
        },

        showCriticalAlert: function(data) {
            // Solo mostrar si no hay notificaciones críticas visibles
            if ($('.wc-template-fixer-notice[data-notice="critical"]').length > 0) {
                return;
            }
            
            const $alert = $('<div class="notice notice-error wc-template-fixer-notice" data-notice="critical">' +
                '<p><strong>🚨 Alerta Crítica:</strong> Se han detectado ' + data.critical_count + 
                ' templates críticos que requieren actualización inmediata.</p>' +
                '<p><a href="' + data.scan_url + '" class="button button-primary">Revisar Ahora</a></p>' +
                '</div>');
            
            // Insertar al principio del admin
            if ($('#wpbody-content .wrap').length) {
                $('#wpbody-content .wrap').first().prepend($alert);
            } else {
                $('#wpbody-content').prepend($alert);
            }
            
            // Efecto de entrada
            $alert.hide().fadeIn(500);
            
            // Auto-dismiss después de 30 segundos
            setTimeout(function() {
                $alert.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 30000);
        },

        showDesktopNotification: function(title, message, options) {
            // Verificar soporte para notificaciones del navegador
            if (!("Notification" in window)) {
                return false;
            }
            
            // Solicitar permiso si es necesario
            if (Notification.permission === "default") {
                Notification.requestPermission().then(function(permission) {
                    if (permission === "granted") {
                        this.createDesktopNotification(title, message, options);
                    }
                }.bind(this));
            } else if (Notification.permission === "granted") {
                this.createDesktopNotification(title, message, options);
            }
        },

        createDesktopNotification: function(title, message, options) {
            const defaultOptions = {
                icon: '/wp-admin/images/wordpress-logo.svg',
                badge: '/wp-admin/images/wordpress-logo.svg',
                tag: 'wc-template-fixer',
                requireInteraction: true
            };
            
            const notification = new Notification(title, Object.assign(defaultOptions, options, {
                body: message
            }));
            
            // Hacer clic para ir al plugin
            notification.onclick = function() {
                window.focus();
                window.location.href = options.url || '/wp-admin/admin.php?page=wc-template-fixer';
                notification.close();
            };
            
            // Auto-close después de 10 segundos
            setTimeout(function() {
                notification.close();
            }, 10000);
        },

        addNotificationSound: function() {
            // Crear elemento de audio para notificaciones
            if (!$('#wc-template-fixer-notification-sound').length) {
                const $audio = $('<audio id="wc-template-fixer-notification-sound" preload="auto">' +
                    '<source src="' + wcTemplateFixerNotifications.pluginUrl + 'assets/sounds/notification.mp3" type="audio/mpeg">' +
                    '<source src="' + wcTemplateFixerNotifications.pluginUrl + 'assets/sounds/notification.ogg" type="audio/ogg">' +
                    '</audio>');
                $('body').append($audio);
            }
        },

        playNotificationSound: function() {
            const audio = document.getElementById('wc-template-fixer-notification-sound');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(function() {
                    // Falló la reproducción (probablemente por política del navegador)
                });
            }
        },

        createFloatingNotification: function(message, type, duration) {
            const $notification = $('<div class="wc-template-fixer-floating-notification ' + type + '">' +
                '<div class="notification-content">' + message + '</div>' +
                '<button class="notification-close">&times;</button>' +
                '</div>');
            
            // Estilos inline para la notificación flotante
            $notification.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: type === 'success' ? '#00a32a' : '#d63638',
                color: 'white',
                padding: '15px 20px',
                borderRadius: '6px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                zIndex: 99999,
                maxWidth: '400px',
                transform: 'translateX(100%)',
                transition: 'transform 0.3s ease'
            });
            
            $('body').append($notification);
            
            // Animar entrada
            setTimeout(function() {
                $notification.css('transform', 'translateX(0)');
            }, 100);
            
            // Botón de cerrar
            $notification.find('.notification-close').on('click', function() {
                $notification.css('transform', 'translateX(100%)');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            });
            
            // Auto-remove
            if (duration) {
                setTimeout(function() {
                    $notification.css('transform', 'translateX(100%)');
                    setTimeout(function() {
                        $notification.remove();
                    }, 300);
                }, duration);
            }
        },

        updateAdminBarNotification: function(count, criticalCount) {
            const $adminBarItem = $('#wp-admin-bar-wc-template-fixer');
            
            if (count === 0) {
                $adminBarItem.fadeOut();
                return;
            }
            
            const icon = criticalCount > 0 ? '🚨' : '⚠️';
            const newTitle = icon + ' ' + count + ' Templates';
            
            $adminBarItem.find('.ab-item').first().text(newTitle);
            
            // Animar para llamar la atención si hay críticos
            if (criticalCount > 0) {
                $adminBarItem.addClass('critical-pulse');
                setTimeout(function() {
                    $adminBarItem.removeClass('critical-pulse');
                }, 2000);
            }
        },

        initProgressiveNotifications: function() {
            // Sistema de notificaciones progresivas
            // Primero: notificación sutil
            // Después: más prominente
            // Finalmente: crítica
            
            const checkCount = parseInt(localStorage.getItem('wc_template_fixer_check_count') || '0');
            const lastCheck = parseInt(localStorage.getItem('wc_template_fixer_last_check') || '0');
            const now = Date.now();
            
            // Si han pasado más de 24 horas, incrementar contador
            if (now - lastCheck > 24 * 60 * 60 * 1000) {
                const newCount = checkCount + 1;
                localStorage.setItem('wc_template_fixer_check_count', newCount.toString());
                localStorage.setItem('wc_template_fixer_last_check', now.toString());
                
                // Aumentar urgencia según el contador
                if (newCount >= 7) {
                    this.showUrgentNotification();
                } else if (newCount >= 3) {
                    this.showModerateNotification();
                }
            }
        },

        showUrgentNotification: function() {
            this.createFloatingNotification(
                '🚨 You have critical WooCommerce templates that have not been updated for more than a week. ' +
                '<a href="/wp-admin/admin.php?page=wc-template-fixer" style="color: #fff; text-decoration: underline;">Update now</a>',
                'error',
                0 // No auto-dismiss
            );
            
            this.playNotificationSound();
        },

        showModerateNotification: function() {
            this.createFloatingNotification(
                'Recordatorio: Tienes templates WooCommerce pendientes de actualización. ' +
                '<a href="/wp-admin/admin.php?page=wc-template-fixer" style="color: #fff; text-decoration: underline;">Revisar</a>',
                'warning',
                8000
            );
        }
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        TemplateFixerNotifications.init();
    });

})(jQuery);