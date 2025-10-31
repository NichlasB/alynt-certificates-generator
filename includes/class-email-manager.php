<?php
/**
 * Email Manager class
 * Handles email notifications for certificate generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Email_Manager {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = Alynt_Cert_Database::get_instance();
        
        // Add AJAX handlers
        add_action('wp_ajax_alynt_save_email_template', array($this, 'ajax_save_email_template'));
        add_action('wp_ajax_alynt_delete_email_template', array($this, 'ajax_delete_email_template'));
        add_action('wp_ajax_alynt_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_alynt_toggle_email_template', array($this, 'ajax_toggle_email_template'));
    }
    
    /**
     * Send certificate notifications
     */
    public function send_certificate_notifications($template_id, $recipient_data) {
        $email_templates = $this->db->get_email_templates($template_id);
        
        if (empty($email_templates)) {
            return array('success' => true, 'message' => __('No email templates configured', 'alynt-certificates'));
        }
        
        $results = array();
        
        foreach ($email_templates as $email_template) {
            if (!$email_template->is_active) {
                continue;
            }
            
            $result = $this->send_single_notification($email_template, $recipient_data);
            $results[] = $result;
        }
        
        return array('success' => true, 'results' => $results);
    }
    
    /**
     * Send single email notification
     */
    private function send_single_notification($email_template, $recipient_data) {
        try {
            // Process email fields with variables
            $to = $this->process_variables($email_template->to_field, $recipient_data);
            $subject = $this->process_variables($email_template->email_subject, $recipient_data);
            $body = $this->process_variables($email_template->email_body, $recipient_data);
            $cc = !empty($email_template->cc_field) ? $this->process_variables($email_template->cc_field, $recipient_data) : '';
            $bcc = !empty($email_template->bcc_field) ? $this->process_variables($email_template->bcc_field, $recipient_data) : '';
            
            // Validate email addresses
            $to_emails = $this->parse_email_addresses($to);
            if (empty($to_emails)) {
                throw new Exception(__('No valid recipient email addresses', 'alynt-certificates'));
            }
            
            // Get email settings
            $settings = get_option('alynt_cert_settings', array());
            $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
            $from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');
            
            // Set headers
            $headers = array();
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            
            if (!empty($cc)) {
                $cc_emails = $this->parse_email_addresses($cc);
                foreach ($cc_emails as $cc_email) {
                    $headers[] = 'Cc: ' . $cc_email;
                }
            }
            
            if (!empty($bcc)) {
                $bcc_emails = $this->parse_email_addresses($bcc);
                foreach ($bcc_emails as $bcc_email) {
                    $headers[] = 'Bcc: ' . $bcc_email;
                }
            }
            
            // Send email to each recipient
            $sent_count = 0;
            $errors = array();
            
            foreach ($to_emails as $recipient_email) {
                $sent = wp_mail($recipient_email, $subject, $body, $headers);
                
                if ($sent) {
                    $sent_count++;
                } else {
                    $errors[] = sprintf(__('Failed to send to %s', 'alynt-certificates'), $recipient_email);
                }
            }
            
            return array(
                'success' => $sent_count > 0,
                'template_name' => $email_template->name,
                'sent_count' => $sent_count,
                'total_recipients' => count($to_emails),
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'template_name' => $email_template->name,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Process variables in text
     */
    private function process_variables($text, $variables) {
        // Replace variables in format {variable_name}
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        // Replace common WordPress variables
        $text = str_replace('{site_name}', get_bloginfo('name'), $text);
        $text = str_replace('{site_url}', home_url(), $text);
        $text = str_replace('{admin_email}', get_option('admin_email'), $text);
        $text = str_replace('{current_date}', date(get_option('date_format')), $text);
        $text = str_replace('{current_time}', date(get_option('time_format')), $text);
        
        return $text;
    }
    
    /**
     * Parse comma-separated email addresses
     */
    private function parse_email_addresses($email_string) {
        $emails = array();
        $email_list = explode(',', $email_string);
        
        foreach ($email_list as $email) {
            $email = trim($email);
            if (is_email($email)) {
                $emails[] = $email;
            }
        }
        
        return $emails;
    }
    
    /**
     * Get email templates for a certificate template
     */
    public function get_email_templates($template_id) {
        return $this->db->get_email_templates($template_id);
    }
    
    /**
     * Save email template
     */
    public function save_email_template($data) {
        // Validate required fields
        if (empty($data['name']) || empty($data['email_subject']) || empty($data['email_body'])) {
            return array('success' => false, 'error' => __('Name, subject, and body are required', 'alynt-certificates'));
        }
        
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
            $email_data['id'] = intval($data['id']);
        }
        
        $result = $this->db->save_email_template($email_data);
        
        if ($result) {
            return array('success' => true, 'email_template_id' => $result);
        } else {
            return array('success' => false, 'error' => __('Failed to save email template', 'alynt-certificates'));
        }
    }
    
    /**
     * Delete email template
     */
    public function delete_email_template($email_template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_email_templates';
        $result = $wpdb->delete($table, array('id' => $email_template_id));
        
        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => __('Failed to delete email template', 'alynt-certificates'));
        }
    }
    
    /**
     * Toggle email template active status
     */
    public function toggle_email_template($email_template_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_email_templates';
        
        // Get current status
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM $table WHERE id = %d",
            $email_template_id
        ));
        
        if ($current_status === null) {
            return array('success' => false, 'error' => __('Email template not found', 'alynt-certificates'));
        }
        
        // Toggle status
        $new_status = $current_status ? 0 : 1;
        $result = $wpdb->update($table, array('is_active' => $new_status), array('id' => $email_template_id));
        
        if ($result !== false) {
            return array('success' => true, 'new_status' => $new_status);
        } else {
            return array('success' => false, 'error' => __('Failed to update email template status', 'alynt-certificates'));
        }
    }
    
    /**
     * Test email template
     */
    public function test_email_template($email_template_id, $test_email, $test_data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_email_templates';
        $email_template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $email_template_id
        ));
        
        if (!$email_template) {
            return array('success' => false, 'error' => __('Email template not found', 'alynt-certificates'));
        }
        
        // Use test data or default sample data
        if (empty($test_data)) {
            $test_data = array(
                'name' => 'John Doe',
                'email' => $test_email,
                'course_name' => 'Sample Course',
                'certificate_number' => 'CERT000001',
                'certificate_url' => home_url('/certificates/sample/certificate.pdf'),
                'generation_date' => current_time('mysql')
            );
        }
        
        // Override the to_field for testing
        $email_template->to_field = $test_email;
        
        $result = $this->send_single_notification($email_template, $test_data);
        
        return $result;
    }
    
    /**
     * Get available variables for email templates
     */
    public function get_available_variables($template_id = null) {
        $variables = array(
            'certificate_number' => __('Certificate Number', 'alynt-certificates'),
            'certificate_url' => __('Certificate Download URL', 'alynt-certificates'),
            'generation_date' => __('Generation Date', 'alynt-certificates'),
            'site_name' => __('Site Name', 'alynt-certificates'),
            'site_url' => __('Site URL', 'alynt-certificates'),
            'admin_email' => __('Admin Email', 'alynt-certificates'),
            'current_date' => __('Current Date', 'alynt-certificates'),
            'current_time' => __('Current Time', 'alynt-certificates')
        );
        
        // Add template-specific variables
        if ($template_id) {
            $template_variables = $this->db->get_template_variables($template_id);
            foreach ($template_variables as $var) {
                $variables[$var->variable_name] = $var->variable_label;
            }
        }
        
        return $variables;
    }
    
    /**
     * AJAX: Save email template
     */
    public function ajax_save_email_template() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $email_data = array(
            'template_id' => intval($_POST['template_id']),
            'name' => sanitize_text_field($_POST['name']),
            'email_subject' => sanitize_text_field($_POST['email_subject']),
            'email_body' => wp_kses_post($_POST['email_body']),
            'to_field' => sanitize_text_field($_POST['to_field']),
            'cc_field' => sanitize_text_field($_POST['cc_field']),
            'bcc_field' => sanitize_text_field($_POST['bcc_field']),
            'is_active' => intval($_POST['is_active']),
            'trigger_on' => sanitize_text_field($_POST['trigger_on']),
            'sort_order' => intval($_POST['sort_order'])
        );
        
        if (isset($_POST['email_template_id']) && $_POST['email_template_id'] > 0) {
            $email_data['id'] = intval($_POST['email_template_id']);
        }
        
        $result = $this->save_email_template($email_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Delete email template
     */
    public function ajax_delete_email_template() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $email_template_id = intval($_POST['email_template_id']);
        $result = $this->delete_email_template($email_template_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Toggle email template
     */
    public function ajax_toggle_email_template() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $email_template_id = intval($_POST['email_template_id']);
        $result = $this->toggle_email_template($email_template_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Test email
     */
    public function ajax_test_email() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $email_template_id = intval($_POST['email_template_id']);
        $test_email = sanitize_email($_POST['test_email']);
        $test_data = isset($_POST['test_data']) ? $_POST['test_data'] : array();
        
        if (!is_email($test_email)) {
            wp_send_json(array('success' => false, 'error' => __('Invalid test email address', 'alynt-certificates')));
        }
        
        $result = $this->test_email_template($email_template_id, $test_email, $test_data);
        
        wp_send_json($result);
    }
}
