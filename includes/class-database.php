<?php
/**
 * Database management class
 * Handles all database operations for the certificate generator plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Certificate templates table
        $templates_table = $wpdb->prefix . 'alynt_cert_templates';
        $templates_sql = "CREATE TABLE $templates_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            template_image varchar(500) NOT NULL,
            orientation varchar(20) DEFAULT 'landscape',
            width int(11) DEFAULT 800,
            height int(11) DEFAULT 600,
            variables longtext,
            placeholders longtext,
            status varchar(20) DEFAULT 'active',
            created_by int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_created_by (created_by)
        ) $charset_collate;";
        
        // Certificate variables table
        $variables_table = $wpdb->prefix . 'alynt_cert_variables';
        $variables_sql = "CREATE TABLE $variables_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            template_id int(11) NOT NULL,
            variable_name varchar(100) NOT NULL,
            variable_label varchar(255) NOT NULL,
            variable_type varchar(50) DEFAULT 'text',
            is_required tinyint(1) DEFAULT 0,
            default_value text,
            validation_rules text,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_template_id (template_id),
            KEY idx_variable_name (variable_name),
            UNIQUE KEY unique_template_variable (template_id, variable_name)
        ) $charset_collate;";
        
        // Certificate placeholders table
        $placeholders_table = $wpdb->prefix . 'alynt_cert_placeholders';
        $placeholders_sql = "CREATE TABLE $placeholders_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            template_id int(11) NOT NULL,
            variable_id int(11) NOT NULL,
            x_position int(11) NOT NULL,
            y_position int(11) NOT NULL,
            width int(11) DEFAULT 200,
            height int(11) DEFAULT 30,
            font_size int(11) DEFAULT 14,
            font_color varchar(7) DEFAULT '#000000',
            font_family varchar(100) DEFAULT 'Arial',
            text_align varchar(20) DEFAULT 'left',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_template_id (template_id),
            KEY idx_variable_id (variable_id)
        ) $charset_collate;";
        
        // Generated certificates table
        $certificates_table = $wpdb->prefix . 'alynt_cert_generated';
        $certificates_sql = "CREATE TABLE $certificates_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            certificate_number varchar(100) NOT NULL,
            template_id int(11) NOT NULL,
            recipient_data longtext NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            download_token varchar(100) NOT NULL,
            generated_by int(11),
            generation_method varchar(50) DEFAULT 'manual',
            webhook_data longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            downloaded_at datetime NULL,
            download_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_cert_number (certificate_number),
            UNIQUE KEY unique_download_token (download_token),
            KEY idx_template_id (template_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Email templates table
        $email_templates_table = $wpdb->prefix . 'alynt_cert_email_templates';
        $email_templates_sql = "CREATE TABLE $email_templates_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            template_id int(11) NOT NULL,
            name varchar(255) NOT NULL,
            email_subject varchar(500) NOT NULL,
            email_body longtext NOT NULL,
            to_field varchar(255) DEFAULT '{email}',
            cc_field varchar(255) DEFAULT '',
            bcc_field varchar(255) DEFAULT '',
            is_active tinyint(1) DEFAULT 1,
            trigger_on varchar(50) DEFAULT 'generation',
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_template_id (template_id),
            KEY idx_is_active (is_active)
        ) $charset_collate;";
        
        // Webhook configurations table
        $webhooks_table = $wpdb->prefix . 'alynt_cert_webhooks';
        $webhooks_sql = "CREATE TABLE $webhooks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            webhook_type varchar(50) NOT NULL,
            endpoint_url varchar(500),
            api_key varchar(255),
            secret_key varchar(255),
            template_id int(11),
            is_active tinyint(1) DEFAULT 1,
            headers longtext,
            payload_template longtext,
            last_triggered datetime NULL,
            success_count int(11) DEFAULT 0,
            error_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_webhook_type (webhook_type),
            KEY idx_is_active (is_active),
            KEY idx_template_id (template_id)
        ) $charset_collate;";
        
        // Webhook logs table
        $webhook_logs_table = $wpdb->prefix . 'alynt_cert_webhook_logs';
        $webhook_logs_sql = "CREATE TABLE $webhook_logs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            webhook_id int(11) NOT NULL,
            certificate_id int(11),
            request_data longtext,
            response_data longtext,
            status_code int(11),
            success tinyint(1) DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_webhook_id (webhook_id),
            KEY idx_certificate_id (certificate_id),
            KEY idx_success (success),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($templates_sql);
        dbDelta($variables_sql);
        dbDelta($placeholders_sql);
        dbDelta($certificates_sql);
        dbDelta($email_templates_sql);
        dbDelta($webhooks_sql);
        dbDelta($webhook_logs_sql);
    }
    
    /**
     * Drop all plugin tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'alynt_cert_webhook_logs',
            $wpdb->prefix . 'alynt_cert_webhooks',
            $wpdb->prefix . 'alynt_cert_email_templates',
            $wpdb->prefix . 'alynt_cert_generated',
            $wpdb->prefix . 'alynt_cert_placeholders',
            $wpdb->prefix . 'alynt_cert_variables',
            $wpdb->prefix . 'alynt_cert_templates'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Get certificate template by ID
     */
    public function get_template($template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_templates';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND status = 'active'",
            $template_id
        ));
    }
    
    /**
     * Get all certificate templates
     */
    public function get_templates($status = 'active') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_templates';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC",
            $status
        ));
    }
    
    /**
     * Create or update certificate template
     */
    public function save_template($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_templates';
        
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'template_image' => esc_url_raw($data['template_image']),
            'orientation' => sanitize_text_field($data['orientation']),
            'width' => intval($data['width']),
            'height' => intval($data['height']),
            'variables' => wp_json_encode($data['variables']),
            'placeholders' => wp_json_encode($data['placeholders']),
            'status' => sanitize_text_field($data['status']),
            'created_by' => get_current_user_id()
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            // Update existing template
            unset($template_data['created_by']);
            $result = $wpdb->update($table, $template_data, array('id' => intval($data['id'])));
            return $result !== false ? intval($data['id']) : false;
        } else {
            // Create new template
            $result = $wpdb->insert($table, $template_data);
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Get template variables
     */
    public function get_template_variables($template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_variables';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE template_id = %d ORDER BY sort_order ASC",
            $template_id
        ));
    }
    
    /**
     * Save template variable
     */
    public function save_variable($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_variables';
        
        $variable_data = array(
            'template_id' => intval($data['template_id']),
            'variable_name' => sanitize_text_field($data['variable_name']),
            'variable_label' => sanitize_text_field($data['variable_label']),
            'variable_type' => sanitize_text_field($data['variable_type']),
            'is_required' => intval($data['is_required']),
            'default_value' => sanitize_textarea_field($data['default_value']),
            'validation_rules' => sanitize_textarea_field($data['validation_rules']),
            'sort_order' => intval($data['sort_order'])
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            return $wpdb->update($table, $variable_data, array('id' => intval($data['id'])));
        } else {
            return $wpdb->insert($table, $variable_data);
        }
    }
    
    /**
     * Get template placeholders
     */
    public function get_template_placeholders($template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_placeholders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, v.variable_name, v.variable_label 
             FROM $table p 
             LEFT JOIN {$wpdb->prefix}alynt_cert_variables v ON p.variable_id = v.id 
             WHERE p.template_id = %d",
            $template_id
        ));
    }
    
    /**
     * Save placeholder position
     */
    public function save_placeholder($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_placeholders';
        
        $placeholder_data = array(
            'template_id' => intval($data['template_id']),
            'variable_id' => intval($data['variable_id']),
            'x_position' => intval($data['x_position']),
            'y_position' => intval($data['y_position']),
            'width' => intval($data['width']),
            'height' => intval($data['height']),
            'font_size' => intval($data['font_size']),
            'font_color' => sanitize_hex_color($data['font_color']),
            'font_family' => sanitize_text_field($data['font_family']),
            'text_align' => sanitize_text_field($data['text_align'])
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            return $wpdb->update($table, $placeholder_data, array('id' => intval($data['id'])));
        } else {
            return $wpdb->insert($table, $placeholder_data);
        }
    }
    
    /**
     * Generate unique certificate number
     */
    public function generate_certificate_number($prefix = 'CERT') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_generated';
        
        // Get the highest existing number
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING(certificate_number, %d) AS UNSIGNED)) 
             FROM $table 
             WHERE certificate_number LIKE %s",
            strlen($prefix) + 1,
            $prefix . '%'
        ));
        
        $next_number = ($result ? $result : 0) + 1;
        return $prefix . str_pad($next_number, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Save generated certificate
     */
    public function save_generated_certificate($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_generated';
        
        $cert_data = array(
            'certificate_number' => sanitize_text_field($data['certificate_number']),
            'template_id' => intval($data['template_id']),
            'recipient_data' => wp_json_encode($data['recipient_data']),
            'file_path' => sanitize_text_field($data['file_path']),
            'file_url' => esc_url_raw($data['file_url']),
            'download_token' => sanitize_text_field($data['download_token']),
            'generated_by' => intval($data['generated_by']),
            'generation_method' => sanitize_text_field($data['generation_method']),
            'webhook_data' => wp_json_encode($data['webhook_data']),
            'status' => sanitize_text_field($data['status'])
        );
        
        $result = $wpdb->insert($table, $cert_data);
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get generated certificate by token
     */
    public function get_certificate_by_token($token) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_generated';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE download_token = %s AND status = 'active'",
            $token
        ));
    }
    
    /**
     * Update certificate download count
     */
    public function update_download_count($certificate_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_generated';
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table 
             SET download_count = download_count + 1, downloaded_at = NOW() 
             WHERE id = %d",
            $certificate_id
        ));
    }
    
    /**
     * Get email templates for a certificate template
     */
    public function get_email_templates($template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_email_templates';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE template_id = %d ORDER BY sort_order ASC",
            $template_id
        ));
    }
    
    /**
     * Save email template
     */
    public function save_email_template($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_email_templates';
        
        $email_data = array(
            'template_id' => intval($data['template_id']),
            'name' => sanitize_text_field($data['name']),
            'email_subject' => sanitize_text_field($data['email_subject']),
            'email_body' => wp_kses_post($data['email_body']),
            'to_field' => sanitize_text_field($data['to_field']),
            'cc_field' => sanitize_text_field($data['cc_field']),
            'bcc_field' => sanitize_text_field($data['bcc_field']),
            'is_active' => intval($data['is_active']),
            'trigger_on' => sanitize_text_field($data['trigger_on']),
            'sort_order' => intval($data['sort_order'])
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            return $wpdb->update($table, $email_data, array('id' => intval($data['id'])));
        } else {
            return $wpdb->insert($table, $email_data);
        }
    }
}
