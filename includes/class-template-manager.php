<?php
/**
 * Template Manager class
 * Handles certificate template operations and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alynt_Cert_Template_Manager {
    
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
        
        // Add AJAX handlers
        add_action('wp_ajax_alynt_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_alynt_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_alynt_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_alynt_save_variable', array($this, 'ajax_save_variable'));
        add_action('wp_ajax_alynt_delete_variable', array($this, 'ajax_delete_variable'));
        add_action('wp_ajax_alynt_save_placeholder', array($this, 'ajax_save_placeholder'));
        add_action('wp_ajax_alynt_delete_placeholder', array($this, 'ajax_delete_placeholder'));
        add_action('wp_ajax_alynt_upload_template_image', array($this, 'ajax_upload_template_image'));
    }
    
    /**
     * Get all templates
     */
    public function get_templates($status = 'active') {
        return $this->db->get_templates($status);
    }
    
    /**
     * Get single template with all related data
     */
    public function get_template_with_data($template_id) {
        $template = $this->db->get_template($template_id);
        if (!$template) {
            return false;
        }
        
        $template->variables = $this->db->get_template_variables($template_id);
        $template->placeholders = $this->db->get_template_placeholders($template_id);
        
        return $template;
    }
    
    /**
     * Create new template
     */
    public function create_template($data) {
        // Validate required fields
        if (empty($data['name'])) {
            return array('success' => false, 'error' => __('Template name is required', 'alynt-certificates'));
        }
        
        if (empty($data['template_image'])) {
            return array('success' => false, 'error' => __('Template image is required', 'alynt-certificates'));
        }
        
        // Get image dimensions
        $image_path = $this->get_image_path_from_url($data['template_image']);
        $image_size = getimagesize($image_path);
        
        if (!$image_size) {
            return array('success' => false, 'error' => __('Invalid image file', 'alynt-certificates'));
        }
        
        // Determine orientation
        $orientation = ($image_size[0] > $image_size[1]) ? 'landscape' : 'portrait';
        
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'template_image' => esc_url_raw($data['template_image']),
            'orientation' => $orientation,
            'width' => $image_size[0],
            'height' => $image_size[1],
            'variables' => array(),
            'placeholders' => array(),
            'status' => 'active'
        );
        
        $template_id = $this->db->save_template($template_data);
        
        if ($template_id) {
            return array('success' => true, 'template_id' => $template_id);
        } else {
            return array('success' => false, 'error' => __('Failed to create template', 'alynt-certificates'));
        }
    }
    
    /**
     * Update existing template
     */
    public function update_template($template_id, $data) {
        $data['id'] = $template_id;
        $result = $this->db->save_template($data);
        
        if ($result) {
            return array('success' => true, 'template_id' => $template_id);
        } else {
            return array('success' => false, 'error' => __('Failed to update template', 'alynt-certificates'));
        }
    }
    
    /**
     * Delete template
     */
    public function delete_template($template_id) {
        // Soft delete - set status to inactive
        $data = array(
            'id' => $template_id,
            'status' => 'inactive'
        );
        
        $result = $this->db->save_template($data);
        
        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => __('Failed to delete template', 'alynt-certificates'));
        }
    }
    
    /**
     * Add variable to template
     */
    public function add_variable($template_id, $data) {
        // Validate required fields
        if (empty($data['variable_name']) || empty($data['variable_label'])) {
            return array('success' => false, 'error' => __('Variable name and label are required', 'alynt-certificates'));
        }
        
        // Check if variable name already exists for this template
        $existing_variables = $this->db->get_template_variables($template_id);
        foreach ($existing_variables as $var) {
            if ($var->variable_name === $data['variable_name']) {
                return array('success' => false, 'error' => __('Variable name already exists', 'alynt-certificates'));
            }
        }
        
        $variable_data = array(
            'template_id' => $template_id,
            'variable_name' => sanitize_text_field($data['variable_name']),
            'variable_label' => sanitize_text_field($data['variable_label']),
            'variable_type' => sanitize_text_field($data['variable_type']),
            'is_required' => intval($data['is_required']),
            'default_value' => sanitize_textarea_field($data['default_value']),
            'validation_rules' => sanitize_textarea_field($data['validation_rules']),
            'sort_order' => intval($data['sort_order'])
        );
        
        $result = $this->db->save_variable($variable_data);
        
        if ($result) {
            return array('success' => true, 'variable_id' => $result);
        } else {
            return array('success' => false, 'error' => __('Failed to add variable', 'alynt-certificates'));
        }
    }
    
    /**
     * Update variable
     */
    public function update_variable($variable_id, $data) {
        $data['id'] = $variable_id;
        $result = $this->db->save_variable($data);
        
        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => __('Failed to update variable', 'alynt-certificates'));
        }
    }
    
    /**
     * Delete variable
     */
    public function delete_variable($variable_id) {
        global $wpdb;
        
        // Delete variable and associated placeholders
        $variables_table = $wpdb->prefix . 'alynt_cert_variables';
        $placeholders_table = $wpdb->prefix . 'alynt_cert_placeholders';
        
        // Delete placeholders first
        $wpdb->delete($placeholders_table, array('variable_id' => $variable_id));
        
        // Delete variable
        $result = $wpdb->delete($variables_table, array('id' => $variable_id));
        
        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => __('Failed to delete variable', 'alynt-certificates'));
        }
    }
    
    /**
     * Save placeholder position
     */
    public function save_placeholder($data) {
        $placeholder_data = array(
            'template_id' => intval($data['template_id']),
            'variable_id' => intval($data['variable_id']),
            'x_position' => intval($data['x_position']),
            'y_position' => intval($data['y_position']),
            'width' => intval($data['width']),
            'height' => intval($data['height']),
            'font_size' => intval($data['font_size']),
            'font_color' => sanitize_hex_color($data['font_color']),
            'font_family' => sanitize_text_field($data['font_family']),
            'text_align' => sanitize_text_field($data['text_align'])
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            $placeholder_data['id'] = intval($data['id']);
        }
        
        $result = $this->db->save_placeholder($placeholder_data);
        
        if ($result) {
            return array('success' => true, 'placeholder_id' => $result);
        } else {
            return array('success' => false, 'error' => __('Failed to save placeholder', 'alynt-certificates'));
        }
    }
    
    /**
     * Delete placeholder
     */
    public function delete_placeholder($placeholder_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'alynt_cert_placeholders';
        $result = $wpdb->delete($table, array('id' => $placeholder_id));
        
        if ($result) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => __('Failed to delete placeholder', 'alynt-certificates'));
        }
    }
    
    /**
     * Get image path from URL
     */
    private function get_image_path_from_url($image_url) {
        $upload_dir = wp_upload_dir();
        
        if (strpos($image_url, $upload_dir['baseurl']) === 0) {
            return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        }
        
        return false;
    }
    
    /**
     * AJAX: Save template
     */
    public function ajax_save_template() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $template_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'template_image' => esc_url_raw($_POST['template_image']),
            'orientation' => sanitize_text_field($_POST['orientation']),
            'width' => intval($_POST['width']),
            'height' => intval($_POST['height']),
            'status' => 'active'
        );
        
        if (isset($_POST['template_id']) && $_POST['template_id'] > 0) {
            $result = $this->update_template($_POST['template_id'], $template_data);
        } else {
            $result = $this->create_template($template_data);
        }
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $template_id = intval($_POST['template_id']);
        $result = $this->delete_template($template_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Get template data
     */
    public function ajax_get_template() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $template_id = intval($_POST['template_id']);
        $template = $this->get_template_with_data($template_id);
        
        if ($template) {
            wp_send_json(array('success' => true, 'template' => $template));
        } else {
            wp_send_json(array('success' => false, 'error' => __('Template not found', 'alynt-certificates')));
        }
    }
    
    /**
     * AJAX: Save variable
     */
    public function ajax_save_variable() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $template_id = intval($_POST['template_id']);
        $variable_data = array(
            'variable_name' => sanitize_text_field($_POST['variable_name']),
            'variable_label' => sanitize_text_field($_POST['variable_label']),
            'variable_type' => sanitize_text_field($_POST['variable_type']),
            'is_required' => intval($_POST['is_required']),
            'default_value' => sanitize_textarea_field($_POST['default_value']),
            'validation_rules' => sanitize_textarea_field($_POST['validation_rules']),
            'sort_order' => intval($_POST['sort_order'])
        );
        
        if (isset($_POST['variable_id']) && $_POST['variable_id'] > 0) {
            $result = $this->update_variable($_POST['variable_id'], $variable_data);
        } else {
            $result = $this->add_variable($template_id, $variable_data);
        }
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Delete variable
     */
    public function ajax_delete_variable() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $variable_id = intval($_POST['variable_id']);
        $result = $this->delete_variable($variable_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Save placeholder
     */
    public function ajax_save_placeholder() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $placeholder_data = array(
            'template_id' => intval($_POST['template_id']),
            'variable_id' => intval($_POST['variable_id']),
            'x_position' => intval($_POST['x_position']),
            'y_position' => intval($_POST['y_position']),
            'width' => intval($_POST['width']),
            'height' => intval($_POST['height']),
            'font_size' => intval($_POST['font_size']),
            'font_color' => sanitize_hex_color($_POST['font_color']),
            'font_family' => sanitize_text_field($_POST['font_family']),
            'text_align' => sanitize_text_field($_POST['text_align'])
        );
        
        if (isset($_POST['placeholder_id']) && $_POST['placeholder_id'] > 0) {
            $placeholder_data['id'] = intval($_POST['placeholder_id']);
        }
        
        $result = $this->save_placeholder($placeholder_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Delete placeholder
     */
    public function ajax_delete_placeholder() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        $placeholder_id = intval($_POST['placeholder_id']);
        $result = $this->delete_placeholder($placeholder_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Upload template image
     */
    public function ajax_upload_template_image() {
        check_ajax_referer('alynt_cert_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'alynt-certificates'));
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES['template_image'];
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png');
        if (!in_array($uploadedfile['type'], $allowed_types)) {
            wp_send_json(array('success' => false, 'error' => __('Only JPG and PNG files are allowed', 'alynt-certificates')));
        }
        
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = array(
                'guid' => $movefile['url'],
                'post_mime_type' => $movefile['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                wp_send_json(array(
                    'success' => true,
                    'url' => $movefile['url'],
                    'attachment_id' => $attachment_id
                ));
            }
        }
        
        wp_send_json(array('success' => false, 'error' => __('Upload failed', 'alynt-certificates')));
    }
}
