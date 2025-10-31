<?php
/**
 * Admin Webhooks View
 * Manage webhook endpoints and configurations
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get webhook settings
$webhook_settings = get_option('alynt_cert_webhook_settings', array());
$webhooks = isset($webhook_settings['webhooks']) ? $webhook_settings['webhooks'] : array();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="alynt-webhooks-container">
        <div class="alynt-webhook-info">
            <h2><?php _e('Webhook Information', 'alynt-certificates'); ?></h2>
            <div class="webhook-endpoints">
                <div class="endpoint-card">
                    <h3><?php _e('Incoming Webhook URL', 'alynt-certificates'); ?></h3>
                    <div class="endpoint-url">
                        <code><?php echo home_url('/wp-json/alynt-certificates/v1/webhook/generate'); ?></code>
                        <button type="button" class="button button-small copy-url" data-url="<?php echo home_url('/wp-json/alynt-certificates/v1/webhook/generate'); ?>">
                            <?php _e('Copy', 'alynt-certificates'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Use this URL to receive webhook requests for certificate generation from external systems.', 'alynt-certificates'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="alynt-webhook-security">
            <h2><?php _e('Security Settings', 'alynt-certificates'); ?></h2>
            <form id="webhook-security-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="webhook-secret"><?php _e('Webhook Secret', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="webhook-secret" name="webhook_secret" class="regular-text" 
                                   value="<?php echo esc_attr(isset($webhook_settings['secret']) ? $webhook_settings['secret'] : ''); ?>"
                                   placeholder="<?php _e('Enter a secret key for webhook verification', 'alynt-certificates'); ?>">
                            <button type="button" class="button button-small" id="generate-secret">
                                <?php _e('Generate', 'alynt-certificates'); ?>
                            </button>
                            <p class="description">
                                <?php _e('A secret key used to verify webhook requests. Include this in the X-Webhook-Secret header.', 'alynt-certificates'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="allowed-ips"><?php _e('Allowed IP Addresses', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <textarea id="allowed-ips" name="allowed_ips" rows="3" class="large-text"
                                      placeholder="<?php _e('Enter IP addresses, one per line (optional)', 'alynt-certificates'); ?>"><?php echo esc_textarea(isset($webhook_settings['allowed_ips']) ? implode("\n", $webhook_settings['allowed_ips']) : ''); ?></textarea>
                            <p class="description">
                                <?php _e('Restrict webhook access to specific IP addresses. Leave empty to allow all IPs.', 'alynt-certificates'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Security Settings', 'alynt-certificates'); ?></button>
                </p>
            </form>
        </div>
        
        <div class="alynt-outgoing-webhooks">
            <h2><?php _e('Outgoing Webhooks', 'alynt-certificates'); ?></h2>
            <p><?php _e('Configure URLs to notify when certificates are generated.', 'alynt-certificates'); ?></p>
            
            <div class="webhooks-header">
                <button type="button" class="button button-primary" id="add-webhook">
                    <?php _e('Add Webhook', 'alynt-certificates'); ?>
                </button>
            </div>
            
            <div class="webhooks-list">
                <?php if (!empty($webhooks)): ?>
                    <?php foreach ($webhooks as $index => $webhook): ?>
                        <div class="webhook-card" data-index="<?php echo $index; ?>">
                            <div class="webhook-header">
                                <h4><?php echo esc_html($webhook['name']); ?></h4>
                                <div class="webhook-actions">
                                    <button type="button" class="button button-small edit-webhook" data-index="<?php echo $index; ?>">
                                        <?php _e('Edit', 'alynt-certificates'); ?>
                                    </button>
                                    <button type="button" class="button button-small test-webhook" data-index="<?php echo $index; ?>">
                                        <?php _e('Test', 'alynt-certificates'); ?>
                                    </button>
                                    <button type="button" class="button button-small button-link-delete delete-webhook" data-index="<?php echo $index; ?>">
                                        <?php _e('Delete', 'alynt-certificates'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="webhook-details">
                                <p><strong><?php _e('URL:', 'alynt-certificates'); ?></strong> <?php echo esc_html($webhook['url']); ?></p>
                                <p><strong><?php _e('Method:', 'alynt-certificates'); ?></strong> <?php echo esc_html($webhook['method']); ?></p>
                                <p><strong><?php _e('Status:', 'alynt-certificates'); ?></strong> 
                                    <span class="status-<?php echo $webhook['enabled'] ? 'enabled' : 'disabled'; ?>">
                                        <?php echo $webhook['enabled'] ? __('Enabled', 'alynt-certificates') : __('Disabled', 'alynt-certificates'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-webhooks">
                        <p><?php _e('No outgoing webhooks configured.', 'alynt-certificates'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alynt-webhook-logs">
            <h2><?php _e('Recent Webhook Activity', 'alynt-certificates'); ?></h2>
            <div class="webhook-logs-container">
                <p><?php _e('Webhook logging will be implemented in a future version.', 'alynt-certificates'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Webhook Modal -->
<div id="webhook-modal" class="alynt-modal" style="display: none;">
    <div class="alynt-modal-content">
        <div class="alynt-modal-header">
            <h2 id="webhook-modal-title"><?php _e('Add Webhook', 'alynt-certificates'); ?></h2>
            <button type="button" class="alynt-modal-close">&times;</button>
        </div>
        
        <div class="alynt-modal-body">
            <form id="webhook-form">
                <input type="hidden" id="webhook-index" name="webhook_index" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="webhook-name"><?php _e('Webhook Name', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="webhook-name" name="webhook_name" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="webhook-url"><?php _e('Webhook URL', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="webhook-url" name="webhook_url" class="large-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="webhook-method"><?php _e('HTTP Method', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="webhook-method" name="webhook_method">
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="webhook-headers"><?php _e('Custom Headers', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <textarea id="webhook-headers" name="webhook_headers" rows="4" class="large-text code"
                                      placeholder="Authorization: Bearer your-token&#10;Content-Type: application/json"></textarea>
                            <p class="description"><?php _e('One header per line in format: Header-Name: Header-Value', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="webhook-enabled"><?php _e('Status', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="webhook-enabled" name="webhook_enabled" value="1" checked>
                                <?php _e('Enable this webhook', 'alynt-certificates'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <div class="alynt-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save Webhook', 'alynt-certificates'); ?></button>
                    <button type="button" class="button alynt-modal-close"><?php _e('Cancel', 'alynt-certificates'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.alynt-webhooks-container {
    max-width: 1000px;
}

.alynt-webhook-info,
.alynt-webhook-security,
.alynt-outgoing-webhooks,
.alynt-webhook-logs {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.alynt-webhook-info h2,
.alynt-webhook-security h2,
.alynt-outgoing-webhooks h2,
.alynt-webhook-logs h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.endpoint-card {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.endpoint-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.endpoint-url {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.endpoint-url code {
    flex: 1;
    padding: 8px 12px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}

.webhooks-header {
    margin-bottom: 20px;
}

.webhooks-list {
    display: grid;
    gap: 15px;
}

.webhook-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #fff;
}

.webhook-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.webhook-header h4 {
    margin: 0;
}

.webhook-actions {
    display: flex;
    gap: 8px;
}

.webhook-details p {
    margin: 5px 0;
    font-size: 14px;
}

.status-enabled {
    color: #46b450;
    font-weight: 600;
}

.status-disabled {
    color: #dc3232;
    font-weight: 600;
}

.no-webhooks {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

.webhook-logs-container {
    min-height: 100px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
    text-align: center;
    color: #666;
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
</style>

<script>
jQuery(document).ready(function($) {
    // Generate secret key
    $('#generate-secret').on('click', function() {
        var secret = generateRandomString(32);
        $('#webhook-secret').val(secret);
    });
    
    // Copy URL functionality
    $('.copy-url').on('click', function() {
        var url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function() {
            alert('<?php _e('URL copied to clipboard!', 'alynt-certificates'); ?>');
        });
    });
    
    // Security form submission
    $('#webhook-security-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'alynt_save_webhook_security',
            nonce: alynt_cert_ajax.nonce,
            webhook_secret: $('#webhook-secret').val(),
            allowed_ips: $('#allowed-ips').val().split('\n').filter(function(ip) {
                return ip.trim() !== '';
            })
        };
        
        $.post(alynt_cert_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    showNotice('success', '<?php _e('Security settings saved successfully!', 'alynt-certificates'); ?>');
                } else {
                    showNotice('error', response.data || '<?php _e('Failed to save settings.', 'alynt-certificates'); ?>');
                }
            })
            .fail(function() {
                showNotice('error', '<?php _e('Network error. Please try again.', 'alynt-certificates'); ?>');
            });
    });
    
    // Add webhook
    $('#add-webhook').on('click', function() {
        $('#webhook-modal-title').text('<?php _e('Add Webhook', 'alynt-certificates'); ?>');
        $('#webhook-form')[0].reset();
        $('#webhook-index').val('');
        $('#webhook-enabled').prop('checked', true);
        $('#webhook-modal').show();
    });
    
    // Edit webhook
    $('.edit-webhook').on('click', function() {
        var index = $(this).data('index');
        $('#webhook-modal-title').text('<?php _e('Edit Webhook', 'alynt-certificates'); ?>');
        $('#webhook-index').val(index);
        // Load webhook data here
        $('#webhook-modal').show();
    });
    
    // Delete webhook
    $('.delete-webhook').on('click', function() {
        if (confirm(alynt_cert_ajax.strings.confirm_delete)) {
            var index = $(this).data('index');
            // Handle deletion
            alert('Delete functionality will be implemented in the next phase.');
        }
    });
    
    // Test webhook
    $('.test-webhook').on('click', function() {
        var index = $(this).data('index');
        alert('Test functionality will be implemented in the next phase.');
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
    
    // Webhook form submission
    $('#webhook-form').on('submit', function(e) {
        e.preventDefault();
        alert('Webhook functionality will be implemented in the next phase.');
    });
    
    function generateRandomString(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
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
