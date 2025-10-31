<?php
/**
 * Certificate Download Handler
 * Handles secure certificate downloads
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Certificate_Download {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('template_redirect', array($this, 'handle_download_request'));
    }
    
    /**
     * Handle certificate download requests
     */
    public function handle_download_request() {
        if (!get_query_var('alynt_cert_download')) {
            return;
        }
        
        $cert_token = get_query_var('cert_token');
        $cert_file = get_query_var('cert_file');
        
        if (empty($cert_token) || empty($cert_file)) {
            wp_die(__('Invalid certificate request', 'alynt-certificates'), 404);
        }
        
        // Get security instance
        $security = Alynt_Cert_Security::get_instance();
        
        // Serve the certificate through security layer
        $this->serve_certificate_download($cert_token, $cert_file);
    }
    
    /**
     * Serve certificate download
     */
    private function serve_certificate_download($token, $filename) {
        // Get PDF generator instance
        $pdf_generator = Alynt_Cert_PDF_Generator::get_instance();
        
        // Get certificate by token
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
        
        // Log download event
        $security = Alynt_Cert_Security::get_instance();
        $security->log_security_event('certificate_download', array(
            'certificate_id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'filename' => $filename
        ));
        
        // Set headers and serve file
        $this->set_download_headers($filename, $certificate->file_path);
        readfile($certificate->file_path);
        exit;
    }
    
    /**
     * Set download headers
     */
    private function set_download_headers($filename, $filepath) {
        // Clear any existing output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set content type
        header('Content-Type: application/pdf');
        
        // Set download headers
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        
        // Set security headers
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // Set file size
        if (file_exists($filepath)) {
            header('Content-Length: ' . filesize($filepath));
        }
    }
}
