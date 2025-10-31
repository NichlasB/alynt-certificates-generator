<?php
/**
 * Admin Settings View
 * General plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('alynt_cert_settings', array());
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="alynt-settings-container">
        <form id="alynt-settings-form" method="post" action="options.php">
            <?php settings_fields('alynt_cert_settings_group'); ?>
            
            <div class="settings-section">
                <h2><?php _e('General Settings', 'alynt-certificates'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="certificate-numbering"><?php _e('Certificate Numbering', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="certificate-numbering" name="alynt_cert_settings[certificate_numbering]">
                                <option value="auto" <?php selected(isset($settings['certificate_numbering']) ? $settings['certificate_numbering'] : 'auto', 'auto'); ?>>
                                    <?php _e('Auto-increment (CERT-0001, CERT-0002, etc.)', 'alynt-certificates'); ?>
                                </option>
                                <option value="timestamp" <?php selected(isset($settings['certificate_numbering']) ? $settings['certificate_numbering'] : 'auto', 'timestamp'); ?>>
                                    <?php _e('Timestamp-based', 'alynt-certificates'); ?>
                                </option>
                                <option value="uuid" <?php selected(isset($settings['certificate_numbering']) ? $settings['certificate_numbering'] : 'auto', 'uuid'); ?>>
                                    <?php _e('UUID (Unique identifier)', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Choose how certificate numbers are generated.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="certificate-prefix"><?php _e('Certificate Number Prefix', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="certificate-prefix" name="alynt_cert_settings[certificate_prefix]" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr(isset($settings['certificate_prefix']) ? $settings['certificate_prefix'] : 'CERT'); ?>"
                                   placeholder="CERT">
                            <p class="description"><?php _e('Prefix for certificate numbers (e.g., CERT-0001).', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="certificate-expiry"><?php _e('Certificate Expiry', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="certificate-expiry" name="alynt_cert_settings[certificate_expiry]">
                                <option value="never" <?php selected(isset($settings['certificate_expiry']) ? $settings['certificate_expiry'] : 'never', 'never'); ?>>
                                    <?php _e('Never expire', 'alynt-certificates'); ?>
                                </option>
                                <option value="1_year" <?php selected(isset($settings['certificate_expiry']) ? $settings['certificate_expiry'] : 'never', '1_year'); ?>>
                                    <?php _e('1 Year', 'alynt-certificates'); ?>
                                </option>
                                <option value="2_years" <?php selected(isset($settings['certificate_expiry']) ? $settings['certificate_expiry'] : 'never', '2_years'); ?>>
                                    <?php _e('2 Years', 'alynt-certificates'); ?>
                                </option>
                                <option value="3_years" <?php selected(isset($settings['certificate_expiry']) ? $settings['certificate_expiry'] : 'never', '3_years'); ?>>
                                    <?php _e('3 Years', 'alynt-certificates'); ?>
                                </option>
                                <option value="custom" <?php selected(isset($settings['certificate_expiry']) ? $settings['certificate_expiry'] : 'never', 'custom'); ?>>
                                    <?php _e('Custom', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <input type="number" id="custom-expiry-days" name="alynt_cert_settings[custom_expiry_days]" 
                                   class="small-text" min="1" max="3650"
                                   value="<?php echo esc_attr(isset($settings['custom_expiry_days']) ? $settings['custom_expiry_days'] : '365'); ?>"
                                   style="display: none;">
                            <span id="custom-expiry-label" style="display: none;"><?php _e('days', 'alynt-certificates'); ?></span>
                            <p class="description"><?php _e('Set certificate expiration period.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2><?php _e('Security & Access', 'alynt-certificates'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="minimum-role"><?php _e('Minimum User Role', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="minimum-role" name="alynt_cert_settings[minimum_role]">
                                <option value="edit_posts" <?php selected(isset($settings['minimum_role']) ? $settings['minimum_role'] : 'edit_posts', 'edit_posts'); ?>>
                                    <?php _e('Editor', 'alynt-certificates'); ?>
                                </option>
                                <option value="manage_options" <?php selected(isset($settings['minimum_role']) ? $settings['minimum_role'] : 'edit_posts', 'manage_options'); ?>>
                                    <?php _e('Administrator', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Minimum user role required to create templates and generate certificates.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="download-protection"><?php _e('Download Protection', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="download-protection" name="alynt_cert_settings[download_protection]" value="1" 
                                       <?php checked(isset($settings['download_protection']) ? $settings['download_protection'] : 1, 1); ?>>
                                <?php _e('Enable secure download URLs', 'alynt-certificates'); ?>
                            </label>
                            <p class="description"><?php _e('Use hard-to-guess URLs and prevent search engine indexing.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="download-expiry"><?php _e('Download Link Expiry', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="download-expiry" name="alynt_cert_settings[download_expiry]">
                                <option value="never" <?php selected(isset($settings['download_expiry']) ? $settings['download_expiry'] : '30_days', 'never'); ?>>
                                    <?php _e('Never expire', 'alynt-certificates'); ?>
                                </option>
                                <option value="7_days" <?php selected(isset($settings['download_expiry']) ? $settings['download_expiry'] : '30_days', '7_days'); ?>>
                                    <?php _e('7 Days', 'alynt-certificates'); ?>
                                </option>
                                <option value="30_days" <?php selected(isset($settings['download_expiry']) ? $settings['download_expiry'] : '30_days', '30_days'); ?>>
                                    <?php _e('30 Days', 'alynt-certificates'); ?>
                                </option>
                                <option value="90_days" <?php selected(isset($settings['download_expiry']) ? $settings['download_expiry'] : '30_days', '90_days'); ?>>
                                    <?php _e('90 Days', 'alynt-certificates'); ?>
                                </option>
                                <option value="1_year" <?php selected(isset($settings['download_expiry']) ? $settings['download_expiry'] : '30_days', '1_year'); ?>>
                                    <?php _e('1 Year', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('How long download links remain valid.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2><?php _e('PDF Generation', 'alynt-certificates'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pdf-quality"><?php _e('PDF Quality', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="pdf-quality" name="alynt_cert_settings[pdf_quality]">
                                <option value="72" <?php selected(isset($settings['pdf_quality']) ? $settings['pdf_quality'] : '150', '72'); ?>>
                                    <?php _e('Low (72 DPI) - Smaller file size', 'alynt-certificates'); ?>
                                </option>
                                <option value="150" <?php selected(isset($settings['pdf_quality']) ? $settings['pdf_quality'] : '150', '150'); ?>>
                                    <?php _e('Medium (150 DPI) - Recommended', 'alynt-certificates'); ?>
                                </option>
                                <option value="300" <?php selected(isset($settings['pdf_quality']) ? $settings['pdf_quality'] : '150', '300'); ?>>
                                    <?php _e('High (300 DPI) - Print quality', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Higher quality results in larger file sizes.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default-font"><?php _e('Default Font', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="default-font" name="alynt_cert_settings[default_font]">
                                <option value="helvetica" <?php selected(isset($settings['default_font']) ? $settings['default_font'] : 'helvetica', 'helvetica'); ?>>
                                    <?php _e('Helvetica', 'alynt-certificates'); ?>
                                </option>
                                <option value="times" <?php selected(isset($settings['default_font']) ? $settings['default_font'] : 'helvetica', 'times'); ?>>
                                    <?php _e('Times Roman', 'alynt-certificates'); ?>
                                </option>
                                <option value="courier" <?php selected(isset($settings['default_font']) ? $settings['default_font'] : 'helvetica', 'courier'); ?>>
                                    <?php _e('Courier', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Default font for certificate text.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2><?php _e('Storage & Cleanup', 'alynt-certificates'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="storage-location"><?php _e('Storage Location', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="storage-location" name="alynt_cert_settings[storage_location]">
                                <option value="media_library" <?php selected(isset($settings['storage_location']) ? $settings['storage_location'] : 'media_library', 'media_library'); ?>>
                                    <?php _e('WordPress Media Library', 'alynt-certificates'); ?>
                                </option>
                                <option value="uploads_folder" <?php selected(isset($settings['storage_location']) ? $settings['storage_location'] : 'media_library', 'uploads_folder'); ?>>
                                    <?php _e('Custom Uploads Folder', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Where generated certificates are stored.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto-cleanup"><?php _e('Automatic Cleanup', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto-cleanup" name="alynt_cert_settings[auto_cleanup]" value="1" 
                                       <?php checked(isset($settings['auto_cleanup']) ? $settings['auto_cleanup'] : 0, 1); ?>>
                                <?php _e('Enable automatic cleanup of expired certificates', 'alynt-certificates'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically delete certificate files after they expire.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2><?php _e('Debug & Logging', 'alynt-certificates'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="debug-mode"><?php _e('Debug Mode', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="debug-mode" name="alynt_cert_settings[debug_mode]" value="1" 
                                       <?php checked(isset($settings['debug_mode']) ? $settings['debug_mode'] : 0, 1); ?>>
                                <?php _e('Enable debug logging', 'alynt-certificates'); ?>
                            </label>
                            <p class="description"><?php _e('Log detailed information for troubleshooting. Disable in production.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="log-retention"><?php _e('Log Retention', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="log-retention" name="alynt_cert_settings[log_retention]">
                                <option value="7" <?php selected(isset($settings['log_retention']) ? $settings['log_retention'] : '30', '7'); ?>>
                                    <?php _e('7 Days', 'alynt-certificates'); ?>
                                </option>
                                <option value="30" <?php selected(isset($settings['log_retention']) ? $settings['log_retention'] : '30', '30'); ?>>
                                    <?php _e('30 Days', 'alynt-certificates'); ?>
                                </option>
                                <option value="90" <?php selected(isset($settings['log_retention']) ? $settings['log_retention'] : '30', '90'); ?>>
                                    <?php _e('90 Days', 'alynt-certificates'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('How long to keep log files.', 'alynt-certificates'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button(__('Save Settings', 'alynt-certificates')); ?>
        </form>
        
        <div class="settings-section">
            <h2><?php _e('System Information', 'alynt-certificates'); ?></h2>
            
            <div class="system-info">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Plugin Version:', 'alynt-certificates'); ?></strong></td>
                            <td><?php echo ALYNT_CERT_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Version:', 'alynt-certificates'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version:', 'alynt-certificates'); ?></strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Upload Directory:', 'alynt-certificates'); ?></strong></td>
                            <td>
                                <?php 
                                $upload_dir = wp_upload_dir();
                                echo $upload_dir['basedir'] . '/certificates/';
                                ?>
                                <?php if (is_writable($upload_dir['basedir'] . '/certificates/')): ?>
                                    <span style="color: green;">✓ <?php _e('Writable', 'alynt-certificates'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Not writable', 'alynt-certificates'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Database Tables:', 'alynt-certificates'); ?></strong></td>
                            <td>
                                <?php
                                global $wpdb;
                                $tables = array(
                                    $wpdb->prefix . 'alynt_cert_templates',
                                    $wpdb->prefix . 'alynt_cert_variables',
                                    $wpdb->prefix . 'alynt_cert_generated'
                                );
                                
                                $all_exist = true;
                                foreach ($tables as $table) {
                                    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                                        $all_exist = false;
                                        break;
                                    }
                                }
                                
                                if ($all_exist): ?>
                                    <span style="color: green;">✓ <?php _e('All tables exist', 'alynt-certificates'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Some tables missing', 'alynt-certificates'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.alynt-settings-container {
    max-width: 1000px;
}

.settings-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.settings-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.system-info {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.system-info table {
    margin: 0;
}

.system-info td {
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
}

.system-info tr:last-child td {
    border-bottom: none;
}

#custom-expiry-days,
#custom-expiry-label {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Certificate expiry change handler
    $('#certificate-expiry').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#custom-expiry-days, #custom-expiry-label').show();
        } else {
            $('#custom-expiry-days, #custom-expiry-label').hide();
        }
    });
    
    // Initialize expiry display
    $('#certificate-expiry').trigger('change');
});
</script>
