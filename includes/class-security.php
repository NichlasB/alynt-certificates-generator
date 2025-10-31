<?php
/**
 * Security class
 * Handles security features for the certificate generator plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Security {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add security hooks
        add_action('init', array($this, 'init_security'));
        add_filter('pre_get_posts', array($this, 'hide_certificate_attachments'));
        add_action('wp_head', array($this, 'add_robots_meta'));
    }
    
    /**
     * Initialize security features
     */
    public function init_security() {
        // Add robots.txt rules to prevent certificate indexing
        add_action('do_robotstxt', array($this, 'add_robots_txt_rules'));
        
        // Prevent direct access to certificate files
        add_action('template_redirect', array($this, 'protect_certificate_files'));
    }
    
    /**
     * Hide certificate attachments from media library searches
     */
    public function hide_certificate_attachments($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Hide certificate files from media library
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'attachment') {
            $meta_query = $query->get('meta_query');
            if (!is_array($meta_query)) {
                $meta_query = array();
            }
            
            $meta_query[] = array(
                'key' => '_alynt_cert_file',
                'compare' => 'NOT EXISTS'
            );
            
            $query->set('meta_query', $meta_query);
        }
    }
    
    /**
     * Add robots meta tag to prevent indexing
     */
    public function add_robots_meta() {
        if (get_query_var('alynt_cert_download')) {
            echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
        }
    }
    
    /**
     * Add robots.txt rules
     */
    public function add_robots_txt_rules() {
        echo "# Alynt Certificates Generator - Prevent certificate indexing\n";
        echo "Disallow: /wp-content/uploads/certificates/\n";
        echo "Disallow: /certificates/\n";
        echo "\n";
    }
    
    /**
     * Protect certificate files from direct access
     */
    public function protect_certificate_files() {
        // Check if this is a certificate download request
        if (!get_query_var('alynt_cert_download')) {
            return;
        }
        
        $cert_token = get_query_var('cert_token');
        $cert_file = get_query_var('cert_file');
        
        if (empty($cert_token) || empty($cert_file)) {
            wp_die(__('Invalid certificate request', 'alynt-certificates'), 404);
        }
        
        // Validate and serve the certificate
        $this->serve_certificate($cert_token, $cert_file);
    }
    
    /**
     * Serve certificate file securely
     */
    private function serve_certificate($token, $filename) {
        // Get certificate by token
        $pdf_generator = Alynt_Cert_PDF_Generator::get_instance();
        $certificate = $pdf_generator->get_certificate_for_download($token);
        
        if (!$certificate) {
            wp_die(__('Certificate not found or expired', 'alynt-certificates'), 404);
        }
        
        // Validate filename matches
        $expected_filename = basename($certificate->file_path);
        if ($filename !== $expected_filename) {
            wp_die(__('Invalid certificate file', 'alynt-certificates'), 404);
        }
        
        // Check if file exists
        if (!file_exists($certificate->file_path)) {
            wp_die(__('Certificate file not found', 'alynt-certificates'), 404);
        }
        
        // Update download count
        $pdf_generator->update_download_count($certificate->id);
        
        // Set security headers
        $this->set_download_headers($filename);
        
        // Serve the file
        readfile($certificate->file_path);
        exit;
    }
    
    /**
     * Set secure download headers
     */
    private function set_download_headers($filename) {
        // Clear any existing output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set content type
        header('Content-Type: application/pdf');
        
        // Set download headers
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        
        // Set security headers
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // Set file size if available
        if (file_exists($filename)) {
            header('Content-Length: ' . filesize($filename));
        }
    }
    
    /**
     * Validate user permissions for certificate operations
     */
    public function can_manage_certificates($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Check if user has editor role or higher
        return user_can($user, 'edit_posts');
    }
    
    /**
     * Sanitize certificate data
     */
    public function sanitize_certificate_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_certificate_data($value);
            } else {
                // Sanitize based on field type
                switch ($key) {
                    case 'email':
                        $sanitized[$key] = sanitize_email($value);
                        break;
                    case 'url':
                    case 'website':
                        $sanitized[$key] = esc_url_raw($value);
                        break;
                    case 'phone':
                        $sanitized[$key] = preg_replace('/[^0-9+\-\(\)\s]/', '', $value);
                        break;
                    default:
                        $sanitized[$key] = sanitize_text_field($value);
                        break;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Generate secure random token
     */
    public function generate_secure_token($length = 32) {
        return wp_generate_password($length, false);
    }
    
    /**
     * Validate API key format
     */
    public function validate_api_key($api_key) {
        // API key should be at least 16 characters and contain only alphanumeric characters
        return preg_match('/^[a-zA-Z0-9]{16,}$/', $api_key);
    }
    
    /**
     * Rate limiting for webhook requests
     */
    public function check_rate_limit($identifier, $max_requests = 100, $time_window = 3600) {
        $transient_key = 'alynt_cert_rate_limit_' . md5($identifier);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request in time window
            set_transient($transient_key, 1, $time_window);
            return true;
        }
        
        if ($requests >= $max_requests) {
            return false;
        }
        
        // Increment request count
        set_transient($transient_key, $requests + 1, $time_window);
        return true;
    }
    
    /**
     * Log security events
     */
    public function log_security_event($event_type, $details = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'details' => $details
        );
        
        // Store in WordPress logs or custom table
        error_log('Alynt Certificates Security Event: ' . json_encode($log_entry));
        
        // Optionally store in database for admin review
        $this->store_security_log($log_entry);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Store security log in database
     */
    private function store_security_log($log_entry) {
        global $wpdb;
        
        // Create security logs table if it doesn't exist
        $table_name = $wpdb->prefix . 'alynt_cert_security_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            event_type varchar(50) NOT NULL,
            user_id int(11),
            ip_address varchar(45),
            user_agent text,
            details longtext,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_event_type (event_type),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert log entry
        $wpdb->insert($table_name, array(
            'timestamp' => $log_entry['timestamp'],
            'event_type' => $log_entry['event_type'],
            'user_id' => $log_entry['user_id'],
            'ip_address' => $log_entry['ip_address'],
            'user_agent' => $log_entry['user_agent'],
            'details' => json_encode($log_entry['details'])
        ));
    }
    
    /**
     * Clean up old security logs
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'alynt_cert_security_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Validate file upload security
     */
    public function validate_file_upload($file) {
        // Check file size (max 10MB for certificate templates)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            return array('success' => false, 'error' => __('File size too large. Maximum 10MB allowed.', 'alynt-certificates'));
        }
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png');
        if (!in_array($file['type'], $allowed_types)) {
            return array('success' => false, 'error' => __('Invalid file type. Only JPG and PNG files are allowed.', 'alynt-certificates'));
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png');
        if (!in_array($file_extension, $allowed_extensions)) {
            return array('success' => false, 'error' => __('Invalid file extension.', 'alynt-certificates'));
        }
        
        // Additional security checks
        if (!getimagesize($file['tmp_name'])) {
            return array('success' => false, 'error' => __('Invalid image file.', 'alynt-certificates'));
        }
        
        return array('success' => true);
    }
}
