<?php
/**
 * Certificate Form Handler
 * Handles frontend certificate generation forms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Certificate_Form {
    
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
        
        // Add shortcode for certificate forms
        add_shortcode('alynt_certificate_form', array($this, 'certificate_form_shortcode'));
        
        // Add AJAX handlers for frontend
        add_action('wp_ajax_alynt_generate_certificate', array($this, 'ajax_generate_certificate'));
        add_action('wp_ajax_nopriv_alynt_generate_certificate', array($this, 'ajax_generate_certificate'));
    }
    
    /**
     * Certificate form shortcode
     * Usage: [alynt_certificate_form template_id="1"]
     */
    public function certificate_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'template_id' => '',
            'title' => __('Generate Certificate', 'alynt-certificates'),
            'submit_text' => __('Generate Certificate', 'alynt-certificates')
        ), $atts);
        
        if (empty($atts['template_id'])) {
            return '<p>' . __('Error: Template ID is required', 'alynt-certificates') . '</p>';
        }
        
        $template_id = intval($atts['template_id']);
        
        // Get template data
        $template_manager = Alynt_Cert_Template_Manager::get_instance();
        $template = $template_manager->get_template_with_data($template_id);
        
        if (!$template) {
            return '<p>' . __('Error: Template not found', 'alynt-certificates') . '</p>';
        }
        
        // Generate form HTML
        return $this->generate_form_html($template, $atts);
    }
    
    /**
     * Generate form HTML
     */
    private function generate_form_html($template, $atts) {
        ob_start();
        ?>
        <div class="alynt-certificate-form-container">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <form id="alynt-certificate-form-<?php echo $template->id; ?>" class="alynt-certificate-form" data-template-id="<?php echo $template->id; ?>">
                <?php wp_nonce_field('alynt_cert_nonce', 'alynt_cert_nonce'); ?>
                
                <?php if (!empty($template->description)): ?>
                    <div class="form-description">
                        <p><?php echo esc_html($template->description); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="form-fields">
                    <?php foreach ($template->variables as $variable): ?>
                        <div class="form-field">
                            <label for="field_<?php echo esc_attr($variable->variable_name); ?>">
                                <?php echo esc_html($variable->variable_label); ?>
                                <?php if ($variable->is_required): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php $this->render_form_field($variable); ?>
                            
                            <?php if (!empty($variable->validation_rules)): ?>
                                <small class="field-help"><?php echo esc_html($variable->validation_rules); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="alynt-submit-btn">
                        <?php echo esc_html($atts['submit_text']); ?>
                    </button>
                </div>
                
                <div class="form-messages"></div>
            </form>
        </div>
        
        <style>
        .alynt-certificate-form-container {
            max-width: 600px;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .alynt-certificate-form h3 {
            margin-top: 0;
            color: #333;
        }
        
        .form-field {
            margin-bottom: 20px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-field .required {
            color: #e74c3c;
        }
        
        .form-field input,
        .form-field textarea,
        .form-field select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 14px;
        }
        
        .form-field textarea {
            height: 80px;
            resize: vertical;
        }
        
        .field-help {
            display: block;
            margin-top: 5px;
            color: #666;
            font-style: italic;
        }
        
        .alynt-submit-btn {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .alynt-submit-btn:hover {
            background: #005a87;
        }
        
        .alynt-submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .form-messages {
            margin-top: 20px;
        }
        
        .message {
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .certificate-result {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 3px;
            margin-top: 20px;
        }
        
        .certificate-result h4 {
            margin-top: 0;
        }
        
        .download-link {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 10px;
        }
        
        .download-link:hover {
            background: #218838;
            color: white;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.alynt-certificate-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var templateId = form.data('template-id');
                var submitBtn = form.find('.alynt-submit-btn');
                var messagesDiv = form.find('.form-messages');
                
                // Disable submit button
                submitBtn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'alynt-certificates')); ?>');
                
                // Clear previous messages
                messagesDiv.empty();
                
                // Collect form data
                var formData = {
                    action: 'alynt_generate_certificate',
                    template_id: templateId,
                    nonce: form.find('#alynt_cert_nonce').val()
                };
                
                // Add field values
                form.find('input, textarea, select').each(function() {
                    var field = $(this);
                    if (field.attr('name') && field.attr('name') !== 'alynt_cert_nonce') {
                        formData[field.attr('name')] = field.val();
                    }
                });
                
                // Submit via AJAX
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            messagesDiv.html(
                                '<div class="certificate-result">' +
                                '<h4><?php echo esc_js(__('Certificate Generated Successfully!', 'alynt-certificates')); ?></h4>' +
                                '<p><?php echo esc_js(__('Certificate Number:', 'alynt-certificates')); ?> <strong>' + response.data.certificate_number + '</strong></p>' +
                                '<a href="' + response.data.download_url + '" class="download-link" target="_blank"><?php echo esc_js(__('Download Certificate', 'alynt-certificates')); ?></a>' +
                                '</div>'
                            );
                            
                            // Reset form
                            form[0].reset();
                        } else {
                            messagesDiv.html('<div class="message error">' + response.data.error + '</div>');
                        }
                    },
                    error: function() {
                        messagesDiv.html('<div class="message error"><?php echo esc_js(__('An error occurred. Please try again.', 'alynt-certificates')); ?></div>');
                    },
                    complete: function() {
                        // Re-enable submit button
                        submitBtn.prop('disabled', false).text('<?php echo esc_js($atts['submit_text']); ?>');
                    }
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render individual form field
     */
    private function render_form_field($variable) {
        $field_name = 'field_' . $variable->variable_name;
        $field_id = $field_name;
        $required = $variable->is_required ? 'required' : '';
        $default_value = esc_attr($variable->default_value);
        
        switch ($variable->variable_type) {
            case 'email':
                echo '<input type="email" id="' . $field_id . '" name="' . $field_name . '" value="' . $default_value . '" ' . $required . '>';
                break;
                
            case 'number':
                echo '<input type="number" id="' . $field_id . '" name="' . $field_name . '" value="' . $default_value . '" ' . $required . '>';
                break;
                
            case 'date':
                echo '<input type="date" id="' . $field_id . '" name="' . $field_name . '" value="' . $default_value . '" ' . $required . '>';
                break;
                
            case 'textarea':
                echo '<textarea id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>' . $default_value . '</textarea>';
                break;
                
            case 'select':
                echo '<select id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>';
                echo '<option value="">' . __('Select...', 'alynt-certificates') . '</option>';
                
                // Parse options from validation_rules (format: option1|option2|option3)
                if (!empty($variable->validation_rules)) {
                    $options = explode('|', $variable->validation_rules);
                    foreach ($options as $option) {
                        $option = trim($option);
                        $selected = ($option === $default_value) ? 'selected' : '';
                        echo '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
                    }
                }
                echo '</select>';
                break;
                
            default: // text
                echo '<input type="text" id="' . $field_id . '" name="' . $field_name . '" value="' . $default_value . '" ' . $required . '>';
                break;
        }
    }
    
    /**
     * AJAX handler for certificate generation
     */
    public function ajax_generate_certificate() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'alynt_cert_nonce')) {
            wp_send_json_error(array('error' => __('Security check failed', 'alynt-certificates')));
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
        
        // Extract recipient data from form fields
        $recipient_data = array();
        foreach ($template->variables as $variable) {
            $field_name = 'field_' . $variable->variable_name;
            if (isset($_POST[$field_name])) {
                $recipient_data[$variable->variable_name] = sanitize_text_field($_POST[$field_name]);
            }
        }
        
        // Sanitize data
        $security = Alynt_Cert_Security::get_instance();
        $recipient_data = $security->sanitize_certificate_data($recipient_data);
        
        // Generate certificate
        $pdf_generator = Alynt_Cert_PDF_Generator::get_instance();
        $result = $pdf_generator->generate_certificate($template_id, $recipient_data, 'frontend');
        
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
