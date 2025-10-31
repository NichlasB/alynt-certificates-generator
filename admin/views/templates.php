<?php
/**
 * Admin Templates View
 * Manage certificate templates
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
    
    <div class="alynt-templates-header">
        <button type="button" class="button button-primary" id="alynt-add-template">
            <?php _e('Add New Template', 'alynt-certificates'); ?>
        </button>
    </div>
    
    <div class="alynt-templates-grid">
        <?php if (!empty($templates)): ?>
            <?php foreach ($templates as $template): ?>
                <div class="alynt-template-card" data-template-id="<?php echo $template->id; ?>">
                    <div class="template-preview">
                        <?php if ($template->background_image): ?>
                            <img src="<?php echo esc_url($template->background_image); ?>" alt="<?php echo esc_attr($template->name); ?>">
                        <?php else: ?>
                            <div class="no-preview"><?php _e('No Preview', 'alynt-certificates'); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="template-info">
                        <h3><?php echo esc_html($template->name); ?></h3>
                        <p><?php echo esc_html($template->description); ?></p>
                        
                        <div class="template-meta">
                            <span class="orientation"><?php echo ucfirst($template->orientation); ?></span>
                            <span class="variables-count"><?php echo count($template->variables); ?> <?php _e('variables', 'alynt-certificates'); ?></span>
                        </div>
                        
                        <div class="template-actions">
                            <button type="button" class="button button-small alynt-edit-template" data-template-id="<?php echo $template->id; ?>">
                                <?php _e('Edit', 'alynt-certificates'); ?>
                            </button>
                            <button type="button" class="button button-small alynt-duplicate-template" data-template-id="<?php echo $template->id; ?>">
                                <?php _e('Duplicate', 'alynt-certificates'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete alynt-delete-template" data-template-id="<?php echo $template->id; ?>">
                                <?php _e('Delete', 'alynt-certificates'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alynt-no-templates">
                <h3><?php _e('No Templates Found', 'alynt-certificates'); ?></h3>
                <p><?php _e('Create your first certificate template to get started.', 'alynt-certificates'); ?></p>
                <button type="button" class="button button-primary" id="alynt-add-first-template">
                    <?php _e('Create First Template', 'alynt-certificates'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Template Editor Modal -->
<div id="alynt-template-modal" class="alynt-modal" style="display: none;">
    <div class="alynt-modal-content">
        <div class="alynt-modal-header">
            <h2 id="alynt-modal-title"><?php _e('Add New Template', 'alynt-certificates'); ?></h2>
            <button type="button" class="alynt-modal-close">&times;</button>
        </div>
        
        <div class="alynt-modal-body">
            <form id="alynt-template-form">
                <input type="hidden" id="template-id" name="template_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="template-name"><?php _e('Template Name', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="template-name" name="template_name" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="template-description"><?php _e('Description', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <textarea id="template-description" name="template_description" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="template-orientation"><?php _e('Orientation', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <select id="template-orientation" name="template_orientation">
                                <option value="horizontal"><?php _e('Horizontal (Landscape)', 'alynt-certificates'); ?></option>
                                <option value="vertical"><?php _e('Vertical (Portrait)', 'alynt-certificates'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="background-image"><?php _e('Background Image', 'alynt-certificates'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="background-image" name="background_image" accept="image/jpeg,image/jpg,image/png">
                            <p class="description"><?php _e('Upload a JPG or PNG image for the certificate background.', 'alynt-certificates'); ?></p>
                            <div id="background-preview" style="display: none;">
                                <img src="" alt="Background Preview" style="max-width: 300px; height: auto;">
                            </div>
                        </td>
                    </tr>
                </table>
                
                <div class="alynt-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save Template', 'alynt-certificates'); ?></button>
                    <button type="button" class="button alynt-modal-close"><?php _e('Cancel', 'alynt-certificates'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.alynt-templates-header {
    margin-bottom: 20px;
}

.alynt-templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.alynt-template-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.alynt-template-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.template-preview {
    height: 200px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.template-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.no-preview {
    color: #666;
    font-style: italic;
}

.template-info {
    padding: 15px;
}

.template-info h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.template-info p {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
}

.template-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    font-size: 12px;
    color: #888;
}

.template-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.alynt-no-templates {
    grid-column: 1 / -1;
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

#background-preview img {
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add new template
    $('#alynt-add-template, #alynt-add-first-template').on('click', function() {
        $('#alynt-modal-title').text('<?php _e('Add New Template', 'alynt-certificates'); ?>');
        $('#alynt-template-form')[0].reset();
        $('#template-id').val('');
        $('#background-preview').hide();
        $('#alynt-template-modal').show();
    });
    
    // Edit template
    $('.alynt-edit-template').on('click', function() {
        var templateId = $(this).data('template-id');
        // Load template data via AJAX
        // Implementation would go here
        $('#alynt-modal-title').text('<?php _e('Edit Template', 'alynt-certificates'); ?>');
        $('#template-id').val(templateId);
        $('#alynt-template-modal').show();
    });
    
    // Close modal
    $('.alynt-modal-close').on('click', function() {
        $('#alynt-template-modal').hide();
    });
    
    // Close modal on background click
    $('#alynt-template-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Background image preview
    $('#background-image').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#background-preview img').attr('src', e.target.result);
                $('#background-preview').show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#background-preview').hide();
        }
    });
    
    // Form submission
    $('#alynt-template-form').on('submit', function(e) {
        e.preventDefault();
        // Handle form submission via AJAX
        // Implementation would go here
        alert('Template functionality will be implemented in the next phase.');
    });
    
    // Delete template
    $('.alynt-delete-template').on('click', function() {
        if (confirm(alynt_cert_ajax.strings.confirm_delete)) {
            var templateId = $(this).data('template-id');
            // Handle deletion via AJAX
            // Implementation would go here
            alert('Delete functionality will be implemented in the next phase.');
        }
    });
    
    // Duplicate template
    $('.alynt-duplicate-template').on('click', function() {
        var templateId = $(this).data('template-id');
        // Handle duplication via AJAX
        // Implementation would go here
        alert('Duplicate functionality will be implemented in the next phase.');
    });
});
</script>
