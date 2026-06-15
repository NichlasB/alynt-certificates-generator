<?php
/**
 * Certificate template metabox renderer.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

class Alynt_Certificate_Generator_Template_Metabox_Renderer {
	/**
	 * Render image selection metabox.
	 *
	 * @param int    $image_id    Image attachment ID.
	 * @param string $orientation Template orientation.
	 * @param string $image_url   Image preview URL.
	 */
	public function render_image_metabox( int $image_id, string $orientation, string $image_url ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce' );

		echo '<p>' . esc_html__( 'Upload a JPG or PNG (max 5MB, 1000x800 to 6000x6000).', 'alynt-certificate-generator' ) . '</p>';
		echo '<input type="hidden" id="acg_template_image_id" name="acg_template_image_id" value="' . esc_attr( (string) $image_id ) . '" />';
		echo '<button type="button" class="button" id="acg_template_select_image">' . esc_html__( 'Select Image', 'alynt-certificate-generator' ) . '</button>';
		echo '<div id="acg_template_image_preview" style="margin-top:10px;">';
		if ( '' !== $image_url ) {
			echo '<img src="' . esc_url( $image_url ) . '" style="max-width:100%;height:auto;" alt="" />';
		}
		echo '</div>';

		echo '<p style="margin-top:10px;">' . esc_html__( 'Orientation', 'alynt-certificate-generator' ) . '</p>';
		echo '<label class="screen-reader-text" for="acg_template_orientation">' . esc_html__( 'Template orientation', 'alynt-certificate-generator' ) . '</label>';
		echo '<select name="acg_template_orientation" id="acg_template_orientation">';
		echo '<option value="landscape"' . selected( $orientation, 'landscape', false ) . '>' . esc_html__( 'Landscape', 'alynt-certificate-generator' ) . '</option>';
		echo '<option value="portrait"' . selected( $orientation, 'portrait', false ) . '>' . esc_html__( 'Portrait', 'alynt-certificate-generator' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Render variables and preview metabox.
	 *
	 * @param string $variables_json Variables JSON.
	 * @param string $image_url      Image URL.
	 * @param int    $image_width    Image width.
	 * @param int    $image_height   Image height.
	 */
	public function render_variables_metabox( string $variables_json, string $image_url, int $image_width, int $image_height ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce', false );

		echo '<input type="hidden" id="acg_template_variables_input" name="acg_template_variables" value="' . esc_attr( $variables_json ) . '" />';
		echo '<div id="acg-template-builder" data-image-url="' . esc_attr( $image_url ) . '" data-image-width="' . esc_attr( (string) $image_width ) . '" data-image-height="' . esc_attr( (string) $image_height ) . '">';
		echo '<div class="acg-template-preview">';
		if ( '' !== $image_url ) {
			echo '<img id="acg-template-image" src="' . esc_url( $image_url ) . '" alt="" />';
			echo '<div id="acg-template-overlay" class="acg-template-overlay"></div>';
		} else {
			echo '<p>' . esc_html__( 'Select a template image to start positioning variables.', 'alynt-certificate-generator' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="acg-template-controls">';
		echo '<button type="button" class="button" id="acg-add-variable">' . esc_html__( 'Add Variable', 'alynt-certificate-generator' ) . '</button>';
		echo '</div>';

		echo '<table class="widefat fixed striped acg-variables-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Template variables', 'alynt-certificate-generator' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col" style="width: 30px;"><span class="screen-reader-text">' . esc_html__( 'Reorder', 'alynt-certificate-generator' ) . '</span></th>';
		echo '<th scope="col">' . esc_html__( 'Label', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Key', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Type', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Required', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Display', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Style', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Position', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Actions', 'alynt-certificate-generator' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="acg-template-variables-body"></tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Render access control metabox.
	 *
	 * @param array $permissions Permissions settings.
	 */
	public function render_access_metabox( array $permissions ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce', false );

		$access = $permissions['access'];
		$roles  = $permissions['roles'];

		echo '<p>' . esc_html__( 'Choose who can access the frontend form for this template.', 'alynt-certificate-generator' ) . '</p>';
		echo '<label class="screen-reader-text" for="acg_template_access_select">' . esc_html__( 'Template form access', 'alynt-certificate-generator' ) . '</label>';
		echo '<select name="acg_template_access" id="acg_template_access_select">';
		echo '<option value="any"' . selected( $access, 'any', false ) . '>' . esc_html__( 'Any logged-in user', 'alynt-certificate-generator' ) . '</option>';
		echo '<option value="roles"' . selected( $access, 'roles', false ) . '>' . esc_html__( 'Specific roles', 'alynt-certificate-generator' ) . '</option>';
		echo '</select>';

		$roles_style = 'roles' === $access ? 'margin-top:10px;' : 'margin-top:10px;display:none;';
		echo '<div class="acg-template-roles" id="acg_template_roles_wrap" style="' . esc_attr( $roles_style ) . '">';
		foreach ( \wp_roles()->roles as $role_key => $role ) {
			$checked = in_array( $role_key, $roles, true );
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="acg_template_roles[]" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $role_key ),
				checked( $checked, true, false ),
				esc_html( $role['name'] )
			);
		}
		echo '</div>';

		?>
		<script>
		(function() {
			var select = document.getElementById('acg_template_access_select');
			var rolesWrap = document.getElementById('acg_template_roles_wrap');
			if (!select || !rolesWrap) {
				return;
			}

			select.addEventListener('change', function() {
				if (select.value === 'roles') {
					rolesWrap.style.display = 'block';
				} else {
					rolesWrap.style.display = 'none';
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render webhook settings metabox.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $settings Webhook settings.
	 */
	public function render_webhook_metabox( int $post_id, array $settings ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce', false );

		$incoming = $settings['incoming'];
		$outgoing = $settings['outgoing'];

		$incoming_url = rest_url( 'acg/v1/templates/' . $post_id . '/incoming' );

		echo '<p><strong>' . esc_html__( 'Incoming Webhook URL', 'alynt-certificate-generator' ) . '</strong></p>';
		echo '<code>' . esc_html( $incoming_url ) . '</code>';

		echo '<p style="margin-top:12px;"><label for="acg_webhook_api_key">' . esc_html__( 'API Key', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="text" class="widefat" name="acg_webhook_api_key" id="acg_webhook_api_key" value="' . esc_attr( $incoming['api_key'] ) . '" />';

		echo '<p><label for="acg_webhook_signature_secret">' . esc_html__( 'Signature Secret (optional)', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="text" class="widefat" name="acg_webhook_signature_secret" id="acg_webhook_signature_secret" value="' . esc_attr( $incoming['signature_secret'] ) . '" />';

		echo '<p><label for="acg_webhook_rate_limit">' . esc_html__( 'Rate Limit Override (per minute)', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="number" class="small-text" name="acg_webhook_rate_limit" id="acg_webhook_rate_limit" value="' . esc_attr( (string) $incoming['rate_limit'] ) . '" min="0" />';
		echo '<p class="description">' . esc_html__( 'Leave 0 to use the global rate limit.', 'alynt-certificate-generator' ) . '</p>';

		echo '<hr />';
		echo '<p><strong>' . esc_html__( 'Outgoing Webhook', 'alynt-certificate-generator' ) . '</strong></p>';
		echo '<p><label for="acg_webhook_outgoing_url">' . esc_html__( 'Webhook URL', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="url" class="widefat" name="acg_webhook_outgoing_url" id="acg_webhook_outgoing_url" value="' . esc_attr( $outgoing['url'] ) . '" />';

		echo '<p style="margin-top:8px;">';
		echo '<label><input type="checkbox" name="acg_webhook_outgoing_enabled" value="1" ' . checked( $outgoing['enabled'], true, false ) . ' /> ';
		echo esc_html__( 'Enable outgoing webhook', 'alynt-certificate-generator' ) . '</label>';
		echo '</p>';
	}

	/**
	 * Render custom fonts metabox.
	 *
	 * @param array $global_fonts    Global fonts.
	 * @param array $template_fonts  Template-specific fonts.
	 * @param array $allowed_weights Allowed font weights.
	 */
	public function render_fonts_metabox( array $global_fonts, array $template_fonts, array $allowed_weights ): void {
		echo '<div class="acg-template-fonts">';

		$global_count = count( $global_fonts );
		echo '<p>';
		printf(
			/* translators: %d: number of global fonts */
			esc_html__( '%d global font(s) available.', 'alynt-certificate-generator' ),
			(int) $global_count
		);
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=alynt-certificate-generator&tab=fonts' ) ) . '">';
		echo esc_html__( 'Manage Global Fonts', 'alynt-certificate-generator' );
		echo '</a></p>';

		echo '<hr />';

		echo '<h4>' . esc_html__( 'Template-Specific Fonts', 'alynt-certificate-generator' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Upload fonts that are only available for this template.', 'alynt-certificate-generator' ) . '</p>';

		if ( ! empty( $template_fonts ) ) {
			echo '<ul style="margin: 10px 0;">';
			foreach ( $template_fonts as $family_data ) {
				$weights_list = array();
				foreach ( $family_data['weights'] as $weight_key => $weight_data ) {
					$weights_list[] = $allowed_weights[ $weight_key ] ?? $weight_key;
				}
				echo '<li><strong>' . esc_html( $family_data['family'] ) . '</strong>: ' . esc_html( implode( ', ', $weights_list ) ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p><em>' . esc_html__( 'No template-specific fonts uploaded.', 'alynt-certificate-generator' ) . '</em></p>';
		}

		echo '<div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">';
		echo '<p><strong>' . esc_html__( 'Quick Upload', 'alynt-certificate-generator' ) . '</strong></p>';

		echo '<p>';
		echo '<label>' . esc_html__( 'Family Name:', 'alynt-certificate-generator' ) . '<br />';
		echo '<input type="text" name="acg_template_font_family" class="widefat" placeholder="' . esc_attr__( 'e.g., Roboto', 'alynt-certificate-generator' ) . '" />';
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>' . esc_html__( 'Weight:', 'alynt-certificate-generator' ) . '<br />';
		echo '<select name="acg_template_font_weight" class="widefat">';
		foreach ( $allowed_weights as $weight_key => $weight_label ) {
			echo '<option value="' . esc_attr( $weight_key ) . '">' . esc_html( $weight_label ) . '</option>';
		}
		echo '</select>';
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>' . esc_html__( 'Font File (TTF/OTF):', 'alynt-certificate-generator' ) . '<br />';
		echo '<input type="file" name="acg_template_font_file" accept=".ttf,.otf" class="widefat" />';
		echo '</label>';
		echo '</p>';

		echo '<p class="description">' . esc_html__( 'The font will be uploaded when you save/update the template.', 'alynt-certificate-generator' ) . '</p>';
		echo '</div>';

		echo '</div>';
	}
}
