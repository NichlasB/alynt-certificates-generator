<?php
/**
 * Admin class
 * Handles WordPress admin interface for the certificate generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add AJAX handlers for certificate generation
        add_action('wp_ajax_alynt_admin_generate_certificate', array($this, 'ajax_admin_generate_certificate'));
        
        // Initialize admin settings
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Initialize admin settings
     */
    public function init_settings() {
        // Register settings
        register_setting(
            'alynt_cert_settings_group',
            'alynt_cert_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize certificate numbering
        if (isset($input['certificate_numbering'])) {
            $allowed_numbering = array('auto', 'timestamp', 'uuid');
            $sanitized['certificate_numbering'] = in_array($input['certificate_numbering'], $allowed_numbering) ? $input['certificate_numbering'] : 'auto';
        }
        
        // Sanitize certificate prefix
        if (isset($input['certificate_prefix'])) {
            $sanitized['certificate_prefix'] = sanitize_text_field($input['certificate_prefix']);
        }
        
        // Sanitize certificate expiry
        if (isset($input['certificate_expiry'])) {
            $allowed_expiry = array('never', '1_year', '2_years', '3_years', 'custom');
            $sanitized['certificate_expiry'] = in_array($input['certificate_expiry'], $allowed_expiry) ? $input['certificate_expiry'] : 'never';
        }
        
        // Sanitize custom expiry days
        if (isset($input['custom_expiry_days'])) {
            $sanitized['custom_expiry_days'] = absint($input['custom_expiry_days']);
        }
        
        // Sanitize minimum role
        if (isset($input['minimum_role'])) {
            $allowed_roles = array('edit_posts', 'manage_options');
            $sanitized['minimum_role'] = in_array($input['minimum_role'], $allowed_roles) ? $input['minimum_role'] : 'edit_posts';
        }
        
        // Sanitize boolean options
        $boolean_options = array('download_protection', 'auto_cleanup', 'debug_mode');
        foreach ($boolean_options as $option) {
            $sanitized[$option] = isset($input[$option]) ? 1 : 0;
        }
        
        // Sanitize download expiry
        if (isset($input['download_expiry'])) {
            $allowed_download_expiry = array('never', '7_days', '30_days', '90_days', '1_year');
            $sanitized['download_expiry'] = in_array($input['download_expiry'], $allowed_download_expiry) ? $input['download_expiry'] : '30_days';
        }
        
        // Sanitize PDF quality
        if (isset($input['pdf_quality'])) {
            $allowed_quality = array('72', '150', '300');
            $sanitized['pdf_quality'] = in_array($input['pdf_quality'], $allowed_quality) ? $input['pdf_quality'] : '150';
        }
        
        // Sanitize default font
        if (isset($input['default_font'])) {
            $allowed_fonts = array('helvetica', 'times', 'courier');
            $sanitized['default_font'] = in_array($input['default_font'], $allowed_fonts) ? $input['default_font'] : 'helvetica';
        }
        
        // Sanitize storage location
        if (isset($input['storage_location'])) {
            $allowed_storage = array('media_library', 'uploads_folder');
            $sanitized['storage_location'] = in_array($input['storage_location'], $allowed_storage) ? $input['storage_location'] : 'media_library';
        }
        
        // Sanitize log retention
        if (isset($input['log_retention'])) {
            $allowed_retention = array('7', '30', '90');
            $sanitized['log_retention'] = in_array($input['log_retention'], $allowed_retention) ? $input['log_retention'] : '30';
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler for admin certificate generation
     */
    public function ajax_admin_generate_certificate() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $template_id = intval($_POST['template_id']);
        
        if (empty($template_id)) {
            wp_send_json_error(array('error' => __('Template ID is required', 'alynt-certificates')));
        }
        
        // Get template data
        $template_manager = Alynt_Cert_Template_Manager::get_instance();
        $template = $template_manager->get_template_with_data($template_id);
        
        if (!$template) {
            wp_send_json_error(array('error' => __('Template not found', 'alynt-certificates')));
        }
        
        // Extract recipient data
        $recipient_data = array();
        foreach ($template->variables as $variable) {
            $field_name = $variable->variable_name;
            if (isset($_POST[$field_name])) {
                $recipient_data[$field_name] = sanitize_text_field($_POST[$field_name]);
            }
        }
        
        // Sanitize data
        $security = Alynt_Cert_Security::get_instance();
        $recipient_data = $security->sanitize_certificate_data($recipient_data);
        
        // Generate certificate
        $pdf_generator = Alynt_Cert_PDF_Generator::get_instance();
        $result = $pdf_generator->generate_certificate($template_id, $recipient_data, 'manual');
        
        if ($result['success']) {
            wp_send_json_success(array(
                'certificate_number' => $result['certificate_number'],
                'download_url' => $result['download_url'],
                'message' => __('Certificate generated successfully!', 'alynt-certificates')
            ));
        } else {
            wp_send_json_error(array('error' => $result['error']));
        }
    }
}
