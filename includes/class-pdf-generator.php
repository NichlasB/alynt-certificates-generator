<?php
/**
 * PDF Generator class
 * Handles PDF certificate generation using TCPDF
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if TCPDF is available, otherwise use alternative
if (!class_exists('TCPDF')) {
    // Try to include TCPDF from plugin directory
    $tcpdf_path = ALYNT_CERT_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    } else {
        // TCPDF not available - we'll create a simple PDF alternative
        // For now, we'll create a placeholder class
        if (!class_exists('TCPDF')) {
            class TCPDF {
                public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {}
                public function AddPage() {}
                public function SetFont($family, $style = '', $size = 0) {}
                public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {}
                public function Image($file, $x = '', $y = '', $w = 0, $h = 0) {}
                public function Output($name = 'doc.pdf', $dest = 'I') { return 'PDF generation requires TCPDF library'; }
                public function SetXY($x, $y) {}
                public function SetTextColor($r, $g = -1, $b = -1) {}
            }
        }
    }
}

class Alynt_Cert_PDF_Generator {
    
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
    }
    
    /**
     * Generate certificate PDF
     */
    public function generate_certificate($template_id, $recipient_data, $generation_method = 'manual', $webhook_data = null) {
        try {
            // Get template data
            $template = $this->db->get_template($template_id);
            if (!$template) {
                throw new Exception(__('Template not found', 'alynt-certificates'));
            }
            
            // Get template variables and placeholders
            $variables = $this->db->get_template_variables($template_id);
            $placeholders = $this->db->get_template_placeholders($template_id);
            
            // Validate required fields
            $this->validate_recipient_data($variables, $recipient_data);
            
            // Generate certificate number
            $settings = get_option('alynt_cert_settings', array());
            $prefix = isset($settings['certificate_prefix']) ? $settings['certificate_prefix'] : 'CERT';
            $certificate_number = $this->db->generate_certificate_number($prefix);
            
            // Create PDF
            $pdf_data = $this->create_pdf($template, $placeholders, $recipient_data, $certificate_number);
            
            // Save PDF to media library
            $file_info = $this->save_to_media_library($pdf_data, $certificate_number, $template->name);
            
            // Generate download token
            $download_token = $this->generate_download_token();
            
            // Save certificate record
            $cert_data = array(
                'certificate_number' => $certificate_number,
                'template_id' => $template_id,
                'recipient_data' => $recipient_data,
                'file_path' => $file_info['file_path'],
                'file_url' => $file_info['file_url'],
                'download_token' => $download_token,
                'generated_by' => get_current_user_id(),
                'generation_method' => $generation_method,
                'webhook_data' => $webhook_data,
                'status' => 'active'
            );
            
            $certificate_id = $this->db->save_generated_certificate($cert_data);
            
            if (!$certificate_id) {
                throw new Exception(__('Failed to save certificate record', 'alynt-certificates'));
            }
            
            // Add certificate URL to recipient data for email templates
            $recipient_data['certificate_url'] = $this->get_certificate_download_url($download_token, $file_info['filename']);
            $recipient_data['certificate_number'] = $certificate_number;
            $recipient_data['generation_date'] = current_time('mysql');
            
            // Send email notifications
            $this->send_email_notifications($template_id, $recipient_data);
            
            // Trigger outgoing webhooks
            if ($generation_method === 'manual') {
                $this->trigger_outgoing_webhooks($template_id, $certificate_id, $recipient_data);
            }
            
            return array(
                'success' => true,
                'certificate_id' => $certificate_id,
                'certificate_number' => $certificate_number,
                'download_url' => $recipient_data['certificate_url'],
                'file_path' => $file_info['file_path']
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create PDF using TCPDF
     */
    private function create_pdf($template, $placeholders, $recipient_data, $certificate_number) {
        // Create new PDF document
        $orientation = ($template->orientation === 'portrait') ? 'P' : 'L';
        $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Alynt Certificates Generator');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Certificate - ' . $certificate_number);
        $pdf->SetSubject('Certificate');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Add a page
        $pdf->AddPage();
        
        // Get template image path
        $template_image_path = $this->get_template_image_path($template->template_image);
        
        if ($template_image_path && file_exists($template_image_path)) {
            // Get page dimensions
            $page_width = $pdf->getPageWidth();
            $page_height = $pdf->getPageHeight();
            
            // Add background image
            $pdf->Image($template_image_path, 0, 0, $page_width, $page_height, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Add text placeholders
        foreach ($placeholders as $placeholder) {
            $variable_name = $placeholder->variable_name;
            $text_value = isset($recipient_data[$variable_name]) ? $recipient_data[$variable_name] : '';
            
            if (!empty($text_value)) {
                // Convert pixel positions to mm (assuming 96 DPI)
                $x_mm = ($placeholder->x_position * 25.4) / 96;
                $y_mm = ($placeholder->y_position * 25.4) / 96;
                $width_mm = ($placeholder->width * 25.4) / 96;
                $height_mm = ($placeholder->height * 25.4) / 96;
                
                // Set font
                $pdf->SetFont($placeholder->font_family, '', $placeholder->font_size);
                
                // Set text color
                $color = $this->hex_to_rgb($placeholder->font_color);
                $pdf->SetTextColor($color['r'], $color['g'], $color['b']);
                
                // Set position and add text
                $pdf->SetXY($x_mm, $y_mm);
                
                // Determine text alignment
                $align = 'L';
                switch ($placeholder->text_align) {
                    case 'center':
                        $align = 'C';
                        break;
                    case 'right':
                        $align = 'R';
                        break;
                }
                
                // Add text cell
                $pdf->Cell($width_mm, $height_mm, $text_value, 0, 0, $align, false, '', 0, false, 'T', 'M');
            }
        }
        
        // Return PDF as string
        return $pdf->Output('', 'S');
    }
    
    /**
     * Save PDF to WordPress media library
     */
    private function save_to_media_library($pdf_data, $certificate_number, $template_name) {
        $upload_dir = wp_upload_dir();
        
        // Create certificates subdirectory with random token
        $cert_token = wp_generate_password(12, false);
        $cert_dir = $upload_dir['basedir'] . '/certificates/' . $cert_token;
        
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }
        
        // Generate filename
        $filename = sanitize_file_name($certificate_number . '-' . $template_name . '.pdf');
        $file_path = $cert_dir . '/' . $filename;
        
        // Save PDF file
        if (file_put_contents($file_path, $pdf_data) === false) {
            throw new Exception(__('Failed to save PDF file', 'alynt-certificates'));
        }
        
        // Create attachment in media library
        $attachment = array(
            'guid' => $upload_dir['baseurl'] . '/certificates/' . $cert_token . '/' . $filename,
            'post_mime_type' => 'application/pdf',
            'post_title' => $certificate_number . ' - ' . $template_name,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (!$attachment_id) {
            throw new Exception(__('Failed to create media library entry', 'alynt-certificates'));
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Add custom meta to hide from media library searches
        update_post_meta($attachment_id, '_alynt_cert_file', true);
        update_post_meta($attachment_id, '_alynt_cert_number', $certificate_number);
        update_post_meta($attachment_id, '_alynt_cert_token', $cert_token);
        
        return array(
            'attachment_id' => $attachment_id,
            'file_path' => $file_path,
            'file_url' => $upload_dir['baseurl'] . '/certificates/' . $cert_token . '/' . $filename,
            'filename' => $filename,
            'cert_token' => $cert_token
        );
    }
    
    /**
     * Generate secure download token
     */
    private function generate_download_token() {
        return wp_generate_password(32, false);
    }
    
    /**
     * Get certificate download URL
     */
    private function get_certificate_download_url($download_token, $filename) {
        return home_url('/certificates/' . $download_token . '/' . $filename);
    }
    
    /**
     * Get template image path from URL
     */
    private function get_template_image_path($image_url) {
        $upload_dir = wp_upload_dir();
        
        // Convert URL to file path
        if (strpos($image_url, $upload_dir['baseurl']) === 0) {
            return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        }
        
        return false;
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * Validate recipient data against template variables
     */
    private function validate_recipient_data($variables, $recipient_data) {
        foreach ($variables as $variable) {
            if ($variable->is_required) {
                if (!isset($recipient_data[$variable->variable_name]) || 
                    empty(trim($recipient_data[$variable->variable_name]))) {
                    throw new Exception(
                        sprintf(__('Required field "%s" is missing or empty', 'alynt-certificates'), 
                        $variable->variable_label)
                    );
                }
            }
            
            // Additional validation based on variable type
            if (isset($recipient_data[$variable->variable_name])) {
                $value = $recipient_data[$variable->variable_name];
                
                switch ($variable->variable_type) {
                    case 'email':
                        if (!is_email($value)) {
                            throw new Exception(
                                sprintf(__('Invalid email format for field "%s"', 'alynt-certificates'), 
                                $variable->variable_label)
                            );
                        }
                        break;
                        
                    case 'number':
                        if (!is_numeric($value)) {
                            throw new Exception(
                                sprintf(__('Field "%s" must be a number', 'alynt-certificates'), 
                                $variable->variable_label)
                            );
                        }
                        break;
                        
                    case 'date':
                        if (!strtotime($value)) {
                            throw new Exception(
                                sprintf(__('Invalid date format for field "%s"', 'alynt-certificates'), 
                                $variable->variable_label)
                            );
                        }
                        break;
                }
            }
        }
    }
    
    /**
     * Send email notifications
     */
    private function send_email_notifications($template_id, $recipient_data) {
        $email_manager = Alynt_Cert_Email_Manager::get_instance();
        $email_manager->send_certificate_notifications($template_id, $recipient_data);
    }
    
    /**
     * Trigger outgoing webhooks
     */
    private function trigger_outgoing_webhooks($template_id, $certificate_id, $recipient_data) {
        $webhook_handler = Alynt_Cert_Webhook_Handler::get_instance();
        $webhook_handler->trigger_outgoing_webhooks($template_id, $certificate_id, $recipient_data);
    }
    
    /**
     * Get certificate by download token (for download handler)
     */
    public function get_certificate_for_download($token) {
        return $this->db->get_certificate_by_token($token);
    }
    
    /**
     * Update download count
     */
    public function update_download_count($certificate_id) {
        return $this->db->update_download_count($certificate_id);
    }
}
