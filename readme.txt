=== WooCommerce Template Fixer ===
Contributors: keepxdev
Donate link: https://wordpress.org/support/users/keepxdev/
Tags: woocommerce, templates, template-fixer, outdated-templates, woocommerce-compatibility
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 5.0
WC tested up to: 9.9

Automatically updates outdated WooCommerce templates while maintaining customizations and security.

== Description ==

**WooCommerce Template Fixer** is the definitive solution for one of the most common problems in WooCommerce stores: **outdated templates that compromise security and functionality**.

### 🚨 Why do you need this plugin?

When WooCommerce updates, your theme templates may become outdated, causing:
- **Security vulnerabilities**
- **Functional incompatibilities**
- **Checkout and payment problems**
- **Loss of new features**

### ✨ Main Features

**🔍 Intelligent Scanning**
- Automatic detection of outdated templates
- Risk analysis by severity (Critical, High, Medium, Low)
- Theme customization identification
- WooCommerce compatibility verification

**🛡️ Safe Updates**
- Automatic preservation of theme customizations
- Automatic backups before each update
- Intelligent application of theme styles (Bootstrap, CSS classes)
- Maintenance of custom text domains

**📦 Backup Management**
- Automatic backups with complete metadata
- One-click restoration
- Automatic cleanup of old backups
- ZIP backup export

**🚨 Notification System**
- Real-time alerts for critical templates
- Configurable email notifications
- WordPress dashboard widget
- Admin bar indicators

**⚙️ Advanced Automation**
- Scheduled daily scanning
- Automatic updating of safe templates
- Detailed logs of all operations
- Granular behavior configuration

### 🎯 Ideal Use Cases

**For Theme Developers:**
- Maintain compatibility with latest WooCommerce versions
- Preserve customizations while updating core functionality
- Automate template maintenance process

**For Site Administrators:**
- Remove outdated template warnings
- Maintain security without deep technical knowledge
- Monitor template status from dashboard

**For Web Agencies:**
- Centralized management of multiple client sites
- Automation of maintenance tasks
- Detailed template status reports

### 🔧 Special Compatibility

This plugin has been developed and tested specifically with:
- **Theme** - Full support for Bootstrap structure
- **WooCommerce 9.9+** - Fully compatible
- **WordPress 6.0+** - Optimized for latest versions

### 🚀 Recommended Setup

1. **Install and activate** the plugin
2. **Configure daily scanning** in Settings
3. **Enable email notifications**
4. **Run first scan** to identify outdated templates
5. **Create complete backup** before first update
6. **Update critical templates** first

### 📊 Metrics and Reports

- Visual dashboard with real-time statistics
- Complete update history in logs
- Risk analysis by template
- WooCommerce compatibility reports

### 🛠️ Technology

- **Recursive scanner** of template directories
- **Version analysis** using advanced regex
- **Customization detection** by content comparison
- **Intelligent preservation** of theme modifications
- **Hook system** for extensibility

== Installation ==

### Automatic Installation

1. Go to **Plugins > Add New** in your WordPress dashboard
2. Search for "WooCommerce Template Fixer"
3. Click **Install Now**
4. **Activate** the plugin

### Manual Installation

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. **Activate** the plugin

### Initial Setup

1. Go to **Template Fixer** in the administration menu
2. Click **Scan Templates** for the first analysis
3. Review the detected outdated templates
4. Configure options in **Settings**

== Frequently Asked Questions ==

= Is it safe to update templates automatically? =

Yes, the plugin creates automatic backups before each update and preserves theme customizations. You can always restore the previous state if something goes wrong.

= What happens to my theme customizations? =

The plugin automatically detects customizations such as Bootstrap CSS classes, custom text domains, and theme-specific HTML structure, preserving them during updates.

= Does it work with all themes? =

The plugin works with any WordPress theme, but has special optimizations for themes that use Bootstrap and specific structures like uTech.

= Can I undo an update? =

Yes, each update generates an automatic backup that you can restore with one click from the Backup Management section.

= Does the plugin affect site performance? =

No, the plugin only runs in the admin area and scheduled scans are very lightweight. It does not affect the speed of your store's frontend.

= Which templates are considered "critical"? =

Critical templates are those with significant major version differences or that contain security-related functionalities, such as login forms, checkout, and payment processes.

= Can I exclude certain templates from updates? =

Yes, in the settings you can specify a list of templates that should not be automatically updated.

= Does the plugin work with multisite? =

Yes, it includes special features for centralized management in multisite installations.

== Screenshots ==

1. **Dashboard Principal** - Vista general con estadísticas y botones de acción
2. **Resultados del Escaneo** - Lista detallada de templates obsoletos con niveles de riesgo
3. **Gestión de Backups** - Interfaz para crear, restaurar y gestionar backups
4. **Configuración** - Opciones detalladas para personalizar el comportamiento
5. **Logs de Actividad** - Historial completo de todas las operaciones
6. **Notificaciones** - Alertas en tiempo real sobre templates críticos

== Changelog ==

= 2.0.0 =
**Major Release - 2024-12-01**

**🚀 New Features:**
- Complete redesign of admin interface with modern responsive design
- Enhanced backup management with full metadata support
- Advanced template scanner with risk level analysis
- Intelligent customization preservation for popular themes
- Automated cache clearing for popular cache plugins
- Real-time dashboard with visual statistics
- Email notification system for critical updates
- WordPress admin bar notifications
- Multisite compatibility improvements

**🔧 Technical Improvements:**
- WordPress 6.4 compatibility
- WooCommerce 9.9+ support
- PHP 8.3 compatibility
- HPOS (High-Performance Order Storage) support
- Improved security with enhanced nonce verification
- Better database query optimization
- Complete code refactoring following WordPress standards

**🛡️ Security Enhancements:**
- Enhanced input sanitization and escaping
- Improved file access permissions
- Secure backup storage with .htaccess protection
- Better user capability checks

**📱 User Experience:**
- Mobile-responsive admin interface
- Intuitive card-based dashboard
- Progress indicators for long operations
- Contextual help and tooltips
- Improved error messaging

= 1.0.0 =
**Initial Release - 2024-01-15**

**🎉 Funcionalidades Principales:**
- Escáner inteligente de templates obsoletos
- Sistema de actualizaciones con preservación de personalizaciones
- Gestión completa de backups con metadatos
- Interfaz de administración con dashboard visual
- Sistema de notificaciones por email y navegador
- Logs detallados de todas las operaciones

**🔧 Características Técnicas:**
- Soporte para WooCommerce 9.9+
- Compatibilidad con WordPress 6.0+
- Optimización especial para tema uTech
- API REST para integraciones futuras
- Hooks y filtros para desarrolladores

**🛡️ Seguridad:**
- Verificación de permisos granular
- Sanitización completa de inputs
- Nonces para todas las operaciones AJAX
- Protección de archivos de backup

**📱 Experiencia de Usuario:**
- Interfaz responsive para móviles
- Animaciones y transiciones fluidas
- Tooltips informativos
- Confirmaciones antes de acciones críticas

== Upgrade Notice ==

= 1.0.0 =
Versión inicial del plugin. Instala para comenzar a solucionar templates obsoletos de WooCommerce de forma automática y segura.

== Developer Information ==

### Hooks y Filtros Disponibles

**Filtros:**
- `wc_template_fixer_scan_results` - Modificar resultados del escaneo
- `wc_template_fixer_backup_directory` - Cambiar directorio de backups
- `wc_template_fixer_preserve_customizations` - Control de preservación
- `wc_template_fixer_risk_calculation` - Personalizar cálculo de riesgo

**Acciones:**
- `wc_template_fixer_before_update` - Antes de actualizar template
- `wc_template_fixer_after_update` - Después de actualizar template
- `wc_template_fixer_backup_created` - Cuando se crea un backup
- `wc_template_fixer_template_restored` - Cuando se restaura un template

### API de Funciones

```php
// Escanear templates programáticamente
$scanner = new WC_Template_Fixer_Scanner();
$outdated = $scanner->scan_outdated_templates();

// Actualizar template específico
$updater = new WC_Template_Fixer_Updater();
$result = $updater->update_template('cart/cart.php', true);

// Crear backup
$backup_manager = new WC_Template_Fixer_Backup_Manager();
$backup_path = $backup_manager->create_backup('checkout/form-checkout.php');
```

### Contribuir al Desarrollo

El código fuente está disponible en GitHub. Las contribuciones son bienvenidas:
- Reportar bugs
- Sugerir nuevas características
- Enviar pull requests
- Mejorar documentación

**Support Forum:** https://wordpress.org/support/plugin/template-fixer-for-woocommerce/
**GitHub Repository:** https://github.com/tbg-ia/template-fixer-for-woocommerce

== Privacy Policy ==

Este plugin no recopila, almacena ni transmite datos personales de los usuarios. Toda la información procesada se mantiene localmente en tu servidor:

- Los logs de actividad se almacenan en tu base de datos local
- Los backups se guardan en tu servidor
- Las notificaciones por email se envían desde tu servidor
- No se envía información a servicios externos

El plugin respeta completamente la privacidad de tus datos y los de tus clientes.