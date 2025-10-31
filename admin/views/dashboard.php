<?php
/**
 * Admin Dashboard View
 * Main dashboard for the certificate generator plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get instances
$template_manager = Alynt_Cert_Template_Manager::get_instance();
$db = Alynt_Cert_Database::get_instance();

// Get statistics
$templates = $template_manager->get_templates();
$total_templates = count($templates);

// Get recent certificates (last 10)
global $wpdb;
$certificates_table = $wpdb->prefix . 'alynt_cert_generated';
$recent_certificates = $wpdb->get_results("
    SELECT c.*, t.name as template_name 
    FROM $certificates_table c 
    LEFT JOIN {$wpdb->prefix}alynt_cert_templates t ON c.template_id = t.id 
    ORDER BY c.created_at DESC 
    LIMIT 10
");

$total_certificates = $wpdb->get_var("SELECT COUNT(*) FROM $certificates_table WHERE status = 'active'");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="alynt-dashboard-stats">
        <div class="alynt-stat-box">
            <h3><?php _e('Total Templates', 'alynt-certificates'); ?></h3>
            <div class="stat-number"><?php echo $total_templates; ?></div>
        </div>
        
        <div class="alynt-stat-box">
            <h3><?php _e('Certificates Generated', 'alynt-certificates'); ?></h3>
            <div class="stat-number"><?php echo $total_certificates; ?></div>
        </div>
        
        <div class="alynt-stat-box">
            <h3><?php _e('Active Webhooks', 'alynt-certificates'); ?></h3>
            <div class="stat-number">
                <?php 
                $webhook_handler = Alynt_Cert_Webhook_Handler::get_instance();
                $active_webhooks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}alynt_cert_webhooks WHERE is_active = 1");
                echo $active_webhooks;
                ?>
            </div>
        </div>
    </div>
    
    <div class="alynt-dashboard-content">
        <div class="alynt-dashboard-left">
            <div class="alynt-panel">
                <h2><?php _e('Quick Actions', 'alynt-certificates'); ?></h2>
                <div class="alynt-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-templates'); ?>" class="button button-primary">
                        <?php _e('Manage Templates', 'alynt-certificates'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-generate'); ?>" class="button button-secondary">
                        <?php _e('Generate Certificate', 'alynt-certificates'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-emails'); ?>" class="button button-secondary">
                        <?php _e('Email Templates', 'alynt-certificates'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-webhooks'); ?>" class="button button-secondary">
                        <?php _e('Webhooks', 'alynt-certificates'); ?>
                    </a>
                </div>
            </div>
            
            <div class="alynt-panel">
                <h2><?php _e('Getting Started', 'alynt-certificates'); ?></h2>
                <div class="alynt-getting-started">
                    <ol>
                        <li><?php _e('Create a certificate template by uploading a JPG/PNG image', 'alynt-certificates'); ?></li>
                        <li><?php _e('Add custom variables (name, email, course, etc.)', 'alynt-certificates'); ?></li>
                        <li><?php _e('Position the variables on your template using drag-and-drop', 'alynt-certificates'); ?></li>
                        <li><?php _e('Set up email notification templates (optional)', 'alynt-certificates'); ?></li>
                        <li><?php _e('Generate certificates manually or via webhooks', 'alynt-certificates'); ?></li>
                    </ol>
                </div>
            </div>
            
            <div class="alynt-panel">
                <h2><?php _e('Frontend Usage', 'alynt-certificates'); ?></h2>
                <p><?php _e('Use the shortcode to display certificate generation forms on your website:', 'alynt-certificates'); ?></p>
                <code>[alynt_certificate_form template_id="1" title="Get Your Certificate"]</code>
                <p><small><?php _e('Replace "1" with your actual template ID.', 'alynt-certificates'); ?></small></p>
            </div>
        </div>
        
        <div class="alynt-dashboard-right">
            <div class="alynt-panel">
                <h2><?php _e('Recent Certificates', 'alynt-certificates'); ?></h2>
                <?php if (!empty($recent_certificates)): ?>
                    <div class="alynt-recent-certificates">
                        <?php foreach ($recent_certificates as $cert): ?>
                            <div class="certificate-item">
                                <div class="cert-info">
                                    <strong><?php echo esc_html($cert->certificate_number); ?></strong>
                                    <br>
                                    <small><?php echo esc_html($cert->template_name); ?></small>
                                </div>
                                <div class="cert-meta">
                                    <small><?php echo date('M j, Y', strtotime($cert->created_at)); ?></small>
                                    <br>
                                    <small><?php printf(__('Downloaded %d times', 'alynt-certificates'), $cert->download_count); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No certificates generated yet.', 'alynt-certificates'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="alynt-panel">
                <h2><?php _e('Certificate Templates', 'alynt-certificates'); ?></h2>
                <?php if (!empty($templates)): ?>
                    <div class="alynt-template-list">
                        <?php foreach ($templates as $template): ?>
                            <div class="template-item">
                                <div class="template-info">
                                    <strong><?php echo esc_html($template->name); ?></strong>
                                    <br>
                                    <small><?php echo esc_html($template->orientation); ?> - <?php echo $template->width; ?>x<?php echo $template->height; ?>px</small>
                                </div>
                                <div class="template-actions">
                                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-templates&action=edit&id=' . $template->id); ?>" class="button button-small">
                                        <?php _e('Edit', 'alynt-certificates'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No templates created yet.', 'alynt-certificates'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=alynt-certificates-templates'); ?>" class="button button-primary">
                        <?php _e('Create Your First Template', 'alynt-certificates'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.alynt-dashboard-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.alynt-stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    flex: 1;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.alynt-stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.alynt-dashboard-content {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.alynt-dashboard-left {
    flex: 2;
}

.alynt-dashboard-right {
    flex: 1;
}

.alynt-panel {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.alynt-panel h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
    color: #333;
}

.alynt-quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.alynt-getting-started ol {
    padding-left: 20px;
}

.alynt-getting-started li {
    margin-bottom: 8px;
    line-height: 1.5;
}

.alynt-recent-certificates,
.alynt-template-list {
    max-height: 300px;
    overflow-y: auto;
}

.certificate-item,
.template-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.certificate-item:last-child,
.template-item:last-child {
    border-bottom: none;
}

.cert-info,
.template-info {
    flex: 1;
}

.cert-meta {
    text-align: right;
    color: #666;
}

.template-actions {
    margin-left: 10px;
}

code {
    background: #f1f1f1;
    padding: 4px 8px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
}

@media (max-width: 768px) {
    .alynt-dashboard-stats {
        flex-direction: column;
    }
    
    .alynt-dashboard-content {
        flex-direction: column;
    }
    
    .alynt-quick-actions {
        flex-direction: column;
    }
    
    .alynt-quick-actions .button {
        text-align: center;
    }
}
</style>
