<?php
/**
 * Webhook Handler class
 * Handles incoming and outgoing webhooks for certificate generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Webhook_Handler {
    
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
        
        // Add webhook endpoints
        add_action('init', array($this, 'add_webhook_endpoints'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
        
        // Add AJAX handlers
        add_action('wp_ajax_alynt_save_webhook', array($this, 'ajax_save_webhook'));
        add_action('wp_ajax_alynt_delete_webhook', array($this, 'ajax_delete_webhook'));
        add_action('wp_ajax_alynt_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_alynt_toggle_webhook', array($this, 'ajax_toggle_webhook'));
    }
    
    /**
     * Add webhook rewrite rules
     */
    public function add_webhook_endpoints() {
        add_rewrite_rule(
            '^alynt-webhook/([a-zA-Z0-9]+)/?$',
            'index.php?alynt_webhook=1&webhook_key=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%alynt_webhook%', '([^&]+)');
        add_rewrite_tag('%webhook_key%', '([^&]+)');
    }
    
    /**
     * Handle incoming webhook requests
     */
    public function handle_webhook_request() {
        if (!get_query_var('alynt_webhook')) {
            return;
        }
        
        $webhook_key = get_query_var('webhook_key');
        
        if (empty($webhook_key)) {
            $this->send_webhook_response(400, array('error' => 'Missing webhook key'));
            return;
        }
        
        // Get webhook configuration
        $webhook = $this->get_webhook_by_key($webhook_key);
        
        if (!$webhook || !$webhook->is_active) {
            $this->send_webhook_response(404, array('error' => 'Webhook not found or inactive'));
            return;
        }
        
        // Validate request method
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST') {
            $this->send_webhook_response(405, array('error' => 'Method not allowed'));
            return;
        }
        
        // Get request data
        $raw_input = file_get_contents('php://input');
        $request_data = json_decode($raw_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->send_webhook_response(400, array('error' => 'Invalid JSON payload'));
            return;
        }
        
        // Validate webhook signature if configured
        if (!empty($webhook->secret_key)) {
            if (!$this->validate_webhook_signature($raw_input, $webhook->secret_key)) {
                $this->send_webhook_response(401, array('error' => 'Invalid signature'));
                return;
            }
        }
        
        // Process webhook
        $result = $this->process_incoming_webhook($webhook, $request_data);
        
        // Log webhook request
        $this->log_webhook_request($webhook->id, null, $request_data, $result, $result['success']);
        
        // Send response
        $status_code = $result['success'] ? 200 : 400;
        $this->send_webhook_response($status_code, $result);
    }
    
    /**
     * Process incoming webhook
     */
    private function process_incoming_webhook($webhook, $request_data) {
        try {
            // Validate required fields
            if (empty($request_data['template_id'])) {
                throw new Exception('Missing template_id');
            }
            
            $template_id = intval($request_data['template_id']);
            
            // Get template to validate it exists
            $template = $this->db->get_template($template_id);
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            // Extract recipient data
            $recipient_data = isset($request_data['recipient_data']) ? $request_data['recipient_data'] : $request_data;
            
            // Remove system fields from recipient data
            unset($recipient_data['template_id']);
            
            // Generate certificate
            $pdf_generator = Alynt_Cert_PDF_Generator::get_instance();
            $result = $pdf_generator->generate_certificate($template_id, $recipient_data, 'webhook', $request_data);
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => 'Certificate generated successfully',
                    'certificate_number' => $result['certificate_number'],
                    'download_url' => $result['download_url']
                );
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Validate webhook signature
     */
    private function validate_webhook_signature($payload, $secret) {
        $headers = getallheaders();
        $signature_header = null;
        
        // Check for common signature headers
        foreach ($headers as $header => $value) {
            $header_lower = strtolower($header);
            if (in_array($header_lower, ['x-signature', 'x-hub-signature', 'x-webhook-signature'])) {
                $signature_header = $value;
                break;
            }
        }
        
        if (!$signature_header) {
            return false;
        }
        
        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $payload, $secret);
        
        // Remove algorithm prefix if present (e.g., "sha256=")
        if (strpos($signature_header, '=') !== false) {
            $signature_header = explode('=', $signature_header, 2)[1];
        }
        
        return hash_equals($expected_signature, $signature_header);
    }
    
    /**
     * Send webhook response
     */
    private function send_webhook_response($status_code, $data) {
        status_header($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Get webhook by API key
     */
    private function get_webhook_by_key($api_key) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_key = %s AND webhook_type = 'incoming'",
            $api_key
        ));
    }
    
    /**
     * Trigger outgoing webhooks
     */
    public function trigger_outgoing_webhooks($template_id, $certificate_id, $recipient_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        $webhooks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE webhook_type = 'outgoing' 
             AND is_active = 1 
             AND (template_id = %d OR template_id IS NULL)",
            $template_id
        ));
        
        foreach ($webhooks as $webhook) {
            $this->send_outgoing_webhook($webhook, $certificate_id, $recipient_data);
        }
    }
    
    /**
     * Send outgoing webhook
     */
    private function send_outgoing_webhook($webhook, $certificate_id, $recipient_data) {
        try {
            // Prepare payload
            $payload = array(
                'event' => 'certificate_generated',
                'certificate_id' => $certificate_id,
                'timestamp' => current_time('timestamp'),
                'data' => $recipient_data
            );
            
            // Process custom payload template if configured
            if (!empty($webhook->payload_template)) {
                $custom_payload = json_decode($webhook->payload_template, true);
                if ($custom_payload) {
                    $payload = array_merge($payload, $custom_payload);
                }
            }
            
            // Replace variables in payload
            $payload = $this->process_webhook_variables($payload, $recipient_data);
            
            // Prepare headers
            $headers = array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Alynt-Certificates-Webhook/1.0'
            );
            
            // Add API key if configured
            if (!empty($webhook->api_key)) {
                $headers['Authorization'] = 'Bearer ' . $webhook->api_key;
            }
            
            // Add custom headers if configured
            if (!empty($webhook->headers)) {
                $custom_headers = json_decode($webhook->headers, true);
                if ($custom_headers) {
                    $headers = array_merge($headers, $custom_headers);
                }
            }
            
            // Add signature if secret key is configured
            if (!empty($webhook->secret_key)) {
                $payload_json = json_encode($payload);
                $signature = hash_hmac('sha256', $payload_json, $webhook->secret_key);
                $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
            }
            
            // Send request
            $response = wp_remote_post($webhook->endpoint_url, array(
                'headers' => $headers,
                'body' => json_encode($payload),
                'timeout' => 30
            ));
            
            // Process response
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            $success = ($status_code >= 200 && $status_code < 300);
            
            // Update webhook stats
            $this->update_webhook_stats($webhook->id, $success);
            
            // Log webhook request
            $this->log_webhook_request($webhook->id, $certificate_id, $payload, array(
                'status_code' => $status_code,
                'response' => $response_body
            ), $success);
            
            return array('success' => $success, 'status_code' => $status_code);
            
        } catch (Exception $e) {
            // Update error stats
            $this->update_webhook_stats($webhook->id, false);
            
            // Log error
            $this->log_webhook_request($webhook->id, $certificate_id, $payload, array(
                'error' => $e->getMessage()
            ), false);
            
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Process variables in webhook payload
     */
    private function process_webhook_variables($payload, $variables) {
        $payload_json = json_encode($payload);
        
        // Replace variables
        foreach ($variables as $key => $value) {
            $payload_json = str_replace('{' . $key . '}', $value, $payload_json);
        }
        
        // Replace system variables
        $payload_json = str_replace('{site_name}', get_bloginfo('name'), $payload_json);
        $payload_json = str_replace('{site_url}', home_url(), $payload_json);
        $payload_json = str_replace('{timestamp}', current_time('timestamp'), $payload_json);
        
        return json_decode($payload_json, true);
    }
    
    /**
     * Update webhook statistics
     */
    private function update_webhook_stats($webhook_id, $success) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        
        if ($success) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table 
                 SET success_count = success_count + 1, last_triggered = NOW() 
                 WHERE id = %d",
                $webhook_id
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table 
                 SET error_count = error_count + 1 
                 WHERE id = %d",
                $webhook_id
            ));
        }
    }
    
    /**
     * Log webhook request
     */
    private function log_webhook_request($webhook_id, $certificate_id, $request_data, $response_data, $success) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_webhook_logs';
        
        $log_data = array(
            'webhook_id' => $webhook_id,
            'certificate_id' => $certificate_id,
            'request_data' => json_encode($request_data),
            'response_data' => json_encode($response_data),
            'status_code' => isset($response_data['status_code']) ? $response_data['status_code'] : null,
            'success' => $success ? 1 : 0,
            'error_message' => isset($response_data['error']) ? $response_data['error'] : null
        );
        
        $wpdb->insert($table, $log_data);
    }
    
    /**
     * Get webhooks
     */
    public function get_webhooks($webhook_type = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        
        if ($webhook_type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE webhook_type = %s ORDER BY created_at DESC",
                $webhook_type
            ));
        } else {
            return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        }
    }
    
    /**
     * Save webhook configuration
     */
    public function save_webhook($data) {
        // Validate required fields
        if (empty($data['name']) || empty($data['webhook_type'])) {
            return array('success' => false, 'error' => __('Name and webhook type are required', 'alynt-certificates'));
        }
        
        if ($data['webhook_type'] === 'outgoing' && empty($data['endpoint_url'])) {
            return array('success' => false, 'error' => __('Endpoint URL is required for outgoing webhooks', 'alynt-certificates'));
        }
        
        // Generate API key for incoming webhooks
        if ($data['webhook_type'] === 'incoming' && empty($data['api_key'])) {
            $data['api_key'] = wp_generate_password(32, false);
        }
        
        $webhook_data = array(
            'name' => sanitize_text_field($data['name']),
            'webhook_type' => sanitize_text_field($data['webhook_type']),
            'endpoint_url' => esc_url_raw($data['endpoint_url']),
            'api_key' => sanitize_text_field($data['api_key']),
            'secret_key' => sanitize_text_field($data['secret_key']),
            'template_id' => !empty($data['template_id']) ? intval($data['template_id']) : null,
            'is_active' => intval($data['is_active']),
            'headers' => wp_json_encode($data['headers']),
            'payload_template' => wp_json_encode($data['payload_template'])
        );
        
        global $wpdb;
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        
        if (isset($data['id']) && $data['id'] > 0) {
            $result = $wpdb->update($table, $webhook_data, array('id' => intval($data['id'])));
            return $result !== false ? array('success' => true, 'webhook_id' => intval($data['id'])) : array('success' => false, 'error' => __('Failed to update webhook', 'alynt-certificates'));
        } else {
            $result = $wpdb->insert($table, $webhook_data);
            return $result ? array('success' => true, 'webhook_id' => $wpdb->insert_id, 'api_key' => $webhook_data['api_key']) : array('success' => false, 'error' => __('Failed to create webhook', 'alynt-certificates'));
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_save_webhook() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $webhook_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'webhook_type' => sanitize_text_field($_POST['webhook_type']),
            'endpoint_url' => esc_url_raw($_POST['endpoint_url']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'secret_key' => sanitize_text_field($_POST['secret_key']),
            'template_id' => !empty($_POST['template_id']) ? intval($_POST['template_id']) : null,
            'is_active' => intval($_POST['is_active']),
            'headers' => isset($_POST['headers']) ? $_POST['headers'] : array(),
            'payload_template' => isset($_POST['payload_template']) ? $_POST['payload_template'] : array()
        );
        
        if (isset($_POST['webhook_id']) && $_POST['webhook_id'] > 0) {
            $webhook_data['id'] = intval($_POST['webhook_id']);
        }
        
        $result = $this->save_webhook($webhook_data);
        wp_send_json($result);
    }
    
    public function ajax_delete_webhook() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        global $wpdb;
        $webhook_id = intval($_POST['webhook_id']);
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        
        $result = $wpdb->delete($table, array('id' => $webhook_id));
        
        if ($result) {
            wp_send_json(array('success' => true));
        } else {
            wp_send_json(array('success' => false, 'error' => __('Failed to delete webhook', 'alynt-certificates')));
        }
    }
    
    public function ajax_toggle_webhook() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        global $wpdb;
        $webhook_id = intval($_POST['webhook_id']);
        $table = $wpdb->prefix . 'alynt_cert_webhooks';
        
        // Get current status
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM $table WHERE id = %d",
            $webhook_id
        ));
        
        if ($current_status === null) {
            wp_send_json(array('success' => false, 'error' => __('Webhook not found', 'alynt-certificates')));
        }
        
        // Toggle status
        $new_status = $current_status ? 0 : 1;
        $result = $wpdb->update($table, array('is_active' => $new_status), array('id' => $webhook_id));
        
        if ($result !== false) {
            wp_send_json(array('success' => true, 'new_status' => $new_status));
        } else {
            wp_send_json(array('success' => false, 'error' => __('Failed to update webhook status', 'alynt-certificates')));
        }
    }
}
