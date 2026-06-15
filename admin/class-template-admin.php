<?php
/**
 * Template builder admin UI.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Font_Service;

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

	/**
	 * Metabox renderer.
	 *
	 * @var Alynt_Certificate_Generator_Template_Metabox_Renderer
	 */
	private $metabox_renderer;

	/**
	 * Template meta manager.
	 *
	 * @var Alynt_Certificate_Generator_Template_Meta_Manager
	 */
	private $meta_manager;

	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name      = $plugin_name;
		$this->version          = $version;
		$this->metabox_renderer = new Alynt_Certificate_Generator_Template_Metabox_Renderer();
		$this->meta_manager     = new Alynt_Certificate_Generator_Template_Meta_Manager();
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

		\add_meta_box(
			'acg_template_fonts',
			__( 'Custom Fonts', 'alynt-certificate-generator' ),
			array( $this, 'render_fonts_metabox' ),
			'acg_cert_template',
			'side',
			'low'
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
					'i18n'        => $this->get_template_builder_i18n(),
					'constraints' => array(
						'maxSize'           => 5 * 1024 * 1024,
						'minWidth'          => 1000,
						'minHeight'         => 800,
						'minWidthPortrait'  => 800,
						'minHeightPortrait' => 1000,
						'maxWidth'          => 6000,
						'maxHeight'         => 6000,
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
	 * Get localized strings used by the template builder JavaScript.
	 *
	 * @return array<string, string>
	 */
	private function get_template_builder_i18n(): array {
		return array(
			'addOption'                  => __( '+ Add Option', 'alynt-certificate-generator' ),
			'align'                      => __( 'Align', 'alynt-certificate-generator' ),
			'alignCenter'                => __( 'Center', 'alynt-certificate-generator' ),
			'alignLeft'                  => __( 'Left', 'alynt-certificate-generator' ),
			'alignRight'                 => __( 'Right', 'alynt-certificate-generator' ),
			'auto'                       => __( 'Auto', 'alynt-certificate-generator' ),
			'autoCertificateId'          => __( 'Certificate ID', 'alynt-certificate-generator' ),
			'autoGenerationDate'         => __( 'Generation Date', 'alynt-certificate-generator' ),
			'bold'                       => __( 'B', 'alynt-certificate-generator' ),
			'color'                      => __( 'Color', 'alynt-certificate-generator' ),
			'confirmRemoveOption'        => __( 'Remove this dropdown option? This action cannot be undone.', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'confirmRemoveVariable'      => __( 'Remove %s from this template? This action cannot be undone.', 'alynt-certificate-generator' ),
			'customFontsGlobal'          => __( 'Custom Fonts (Global)', 'alynt-certificate-generator' ),
			'customFontsTemplate'        => __( 'Custom Fonts (Template)', 'alynt-certificate-generator' ),
			'dateFormatDayFullMonthYear' => __( 'DD Month YYYY', 'alynt-certificate-generator' ),
			'dateFormatDayMonthYear'     => __( 'DD/MM/YYYY', 'alynt-certificate-generator' ),
			'dateFormatFullMonthDayYear' => __( 'Month DD, YYYY', 'alynt-certificate-generator' ),
			'dateFormatIso'              => __( 'YYYY-MM-DD', 'alynt-certificate-generator' ),
			'dateFormatMonthDayYear'     => __( 'MM/DD/YYYY', 'alynt-certificate-generator' ),
			'dragToReorder'              => __( 'Drag to reorder', 'alynt-certificate-generator' ),
			'dropdownOptions'            => __( 'Dropdown Options:', 'alynt-certificate-generator' ),
			'font'                       => __( 'Font', 'alynt-certificate-generator' ),
			'format'                     => __( 'Format', 'alynt-certificate-generator' ),
			'imageMaxHeight'             => __( 'Max H', 'alynt-certificate-generator' ),
			'imageMaxWidth'              => __( 'Max W', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'labelField'                 => __( 'Label for %s', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'keyField'                   => __( 'Key for %s', 'alynt-certificate-generator' ),
			/* translators: %d: number of dropdown options. */
			'optionCountPlural'          => __( '(%d options)', 'alynt-certificate-generator' ),
			/* translators: %d: number of dropdown options. */
			'optionCountSingular'        => __( '(%d option)', 'alynt-certificate-generator' ),
			'optionTextPlaceholder'      => __( 'Option text (shown in dropdown and on certificate)', 'alynt-certificate-generator' ),
			/* translators: %d: number of dropdown options. */
			'optionsSummaryPlural'       => __( 'Options: %d (edit below)', 'alynt-certificate-generator' ),
			/* translators: %d: number of dropdown options. */
			'optionsSummarySingular'     => __( 'Option: %d (edit below)', 'alynt-certificate-generator' ),
			'remove'                     => __( 'Remove', 'alynt-certificate-generator' ),
			'selectCertificateTemplate'  => __( 'Select certificate template', 'alynt-certificate-generator' ),
			'saveTemplateFailed'         => __( 'Template variables could not be saved.', 'alynt-certificate-generator' ),
			/* translators: %s: marker position. */
			'markerPositionUpdated'      => __( 'Marker moved to %s.', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'moveMarkerInstructions'     => __( 'Move %s. Use arrow keys to move by 1 pixel, or Shift plus arrow keys to move by 10 pixels.', 'alynt-certificate-generator' ),
			'moveVariableDown'           => __( 'Move variable down', 'alynt-certificate-generator' ),
			'moveVariableUp'             => __( 'Move variable up', 'alynt-certificate-generator' ),
			'variable'                   => __( 'Variable', 'alynt-certificate-generator' ),
			/* translators: %d: row position. */
			'variableMoved'              => __( 'Variable moved to position %d.', 'alynt-certificate-generator' ),
			'savingTemplate'             => __( 'Saving template variables...', 'alynt-certificate-generator' ),
			'size'                       => __( 'Size', 'alynt-certificate-generator' ),
			'systemFonts'                => __( 'System Fonts', 'alynt-certificate-generator' ),
			'templateSaved'              => __( 'Template variables saved.', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'displayField'               => __( 'Display %s on certificate', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'requiredField'              => __( '%s is required', 'alynt-certificate-generator' ),
			/* translators: %s: variable label. */
			'typeField'                  => __( 'Type for %s', 'alynt-certificate-generator' ),
			'typeAuto'                   => __( 'Auto', 'alynt-certificate-generator' ),
			'typeDate'                   => __( 'Date', 'alynt-certificate-generator' ),
			'typeImage'                  => __( 'Image', 'alynt-certificate-generator' ),
			'typeSelect'                 => __( 'Select', 'alynt-certificate-generator' ),
			'typeText'                   => __( 'Text', 'alynt-certificate-generator' ),
			'useThisImage'               => __( 'Use this image', 'alynt-certificate-generator' ),
			/* translators: %d: variable number. */
			'variableLabel'              => __( 'Variable %d', 'alynt-certificate-generator' ),
			'xCoordinate'                => __( 'X:', 'alynt-certificate-generator' ),
			'yCoordinate'                => __( 'Y:', 'alynt-certificate-generator' ),
			'italic'                     => __( 'I', 'alynt-certificate-generator' ),
		);
	}

	/**
	 * Render image selection metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_image_metabox( \WP_Post $post ): void {
		$image_id    = (int) \get_post_meta( $post->ID, 'acg_template_image_id', true );
		$orientation = (string) \get_post_meta( $post->ID, 'acg_template_orientation', true );
		$orientation = '' !== $orientation ? $orientation : 'landscape';
		$image_url   = $this->get_image_url( $image_id, 'large' );

		$this->metabox_renderer->render_image_metabox( $image_id, $orientation, $image_url );
	}

	/**
	 * Render variables and preview metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_variables_metabox( \WP_Post $post ): void {
		$image_id     = (int) \get_post_meta( $post->ID, 'acg_template_image_id', true );
		$image_url    = '';
		$image_width  = 0;
		$image_height = 0;

		if ( $image_id ) {
			$image_src = \wp_get_attachment_image_src( $image_id, 'full' );
			if ( is_array( $image_src ) ) {
				$image_url    = $image_src[0];
				$image_width  = (int) $image_src[1];
				$image_height = (int) $image_src[2];
			}
		}

		$variables_json = (string) \get_post_meta( $post->ID, 'acg_template_variables', true );
		$this->metabox_renderer->render_variables_metabox( $variables_json, $image_url, $image_width, $image_height );
	}

	/**
	 * Render access control metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_access_metabox( \WP_Post $post ): void {
		$this->metabox_renderer->render_access_metabox(
			$this->meta_manager->get_permissions( $post->ID )
		);
	}

	/**
	 * Render webhook settings metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_webhook_metabox( \WP_Post $post ): void {
		$this->metabox_renderer->render_webhook_metabox(
			$post->ID,
			$this->meta_manager->get_webhook_settings( $post->ID )
		);
	}

	/**
	 * Render custom fonts metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_fonts_metabox( \WP_Post $post ): void {
		$font_service    = new Alynt_Certificate_Generator_Font_Service();
		$global_fonts    = $font_service->get_global_fonts();
		$template_fonts  = $font_service->get_template_fonts( $post->ID );
		$allowed_weights = Alynt_Certificate_Generator_Font_Service::ALLOWED_WEIGHTS;

		$this->metabox_renderer->render_fonts_metabox( $global_fonts, $template_fonts, $allowed_weights );
	}

	/**
	 * Save template metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_template_meta( int $post_id ): void {
		$this->meta_manager->save_template_meta( $post_id );
	}

	/**
	 * Display admin errors for template save.
	 */
	public function render_admin_errors(): void {
		$this->meta_manager->render_admin_errors();
	}

	/**
	 * Get an image URL for an attachment.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $size     Image size.
	 * @return string
	 */
	private function get_image_url( int $image_id, string $size ): string {
		if ( ! $image_id ) {
			return '';
		}

		$image_src = \wp_get_attachment_image_src( $image_id, $size );
		return is_array( $image_src ) ? (string) $image_src[0] : '';
	}
}
