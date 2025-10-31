<?php
/**
 * Admin Generate Certificate View
 * Manual certificate generation interface
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get template manager instance
$template_manager = Alynt_Cert_Template_Manager::get_instance();
$templates = $template_manager->get_templates();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="alynt-generate-container">
        <?php if (!empty($templates)): ?>
            <div class="alynt-generate-form">
                <form id="alynt-certificate-form">
                    <div class="form-section">
                        <h2><?php _e('Select Template', 'alynt-certificates'); ?></h2>
                        <div class="template-selector">
                            <?php foreach ($templates as $template): ?>
                                <div class="template-option">
                                    <input type="radio" id="template-<?php echo $template->id; ?>" name="template_id" value="<?php echo $template->id; ?>" data-template-id="<?php echo $template->id; ?>">
                                    <label for="template-<?php echo $template->id; ?>">
                                        <div class="template-preview-small">
                                            <?php if ($template->background_image): ?>
                                                <img src="<?php echo esc_url($template->background_image); ?>" alt="<?php echo esc_attr($template->name); ?>">
                                            <?php else: ?>
                                                <div class="no-preview"><?php _e('No Preview', 'alynt-certificates'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="template-info">
                                            <h4><?php echo esc_html($template->name); ?></h4>
                                            <p><?php echo esc_html($template->description); ?></p>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-section" id="certificate-fields" style="display: none;">
                        <h2><?php _e('Certificate Information', 'alynt-certificates'); ?></h2>
                        <div id="dynamic-fields">
                            <!-- Dynamic fields will be loaded here based on selected template -->
                        </div>
                    </div>
                    
                    <div class="form-section" id="generation-options" style="display: none;">
                        <h2><?php _e('Generation Options', 'alynt-certificates'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="send-email"><?php _e('Send Email Notification', 'alynt-certificates'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="send-email" name="send_email" value="1">
                                    <p class="description"><?php _e('Send an email notification to the recipient with the certificate.', 'alynt-certificates'); ?></p>
                                </td>
                            </tr>
                            
                            <tr id="email-field" style="display: none;">
                                <th scope="row">
                                    <label for="recipient-email"><?php _e('Recipient Email', 'alynt-certificates'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="recipient-email" name="recipient_email" class="regular-text">
                                    <p class="description"><?php _e('Email address to send the certificate to.', 'alynt-certificates'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="form-actions" id="form-actions" style="display: none;">
                        <button type="submit" class="button button-primary button-large" id="generate-certificate">
                            <?php _e('Generate Certificate', 'alynt-certificates'); ?>
                        </button>
                        <span class="spinner" id="generation-spinner"></span>
                    </div>
                </form>
            </div>
            
            <div class="alynt-generation-result" id="generation-result" style="display: none;">
                <div class="result-success" id="result-success" style="display: none;">
                    <h3><?php _e('Certificate Generated Successfully!', 'alynt-certificates'); ?></h3>
                    <div class="result-info">
                        <p><strong><?php _e('Certificate Number:', 'alynt-certificates'); ?></strong> <span id="cert-number"></span></p>
                        <p><strong><?php _e('Download URL:', 'alynt-certificates'); ?></strong> <a href="#" id="cert-download-url" target="_blank"><?php _e('Download Certificate', 'alynt-certificates'); ?></a></p>
                    </div>
                    <div class="result-actions">
                        <button type="button" class="button" id="generate-another"><?php _e('Generate Another Certificate', 'alynt-certificates'); ?></button>
                        <button type="button" class="button" id="copy-download-url"><?php _e('Copy Download URL', 'alynt-certificates'); ?></button>
                    </div>
                </div>
                
                <div class="result-error" id="result-error" style="display: none;">
                    <h3><?php _e('Generation Failed', 'alynt-certificates'); ?></h3>
                    <div class="error-message" id="error-message"></div>
                    <div class="result-actions">
                        <button type="button" class="button" id="try-again"><?php _e('Try Again', 'alynt-certificates'); ?></button>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="alynt-no-templates">
                <h3><?php _e('No Templates Available', 'alynt-certificates'); ?></h3>
                <p><?php _e('You need to create at least one certificate template before you can generate certificates.', 'alynt-certificates'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=alynt-certificates-templates'); ?>" class="button button-primary">
                    <?php _e('Create Your First Template', 'alynt-certificates'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.alynt-generate-container {
    max-width: 800px;
}

.form-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-section h2 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.template-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.template-option {
    position: relative;
}

.template-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.template-option label {
    display: block;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fff;
}

.template-option input[type="radio"]:checked + label {
    border-color: #0073aa;
    background: #f0f8ff;
}

.template-option label:hover {
    border-color: #0073aa;
}

.template-preview-small {
    height: 80px;
    background: #f5f5f5;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    overflow: hidden;
}

.template-preview-small img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.template-preview-small .no-preview {
    color: #666;
    font-size: 12px;
    font-style: italic;
}

.template-info h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
}

.template-info p {
    margin: 0;
    font-size: 12px;
    color: #666;
}

#dynamic-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.dynamic-field {
    display: flex;
    flex-direction: column;
}

.dynamic-field label {
    font-weight: 600;
    margin-bottom: 5px;
}

.dynamic-field input,
.dynamic-field textarea,
.dynamic-field select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.dynamic-field textarea {
    resize: vertical;
    min-height: 80px;
}

.form-actions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.alynt-generation-result {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.result-success {
    border-left: 4px solid #46b450;
    padding-left: 15px;
}

.result-error {
    border-left: 4px solid #dc3232;
    padding-left: 15px;
}

.result-info {
    margin: 15px 0;
}

.result-info p {
    margin: 8px 0;
}

.result-actions {
    margin-top: 15px;
}

.result-actions .button {
    margin-right: 10px;
}

.error-message {
    background: #ffeaea;
    border: 1px solid #dc3232;
    border-radius: 4px;
    padding: 10px;
    margin: 10px 0;
    color: #dc3232;
}

.alynt-no-templates {
    text-align: center;
    padding: 60px 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.alynt-no-templates h3 {
    margin-bottom: 10px;
    color: #666;
}

.alynt-no-templates p {
    margin-bottom: 20px;
    color: #888;
}

#generation-spinner {
    float: none;
    margin-left: 10px;
}

#email-field {
    transition: all 0.3s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Template selection handler
    $('input[name="template_id"]').on('change', function() {
        var templateId = $(this).val();
        if (templateId) {
            loadTemplateFields(templateId);
            $('#certificate-fields, #generation-options, #form-actions').show();
        }
    });
    
    // Email notification toggle
    $('#send-email').on('change', function() {
        if ($(this).is(':checked')) {
            $('#email-field').show();
            $('#recipient-email').prop('required', true);
        } else {
            $('#email-field').hide();
            $('#recipient-email').prop('required', false);
        }
    });
    
    // Form submission
    $('#alynt-certificate-form').on('submit', function(e) {
        e.preventDefault();
        generateCertificate();
    });
    
    // Result actions
    $('#generate-another').on('click', function() {
        $('#generation-result').hide();
        $('#alynt-certificate-form')[0].reset();
        $('#certificate-fields, #generation-options, #form-actions').hide();
        $('#dynamic-fields').empty();
    });
    
    $('#try-again').on('click', function() {
        $('#generation-result').hide();
    });
    
    $('#copy-download-url').on('click', function() {
        var url = $('#cert-download-url').attr('href');
        navigator.clipboard.writeText(url).then(function() {
            alert('<?php _e('Download URL copied to clipboard!', 'alynt-certificates'); ?>');
        });
    });
    
    function loadTemplateFields(templateId) {
        // This would normally load template variables via AJAX
        // For now, we'll show some example fields
        var exampleFields = [
            {name: 'recipient_name', label: '<?php _e('Recipient Name', 'alynt-certificates'); ?>', type: 'text', required: true},
            {name: 'course_name', label: '<?php _e('Course Name', 'alynt-certificates'); ?>', type: 'text', required: true},
            {name: 'completion_date', label: '<?php _e('Completion Date', 'alynt-certificates'); ?>', type: 'date', required: true},
            {name: 'instructor_name', label: '<?php _e('Instructor Name', 'alynt-certificates'); ?>', type: 'text', required: false}
        ];
        
        var fieldsHtml = '';
        exampleFields.forEach(function(field) {
            fieldsHtml += '<div class="dynamic-field">';
            fieldsHtml += '<label for="' + field.name + '">' + field.label;
            if (field.required) fieldsHtml += ' <span style="color: red;">*</span>';
            fieldsHtml += '</label>';
            
            if (field.type === 'textarea') {
                fieldsHtml += '<textarea id="' + field.name + '" name="' + field.name + '"';
            } else {
                fieldsHtml += '<input type="' + field.type + '" id="' + field.name + '" name="' + field.name + '"';
            }
            
            if (field.required) fieldsHtml += ' required';
            
            if (field.type === 'textarea') {
                fieldsHtml += '></textarea>';
            } else {
                fieldsHtml += '>';
            }
            
            fieldsHtml += '</div>';
        });
        
        $('#dynamic-fields').html(fieldsHtml);
    }
    
    function generateCertificate() {
        var $form = $('#alynt-certificate-form');
        var $button = $('#generate-certificate');
        var $spinner = $('#generation-spinner');
        
        // Show loading state
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Collect form data
        var formData = new FormData($form[0]);
        formData.append('action', 'alynt_admin_generate_certificate');
        formData.append('nonce', alynt_cert_ajax.nonce);
        
        // Submit via AJAX
        $.ajax({
            url: alynt_cert_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showError(response.data.error || '<?php _e('Unknown error occurred.', 'alynt-certificates'); ?>');
                }
            },
            error: function() {
                showError('<?php _e('Network error. Please try again.', 'alynt-certificates'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    function showSuccess(data) {
        $('#cert-number').text(data.certificate_number);
        $('#cert-download-url').attr('href', data.download_url);
        $('#result-success').show();
        $('#result-error').hide();
        $('#generation-result').show();
        
        // Scroll to result
        $('html, body').animate({
            scrollTop: $('#generation-result').offset().top - 50
        }, 500);
    }
    
    function showError(message) {
        $('#error-message').text(message);
        $('#result-error').show();
        $('#result-success').hide();
        $('#generation-result').show();
        
        // Scroll to result
        $('html, body').animate({
            scrollTop: $('#generation-result').offset().top - 50
        }, 500);
    }
});
</script>
