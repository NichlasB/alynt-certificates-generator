<?php
/**
 * Fonts settings page controller.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Font_Service;

/**
 * Handles custom font settings form actions.
 */
class Alynt_Certificate_Generator_Fonts_Settings_Page {
	/**
	 * Font service.
	 *
	 * @var Alynt_Certificate_Generator_Font_Service
	 */
	private $font_service;

	/**
	 * Page renderer.
	 *
	 * @var Alynt_Certificate_Generator_Fonts_Settings_Page_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Certificate_Generator_Font_Service|null                 $font_service Font service.
	 * @param Alynt_Certificate_Generator_Fonts_Settings_Page_Renderer|null $renderer Page renderer.
	 */
	public function __construct(
		?Alynt_Certificate_Generator_Font_Service $font_service = null,
		?Alynt_Certificate_Generator_Fonts_Settings_Page_Renderer $renderer = null
	) {
		$this->font_service = null !== $font_service ? $font_service : new Alynt_Certificate_Generator_Font_Service();
		$this->renderer     = null !== $renderer ? $renderer : new Alynt_Certificate_Generator_Fonts_Settings_Page_Renderer();
	}

	/**
	 * Render the fonts management page.
	 */
	public function render(): void {
		$fonts           = $this->font_service->get_global_fonts();
		$allowed_weights = Alynt_Certificate_Generator_Font_Service::ALLOWED_WEIGHTS;

		if ( $this->has_submitted_action() ) {
			$fonts = $this->handle_form_submission( $fonts );
		}

		$this->renderer->render( $fonts, $allowed_weights );
	}

	/**
	 * Handle submitted font management actions.
	 *
	 * @param array $fonts Current fonts.
	 * @return array Updated fonts.
	 */
	private function handle_form_submission( array $fonts ): array {
		if ( ! $this->has_valid_nonce() ) {
			$this->renderer->render_notice( __( 'Security check failed. Please try again.', 'alynt-certificate-generator' ), 'error' );
			return $fonts;
		}

		$action = $this->get_post_key( 'acg_font_action' );

		if ( 'create_family' === $action ) {
			$family_name = $this->get_post_text( 'acg_font_family_name' );
			if ( '' === $family_name ) {
				$this->renderer->render_notice( __( 'Font family name is required.', 'alynt-certificate-generator' ), 'error' );
				return $fonts;
			}
			return $this->handle_create_family( $family_name );
		}

		if ( 'upload_weight' === $action ) {
			return $this->handle_upload_weight(
				$fonts,
				$this->get_post_key( 'acg_font_family_slug' ),
				$this->get_post_key( 'acg_font_weight' ),
				$this->get_uploaded_font_file()
			);
		}

		if ( 'delete_weight' === $action ) {
			return $this->handle_delete_weight(
				$this->get_post_key( 'acg_font_family_slug' ),
				$this->get_post_key( 'acg_font_weight' )
			);
		}

		if ( 'delete_family' === $action ) {
			$family_slug = $this->get_post_key( 'acg_font_family_slug' );
			if ( '' === $family_slug ) {
				$this->renderer->render_notice( __( 'Font family is required.', 'alynt-certificate-generator' ), 'error' );
				return $fonts;
			}
			return $this->handle_delete_family( $family_slug );
		}

		return $fonts;
	}

	/**
	 * Determine whether a font action was submitted.
	 *
	 * @return bool
	 */
	private function has_submitted_action(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified before submitted data is processed.
		return isset( $_POST['acg_font_action'] );
	}

	/**
	 * Verify the font management nonce.
	 *
	 * @return bool
	 */
	private function has_valid_nonce(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This method performs the nonce verification.
		if ( ! isset( $_POST['acg_font_nonce'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This method performs the nonce verification.
		$nonce = \sanitize_text_field( \wp_unslash( $_POST['acg_font_nonce'] ) );

		return (bool) \wp_verify_nonce( $nonce, 'acg_font_management' );
	}

	/**
	 * Read a submitted text field after nonce verification.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function get_post_text( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified before this helper is called.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified before this helper is called.
		return \sanitize_text_field( \wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Read a submitted key field after nonce verification.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function get_post_key( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified before this helper is called.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified before this helper is called.
		return \sanitize_key( \wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Get the submitted font file array.
	 *
	 * @return array
	 */
	private function get_uploaded_font_file(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The nonce is verified before this helper is called; the font service validates the uploaded file array.
		if ( ! isset( $_FILES['acg_font_file'] ) || ! is_array( $_FILES['acg_font_file'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The nonce is verified before this helper is called; the font service validates the uploaded file array.
		return $_FILES['acg_font_file'];
	}

	/**
	 * Handle font family creation.
	 *
	 * @param string $family_name Font family name.
	 * @return array Updated fonts.
	 */
	private function handle_create_family( string $family_name ): array {
		$result = $this->font_service->create_font_family( $family_name );

		if ( \is_wp_error( $result ) ) {
			$this->renderer->render_notice( $result->get_error_message(), 'error' );
			return $this->font_service->get_global_fonts();
		}

		$this->renderer->render_notice( __( 'Font family created successfully.', 'alynt-certificate-generator' ), 'success' );

		return $this->font_service->get_global_fonts();
	}

	/**
	 * Handle font weight upload.
	 *
	 * @param array  $fonts       Current fonts.
	 * @param string $family_slug Font family slug.
	 * @param string $weight      Font weight.
	 * @param array  $font_file   Uploaded file array.
	 * @return array Updated fonts.
	 */
	private function handle_upload_weight( array $fonts, string $family_slug, string $weight, array $font_file ): array {
		if ( ! isset( $fonts[ $family_slug ] ) || empty( $font_file['tmp_name'] ) ) {
			$this->renderer->render_notice( __( 'Font family and file are required.', 'alynt-certificate-generator' ), 'error' );
			return $fonts;
		}

		$result = $this->font_service->upload_font(
			$font_file,
			$fonts[ $family_slug ]['family'],
			$weight
		);

		if ( \is_wp_error( $result ) ) {
			$this->renderer->render_notice( $result->get_error_message(), 'error' );
			return $this->font_service->get_global_fonts();
		}

		$this->renderer->render_notice( __( 'Font weight uploaded successfully.', 'alynt-certificate-generator' ), 'success' );

		return $this->font_service->get_global_fonts();
	}

	/**
	 * Handle font weight deletion.
	 *
	 * @param string $family_slug Font family slug.
	 * @param string $weight      Font weight.
	 * @return array Updated fonts.
	 */
	private function handle_delete_weight( string $family_slug, string $weight ): array {
		$result = $this->font_service->delete_font_weight( $family_slug, $weight );

		if ( \is_wp_error( $result ) ) {
			$this->renderer->render_notice( $result->get_error_message(), 'error' );
			return $this->font_service->get_global_fonts();
		}

		$this->renderer->render_notice( __( 'Font weight deleted successfully.', 'alynt-certificate-generator' ), 'success' );

		return $this->font_service->get_global_fonts();
	}

	/**
	 * Handle font family deletion.
	 *
	 * @param string $family_slug Font family slug.
	 * @return array Updated fonts.
	 */
	private function handle_delete_family( string $family_slug ): array {
		$result = $this->font_service->delete_font_family( $family_slug );

		if ( \is_wp_error( $result ) ) {
			$this->renderer->render_notice( $result->get_error_message(), 'error' );
			return $this->font_service->get_global_fonts();
		}

		$this->renderer->render_notice( __( 'Font family deleted successfully.', 'alynt-certificate-generator' ), 'success' );

		return $this->font_service->get_global_fonts();
	}
}
