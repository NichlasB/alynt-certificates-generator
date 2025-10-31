<?php
/**
 * Plugin Name: Alynt Certificates Generator
 * Plugin URI: https://github.com/alynt/certificates-generator
 * Description: A comprehensive WordPress plugin for generating PDF certificates with drag-and-drop template editing, webhook support, and email notifications.
 * Version: 1.0.0
 * Author: Alynt
 * License: GPL v2 or later
 * Text Domain: alynt-certificates
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ALYNT_CERT_VERSION', '1.0.0');
define('ALYNT_CERT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALYNT_CERT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALYNT_CERT_PLUGIN_FILE', __FILE__);
define('ALYNT_CERT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class Alynt_Certificates_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Include required files first
        $this->includes();
        
        // Register hooks after includes
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Alynt_Certificates_Generator', 'uninstall'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('alynt-certificates', false, dirname(ALYNT_CERT_PLUGIN_BASENAME) . '/languages');
        
        // Initialize components
        $this->init_components();
        
        // Add admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // Add frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('init', array($this, 'init_rewrite_rules'));
    }
    
    private function includes() {
        // Core classes
        require_once ALYNT_CERT_PLUGIN_DIR . 'includes/class-database.php';
        require_once ALYNT_CERT_PLUGIN_DIR . 'includes/class-template-manager.php';
        require_once ALYNT_CERT_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        require_once ALYNT_CERT_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once ALYNT_CERT_PLUGIN_DIR . 'includes/class-email-manager.php';
        require_once ALYNT_CERT_PLUGIN_DIR . 'includes/class-security.php';
        
        // Admin classes
        if (is_admin()) {
            require_once ALYNT_CERT_PLUGIN_DIR . 'admin/class-admin.php';
        }
        
        // Public classes
        require_once ALYNT_CERT_PLUGIN_DIR . 'public/class-certificate-form.php';
        require_once ALYNT_CERT_PLUGIN_DIR . 'public/class-certificate-download.php';
    }
    
    private function init_components() {
        // Initialize database
        Alynt_Cert_Database::get_instance();
        
        // Initialize managers
        Alynt_Cert_Template_Manager::get_instance();
        Alynt_Cert_PDF_Generator::get_instance();
        Alynt_Cert_Webhook_Handler::get_instance();
        Alynt_Cert_Email_Manager::get_instance();
        Alynt_Cert_Security::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            Alynt_Cert_Admin::get_instance();
        }
        
        // Initialize public components
        Alynt_Cert_Certificate_Form::get_instance();
        Alynt_Cert_Certificate_Download::get_instance();
    }
    
    public function add_admin_menu() {
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        add_menu_page(
            __('Certificate Generator', 'alynt-certificates'),
            __('Certificates', 'alynt-certificates'),
            'edit_posts',
            'alynt-certificates',
            array($this, 'admin_page'),
            'dashicons-awards',
            30
        );
        
        add_submenu_page(
            'alynt-certificates',
            __('Templates', 'alynt-certificates'),
            __('Templates', 'alynt-certificates'),
            'edit_posts',
            'alynt-certificates-templates',
            array($this, 'templates_page')
        );
        
        add_submenu_page(
            'alynt-certificates',
            __('Generate Certificate', 'alynt-certificates'),
            __('Generate', 'alynt-certificates'),
            'edit_posts',
            'alynt-certificates-generate',
            array($this, 'generate_page')
        );
        
        add_submenu_page(
            'alynt-certificates',
            __('Email Templates', 'alynt-certificates'),
            __('Email Templates', 'alynt-certificates'),
            'edit_posts',
            'alynt-certificates-emails',
            array($this, 'emails_page')
        );
        
        add_submenu_page(
            'alynt-certificates',
            __('Webhooks', 'alynt-certificates'),
            __('Webhooks', 'alynt-certificates'),
            'edit_posts',
            'alynt-certificates-webhooks',
            array($this, 'webhooks_page')
        );
        
        add_submenu_page(
            'alynt-certificates',
            __('Settings', 'alynt-certificates'),
            __('Settings', 'alynt-certificates'),
            'edit_posts',
            'alynt-certificates-settings',
            array($this, 'settings_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'alynt-certificates') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('jquery-ui-resizable');
        
        wp_enqueue_script(
            'alynt-cert-admin',
            ALYNT_CERT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            ALYNT_CERT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'alynt-cert-admin',
            ALYNT_CERT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ALYNT_CERT_VERSION
        );
        
        // Localize script
        wp_localize_script('alynt-cert-admin', 'alynt_cert_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alynt_cert_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'alynt-certificates'),
                'saving' => __('Saving...', 'alynt-certificates'),
                'saved' => __('Saved!', 'alynt-certificates'),
                'error' => __('Error occurred. Please try again.', 'alynt-certificates')
            )
        ));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'alynt-cert-frontend',
            ALYNT_CERT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ALYNT_CERT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'alynt-cert-frontend',
            ALYNT_CERT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ALYNT_CERT_VERSION
        );
    }
    
    public function init_rewrite_rules() {
        add_rewrite_rule(
            '^certificates/([a-zA-Z0-9]+)/([a-zA-Z0-9\-\.]+)/?$',
            'index.php?alynt_cert_download=1&cert_token=$matches[1]&cert_file=$matches[2]',
            'top'
        );
        
        add_rewrite_tag('%alynt_cert_download%', '([^&]+)');
        add_rewrite_tag('%cert_token%', '([^&]+)');
        add_rewrite_tag('%cert_file%', '([^&]+)');
    }
    
    // Admin page callbacks
    public function admin_page() {
        include ALYNT_CERT_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function templates_page() {
        include ALYNT_CERT_PLUGIN_DIR . 'admin/views/templates.php';
    }
    
    public function generate_page() {
        include ALYNT_CERT_PLUGIN_DIR . 'admin/views/generate.php';
    }
    
    public function emails_page() {
        include ALYNT_CERT_PLUGIN_DIR . 'admin/views/emails.php';
    }
    
    public function webhooks_page() {
        include ALYNT_CERT_PLUGIN_DIR . 'admin/views/webhooks.php';
    }
    
    public function settings_page() {
        include ALYNT_CERT_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    public function activate() {
        // Create database tables
        Alynt_Cert_Database::create_tables();
        
        // Create upload directories
        $this->create_upload_directories();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public static function uninstall() {
        // Remove database tables
        Alynt_Cert_Database::drop_tables();
        
        // Remove options
        delete_option('alynt_cert_version');
        delete_option('alynt_cert_settings');
        
        // Remove upload directories (optional)
        // $upload_dir = wp_upload_dir();
        // wp_delete_file_from_directory($upload_dir['basedir'] . '/certificates/');
    }
    
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/certificates';
        
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
            
            // Create .htaccess to protect directory
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($cert_dir . '/.htaccess', $htaccess_content);
            
            // Create index.php to prevent directory listing
            file_put_contents($cert_dir . '/index.php', '<?php // Silence is golden');
        }
    }
    
    private function set_default_options() {
        add_option('alynt_cert_version', ALYNT_CERT_VERSION);
        
        $default_settings = array(
            'certificate_numbering' => 'auto_increment',
            'certificate_prefix' => 'CERT',
            'webhook_security' => 'api_key',
            'email_from_name' => get_bloginfo('name'),
            'email_from_email' => get_option('admin_email'),
            'pdf_orientation' => 'landscape',
            'pdf_format' => 'A4'
        );
        
        add_option('alynt_cert_settings', $default_settings);
    }
}

// Initialize the plugin
Alynt_Certificates_Generator::get_instance();
