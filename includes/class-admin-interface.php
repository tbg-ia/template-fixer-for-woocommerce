<?php
/**
 * Admin Interface for WooCommerce Template Fixer
 * 
 * @package WC_Template_Fixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Template_Fixer_Admin_Interface {
    
    private $scanner;
    private $updater;
    private $backup_manager;
    
    public function __construct() {
        // Initialize classes with checks
        if ( class_exists( 'WC_Template_Fixer_Scanner' ) ) {
            $this->scanner = new WC_Template_Fixer_Scanner();
        }
        if ( class_exists( 'WC_Template_Fixer_Updater' ) ) {
            $this->updater = new WC_Template_Fixer_Updater();
        }
        if ( class_exists( 'WC_Template_Fixer_Backup_Manager' ) ) {
            $this->backup_manager = new WC_Template_Fixer_Backup_Manager();
        }
        
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_wc_template_fixer_scan', array( $this, 'ajax_scan_templates' ) );
        add_action( 'wp_ajax_wc_template_fixer_update', array( $this, 'ajax_update_templates' ) );
        add_action( 'wp_ajax_wc_template_fixer_backup', array( $this, 'ajax_create_backup' ) );
        add_action( 'wp_ajax_wc_template_fixer_restore', array( $this, 'ajax_restore_backup' ) );
        add_action( 'wp_ajax_wc_template_fixer_download_backup', array( $this, 'ajax_download_backup' ) );
        add_action( 'wp_ajax_wc_template_fixer_quick_stats', array( $this, 'ajax_quick_stats' ) );
        add_action( 'wp_ajax_wc_template_fixer_quick_update', array( $this, 'ajax_quick_update' ) );
        
        // Integration with WC Template Updater legacy
        add_action( 'admin_init', array( $this, 'integrate_with_legacy_updater' ) );
    }
    
    /**
     * Add administration menus
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'WooCommerce Template Manager',
            'WC Templates',
            'manage_woocommerce',
            'wc-template-fixer',
            array( $this, 'main_page' ),
            'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill="black" d="M18 16h-6v-1h6v1zm-8-1v1H2v-1h8zM2 9h8v1H2V9zm10 0h6v1h-6V9zM2 4h16v1H2V4zm0 8h6v1H2v-1zm8 0h8v1h-8v-1zm-8-4h12v1H2V8zm14 0h2v1h-2V8z"/></svg>'),
            26
        );
        
        // Submenus
        add_submenu_page(
            'wc-template-fixer',
            'Template Dashboard',
            '📊 Dashboard',
            'manage_woocommerce',
            'wc-template-fixer',
            array( $this, 'main_page' )
        );
        
        add_submenu_page(
            'wc-template-fixer',
            'Template Backups',
            '💾 Backups',
            'manage_woocommerce',
            'wc-template-fixer-backups',
            array( $this, 'backups_page' )
        );
        
        add_submenu_page(
            'wc-template-fixer',
            'Configuration',
            '⚙️ Settings',
            'manage_woocommerce',
            'wc-template-fixer-settings',
            array( $this, 'settings_page' )
        );
        
        add_submenu_page(
            'wc-template-fixer',
            'Update History',
            '📋 History',
            'manage_woocommerce',
            'wc-template-fixer-logs',
            array( $this, 'logs_page' )
        );
    }
    
    /**
     * Load admin scripts and styles
 */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'wc-template-fixer' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'wc-template-fixer-admin',
            WC_TEMPLATE_FIXER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_TEMPLATE_FIXER_VERSION
        );
        
        wp_enqueue_script(
            'wc-template-fixer-admin',
            WC_TEMPLATE_FIXER_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WC_TEMPLATE_FIXER_VERSION,
            true
        );
        
        wp_localize_script( 'wc-template-fixer-admin', 'wcTemplateFixer', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wc_template_fixer_nonce' ),
            'strings' => array(
                'scanning' => __( 'Scanning templates...', 'template-fixer-for-woocommerce' ),
                'updating' => __( 'Updating templates...', 'template-fixer-for-woocommerce' ),
                'confirm_update' => __( 'Are you sure you want to update these templates?', 'template-fixer-for-woocommerce' ),
                'confirm_backup' => __( 'Create backup of all templates?', 'template-fixer-for-woocommerce' ),
            )
        ) );
    }
    
    /**
     * Main page - Template scanner
     */
    public function main_page() {
        ?>
        <div class="wrap wc-template-fixer-modern">
            <!-- Header Section -->
            <div class="header-section">
                <div class="header-content">
                    <div class="header-text">
                        <h1 class="page-title">
                            <span class="title-icon">🔧</span>
                            WooCommerce Template Fixer
                            <span class="version-badge">v<?php echo esc_html( WC_TEMPLATE_FIXER_VERSION ); ?></span>
                        </h1>
                        <p class="page-description">
                            Detect and automatically fix outdated WooCommerce template files while preserving your theme customizations.
                        </p>
                    </div>
                    <div class="header-actions">
                        <button id="refresh-stats" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span> Refresh
                        </button>
                        <button id="view-help" class="button button-secondary">
                            <span class="dashicons dashicons-editor-help"></span> Help
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card total" id="total-templates">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-portfolio"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <span class="loading-placeholder">-</span>
                        </div>
                        <div class="stat-label">Total Templates</div>
                        <div class="stat-description">Found in theme</div>
                    </div>
                </div>

                <div class="stat-card outdated" id="outdated-templates">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <span class="loading-placeholder">-</span>
                        </div>
                        <div class="stat-label">Outdated</div>
                        <div class="stat-description">Need updating</div>
                    </div>
                </div>

                <div class="stat-card critical" id="critical-templates">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <span class="loading-placeholder">-</span>
                        </div>
                        <div class="stat-label">Critical</div>
                        <div class="stat-description">Security risk</div>
                    </div>
                </div>

                <div class="stat-card safe" id="safe-templates">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <span class="loading-placeholder">-</span>
                        </div>
                        <div class="stat-label">Up to Date</div>
                        <div class="stat-description">All good</div>
                    </div>
                </div>
            </div>

            <!-- Main Action Panel -->
            <div class="action-panel">
                <div class="action-panel-header">
                    <h2>Quick Actions</h2>
                    <p>Perform template scanning and maintenance tasks</p>
                </div>
                
                <div class="action-buttons-grid">
                    <button id="scan-templates" class="action-btn primary">
                        <div class="btn-icon">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                        <div class="btn-content">
                            <div class="btn-title">Scan Templates</div>
                            <div class="btn-description">Check for outdated files</div>
                        </div>
                    </button>

                    <button id="update-critical" class="action-btn danger" disabled>
                        <div class="btn-icon">
                            <span class="dashicons dashicons-shield"></span>
                        </div>
                        <div class="btn-content">
                            <div class="btn-title">Fix Critical</div>
                            <div class="btn-description">Update security templates</div>
                        </div>
                    </button>

                    <button id="create-full-backup" class="action-btn secondary">
                        <div class="btn-icon">
                            <span class="dashicons dashicons-backup"></span>
                        </div>
                        <div class="btn-content">
                            <div class="btn-title">Create Backup</div>
                            <div class="btn-description">Backup all templates</div>
                        </div>
                    </button>

                    <button id="update-safe-templates" class="action-btn success" disabled>
                        <div class="btn-icon">
                            <span class="dashicons dashicons-update-alt"></span>
                        </div>
                        <div class="btn-content">
                            <div class="btn-title">Update Safe</div>
                            <div class="btn-description">Update low-risk templates</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Progress Section -->
            <div id="scan-progress" class="progress-section" style="display: none;">
                <div class="progress-header">
                    <h3>Scanning Templates...</h3>
                    <button id="cancel-scan" class="button button-secondary">Cancel</button>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-percentage">0%</div>
                </div>
                <div class="progress-details">
                    <div class="progress-text">Initializing scan...</div>
                    <div class="progress-stats">
                        <span class="scanned-count">0</span> / <span class="total-count">0</span> templates processed
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div id="scan-results" class="results-section" style="display: none;">
                <div class="results-header">
                    <h2>Scan Results</h2>
                    <div class="results-actions">
                        <button id="export-results" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span> Export
                        </button>
                        <button id="print-results" class="button button-secondary">
                            <span class="dashicons dashicons-printer"></span> Print
                        </button>
                    </div>
                </div>
                
                <div class="results-filter">
                    <div class="filter-controls">
                        <select id="filter-risk" class="filter-select">
                            <option value="">All Risk Levels</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        
                        <select id="filter-status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="outdated">Outdated</option>
                            <option value="updated">Up to Date</option>
                        </select>
                        
                        <input type="text" id="search-templates" placeholder="Search templates..." class="filter-search">
                        
                        <button id="clear-filters" class="button button-secondary">Clear</button>
                    </div>
                </div>

                <div id="templates-list" class="templates-container">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>

            <!-- Help Panel -->
            <div id="help-panel" class="help-panel" style="display: none;">
                <div class="help-content">
                    <h3>How to Use Template Fixer</h3>
                    <div class="help-steps">
                        <div class="help-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Scan Templates</h4>
                                <p>Click "Scan Templates" to check for outdated WooCommerce template files in your theme.</p>
                            </div>
                        </div>
                        <div class="help-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Review Results</h4>
                                <p>Check the scan results and identify which templates need updating based on risk levels.</p>
                            </div>
                        </div>
                        <div class="help-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Create Backup</h4>
                                <p>Always create a backup before updating templates to ensure you can restore if needed.</p>
                            </div>
                        </div>
                        <div class="help-step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Update Templates</h4>
                                <p>Start with critical templates, then update others based on your needs and risk tolerance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .wc-template-fixer-modern {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin: 0 -20px 20px -20px;
            padding: 30px 20px;
            border-radius: 0 0 12px 12px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 28px;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }

        .title-icon {
            font-size: 32px;
        }

        .version-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: normal;
        }

        .page-description {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
            max-width: 600px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .header-actions .button {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 6px;
        }

        .header-actions .button:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-card.total .stat-icon {
            background: #f0f9ff;
            color: #0284c7;
        }

        .stat-card.outdated .stat-icon {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-card.critical .stat-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-card.safe .stat-icon {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 2px;
        }

        .stat-description {
            font-size: 12px;
            color: #6b7280;
        }

        .loading-placeholder {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading-shimmer 2s infinite;
            border-radius: 4px;
            display: inline-block;
            min-width: 40px;
            height: 1em;
        }

        @keyframes loading-shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* Action Panel */
        .action-panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .action-panel-header {
            margin-bottom: 24px;
        }

        .action-panel-header h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: #1f2937;
        }

        .action-panel-header p {
            margin: 0;
            color: #6b7280;
        }

        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            border: 2px solid transparent;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            position: relative;
            overflow: hidden;
        }

        .action-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .action-btn:hover:before {
            left: 100%;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .action-btn.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
        }

        .action-btn.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8fafc !important;
            color: #94a3b8 !important;
        }

        .action-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-icon {
            font-size: 24px;
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .btn-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .btn-description {
            font-size: 14px;
            opacity: 0.8;
        }

        /* Progress Section */
        .progress-section {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .progress-bar-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .progress-bar {
            flex: 1;
            height: 8px;
            background: #f1f5f9;
            border-radius: 8px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 8px;
        }

        .progress-percentage {
            font-weight: 600;
            color: #3b82f6;
            min-width: 40px;
        }

        .progress-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-text {
            color: #6b7280;
        }

        .progress-stats {
            font-size: 14px;
            color: #9ca3af;
        }

        /* Results Section */
        .results-section {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .results-actions {
            display: flex;
            gap: 10px;
        }

        .results-filter {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .filter-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select, .filter-search {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-search {
            min-width: 200px;
        }

        /* Help Panel */
        .help-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            overflow-y: auto;
            transition: right 0.3s ease;
            z-index: 9999;
        }

        .help-panel.active {
            right: 0;
        }

        .help-steps {
            margin-top: 20px;
        }

        .help-step {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .step-content h4 {
            margin: 0 0 8px 0;
            color: #1f2937;
        }

        .step-content p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons-grid {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-search {
                min-width: auto;
            }

            .help-panel {
                width: 100%;
                right: -100%;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Initialize interface
            initializeTemplateFixer();
            
            // Auto-load stats on page load
            loadTemplateStats();
            
            // Event handlers
            $('#scan-templates').on('click', startTemplateScan);
            $('#refresh-stats').on('click', loadTemplateStats);
            $('#view-help').on('click', toggleHelpPanel);
            $('#create-full-backup').on('click', createFullBackup);
            $('#update-critical').on('click', updateCriticalTemplates);
            $('#update-safe-templates').on('click', updateSafeTemplates);
            
            // Filter handlers
            $('#filter-risk, #filter-status').on('change', applyFilters);
            $('#search-templates').on('input', debounce(applyFilters, 300));
            $('#clear-filters').on('click', clearFilters);

            function initializeTemplateFixer() {
                console.log('Template Fixer initialized');
                
                // Add loading animations
                $('.loading-placeholder').each(function() {
                    $(this).addClass('loading');
                });
            }

            function loadTemplateStats() {
                $('#refresh-stats').prop('disabled', true);
                
                $.ajax({
                    url: wcTemplateFixer.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_template_fixer_quick_stats',
                        nonce: wcTemplateFixer.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateStatsDisplay(response.data);
                        }
                    },
                    complete: function() {
                        $('#refresh-stats').prop('disabled', false);
                    }
                });
            }

            function updateStatsDisplay(stats) {
                // Animate number updates
                animateNumber('#total-templates .stat-number', stats.total_theme_templates || 0);
                animateNumber('#outdated-templates .stat-number', stats.outdated_templates || 0);
                animateNumber('#critical-templates .stat-number', stats.risk_levels?.critical || 0);
                animateNumber('#safe-templates .stat-number', (stats.total_theme_templates || 0) - (stats.outdated_templates || 0));
                
                // Update button states
                $('#update-critical').prop('disabled', !stats.risk_levels?.critical);
                $('#update-safe-templates').prop('disabled', !stats.safe_to_update);
                
                // Update stat card styles based on values
                updateStatCardStyles(stats);
            }

            function animateNumber(selector, targetNumber) {
                const $element = $(selector + ' .loading-placeholder');
                $element.removeClass('loading');
                
                $({ counter: 0 }).animate({ counter: targetNumber }, {
                    duration: 1000,
                    easing: 'swing',
                    step: function() {
                        $element.text(Math.floor(this.counter));
                    },
                    complete: function() {
                        $element.text(targetNumber);
                    }
                });
            }

            function updateStatCardStyles(stats) {
                // Add pulse animation for critical items
                if (stats.risk_levels?.critical > 0) {
                    $('#critical-templates').addClass('pulse-critical');
                }
                
                // Update outdated card urgency
                const outdatedCard = $('#outdated-templates');
                if (stats.outdated_templates > 10) {
                    outdatedCard.addClass('high-urgency');
                } else if (stats.outdated_templates > 5) {
                    outdatedCard.addClass('medium-urgency');
                }
            }

            function startTemplateScan() {
                $('#scan-templates').prop('disabled', true);
                $('#scan-progress').fadeIn();
                $('#scan-results').fadeOut();
                
                let progress = 0;
                const progressInterval = setInterval(function() {
                    progress += Math.random() * 10;
                    if (progress > 90) progress = 90;
                    
                    updateProgress(progress);
                }, 200);

                $.ajax({
                    url: wcTemplateFixer.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_template_fixer_scan',
                        nonce: wcTemplateFixer.nonce
                    },
                    success: function(response) {
                        clearInterval(progressInterval);
                        updateProgress(100);
                        
                        setTimeout(function() {
                            $('#scan-progress').fadeOut();
                            if (response.success) {
                                displayScanResults(response.data);
                                loadTemplateStats(); // Refresh stats
                            }
                        }, 500);
                    },
                    complete: function() {
                        $('#scan-templates').prop('disabled', false);
                    }
                });
            }

            function updateProgress(percentage) {
                $('.progress-fill').css('width', percentage + '%');
                $('.progress-percentage').text(Math.floor(percentage) + '%');
                
                if (percentage < 30) {
                    $('.progress-text').text('Scanning theme directory...');
                } else if (percentage < 60) {
                    $('.progress-text').text('Checking template versions...');
                } else if (percentage < 90) {
                    $('.progress-text').text('Analyzing compatibility...');
                } else {
                    $('.progress-text').text('Finalizing results...');
                }
            }

            function displayScanResults(data) {
                $('#scan-results').fadeIn();
                // Implementation for displaying results would go here
                // This would render the template list with modern cards
            }

            function toggleHelpPanel() {
                $('#help-panel').toggleClass('active');
            }

            function createFullBackup() {
                if (!confirm('Create a backup of all current templates? This may take a few minutes.')) {
                    return;
                }
                
                $('#create-full-backup').prop('disabled', true).text('Creating Backup...');
                
                $.ajax({
                    url: wcTemplateFixer.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_template_fixer_backup',
                        nonce: wcTemplateFixer.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Backup created successfully!', 'success');
                        } else {
                            showNotification('Backup failed: ' + response.data, 'error');
                        }
                    },
                    complete: function() {
                        $('#create-full-backup').prop('disabled', false).text('Create Backup');
                    }
                });
            }

            function showNotification(message, type) {
                const notification = $(`
                    <div class="template-fixer-notification ${type}">
                        <span class="notification-icon"></span>
                        <span class="notification-message">${message}</span>
                        <button class="notification-close">&times;</button>
                    </div>
                `);
                
                $('body').append(notification);
                notification.slideDown();
                
                setTimeout(function() {
                    notification.slideUp(function() {
                        $(this).remove();
                    });
                }, 5000);
                
                notification.find('.notification-close').on('click', function() {
                    notification.slideUp(function() {
                        $(this).remove();
                    });
                });
            }

            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            function applyFilters() {
                // Implementation for filtering results
                const riskFilter = $('#filter-risk').val();
                const statusFilter = $('#filter-status').val();
                const searchTerm = $('#search-templates').val();
                
                // Filter the displayed templates based on criteria
                console.log('Applying filters:', { riskFilter, statusFilter, searchTerm });
            }

            function clearFilters() {
                $('#filter-risk, #filter-status').val('');
                $('#search-templates').val('');
                applyFilters();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Backup management page
     */
    public function backups_page() {
        $backups = $this->backup_manager->list_backups();
        $stats = $this->backup_manager->get_backup_statistics();
        
        ?>
        <div class="wrap wc-template-fixer">
            <h1>📦 Backup Management</h1>
            <p class="description">Manage your WooCommerce template backups.</p>
            
            <div class="backup-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Backups</h3>
                        <div class="stat-number"><?php echo esc_html( $stats['total_backups'] ); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Used Space</h3>
                        <div class="stat-number"><?php echo esc_html( $this->format_file_size( $stats['total_size'] ) ); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Templates with Backup</h3>
                        <div class="stat-number"><?php echo count( $stats['templates_with_backups'] ); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="backup-actions">
                <button id="create-backup" class="button button-primary">
                    <span class="dashicons dashicons-backup"></span>
                    Create New Backup
                </button>
                <button id="cleanup-backups" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    Clean Old Backups
                </button>
                <button id="export-backups" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    Export Backups
                </button>
            </div>
            
            <?php if ( ! empty( $backups ) ) : ?>
            <div class="backups-table-container">
                <h2>Available Backups</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Creation Date</th>
                            <th>Original Version</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $backups as $backup ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $backup['template_name'] ); ?></code></td>
                            <td><?php echo esc_html( $backup['created_date'] ); ?></td>
                            <td><?php echo esc_html( $backup['original_version'] ?: 'N/A' ); ?></td>
                            <td><?php echo esc_html( $this->format_file_size( $backup['file_size'] ) ); ?></td>
                            <td>
                                <button class="button button-small restore-backup" 
                                        data-backup="<?php echo esc_attr( $backup['filename'] ); ?>">
                                    Restore
                                </button>
                                <button class="button button-small button-link-delete delete-backup" 
                                        data-backup="<?php echo esc_attr( $backup['filename'] ); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
            <div class="no-backups">
                <p>No backups available. Create your first backup by clicking the button above.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if ( isset( $_POST['save_settings'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wc_template_fixer_settings' ) ) {
            $this->save_settings();
        }
        
        $settings = $this->get_current_settings();
        
        ?>
        <div class="wrap wc-template-fixer">
            <h1>⚙️ Settings</h1>
            <p class="description">Configure the behavior of WooCommerce Template Fixer.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'wc_template_fixer_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Automatic Daily Scan</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_daily_scan" value="1" <?php checked( $settings['enable_daily_scan'] ); ?>>
                                Run automatic scan daily
                            </label>
                            <p class="description">Automatically detects outdated templates daily.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Automatic Update</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_update_safe_templates" value="1" <?php checked( $settings['auto_update_safe_templates'] ); ?>>
                                Automatically update safe templates
                            </label>
                            <p class="description">Automatically updates low-risk templates without customizations.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Email Notifications</th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked( $settings['email_notifications'] ); ?>>
                                Send email notifications
                            </label>
                            <p class="description">Receive alerts when outdated templates are detected.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Notification Email</th>
                        <td>
                            <input type="email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="regular-text">
                            <p class="description">Email where notifications will be sent (default: site admin).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Backup Retention</th>
                        <td>
                            <select name="backup_retention_days">
                                <option value="7" <?php selected( $settings['backup_retention_days'], 7 ); ?>>7 days</option>
                                <option value="30" <?php selected( $settings['backup_retention_days'], 30 ); ?>>30 days</option>
                                <option value="90" <?php selected( $settings['backup_retention_days'], 90 ); ?>>90 days</option>
                                <option value="365" <?php selected( $settings['backup_retention_days'], 365 ); ?>>1 year</option>
                                <option value="0" <?php selected( $settings['backup_retention_days'], 0 ); ?>>Never delete</option>
                            </select>
                            <p class="description">Time that backups will be kept before being automatically deleted.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Preserve Customizations</th>
                        <td>
                            <label>
                                <input type="checkbox" name="preserve_customizations" value="1" <?php checked( $settings['preserve_customizations'] ); ?>>
                                Preserve theme customizations by default
                            </label>
                            <p class="description">Attempts to maintain theme customizations when updating templates.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Logging Level</th>
                        <td>
                            <select name="log_level">
                                <option value="basic" <?php selected( $settings['log_level'], 'basic' ); ?>>Basic</option>
                                <option value="detailed" <?php selected( $settings['log_level'], 'detailed' ); ?>>Detailed</option>
                                <option value="debug" <?php selected( $settings['log_level'], 'debug' ); ?>>Debug</option>
                            </select>
                            <p class="description">Amount of information recorded in logs.</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Advanced Settings</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Excluded Templates</th>
                        <td>
                            <textarea name="excluded_templates" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $settings['excluded_templates'] ); ?></textarea>
                            <p class="description">List of templates that should not be automatically updated (one per line).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Delete Data on Uninstall</th>
                        <td>
                            <label>
                                <input type="checkbox" name="remove_data_on_uninstall" value="1" <?php checked( $settings['remove_data_on_uninstall'] ); ?>>
                                Delete all data and backups when uninstalling the plugin
                            </label>
                            <p class="description"><strong>Warning:</strong> This action cannot be undone.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_settings" class="button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_template_fixer_logs';
        $per_page = 50;
        $page = isset( $_GET['paged'] ) ? max( 1, intval( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $offset = ( $page - 1 ) * $per_page;
        
        // Try to get cached results first
        $cache_key = 'wc_template_fixer_logs_' . $page . '_' . $per_page;
        $logs = wp_cache_get( $cache_key, 'wc_template_fixer' );
        
        if ( false === $logs ) {
            $logs = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                "SELECT * FROM {$wpdb->prefix}wc_template_fixer_logs ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ) );
            wp_cache_set( $cache_key, $logs, 'wc_template_fixer', 300 ); // Cache for 5 minutes
        }
        
        // Try to get cached total count
        $total_cache_key = 'wc_template_fixer_logs_total';
        $total_logs = wp_cache_get( $total_cache_key, 'wc_template_fixer' );
        
        if ( false === $total_logs ) {
            $total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_template_fixer_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            wp_cache_set( $total_cache_key, $total_logs, 'wc_template_fixer', 300 ); // Cache for 5 minutes
        }
        $total_pages = ceil( $total_logs / $per_page );
        
        ?>
        <div class="wrap wc-template-fixer">
            <h1>📋 Activity Logs</h1>
            <p class="description">History of all operations performed by the plugin.</p>
            
            <div class="logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="wc-template-fixer-logs">
                    <select name="action_type">
                        <option value="">All actions</option>
                        <option value="update" <?php selected( sanitize_text_field( wp_unslash( $_GET['action_type'] ?? '' ) ), 'update' ); /* phpcs:ignore WordPress.Security.NonceVerification.Recommended */ ?>>Updates</option>
                        <option value="backup" <?php selected( sanitize_text_field( wp_unslash( $_GET['action_type'] ?? '' ) ), 'backup' ); /* phpcs:ignore WordPress.Security.NonceVerification.Recommended */ ?>>Backups</option>
                        <option value="restore" <?php selected( sanitize_text_field( wp_unslash( $_GET['action_type'] ?? '' ) ), 'restore' ); /* phpcs:ignore WordPress.Security.NonceVerification.Recommended */ ?>>Restores</option>
                    </select>
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <?php if ( ! empty( $logs ) ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Template</th>
                        <th>Action</th>
                        <th>Versions</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                        <td><code><?php echo esc_html( $log->template_name ); ?></code></td>
                        <td>
                            <span class="action-badge action-<?php echo esc_attr( $log->action_type ); ?>">
                                <?php echo esc_html( ucfirst( $log->action_type ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( $log->old_version && $log->new_version ) : ?>
                                <?php echo esc_html( $log->old_version ); ?> → <?php echo esc_html( $log->new_version ); ?>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr( $log->status ); ?>">
                                <?php echo esc_html( ucfirst( $log->status ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log->message ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post( paginate_links( array(
                        'base' => add_query_arg( 'paged', '%#%' ),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ) ) );
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else : ?>
            <p>No logs available.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    
    /**
     * AJAX: Scan templates
     */
    public function ajax_scan_templates() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        if ( ! $this->scanner ) {
            wp_send_json_error( 'Template scanner not available' );
            return;
        }
        
        $outdated_templates = $this->scanner->scan_outdated_templates();
        $stats = $this->scanner->get_template_statistics();
        
        wp_send_json_success( array(
            'outdated_templates' => $outdated_templates,
            'statistics' => $stats
        ) );
    }
    
    /**
     * AJAX: Update templates
     */
    public function ajax_update_templates() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        if ( ! $this->updater ) {
            wp_send_json_error( 'Template updater not available' );
            return;
        }
        
        $template_names = array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ?? array() ) );
        $preserve_customizations = isset( $_POST['preserve_customizations'] ) ? (bool) $_POST['preserve_customizations'] : true;
        
        $results = $this->updater->update_multiple_templates( $template_names, $preserve_customizations );
        
        wp_send_json_success( $results );
    }
    
    /**
     * AJAX: Create backup
     */
    public function ajax_create_backup() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $results = $this->backup_manager->create_full_backup();
        
        wp_send_json_success( $results );
    }
    
    /**
     * AJAX: Restore backup
     */
    public function ajax_restore_backup() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $backup_filename = sanitize_file_name( wp_unslash( $_POST['backup_filename'] ?? '' ) );
        $result = $this->backup_manager->restore_backup( $backup_filename );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }
    
    /**
     * AJAX: Download backup
     */
    public function ajax_download_backup() {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'download_backup' ) ) {
            wp_die( 'Invalid security token' );
        }
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $filename = sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) );
        $backup_path = WP_CONTENT_DIR . '/wc-template-backups/' . $filename;
        
        if ( ! file_exists( $backup_path ) || strpos( $filename, '..' ) !== false ) {
            wp_die( 'File not found' );
        }
        
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
        header( 'Content-Length: ' . filesize( $backup_path ) );
        
        // Use WP_Filesystem instead of readfile()
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $file_content = $wp_filesystem->get_contents( $backup_path );
        if ( $file_content !== false ) {
            echo $file_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File download content
        }
        exit;
    }
    
    /**
     * Get current settings
     */
    private function get_current_settings() {
        return array(
            'enable_daily_scan' => wc_template_fixer_get_option( 'enable_daily_scan', true ),
            'auto_update_safe_templates' => wc_template_fixer_get_option( 'auto_update_safe_templates', false ),
            'email_notifications' => wc_template_fixer_get_option( 'email_notifications', false ),
            'notification_email' => wc_template_fixer_get_option( 'notification_email', get_option( 'admin_email' ) ),
            'backup_retention_days' => wc_template_fixer_get_option( 'backup_retention_days', 30 ),
            'preserve_customizations' => wc_template_fixer_get_option( 'preserve_customizations', true ),
            'log_level' => wc_template_fixer_get_option( 'log_level', 'basic' ),
            'excluded_templates' => wc_template_fixer_get_option( 'excluded_templates', '' ),
            'remove_data_on_uninstall' => wc_template_fixer_get_option( 'remove_data_on_uninstall', false ),
        );
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'enable_daily_scan' => isset( $_POST['enable_daily_scan'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'auto_update_safe_templates' => isset( $_POST['auto_update_safe_templates'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'email_notifications' => isset( $_POST['email_notifications'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'notification_email' => sanitize_email( wp_unslash( $_POST['notification_email'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'backup_retention_days' => intval( wp_unslash( $_POST['backup_retention_days'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'preserve_customizations' => isset( $_POST['preserve_customizations'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'log_level' => sanitize_text_field( wp_unslash( $_POST['log_level'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'excluded_templates' => sanitize_textarea_field( wp_unslash( $_POST['excluded_templates'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'remove_data_on_uninstall' => isset( $_POST['remove_data_on_uninstall'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
        );
        
        foreach ( $settings as $key => $value ) {
            wc_template_fixer_update_option( $key, $value );
        }
        
        // Update special option for uninstallation
        update_option( 'wc_template_fixer_remove_data_on_uninstall', $settings['remove_data_on_uninstall'] );
        
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        });
    }
    
    /**
     * AJAX: Quick statistics
     */
    public function ajax_quick_stats() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $stats = $this->scanner->get_template_statistics();
        
        wp_send_json_success( $stats );
    }
    
    /**
     * AJAX: Quick update for legacy integration
     */
    public function ajax_quick_update() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $template_names = array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ?? array() ) );
        $preserve_customizations = isset( $_POST['preserve_customizations'] ) ? (bool) $_POST['preserve_customizations'] : true;
        
        if ( empty( $template_names ) ) {
            // Get critical templates automatically
            $outdated = $this->scanner->scan_outdated_templates();
            $critical_templates = array();
            
            foreach ( $outdated as $template ) {
                if ( $template['risk_level'] === 'critical' || $template['risk_level'] === 'high' ) {
                    $critical_templates[] = $template['name'];
                }
            }
            
            $template_names = $critical_templates;
        }
        
        $results = $this->updater->update_multiple_templates( $template_names, $preserve_customizations );
        
        // Log success to show notification
        if ( $results['success_count'] > 0 ) {
            set_transient( 'wc_template_fixer_update_success', array(
                'count' => $results['success_count'],
                'backups' => true
            ), 300 );
        }
        
        wp_send_json_success( $results );
    }
    
    /**
     * Integration with WC Template Updater legacy
     */
    public function integrate_with_legacy_updater() {
        // Verificar si existe el mu-plugin legacy
        if ( file_exists( WPMU_PLUGIN_DIR . '/wc-template-updater.php' ) ) {
            // Register function for compatibility
            add_action( 'wp_ajax_wc_template_updater_legacy_bridge', array( $this, 'legacy_bridge_handler' ) );
            
            // Add scripts for inter-system communication
            add_action( 'admin_footer', array( $this, 'add_legacy_bridge_script' ) );
        }
        
        // Siempre mostrar la notificación de templates desactualizados
        add_action( 'admin_notices', array( $this, 'show_outdated_templates_warning' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_notification' ), 100 );
    }
    
    /**
     * Handler for legacy bridge
     */
    public function legacy_bridge_handler() {
        check_ajax_referer( 'wc_template_fixer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $action = sanitize_text_field( wp_unslash( $_POST['bridge_action'] ?? '' ) );
        
        switch ( $action ) {
            case 'get_outdated_count':
                $outdated = $this->scanner->scan_outdated_templates();
                wp_send_json_success( array( 
                    'count' => count( $outdated ),
                    'critical_count' => count( array_filter( $outdated, function( $t ) { 
                        return $t['risk_level'] === 'critical'; 
                    } ) )
                ) );
                break;
                
            case 'quick_fix':
                $results = $this->ajax_quick_update();
                wp_send_json_success( $results );
                break;
                
            default:
                wp_send_json_error( 'Unrecognized action' );
        }
    }
    
    /**
     * Communication script with legacy system
     */
    public function add_legacy_bridge_script() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'wc-template' ) === false ) {
            return;
        }
        
        ?>
        <script>
        // Puente de comunicación con WC Template Updater legacy
        window.wcTemplateFixerLegacyBridge = {
            ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'wc_template_fixer_nonce' ) ); ?>',
            
            getOutdatedCount: function() {
                return jQuery.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_template_updater_legacy_bridge',
                        bridge_action: 'get_outdated_count',
                        nonce: this.nonce
                    }
                });
            },
            
            quickFix: function() {
                return jQuery.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_template_updater_legacy_bridge',
                        bridge_action: 'quick_fix',
                        nonce: this.nonce
                    }
                });
            }
        };
        
        // Auto-update counters if there are legacy elements on the page
        jQuery(document).ready(function($) {
            if ($('.wc-template-legacy-counter').length) {
                window.wcTemplateFixerLegacyBridge.getOutdatedCount().done(function(response) {
                    if (response.success) {
                        $('.wc-template-legacy-counter').text(response.data.count);
                        $('.wc-template-legacy-critical-counter').text(response.data.critical_count);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Show outdated templates warning
     */
    public function show_outdated_templates_warning() {
        // Solo mostrar en páginas del admin de WordPress
        $current_screen = get_current_screen();
        if ( ! $current_screen || ! in_array( $current_screen->id, array( 'dashboard', 'plugins' ) ) ) {
            return;
        }
        
        // Verificar si ya fue dismisseada
        if ( get_transient( 'wc_template_fixer_warning_dismissed' ) ) {
            return;
        }
        
        // Get estadísticas rápidas
        $stats = $this->scanner->get_template_statistics();
        
        if ( $stats['outdated_templates'] > 0 ) {
            $critical_count = $stats['risk_levels']['critical'] ?? 0;
            $theme_name = get_template();
            
            $icon = $critical_count > 0 ? '🚨' : '⚠️';
            $class = $critical_count > 0 ? 'notice-error' : 'notice-warning';
            
            ?>
            <div class="notice <?php echo esc_attr( $class ); ?> wc-template-fixer-notice is-dismissible" data-notice="outdated-templates">
                <p>
                    <strong><?php echo esc_html( $icon ); ?> Outdated WooCommerce Templates</strong><br>
                    The theme <strong><?php echo esc_html( ucfirst( $theme_name ) ); ?></strong> has <strong><?php echo esc_html( $stats['outdated_templates'] ); ?> outdated</strong> WooCommerce templates that may cause compatibility issues.
                    <?php if ( $critical_count > 0 ) : ?>
                        <br><span style="color: #d63638;">⚠️ <strong><?php echo esc_html( $critical_count ); ?> critical templates</strong> require immediate attention.</span>
                    <?php endif; ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-primary">Update Templates</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-template-fixer' ) ); ?>" class="button button-secondary">View Details</a>
                    <button type="button" class="button button-link dismiss-warning" data-dismiss-type="outdated-templates">Hide for 24h</button>
                </p>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('.dismiss-warning').on('click', function() {
                    var dismissType = $(this).data('dismiss-type');
                    $(this).closest('.notice').fadeOut();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wc_template_fixer_dismiss_warning',
                            dismiss_type: dismissType,
                            nonce: '<?php echo esc_js( wp_create_nonce( 'wc_template_fixer_dismiss' ) ); ?>'
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Add notification in admin bar
     */
    public function add_admin_bar_notification( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        
        $stats = $this->scanner->get_template_statistics();
        
        if ( $stats['outdated_templates'] > 0 ) {
            $critical_count = $stats['risk_levels']['critical'] ?? 0;
            $icon = $critical_count > 0 ? '🚨' : '⚠️';
            $class = $critical_count > 0 ? 'wc-template-fixer-critical' : 'wc-template-fixer-warning';
            
            $wp_admin_bar->add_node( array(
                'id'    => 'wc-template-fixer',
                'title' => $icon . ' ' . $stats['outdated_templates'] . ' Templates',
                'href'  => admin_url( 'admin.php?page=wc-template-fixer' ),
                'meta'  => array(
                    'class' => $class,
                    'title' => sprintf( 
                        'WooCommerce: %d outdated templates (%d critical)', 
                        $stats['outdated_templates'], 
                        $critical_count 
                    ),
                ),
            ) );
            
            // Submenu para acción rápida
            $wp_admin_bar->add_node( array(
                'parent' => 'wc-template-fixer',
                'id'     => 'wc-template-fixer-scan',
                'title'  => '🔍 Scan Now',
                'href'   => admin_url( 'admin.php?page=wc-template-fixer' ),
            ) );
            
            if ( $critical_count > 0 ) {
                $wp_admin_bar->add_node( array(
                    'parent' => 'wc-template-fixer',
                    'id'     => 'wc-template-fixer-fix-critical',
                    'title'  => '🚨 Fix Critical',
                    'href'   => admin_url( 'admin.php?page=wc-template-fixer&auto=critical' ),
                ) );
            }
        }
    }
    
    /**
     * AJAX: Dismiss warning
     */
    public function ajax_dismiss_warning() {
        check_ajax_referer( 'wc_template_fixer_dismiss', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $dismiss_type = sanitize_text_field( wp_unslash( $_POST['dismiss_type'] ?? '' ) );
        
        // Ocultar por 24 horas
        set_transient( 'wc_template_fixer_warning_dismissed', true, 24 * HOUR_IN_SECONDS );
        
        wp_send_json_success();
    }
    
    /**
     * Format file size
     */
    private function format_file_size( $bytes ) {
        $units = array( 'B', 'KB', 'MB', 'GB' );
        
        for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }
        
        return round( $bytes, 2 ) . ' ' . $units[$i];
    }
    
    /**
     * Invalidate logs cache when new records are created
     */
    private function invalidate_logs_cache() {
        wp_cache_delete( 'wc_template_fixer_logs_total', 'wc_template_fixer' );
        
        // Invalidar cache de páginas (asumiendo máximo 10 páginas)
        for ( $i = 1; $i <= 10; $i++ ) {
            wp_cache_delete( 'wc_template_fixer_logs_' . $i . '_50', 'wc_template_fixer' );
        }
    }
}