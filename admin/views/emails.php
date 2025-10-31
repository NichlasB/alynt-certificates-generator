<?php
/**
 * Admin Email Templates View
 * Manage email notification templates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get template manager instance
$template_manager = Alynt_Cert_Template_Manager::get_instance();
$templates = $template_manager->get_templates();

// Get current email settings
$email_settings = get_option('alynt_cert_email_settings', array());
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="alynt-email-container">
        <div class="alynt-email-templates">
            <h2><?php _e('Email Templates by Certificate Template', 'alynt-certificates'); ?></h2>
            
            <?php if (!empty($templates)): ?>
                <div class="email-templates-list">
                    <?php foreach ($templates as $template): ?>
                        <?php
                        $email_template_key = 'template_' . $template->id;
                        $email_template = isset($email_settings[$email_template_key]) ? $email_settings[$email_template_key] : array();
                        $is_enabled = isset($email_template['enabled']) ? $email_template['enabled'] : false;
                        ?>
                        <div class="email-template-card" data-template-id="<?php echo $template->id; ?>">
                            <div class="email-template-header">
                                <div class="template-info">
                                    <h3><?php echo esc_html($template->name); ?></h3>
                                    <p><?php echo esc_html($template->description); ?></p>
                                </div>
                                
                                <div class="email-toggle">
                                    <label class="toggle-switch">
                                        <input type="checkbox" class="email-enabled-toggle" 
                                               data-template-id="<?php echo $template->id; ?>"
                                               <?php checked($is_enabled); ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">
                                        <?php _e('Email Notifications', 'alynt-certificates'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="email-template-content" <?php echo !$is_enabled ? 'style="display: none;"' : ''; ?>>
                                <form class="email-template-form" data-template-id="<?php echo $template->id; ?>">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="email-subject-<?php echo $template->id; ?>">
                                                    <?php _e('Email Subject', 'alynt-certificates'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="text" 
                                                       id="email-subject-<?php echo $template->id; ?>" 
                                                       name="email_subject" 
                                                       class="large-text" 
                                                       value="<?php echo esc_attr(isset($email_template['subject']) ? $email_template['subject'] : __('Your Certificate is Ready!', 'alynt-certificates')); ?>"
                                                       placeholder="<?php _e('Your Certificate is Ready!', 'alynt-certificates'); ?>">
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="email-body-<?php echo $template->id; ?>">
                                                    <?php _e('Email Body', 'alynt-certificates'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <?php
                                                $default_body = __("Dear {{recipient_name}},\n\nCongratulations! Your certificate for {{course_name}} is now ready.\n\nYou can download your certificate using the link below:\n{{certificate_url}}\n\nCertificate Number: {{certificate_number}}\nGenerated on: {{generation_date}}\n\nBest regards,\nThe Team", 'alynt-certificates');
                                                $email_body = isset($email_template['body']) ? $email_template['body'] : $default_body;
                                                ?>
                                                <textarea id="email-body-<?php echo $template->id; ?>" 
                                                          name="email_body" 
                                                          rows="12" 
                                                          class="large-text code"
                                                          placeholder="<?php echo esc_attr($default_body); ?>"><?php echo esc_textarea($email_body); ?></textarea>
                                                <p class="description">
                                                    <?php _e('Use HTML tags for formatting. Available variables:', 'alynt-certificates'); ?>
                                                    <code>{{recipient_name}}</code>, 
                                                    <code>{{certificate_url}}</code>, 
                                                    <code>{{certificate_number}}</code>, 
                                                    <code>{{generation_date}}</code>
                                                    <br>
                                                    <?php _e('Template-specific variables will be available based on your certificate template.', 'alynt-certificates'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="from-name-<?php echo $template->id; ?>">
                                                    <?php _e('From Name', 'alynt-certificates'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="text" 
                                                       id="from-name-<?php echo $template->id; ?>" 
                                                       name="from_name" 
                                                       class="regular-text" 
                                                       value="<?php echo esc_attr(isset($email_template['from_name']) ? $email_template['from_name'] : get_bloginfo('name')); ?>"
                                                       placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="from-email-<?php echo $template->id; ?>">
                                                    <?php _e('From Email', 'alynt-certificates'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="email" 
                                                       id="from-email-<?php echo $template->id; ?>" 
                                                       name="from_email" 
                                                       class="regular-text" 
                                                       value="<?php echo esc_attr(isset($email_template['from_email']) ? $email_template['from_email'] : get_option('admin_email')); ?>"
                                                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="reply-to-<?php echo $template->id; ?>">
                                                    <?php _e('Reply To Email', 'alynt-certificates'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="email" 
                                                       id="reply-to-<?php echo $template->id; ?>" 
                                                       name="reply_to" 
                                                       class="regular-text" 
                                                       value="<?php echo esc_attr(isset($email_template['reply_to']) ? $email_template['reply_to'] : ''); ?>"
                                                       placeholder="<?php _e('Optional', 'alynt-certificates'); ?>">
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <div class="email-template-actions">
                                        <button type="submit" class="button button-primary">
                                            <?php _e('Save Email Template', 'alynt-certificates'); ?>
                                        </button>
                                        <button type="button" class="button preview-email" data-template-id="<?php echo $template->id; ?>">
                                            <?php _e('Preview Email', 'alynt-certificates'); ?>
                                        </button>
                                        <button type="button" class="button send-test-email" data-template-id="<?php echo $template->id; ?>">
                                            <?php _e('Send Test Email', 'alynt-certificates'); ?>
                                        </button>
                                        <span class="spinner" id="spinner-<?php echo $template->id; ?>"></span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <div class="alynt-no-templates">
                    <h3><?php _e('No Certificate Templates Found', 'alynt-certificates'); ?></h3>
                    <p><?php _e('You need to create certificate templates before you can configure email notifications.', 'alynt-certificates'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-templates'); ?>" class="button button-primary">
                        <?php _e('Create Certificate Templates', 'alynt-certificates'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="alynt-global-email-settings">
            <h2><?php _e('Global Email Settings', 'alynt-certificates'); ?></h2>
            
            <form id="global-email-settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email-method"><?php _e('Email Method', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="email-method" name="email_method">
                                <option value="wp_mail" <?php selected(get_option('alynt_cert_email_method', 'wp_mail'), 'wp_mail'); ?>>
                                    <?php _e('WordPress Default (wp_mail)', 'alynt-certificates'); ?>
                                </option>
                                <option value="smtp" <?php selected(get_option('alynt_cert_email_method', 'wp_mail'), 'smtp'); ?>>
                                    <?php _e('SMTP', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Choose how emails should be sent.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr id="smtp-settings" style="display: none;">
                        <th scope="row">
                            <label><?php _e('SMTP Configuration', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <p><strong><?php _e('Note:', 'alynt-certificates'); ?></strong> <?php _e('SMTP configuration will be implemented in a future version. For now, please use a dedicated SMTP plugin.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email-content-type"><?php _e('Email Content Type', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="email-content-type" name="email_content_type">
                                <option value="html" <?php selected(get_option('alynt_cert_email_content_type', 'html'), 'html'); ?>>
                                    <?php _e('HTML', 'alynt-certificates'); ?>
                                </option>
                                <option value="text" <?php selected(get_option('alynt_cert_email_content_type', 'html'), 'text'); ?>>
                                    <?php _e('Plain Text', 'alynt-certificates'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Global Settings', 'alynt-certificates'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div id="test-email-modal" class="alynt-modal" style="display: none;">
    <div class="alynt-modal-content">
        <div class="alynt-modal-header">
            <h2><?php _e('Send Test Email', 'alynt-certificates'); ?></h2>
            <button type="button" class="alynt-modal-close">&times;</button>
        </div>
        
        <div class="alynt-modal-body">
            <form id="test-email-form">
                <input type="hidden" id="test-template-id" name="template_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test-email-address"><?php _e('Test Email Address', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="test-email-address" name="test_email" class="regular-text" required>
                            <p class="description"><?php _e('Enter the email address to send the test email to.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="alynt-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Send Test Email', 'alynt-certificates'); ?></button>
                    <button type="button" class="button alynt-modal-close"><?php _e('Cancel', 'alynt-certificates'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div id="email-preview-modal" class="alynt-modal" style="display: none;">
    <div class="alynt-modal-content alynt-modal-large">
        <div class="alynt-modal-header">
            <h2><?php _e('Email Preview', 'alynt-certificates'); ?></h2>
            <button type="button" class="alynt-modal-close">&times;</button>
        </div>
        
        <div class="alynt-modal-body">
            <div class="email-preview-container">
                <div class="email-headers">
                    <p><strong><?php _e('From:', 'alynt-certificates'); ?></strong> <span id="preview-from"></span></p>
                    <p><strong><?php _e('Subject:', 'alynt-certificates'); ?></strong> <span id="preview-subject"></span></p>
                </div>
                <div class="email-body-preview" id="preview-body">
                    <!-- Email body preview will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.alynt-email-container {
    max-width: 1200px;
}

.alynt-email-templates {
    margin-bottom: 40px;
}

.email-templates-list {
    margin-top: 20px;
}

.email-template-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.email-template-header {
    padding: 20px;
    background: #f9f9f9;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.template-info h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
}

.template-info p {
    margin: 0;
    color: #666;
}

.email-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #0073aa;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.toggle-label {
    font-weight: 600;
    color: #333;
}

.email-template-content {
    padding: 20px;
}

.email-template-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
    align-items: center;
}

.alynt-global-email-settings {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.alynt-global-email-settings h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
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

/* Modal Styles */
.alynt-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alynt-modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.alynt-modal-large {
    max-width: 800px;
}

.alynt-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.alynt-modal-header h2 {
    margin: 0;
}

.alynt-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alynt-modal-body {
    padding: 20px;
}

.alynt-modal-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.email-preview-container {
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

.email-headers {
    background: #f9f9f9;
    padding: 15px;
    border-bottom: 1px solid #ddd;
}

.email-headers p {
    margin: 5px 0;
}

.email-body-preview {
    padding: 20px;
    min-height: 200px;
    background: #fff;
}

.spinner {
    float: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Email toggle handler
    $('.email-enabled-toggle').on('change', function() {
        var templateId = $(this).data('template-id');
        var isEnabled = $(this).is(':checked');
        var $content = $(this).closest('.email-template-card').find('.email-template-content');
        
        if (isEnabled) {
            $content.slideDown();
        } else {
            $content.slideUp();
        }
        
        // Save toggle state
        saveEmailToggle(templateId, isEnabled);
    });
    
    // Email method change
    $('#email-method').on('change', function() {
        if ($(this).val() === 'smtp') {
            $('#smtp-settings').show();
        } else {
            $('#smtp-settings').hide();
        }
    });
    
    // Initialize email method display
    $('#email-method').trigger('change');
    
    // Email template form submission
    $('.email-template-form').on('submit', function(e) {
        e.preventDefault();
        var templateId = $(this).data('template-id');
        var $spinner = $('#spinner-' + templateId);
        
        $spinner.addClass('is-active');
        
        // Collect form data
        var formData = {
            action: 'alynt_save_email_template',
            template_id: templateId,
            nonce: alynt_cert_ajax.nonce,
            email_subject: $(this).find('[name="email_subject"]').val(),
            email_body: $(this).find('[name="email_body"]').val(),
            from_name: $(this).find('[name="from_name"]').val(),
            from_email: $(this).find('[name="from_email"]').val(),
            reply_to: $(this).find('[name="reply_to"]').val()
        };
        
        $.post(alynt_cert_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', '<?php _e('Email template saved successfully!', 'alynt-certificates'); ?>');
                } else {
                    showNotice('error', response.data || '<?php _e('Failed to save email template.', 'alynt-certificates'); ?>');
                }
            })
            .fail(function() {
                showNotice('error', '<?php _e('Network error. Please try again.', 'alynt-certificates'); ?>');
            })
            .always(function() {
                $spinner.removeClass('is-active');
            });
    });
    
    // Global settings form
    $('#global-email-settings').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'alynt_save_global_email_settings',
            nonce: alynt_cert_ajax.nonce,
            email_method: $('#email-method').val(),
            email_content_type: $('#email-content-type').val()
        };
        
        $.post(alynt_cert_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', '<?php _e('Global settings saved successfully!', 'alynt-certificates'); ?>');
                } else {
                    showNotice('error', response.data || '<?php _e('Failed to save settings.', 'alynt-certificates'); ?>');
                }
            })
            .fail(function() {
                showNotice('error', '<?php _e('Network error. Please try again.', 'alynt-certificates'); ?>');
            });
    });
    
    // Preview email
    $('.preview-email').on('click', function() {
        var templateId = $(this).data('template-id');
        var $form = $(this).closest('.email-template-form');
        
        var previewData = {
            subject: $form.find('[name="email_subject"]').val(),
            body: $form.find('[name="email_body"]').val(),
            from_name: $form.find('[name="from_name"]').val(),
            from_email: $form.find('[name="from_email"]').val()
        };
        
        // Show preview with sample data
        $('#preview-from').text(previewData.from_name + ' <' + previewData.from_email + '>');
        $('#preview-subject').text(previewData.subject);
        $('#preview-body').html(previewData.body.replace(/\n/g, '<br>'));
        
        $('#email-preview-modal').show();
    });
    
    // Send test email
    $('.send-test-email').on('click', function() {
        var templateId = $(this).data('template-id');
        $('#test-template-id').val(templateId);
        $('#test-email-modal').show();
    });
    
    // Test email form submission
    $('#test-email-form').on('submit', function(e) {
        e.preventDefault();
        
        var templateId = $('#test-template-id').val();
        var testEmail = $('#test-email-address').val();
        
        var formData = {
            action: 'alynt_send_test_email',
            template_id: templateId,
            test_email: testEmail,
            nonce: alynt_cert_ajax.nonce
        };
        
        $.post(alynt_cert_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', '<?php _e('Test email sent successfully!', 'alynt-certificates'); ?>');
                    $('#test-email-modal').hide();
                } else {
                    showNotice('error', response.data || '<?php _e('Failed to send test email.', 'alynt-certificates'); ?>');
                }
            })
            .fail(function() {
                showNotice('error', '<?php _e('Network error. Please try again.', 'alynt-certificates'); ?>');
            });
    });
    
    // Close modals
    $('.alynt-modal-close').on('click', function() {
        $(this).closest('.alynt-modal').hide();
    });
    
    // Close modal on background click
    $('.alynt-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    function saveEmailToggle(templateId, isEnabled) {
        var formData = {
            action: 'alynt_toggle_email_template',
            template_id: templateId,
            enabled: isEnabled ? 1 : 0,
            nonce: alynt_cert_ajax.nonce
        };
        
        $.post(alynt_cert_ajax.ajax_url, formData);
    }
    
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
});
</script>
