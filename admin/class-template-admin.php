<?php
/**
 * Template builder admin UI.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

class Alynt_Certificate_Generator_Template_Admin {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register template metaboxes.
	 */
	public function register_metaboxes(): void {
		\add_meta_box(
			'acg_template_image',
			__( 'Template Image', 'alynt-certificate-generator' ),
			array( $this, 'render_image_metabox' ),
			'acg_cert_template',
			'normal',
			'high'
		);

		\add_meta_box(
			'acg_template_variables',
			__( 'Variables & Preview', 'alynt-certificate-generator' ),
			array( $this, 'render_variables_metabox' ),
			'acg_cert_template',
			'normal',
			'default'
		);

		\add_meta_box(
			'acg_template_access',
			__( 'Form Access', 'alynt-certificate-generator' ),
			array( $this, 'render_access_metabox' ),
			'acg_cert_template',
			'side',
			'default'
		);

		\add_meta_box(
			'acg_template_webhooks',
			__( 'Webhook Settings', 'alynt-certificate-generator' ),
			array( $this, 'render_webhook_metabox' ),
			'acg_cert_template',
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue admin assets for template builder.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = \get_current_screen();
		if ( null === $screen || 'acg_cert_template' !== $screen->post_type ) {
			return;
		}

		$script_path = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'assets/dist/admin/index.js';
		$style_path  = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'assets/dist/admin/index.css';

		if ( file_exists( $script_path ) ) {
			\wp_enqueue_script(
				'alynt-certificate-generator-admin',
				ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL . 'assets/dist/admin/index.js',
				array(),
				(string) filemtime( $script_path ),
				true
			);

			\wp_localize_script(
				'alynt-certificate-generator-admin',
				'acgAdmin',
				array(
					'postId'      => get_the_ID(),
					'restUrl'     => esc_url_raw( rest_url( 'acg/v1' ) ),
					'restNonce'   => wp_create_nonce( 'wp_rest' ),
					'constraints' => array(
						'maxSize'    => 5 * 1024 * 1024,
						'minWidth'   => 1000,
						'minHeight'  => 800,
						'minWidthPortrait'  => 800,
						'minHeightPortrait' => 1000,
						'maxWidth'   => 6000,
						'maxHeight'  => 6000,
					),
				)
			);
		}

		if ( file_exists( $style_path ) ) {
			\wp_enqueue_style(
				'alynt-certificate-generator-admin',
				ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL . 'assets/dist/admin/index.css',
				array(),
				(string) filemtime( $style_path )
			);
		}

		\wp_enqueue_media();
	}

	/**
	 * Render image selection metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_image_metabox( \WP_Post $post ): void {
		$image_id   = (int) \get_post_meta( $post->ID, 'acg_template_image_id', true );
		$orientation = (string) \get_post_meta( $post->ID, 'acg_template_orientation', true );
		$orientation = '' !== $orientation ? $orientation : 'landscape';

		$image_url = '';
		if ( $image_id ) {
			$image_src = \wp_get_attachment_image_src( $image_id, 'large' );
			if ( is_array( $image_src ) ) {
				$image_url = $image_src[0];
			}
		}

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
		echo '<select name="acg_template_orientation" id="acg_template_orientation">';
		echo '<option value="landscape"' . selected( $orientation, 'landscape', false ) . '>' . esc_html__( 'Landscape', 'alynt-certificate-generator' ) . '</option>';
		echo '<option value="portrait"' . selected( $orientation, 'portrait', false ) . '>' . esc_html__( 'Portrait', 'alynt-certificate-generator' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Render variables and preview metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_variables_metabox( \WP_Post $post ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce', false );

		$image_id = (int) \get_post_meta( $post->ID, 'acg_template_image_id', true );
		$image_url = '';
		$image_width = 0;
		$image_height = 0;
		if ( $image_id ) {
			$image_src = \wp_get_attachment_image_src( $image_id, 'full' );
			if ( is_array( $image_src ) ) {
				$image_url   = $image_src[0];
				$image_width = (int) $image_src[1];
				$image_height = (int) $image_src[2];
			}
		}

		$variables_json = (string) \get_post_meta( $post->ID, 'acg_template_variables', true );

		echo '<input type="hidden" id="acg_template_variables" name="acg_template_variables" value="' . esc_attr( $variables_json ) . '" />';
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
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Label', 'alynt-certificate-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'Key', 'alynt-certificate-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'alynt-certificate-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'Required', 'alynt-certificate-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'Style', 'alynt-certificate-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'Position', 'alynt-certificate-generator' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'alynt-certificate-generator' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="acg-template-variables-body"></tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Render access control metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_access_metabox( \WP_Post $post ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce', false );

		$permissions = $this->get_permissions( $post->ID );
		$access      = $permissions['access'];
		$roles       = $permissions['roles'];

		echo '<p>' . esc_html__( 'Choose who can access the frontend form for this template.', 'alynt-certificate-generator' ) . '</p>';
		echo '<select name="acg_template_access" id="acg_template_access">';
		echo '<option value="any"' . selected( $access, 'any', false ) . '>' . esc_html__( 'Any logged-in user', 'alynt-certificate-generator' ) . '</option>';
		echo '<option value="roles"' . selected( $access, 'roles', false ) . '>' . esc_html__( 'Specific roles', 'alynt-certificate-generator' ) . '</option>';
		echo '</select>';

		echo '<div class="acg-template-roles" style="margin-top:10px;">';
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
	}

	/**
	 * Render webhook settings metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_webhook_metabox( \WP_Post $post ): void {
		\wp_nonce_field( 'acg_template_save', 'acg_template_nonce', false );

		$settings = $this->get_webhook_settings( $post->ID );
		$incoming = $settings['incoming'];
		$outgoing = $settings['outgoing'];

		$incoming_url = rest_url( 'acg/v1/templates/' . $post->ID . '/incoming' );

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
	 * Save template metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_template_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['acg_template_nonce'] ) || ! \wp_verify_nonce( wp_unslash( $_POST['acg_template_nonce'] ), 'acg_template_save' ) ) {
			return;
		}

		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE, $post_id ) ) {
			return;
		}

		// Only save image and orientation if form fields are present.
		if ( isset( $_POST['acg_template_image_id'] ) ) {
			$image_id = absint( wp_unslash( $_POST['acg_template_image_id'] ) );
			$orientation = isset( $_POST['acg_template_orientation'] ) ? sanitize_key( wp_unslash( $_POST['acg_template_orientation'] ) ) : 'landscape';

			if ( '' === $orientation ) {
				$orientation = 'landscape';
			}

			$validation = $this->validate_template_image( $image_id, $orientation );
			if ( ! $validation['valid'] ) {
				$this->store_admin_error( $post_id, $validation['message'] );
			} else {
				\update_post_meta( $post_id, 'acg_template_image_id', $image_id );
			}

			if ( in_array( $orientation, array( 'landscape', 'portrait' ), true ) ) {
				\update_post_meta( $post_id, 'acg_template_orientation', $orientation );
			}
		}

		if ( isset( $_POST['acg_template_variables'] ) ) {
			$variables_json = wp_unslash( $_POST['acg_template_variables'] );
			\update_post_meta( $post_id, 'acg_template_variables', $variables_json );
		}

		$this->save_permissions( $post_id );
		$this->save_webhook_settings( $post_id );
	}

	/**
	 * Display admin errors for template save.
	 */
	public function render_admin_errors(): void {
		if ( ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( wp_unslash( $_GET['post'] ) );
		if ( ! $post_id ) {
			return;
		}

		$transient_key = 'acg_template_error_' . $post_id;
		$message       = get_transient( $transient_key );
		if ( ! $message ) {
			return;
		}

		delete_transient( $transient_key );

		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Save permissions data.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_permissions( int $post_id ): void {
		// Only save if the form field is present to avoid wiping data on REST saves.
		if ( ! isset( $_POST['acg_template_access'] ) ) {
			return;
		}

		$access = sanitize_key( wp_unslash( $_POST['acg_template_access'] ) );
		$roles  = isset( $_POST['acg_template_roles'] ) ? (array) wp_unslash( $_POST['acg_template_roles'] ) : array();

		$roles = array_map( 'sanitize_key', $roles );
		if ( 'roles' !== $access ) {
			$roles = array();
		}

		\update_post_meta(
			$post_id,
			'acg_template_permissions',
			wp_json_encode(
				array(
					'access' => $access,
					'roles'  => $roles,
				)
			)
		);
	}

	/**
	 * Get permissions data.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_permissions( int $post_id ): array {
		$raw = (string) \get_post_meta( $post_id, 'acg_template_permissions', true );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return array(
				'access' => 'any',
				'roles'  => array(),
			);
		}

		return array(
			'access' => isset( $decoded['access'] ) ? (string) $decoded['access'] : 'any',
			'roles'  => isset( $decoded['roles'] ) && is_array( $decoded['roles'] ) ? $decoded['roles'] : array(),
		);
	}

	/**
	 * Save webhook settings.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_webhook_settings( int $post_id ): void {
		// Only save if the form field is present to avoid wiping data on REST saves.
		if ( ! isset( $_POST['acg_webhook_api_key'] ) ) {
			return;
		}

		$current = $this->get_webhook_settings( $post_id );
		$api_key = sanitize_text_field( wp_unslash( $_POST['acg_webhook_api_key'] ) );
		$signature_secret = isset( $_POST['acg_webhook_signature_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_webhook_signature_secret'] ) ) : $current['incoming']['signature_secret'];
		$rate_limit = isset( $_POST['acg_webhook_rate_limit'] ) ? absint( wp_unslash( $_POST['acg_webhook_rate_limit'] ) ) : $current['incoming']['rate_limit'];

		if ( '' === $api_key ) {
			$api_key = wp_generate_password( 32, false, false );
		}

		$outgoing_url = isset( $_POST['acg_webhook_outgoing_url'] ) ? esc_url_raw( wp_unslash( $_POST['acg_webhook_outgoing_url'] ) ) : $current['outgoing']['url'];
		$outgoing_enabled = isset( $_POST['acg_webhook_outgoing_enabled'] );

		\update_post_meta(
			$post_id,
			'acg_template_webhook_settings',
			wp_json_encode(
				array(
					'incoming' => array(
						'api_key'          => $api_key,
						'signature_secret' => $signature_secret,
						'rate_limit'       => $rate_limit,
					),
					'outgoing' => array(
						'url'     => $outgoing_url,
						'enabled' => $outgoing_enabled,
					),
				)
			)
		);
	}

	/**
	 * Get webhook settings.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_webhook_settings( int $post_id ): array {
		$raw = (string) \get_post_meta( $post_id, 'acg_template_webhook_settings', true );
		$decoded = json_decode( $raw, true );

		$incoming = array(
			'api_key'          => '',
			'signature_secret' => '',
			'rate_limit'       => 0,
		);
		$outgoing = array(
			'url'     => '',
			'enabled' => false,
		);

		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['incoming'] ) && is_array( $decoded['incoming'] ) ) {
				$incoming = array_merge( $incoming, $decoded['incoming'] );
			}
			if ( isset( $decoded['outgoing'] ) && is_array( $decoded['outgoing'] ) ) {
				$outgoing = array_merge( $outgoing, $decoded['outgoing'] );
			}
		}

		return array(
			'incoming' => $incoming,
			'outgoing' => $outgoing,
		);
	}

	/**
	 * Validate template image.
	 *
	 * @param int    $image_id   Attachment ID.
	 * @param string $orientation Orientation.
	 * @return array<string, mixed>
	 */
	private function validate_template_image( int $image_id, string $orientation ): array {
		if ( 0 === $image_id ) {
			return array(
				'valid'   => true,
				'message' => '',
			);
		}

		$file_path = \get_attached_file( $image_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Selected image file not found.', 'alynt-certificate-generator' ),
			);
		}

		$file_type = \wp_check_filetype( $file_path );
		$allowed   = array( 'jpg', 'jpeg', 'png' );
		if ( empty( $file_type['ext'] ) || ! in_array( strtolower( $file_type['ext'] ), $allowed, true ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image must be a JPG or PNG file.', 'alynt-certificate-generator' ),
			);
		}

		$file_size = filesize( $file_path );
		if ( $file_size && $file_size > 5 * 1024 * 1024 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image exceeds the 5MB size limit.', 'alynt-certificate-generator' ),
			);
		}

		$metadata = \wp_get_attachment_metadata( $image_id );
		$width    = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height   = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		if ( $width < 1 || $height < 1 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image dimensions could not be read.', 'alynt-certificate-generator' ),
			);
		}

		if ( $width > 6000 || $height > 6000 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image exceeds the 6000x6000 maximum resolution.', 'alynt-certificate-generator' ),
			);
		}

		if ( 'portrait' === $orientation ) {
			if ( $width < 800 || $height < 1000 ) {
				return array(
					'valid'   => false,
					'message' => __( 'Portrait templates must be at least 800x1000 pixels.', 'alynt-certificate-generator' ),
				);
			}
		} else {
			if ( $width < 1000 || $height < 800 ) {
				return array(
					'valid'   => false,
					'message' => __( 'Landscape templates must be at least 1000x800 pixels.', 'alynt-certificate-generator' ),
				);
			}
		}

		return array(
			'valid'   => true,
			'message' => '',
		);
	}

	/**
	 * Store admin error message for next load.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Error message.
	 */
	private function store_admin_error( int $post_id, string $message ): void {
		set_transient( 'acg_template_error_' . $post_id, $message, 30 );
	}
}
